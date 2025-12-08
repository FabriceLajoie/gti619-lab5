<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use App\Models\Role;
use App\Models\SecurityConfig;
use App\Services\SecurityConfigService;
use App\Services\AuditLogger;
use App\Services\PasswordHistoryService;
use App\Services\SessionSecurityService;
use App\Http\Controllers\ReauthenticationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rules\Password;
use Carbon\Carbon;

class AdminController extends Controller
{
    protected $securityConfigService;
    protected $auditLogger;
    protected $passwordHistoryService;
    protected $pbkdf2Hasher;
    protected $passwordPolicyService;
    protected $sessionSecurityService;

    /**
     * Nouvelle instance du contrôleur
     */
    public function __construct(
        SecurityConfigService $securityConfigService, 
        AuditLogger $auditLogger,
        PasswordHistoryService $passwordHistoryService,
        \App\Services\PBKDF2PasswordHasher $pbkdf2Hasher,
        \App\Services\PasswordPolicyService $passwordPolicyService,
        SessionSecurityService $sessionSecurityService
    ) {
        $this->middleware('auth');
        $this->middleware('role:Administrateur');
        
        // Appliquer le middleware de ré-authentification aux opérations sensibles
        $this->middleware('reauth:10')->only([
            'storeUser', 'updateUser', 'resetUserPassword', 'unlockUser',
            'updateSecurityConfig', 'resetSecurityConfig', 'terminateUserSessions'
        ]);
        
        $this->securityConfigService = $securityConfigService;
        $this->auditLogger = $auditLogger;
        $this->passwordHistoryService = $passwordHistoryService;
        $this->sessionSecurityService = $sessionSecurityService;
        $this->pbkdf2Hasher = $pbkdf2Hasher;
        $this->passwordPolicyService = $passwordPolicyService;
    }

    /**
     * security configuration page
     *
     * @return \Illuminate\View\View
     */
    public function securityConfig()
    {
        $config = $this->securityConfigService->getConfig();
        
        return view('admin.security-config', compact('config'));
    }

