@extends('layouts.app')

@section('title', 'Modifier l\'utilisateur - ' . $user->name)

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-2">
                            <li class="breadcrumb-item"><a href="{{ route('admin.users') }}">Utilisateurs</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('admin.users.details', $user) }}">{{ $user->name }}</a></li>
                            <li class="breadcrumb-item active">Modifier</li>
                        </ol>
                    </nav>
                    <h1 class="h3 mb-0">Modifier l'utilisateur</h1>
                </div>
                <div>
                    <a href="{{ route('admin.users.details', $user) }}" class="btn btn-secondary">
                        Retour aux détails
                    </a>
                </div>
            </div>

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <div class="row">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                Informations utilisateur
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="{{ route('admin.users.update', $user) }}">
                                @csrf
                                @method('PUT')

                                <div class="mb-3">
                                    <label for="name" class="form-label">Nom <span class="text-danger">*</span></label>
                                    <input type="text" 
                                           class="form-control @error('name') is-invalid @enderror" 
                                           id="name" 
                                           name="name" 
                                           value="{{ old('name', $user->name) }}" 
                                           required 
                                           autofocus>
                                    @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                    <input type="email" 
                                           class="form-control @error('email') is-invalid @enderror" 
                                           id="email" 
                                           name="email" 
                                           value="{{ old('email', $user->email) }}" 
                                           required>
                                    @error('email')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label for="role_id" class="form-label">Rôle <span class="text-danger">*</span></label>
                                    <select class="form-select @error('role_id') is-invalid @enderror" 
                                            id="role_id" 
                                            name="role_id" 
                                            required>
                                        <option value="">Sélectionner un rôle...</option>
                                        @foreach($roles as $role)
                                            <option value="{{ $role->id }}" 
                                                    {{ old('role_id', $user->role_id) == $role->id ? 'selected' : '' }}>
                                                {{ $role->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('role_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="d-flex justify-content-end gap-2">
                                    <a href="{{ route('admin.users.details', $user) }}" class="btn btn-secondary">
                                        Annuler
                                    </a>
                                    <button type="submit" class="btn btn-primary">
                                        Enregistrer les modifications
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Password Reset Section -->
                    <div class="card mt-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                Réinitialiser le mot de passe
                            </h5>
                        </div>
                        <div class="card-body">
                            <p class="text-muted">Réinitialisez le mot de passe de l'utilisateur. Cette action nécessite une ré-authentification.</p>
                            
                            <button type="button" 
                                    class="btn btn-warning" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#resetPasswordModal">
                                Réinitialiser le mot de passe
                            </button>
                        </div>
                    </div>

                    <!-- Session Management Section -->
                    <div class="card mt-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                Gestion des sessions
                            </h5>
                        </div>
                        <div class="card-body">
                            <p class="text-muted">Terminez toutes les sessions actives de cet utilisateur. L'utilisateur devra se reconnecter.</p>
                            
                            <button type="button" 
                                    class="btn btn-danger" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#terminateSessionsModal">
                                Terminer toutes les sessions
                            </button>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                Statut du compte
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label text-muted">Statut</label>
                                <div>
                                    @if($user->isLocked())
                                        <span class="badge bg-danger fs-6">Verrouillé</span>
                                        <div class="text-muted small mt-1">
                                            Jusqu'à : {{ $user->locked_until->format('j M Y H:i') }}
                                        </div>
                                    @else
                                        <span class="badge bg-success fs-6">Actif</span>
                                    @endif
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label text-muted">Tentatives échouées</label>
                                <div>
                                    @if($user->failed_login_attempts > 0)
                                        <span class="badge bg-warning fs-6">{{ $user->failed_login_attempts }}</span>
                                    @else
                                        <span class="text-success">0</span>
                                    @endif
                                </div>
                            </div>

                            <div class="mb-3">
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

                            @if($user->isLocked())
                                <div class="d-grid">
                                    <button type="button" 
                                            class="btn btn-success" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#unlockModal">
                                        Déverrouiller le compte
                                    </button>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Reset Password Modal -->
<div class="modal fade" id="resetPasswordModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('admin.users.reset-password', $user) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Réinitialiser le mot de passe</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Définissez un nouveau mot de passe pour <strong>{{ $user->name }}</strong>.</p>
                    
                    <div class="alert alert-warning">
                        <strong>Attention :</strong> Cette action nécessite une ré-authentification pour des raisons de sécurité.
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">Nouveau mot de passe <span class="text-danger">*</span></label>
                        <input type="password" 
                               class="form-control" 
                               id="password" 
                               name="password" 
                               required>
                    </div>

                    <div class="mb-3">
                        <label for="password_confirmation" class="form-label">Confirmer le mot de passe <span class="text-danger">*</span></label>
                        <input type="password" 
                               class="form-control" 
                               id="password_confirmation" 
                               name="password_confirmation" 
                               required>
                    </div>

                    <div class="form-check">
                        <input class="form-check-input" 
                               type="checkbox" 
                               id="force_change" 
                               name="force_change" 
                               value="1">
                        <label class="form-check-label" for="force_change">
                            Forcer le changement à la prochaine connexion
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-warning">
                        Réinitialiser le mot de passe
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Terminate Sessions Modal -->
<div class="modal fade" id="terminateSessionsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Terminer toutes les sessions</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Êtes-vous sûr de vouloir terminer toutes les sessions actives de <strong>{{ $user->name }}</strong> ?</p>
                
                <div class="alert alert-warning">
                    <strong>Attention :</strong> L'utilisateur sera déconnecté de tous les appareils et devra se reconnecter.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <form method="POST" action="{{ route('admin.users.terminate-sessions', $user) }}" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-danger">
                        Terminer les sessions
                    </button>
                </form>
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
