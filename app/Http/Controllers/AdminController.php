<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class AdminController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('role:Administrateur');
    }

    /**
     * Show the audit logs page.
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

        // Get filter options
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
     * Show audit log details.
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
     * Export audit logs as CSV.
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function exportAuditLogs(Request $request)
    {
        $query = AuditLog::with('user')->orderBy('created_at', 'desc');

        // Apply same filters as the main view
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

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            
            // CSV headers
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

            // Stream data in chunks to handle large datasets
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
     * Get statistics for the audit logs dashboard.
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

        // Authentication statistics
        $authStats = [
            'successful_logins_today' => AuditLog::byEventType('login_success')
                ->whereDate('created_at', Carbon::today())->count(),
            'failed_logins_today' => AuditLog::byEventType('login_failed')
                ->whereDate('created_at', Carbon::today())->count(),
            'locked_accounts_today' => AuditLog::byEventType('account_locked')
                ->whereDate('created_at', Carbon::today())->count(),
        ];

        // Recent high-severity events
        $highSeverityEvents = AuditLog::with('user')
            ->whereIn('event_type', $this->getEventTypesBySeverity()['high'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // Event type distribution (last 30 days)
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
     * Get event types grouped by severity level.
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