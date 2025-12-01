@extends('layouts.app')

@section('title', 'Détails utilisateur - ' . $user->name)

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-2">
                            <li class="breadcrumb-item"><a href="{{ route('admin.users') }}">Utilisateurs</a></li>
                            <li class="breadcrumb-item active">{{ $user->name }}</li>
                        </ol>
                    </nav>
                    <h1 class="h3 mb-0">Détails utilisateur</h1>
                </div>
                <div>
                    @if($user->isLocked())
                        <button type="button" 
                                class="btn btn-success me-2" 
                                data-bs-toggle="modal" 
                                data-bs-target="#unlockModal">
                            Déverrouiller le compte
                        </button>
                    @endif
                    <a href="{{ route('admin.users') }}" class="btn btn-secondary">
                        Retour aux utilisateurs
                    </a>
                </div>
            </div>

            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if(session('warning'))
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    {{ session('warning') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <div class="row">
                <!-- User Information -->
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                Informations utilisateur
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-4">
                                <h5 class="mb-1">{{ $user->name }}</h5>
                                <p class="text-muted mb-0">{{ $user->email }}</p>
                            </div>

                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label text-muted">Rôle</label>
                                    <div>
                                        <span class="badge bg-info fs-6">{{ $user->role->name ?? 'Aucun rôle' }}</span>
                                    </div>
                                </div>
                                
                                <div class="col-12">
                                    <label class="form-label text-muted">Statut du compte</label>
                                    <div>
                                        @if($user->isLocked())
                                            <span class="badge bg-danger fs-6">
                                                Verrouillé
                                            </span>
                                            <div class="text-muted small mt-1">
                                                Jusqu'à : {{ $user->locked_until->format('j M Y H:i') }}<br>
                                                Restant : {{ $user->getLockTimeRemaining() }}
                                            </div>
                                        @else
                                            <span class="badge bg-success fs-6">
                                                Actif
                                            </span>
                                        @endif
                                    </div>
                                </div>
                                
                                <div class="col-6">
                                    <label class="form-label text-muted">Tentatives échouées</label>
                                    <div>
                                        @if($user->failed_login_attempts > 0)
                                            <span class="badge bg-warning fs-6">{{ $user->failed_login_attempts }}</span>
                                        @else
                                            <span class="text-success">0</span>
                                        @endif
                                    </div>
                                </div>
                                
                                <div class="col-6">
                                    <label class="form-label text-muted">Doit changer le mot de passe</label>
                                    <div>
                                        @if($user->must_change_password)
                                            <span class="badge bg-warning fs-6">Oui</span>
                                        @else
                                            <span class="text-success">Non</span>
                                        @endif
                                    </div>
                                </div>
                                
                                <div class="col-12">
                                    <label class="form-label text-muted">Mot de passe modifié</label>
                                    <div>
                                        @if($user->password_changed_at)
                                            {{ $user->password_changed_at->format('j M Y H:i') }}
                                            <div class="text-muted small">{{ $user->password_changed_at->diffForHumans() }}</div>
                                        @else
                                            <span class="text-muted">Jamais</span>
                                        @endif
                                    </div>
                                </div>
                                
                                <div class="col-12">
                                    <label class="form-label text-muted">Compte créé</label>
                                    <div>
                                        {{ $user->created_at->format('j M Y H:i') }}
                                        <div class="text-muted small">{{ $user->created_at->diffForHumans() }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- User Statistics -->
                    <div class="card mt-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                Statistiques
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-6">
                                    <div class="text-center">
                                        <div class="h4 text-success mb-1">{{ $stats['total_logins'] }}</div>
                                        <div class="text-muted small">Connexions totales</div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="text-center">
                                        <div class="h4 text-danger mb-1">{{ $stats['failed_attempts'] }}</div>
                                        <div class="text-muted small">Tentatives échouées</div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="text-center">
                                        <div class="h4 text-info mb-1">{{ $stats['password_changes'] }}</div>
                                        <div class="text-muted small">Changements de mot de passe</div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="text-center">
                                        <div class="h4 text-warning mb-1">{{ $user->passwordHistories->count() }}</div>
                                        <div class="text-muted small">Historique des mots de passe</div>
                                    </div>
                                </div>
                            </div>
                            
                            @if($stats['last_login'])
                                <div class="mt-3 pt-3 border-top">
                                    <label class="form-label text-muted">Dernière connexion</label>
                                    <div>
                                        {{ $stats['last_login']->created_at->format('j M Y H:i') }}
                                        <div class="text-muted small">{{ $stats['last_login']->created_at->diffForHumans() }}</div>
                                        @if($stats['last_login']->ip_address)
                                            <div class="text-muted small">IP : {{ $stats['last_login']->ip_address }}</div>
                                        @endif
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                Activité récente
                            </h5>
                        </div>
                        <div class="card-body p-0">
                            @if($recentLogs->count() > 0)
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Événement</th>
                                                <th>Détails</th>
                                                <th>Adresse IP</th>
                                                <th>Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($recentLogs as $log)
                                                <tr>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <span>{{ $log->formatted_event_type }}</span>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        @if($log->details && is_array($log->details))
                                                            @if(isset($log->details['message']))
                                                                {{ $log->details['message'] }}
                                                            @elseif(isset($log->details['reason']))
                                                                {{ $log->details['reason'] }}
                                                            @else
                                                                <span class="text-muted">-</span>
                                                            @endif
                                                        @else
                                                            <span class="text-muted">-</span>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        <code class="small">{{ $log->ip_address ?? 'N/A' }}</code>
                                                    </td>
                                                    <td>
                                                        <div>{{ $log->created_at->format('j M Y') }}</div>
                                                        <div class="text-muted small">{{ $log->created_at->format('H:i') }}</div>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <div class="text-center py-5">
                                    <h5 class="mt-3 text-muted">Aucune activité récente</h5>
                                    <p class="text-muted">Aucun journal d'audit trouvé pour cet utilisateur.</p>
                                </div>
                            @endif
                        </div>
                        @if($recentLogs->count() > 0)
                            <div class="card-footer text-center">
                                <a href="{{ route('admin.audit-logs', ['user_id' => $user->id]) }}" class="btn btn-secondary btn-sm">
                                    Voir toute l'activité
                                </a>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Unlock Modal -->
@if($user->isLocked())
    <div class="modal fade" id="unlockModal" tabindex="-1">
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
                        <button type="submit" class="btn btn-primary">
                            Déverrouiller le compte
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endif
@endsection

