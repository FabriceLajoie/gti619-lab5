@extends('layouts.app')

@section('title', 'Gestion des utilisateurs')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">Gestion des utilisateurs</h1>
                <div>
                    <a href="{{ route('admin.security-config') }}" class="btn btn-outline-primary me-2">
                        <i class="fas fa-shield-alt"></i> Configuration de sécurité
                    </a>
                    <a href="{{ route('admin.audit-logs') }}" class="btn btn-outline-info">
                        <i class="fas fa-list"></i> Journaux d'audit
                    </a>
                </div>
            </div>

            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if(session('warning'))
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>{{ session('warning') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>{{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" action="{{ route('admin.users') }}" class="row g-3">
                        <div class="col-md-3">
                            <label for="search" class="form-label">Rechercher</label>
                            <input type="text" class="form-control" id="search" name="search" 
                                   value="{{ request('search') }}" placeholder="Nom ou email...">
                        </div>
                        <div class="col-md-3">
                            <label for="role_id" class="form-label">Rôle</label>
                            <select class="form-select" id="role_id" name="role_id">
                                <option value="">Tous les rôles</option>
                                @foreach($roles as $role)
                                    <option value="{{ $role->id }}" {{ request('role_id') == $role->id ? 'selected' : '' }}>
                                        {{ $role->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="status" class="form-label">Statut</label>
                            <select class="form-select" id="status" name="status">
                                <option value="">Tous les statuts</option>
                                <option value="unlocked" {{ request('status') === 'unlocked' ? 'selected' : '' }}>Déverrouillé</option>
                                <option value="locked" {{ request('status') === 'locked' ? 'selected' : '' }}>Verrouillé</option>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="fas fa-search"></i> Filtrer
                            </button>
                            <a href="{{ route('admin.users') }}" class="btn btn-outline-secondary">
                                <i class="fas fa-times"></i> Effacer
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Users Table -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-users me-2"></i>Utilisateurs ({{ $users->total() }})
                    </h5>
                </div>
                <div class="card-body p-0">
                    @if($users->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Utilisateur</th>
                                        <th>Rôle</th>
                                        <th>Statut</th>
                                        <th>Tentatives échouées</th>
                                        <th>Dernière connexion</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($users as $user)
                                        <tr>
                                            <td>
                                                <div>
                                                    <div class="fw-semibold">{{ $user->name }}</div>
                                                    <div class="text-muted small">{{ $user->email }}</div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-info">{{ $user->role->name ?? 'Aucun rôle' }}</span>
                                            </td>
                                            <td>
                                                @if($user->isLocked())
                                                    <span class="badge bg-danger">
                                                        Verrouillé
                                                    </span>
                                                    <div class="text-muted small">
                                                        Jusqu'à : {{ $user->locked_until->format('j M Y H:i') }}
                                                    </div>
                                                @else
                                                    <span class="badge bg-success">
                                                        Actif
                                                    </span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($user->failed_login_attempts > 0)
                                                    <span class="badge bg-warning">{{ $user->failed_login_attempts }}</span>
                                                @else
                                                    <span class="text-muted">0</span>
                                                @endif
                                            </td>
                                            <td>
                                                @php
                                                    $lastLogin = \App\Models\AuditLog::where('user_id', $user->id)
                                                        ->where('event_type', 'login_success')
                                                        ->latest()
                                                        ->first();
                                                @endphp
                                                @if($lastLogin)
                                                    <div>{{ $lastLogin->created_at->format('j M Y') }}</div>
                                                    <div class="text-muted small">{{ $lastLogin->created_at->format('H:i') }}</div>
                                                @else
                                                    <span class="text-muted">Jamais</span>
                                                @endif
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <a href="{{ route('admin.users.details', $user) }}" 
                                                       class="btn btn-sm btn-outline-primary" 
                                                       data-bs-toggle="tooltip" title="Voir les détails">
                                                        Détails
                                                    </a>
                                                    @if($user->isLocked())
                                                        <button type="button" 
                                                                class="btn btn-sm btn-outline-success" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#unlockModal{{ $user->id }}"
                                                                title="Déverrouiller le compte">
                                                            Déverrouiller
                                                        </button>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>

                                        <!-- Unlock Modal for each locked user -->
                                        @if($user->isLocked())
                                            <div class="modal fade" id="unlockModal{{ $user->id }}" tabindex="-1">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Déverrouiller le compte utilisateur</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <p>Êtes-vous sûr de vouloir déverrouiller le compte de <strong>{{ $user->name }}</strong> ?</p>
                                                            <div class="alert alert-info">
                                                                <strong>Statut actuel :</strong><br>
                                                                Verrouillé jusqu'à : {{ $user->locked_until->format('j M Y H:i') }}<br>
                                                                Tentatives échouées : {{ $user->failed_login_attempts }}<br>
                                                                Temps restant : {{ $user->getLockTimeRemaining() }}
                                                            </div>
                                                            <p class="text-muted">Cela déverrouillera immédiatement le compte et remettra à zéro le compteur de tentatives de connexion échouées.</p>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                                                            <form method="POST" action="{{ route('admin.users.unlock', $user) }}" class="d-inline">
                                                                @csrf
                                                                <button type="submit" class="btn btn-success">
                                                                    <i class="fas fa-unlock me-2"></i>Déverrouiller le compte
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        @endif
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        @if($users->hasPages())
                            <div class="card-footer">
                                {{ $users->links() }}
                            </div>
                        @endif
                    @else
                        <div class="text-center py-5">
                            <i class="fas fa-users text-muted" style="font-size: 3rem;"></i>
                            <h5 class="mt-3 text-muted">Aucun utilisateur trouvé</h5>
                            <p class="text-muted">Essayez d'ajuster vos critères de recherche.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>
@endpush