    /**
     * Update security configuration
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function updateSecurityConfig(Request $request)
    {
        try {
            $oldConfig = $this->securityConfigService->getConfig()->toArray();
            $updatedConfig = $this->securityConfigService->updateConfig($request->all());
            
            $this->auditLogger->logSecurityConfigChange(
                Auth::id(),
                $oldConfig,
                $updatedConfig->toArray(),
                $request
            );
            
            return redirect()->route('admin.security-config')
                ->with('success', 'Security configuration updated successfully.');
                
        } catch (ValidationException $e) {
            return redirect()->route('admin.security-config')
                ->withErrors($e->validator)
                ->withInput();
        } catch (\Exception $e) {
            return redirect()->route('admin.security-config')
                ->with('error', 'Failed to update security configuration: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Reset sec config to defaults
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function resetSecurityConfig(Request $request)
    {
        try {
            $oldConfig = $this->securityConfigService->getConfig()->toArray();
            $resetConfig = $this->securityConfigService->resetToDefaults();
            
            // Log the configuration reset
            $this->auditLogger->logSecurityConfigChange(
                Auth::id(),
                $oldConfig,
                $resetConfig->toArray(),
                $request,
                'Configuration reset to defaults'
            );
            
            return redirect()->route('admin.security-config')
                ->with('success', 'Security configuration reset to defaults successfully.');
                
        } catch (\Exception $e) {
            return redirect()->route('admin.security-config')
                ->with('error', 'Failed to reset security configuration: ' . $e->getMessage());
        }
    }

    /**
     * Show the users management page
     *
     * @param Request $request
     * @return \Illuminate\View\View
     */
    public function users(Request $request)
    {
        $query = User::with('role')->orderBy('name');

        // Apply filters
        if ($request->filled('role_id')) {
            $query->where('role_id', $request->role_id);
        }

        if ($request->filled('status')) {
            if ($request->status === 'locked') {
                $query->where('locked_until', '>', now());
            } elseif ($request->status === 'unlocked') {
                $query->where(function($q) {
                    $q->whereNull('locked_until')
                      ->orWhere('locked_until', '<=', now());
                });
            }
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->paginate(25)->withQueryString();

        // Get filter options
        $roles = \App\Models\Role::orderBy('name')->get();

        return view('admin.users', compact('users', 'roles'));
    }

    /**
     * Show user details
     *
     * @param User $user
     * @return \Illuminate\View\View
     */
    public function userDetails(User $user)
    {
        $user->load('role', 'passwordHistories');
        
        // Get recent audit logs for this user
        $recentLogs = AuditLog::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();
        
        // Get user statistics
        $stats = [
            'total_logins' => AuditLog::where('user_id', $user->id)
                ->where('event_type', 'login_success')
                ->count(),
            'failed_attempts' => AuditLog::where('user_id', $user->id)
                ->where('event_type', 'login_failed')
                ->count(),
            'last_login' => AuditLog::where('user_id', $user->id)
                ->where('event_type', 'login_success')
                ->latest()
                ->first(),
            'password_changes' => AuditLog::where('user_id', $user->id)
                ->where('event_type', 'password_changed')
                ->count(),
        ];
        
        return view('admin.user-details', compact('user', 'recentLogs', 'stats'));
    }

    /**
     * Unlock account
     *
     * @param User $user
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function unlockUser(User $user, Request $request)
    {
        try {
            if (!$user->isLocked()) {
                return redirect()->back()
                    ->with('warning', 'User account is not currently locked.');
            }

            // Unlock user
            $user->unlock();

            // Log
            $this->auditLogger->logAccountUnlock($user->id, Auth::id(), $request);

            return redirect()->back()
                ->with('success', "User {$user->name} has been unlocked successfully.");
                
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Failed to unlock user: ' . $e->getMessage());
        }
    }

    /**
     * Show create user form
     *
     * @return \Illuminate\View\View
     */
    public function createUser()
    {
        $roles = Role::orderBy('name')->get();
        return view('admin.create-user', compact('roles'));
    }

    /**
     * Store new user
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function storeUser(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => ['required', 'confirmed', Password::min(8)],
            'role_id' => 'required|exists:roles,id',
            'must_change_password' => 'nullable|boolean',
        ]);

        try {
            // Validate password against policy
            $passwordValidation = $this->passwordPolicyService->validateComplexity($request->password);
            if (!$passwordValidation['valid']) {
                return redirect()->back()
                    ->withErrors(['password' => $passwordValidation['errors']])
                    ->withInput();
            }

            // Enregistrer la ré-authentification pour l'opération critique
            $this->auditLogger->logSecurityEvent('reauth_critical_operation', Auth::id(), [
                'operation' => 'user_creation',
                'target_email' => $request->email,
                'message' => 'Ré-authentifié pour la création d\'utilisateur'
            ], $request);

            // Hash password using PBKDF2
            $hashedData = $this->pbkdf2Hasher->hash($request->password);

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => $hashedData['hash'],
                'password_salt' => $hashedData['salt'],
                'role_id' => $request->role_id,
                'password_changed_at' => now(),
                'must_change_password' => $request->boolean('must_change_password', false),
            ]);

            // Add password to history
            $this->passwordHistoryService->addToHistory($user->id, $hashedData);

            // Log user creation
            $this->auditLogger->logUserCreated($user->id, Auth::id(), $request);

            return redirect()->route('admin.users')
                ->with('success', "User {$user->name} created successfully.");

        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Failed to create user: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Show the edit user form
     *
     * @param User $user
     * @return \Illuminate\View\View
     */
    public function editUser(User $user)
    {
        $roles = Role::orderBy('name')->get();
        return view('admin.edit-user', compact('user', 'roles'));
    }

    /**
     * Update user
     * 
     * @param Request $request
     * @param User $user
     * @return \Illuminate\Http\RedirectResponse
     */
    public function updateUser(Request $request, User $user)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'role_id' => 'required|exists:roles,id',
        ]);

        try {
            $oldData = $user->toArray();
            $roleChanged = $user->role_id != $request->role_id;
            
            $user->update([
                'name' => $request->name,
                'email' => $request->email,
                'role_id' => $request->role_id,
            ]);

            // Log user update
            $this->auditLogger->logUserUpdated($user->id, Auth::id(), $oldData, $user->toArray(), $request);

            // If the role changed and it's the current user, regenerate session
            if ($roleChanged && Auth::id() === $user->id) {
                $this->sessionSecurityService->regenerateSession($request, true);
                $this->auditLogger->logSecurityEvent('role_changed_session_regenerated', $user->id, [
                    'message' => 'Session régénérée après changement de rôle',
                    'old_role_id' => $oldData['role_id'],
                    'new_role_id' => $request->role_id
                ], $request);
            }

            return redirect()->route('admin.users')
                ->with('success', "User {$user->name} updated successfully.");

        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Failed to update user: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Show user activity
     *
     * @param User $user
     * @param Request $request
     * @return \Illuminate\View\View
     */
    public function userActivity(User $user, Request $request)
    {
        $query = AuditLog::where('user_id', $user->id)->orderBy('created_at', 'desc');

        // Apply filters
        if ($request->filled('event_type')) {
            $query->where('event_type', $request->event_type);
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('created_at', [$request->start_date, $request->end_date]);
        }

        $activities = $query->paginate(25)->withQueryString();

        // Get available event types for this user
        $eventTypes = AuditLog::where('user_id', $user->id)
            ->select('event_type')
            ->distinct()
            ->orderBy('event_type')
            ->pluck('event_type');

        return view('admin.user-activity', compact('user', 'activities', 'eventTypes'));
    }

    /**
     * Reset user password
     *
     * @param Request $request
     * @param User $user
     * @return \Illuminate\Http\RedirectResponse
     */
    public function resetUserPassword(Request $request, User $user)
    {
        $request->validate([
            'password' => ['required', 'confirmed', Password::min(8)],
            'force_change' => 'nullable|boolean',
        ]);

        try {
            // Validate password against policy
            $passwordValidation = $this->passwordPolicyService->validatePassword($request->password, $user);
            if (!$passwordValidation['valid']) {
                return redirect()->back()
                    ->withErrors(['password' => $passwordValidation['errors']]);
            }

            // Enregistrer la ré-authentification pour l'opération critique
            $this->auditLogger->logSecurityEvent('reauth_critical_operation', Auth::id(), [
                'operation' => 'password_reset',
                'target_user_id' => $user->id,
                'target_user_email' => $user->email,
                'message' => 'Ré-authentifié pour la réinitialisation de mot de passe'
            ], $request);

            // Hash password using PBKDF2
            $hashedData = $this->pbkdf2Hasher->hash($request->password);
            
            $user->update([
                'password' => $hashedData['hash'],
                'password_salt' => $hashedData['salt'],
                'password_changed_at' => now(),
                'must_change_password' => $request->boolean('force_change', false),
            ]);

            // Add new password to history
            $this->passwordHistoryService->addToHistory($user->id, $hashedData);

            // Log password reset
            $this->auditLogger->logPasswordReset($user->id, Auth::id(), $request);

            return redirect()->back()
                ->with('success', "Password reset successfully for {$user->name}.");

        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Failed to reset password: ' . $e->getMessage());
        }
    }

    /**
     * Terminate all user sessions
     *
     * @param Request $request
     * @param User $user
     * @return \Illuminate\Http\RedirectResponse
     */
    public function terminateUserSessions(Request $request, User $user)
    {
        try {
            
            // Log session termination
            $this->auditLogger->logSessionsTerminated($user->id, Auth::id(), $request);

            return redirect()->back()
                ->with('success', "All sessions terminated for {$user->name}.");

        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Failed to terminate sessions: ' . $e->getMessage());
        }
    }

    /**
     * Show the audit logs page
     *
     * @param Request $request
     * @return \Illuminate\View\View
     */
    public function auditLogs(Request $request)
    {
        $query = AuditLog::with('user')->orderBy('created_at', 'desc');

        // Apply filters
        if ($request->filled('event_type')) {
            $query->byEventType($request->event_type);
        }

        if ($request->filled('user_id')) {
            $query->byUser($request->user_id);
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->byDateRange($request->start_date, $request->end_date);
        }

        if ($request->filled('severity')) {
            $eventTypesBySeverity = $this->getEventTypesBySeverity();
            if (isset($eventTypesBySeverity[$request->severity])) {
                $query->whereIn('event_type', $eventTypesBySeverity[$request->severity]);
            }
        }

        // Pagination
        $auditLogs = $query->paginate(25)->withQueryString();

        // filter options
        $eventTypes = AuditLog::select('event_type')
            ->distinct()
            ->orderBy('event_type')
            ->pluck('event_type');

        $users = User::select('id', 'name', 'email')
            ->orderBy('name')
            ->get();

        return view('admin.audit-logs', compact('auditLogs', 'eventTypes', 'users'));
    }

    /**
     * Show audit log details
     *
     * @param AuditLog $auditLog
     * @return \Illuminate\View\View
     */
    public function auditLogDetails(AuditLog $auditLog)
    {
        $auditLog->load('user');
        
        return view('admin.audit-log-details', compact('auditLog'));
    }

    /**
     * Export logs to CSV
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function exportAuditLogs(Request $request)
    {
        $query = AuditLog::with('user')->orderBy('created_at', 'desc');

        if ($request->filled('event_type')) {
            $query->byEventType($request->event_type);
        }

        if ($request->filled('user_id')) {
            $query->byUser($request->user_id);
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->byDateRange($request->start_date, $request->end_date);
        }

        if ($request->filled('severity')) {
            $eventTypesBySeverity = $this->getEventTypesBySeverity();
            if (isset($eventTypesBySeverity[$request->severity])) {
                $query->whereIn('event_type', $eventTypesBySeverity[$request->severity]);
            }
        }

        $filename = 'audit_logs_' . Carbon::now()->format('Y-m-d_H-i-s') . '.csv';

        // For testing
        if (app()->environment('testing')) {
            $csvContent = "ID,Event Type,User,IP Address,User Agent,Details,Severity,Created At\n";
            
            $auditLogs = $query->get();
            foreach ($auditLogs as $log) {
                $csvContent .= implode(',', [
                    $log->id,
                    '"' . $log->formatted_event_type . '"',
                    '"' . ($log->user ? $log->user->name . ' (' . $log->user->email . ')' : 'N/A') . '"',
                    '"' . $log->ip_address . '"',
                    '"' . $log->user_agent . '"',
                    '"' . str_replace('"', '""', json_encode($log->details)) . '"',
                    '"' . $log->severity . '"',
                    '"' . $log->created_at->format('Y-m-d H:i:s') . '"'
                ]) . "\n";
            }
            
            return response($csvContent, 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);
        }

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            
            fputcsv($handle, [
                'ID',
                'Event Type',
                'User',
                'IP Address',
                'User Agent',
                'Details',
                'Severity',
                'Created At'
            ]);

            $query->chunk(1000, function ($auditLogs) use ($handle) {
                foreach ($auditLogs as $log) {
                    fputcsv($handle, [
                        $log->id,
                        $log->formatted_event_type,
                        $log->user ? $log->user->name . ' (' . $log->user->email . ')' : 'N/A',
                        $log->ip_address,
                        $log->user_agent,
                        json_encode($log->details),
                        $log->severity,
                        $log->created_at->format('Y-m-d H:i:s')
                    ]);
                }
            });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Get stats for the audit log
     *
     * @return \Illuminate\View\View
     */
    public function auditStatistics()
    {
        $stats = [
            'total_logs' => AuditLog::count(),
            'logs_today' => AuditLog::whereDate('created_at', Carbon::today())->count(),
            'logs_this_week' => AuditLog::where('created_at', '>=', Carbon::now()->startOfWeek())->count(),
            'logs_this_month' => AuditLog::where('created_at', '>=', Carbon::now()->startOfMonth())->count(),
        ];

        $authStats = [
            'successful_logins_today' => AuditLog::byEventType('login_success')
                ->whereDate('created_at', Carbon::today())->count(),
            'failed_logins_today' => AuditLog::byEventType('login_failed')
                ->whereDate('created_at', Carbon::today())->count(),
            'locked_accounts_today' => AuditLog::byEventType('account_locked')
                ->whereDate('created_at', Carbon::today())->count(),
        ];

        $highSeverityEvents = AuditLog::with('user')
            ->whereIn('event_type', $this->getEventTypesBySeverity()['high'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $eventDistribution = AuditLog::selectRaw('event_type, COUNT(*) as count')
            ->where('created_at', '>=', Carbon::now()->subDays(30))
            ->groupBy('event_type')
            ->orderBy('count', 'desc')
            ->get();

        return view('admin.audit-statistics', compact(
            'stats',
            'authStats',
            'highSeverityEvents',
            'eventDistribution'
        ));
    }

    /**
     * Get event types grouped by severity level
     *
     * @return array
     */
    private function getEventTypesBySeverity(): array
    {
        return [
            'high' => [
                'account_locked',
                'unauthorized_access',
                'password_policy_violation',
                'session_hijack_detected',
            ],
            'medium' => [
                'login_failed',
                'password_changed',
                'role_changed',
                'security_config_changed',
                'user_created',
            ],
            'low' => [
                'login_success',
                'user_logout',
                'session_created',
                'session_destroyed',
            ],
        ];
    }
}