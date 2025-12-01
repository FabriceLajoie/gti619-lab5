@extends('layouts.app')

@section('title', 'Configuration de sécurité')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">Configuration de sécurité</h1>
                <div>
                    <a href="{{ route('admin.users') }}" class="btn btn-outline-primary me-2">
                        <i class="fas fa-users"></i> Gestion des utilisateurs
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

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>{{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <div class="row">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-shield-alt me-2"></i>Paramètres de sécurité
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="{{ route('admin.security-config.update') }}" id="securityConfigForm">
                                @csrf
                                
                                <!-- Authentication Settings -->
                                <div class="row mb-4">
                                    <div class="col-12">
                                        <h6 class="text-primary border-bottom pb-2 mb-3">
                                            <i class="fas fa-key me-2"></i>Paramètres d'authentification
                                        </h6>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label for="max_login_attempts" class="form-label">
                                            Tentatives de connexion maximales
                                            <i class="fas fa-info-circle text-muted" data-bs-toggle="tooltip" 
                                               title="Nombre de tentatives de connexion échouées avant verrouillage du compte"></i>
                                        </label>
                                        <input type="number" 
                                               class="form-control @error('max_login_attempts') is-invalid @enderror" 
                                               id="max_login_attempts" 
                                               name="max_login_attempts" 
                                               value="{{ old('max_login_attempts', $config->max_login_attempts) }}"
                                               min="1" max="20" required>
                                        @error('max_login_attempts')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label for="lockout_duration_minutes" class="form-label">
                                            Durée de verrouillage (minutes)
                                            <i class="fas fa-info-circle text-muted" data-bs-toggle="tooltip" 
                                               title="Durée pendant laquelle les comptes restent verrouillés après dépassement des tentatives"></i>
                                        </label>
                                        <input type="number" 
                                               class="form-control @error('lockout_duration_minutes') is-invalid @enderror" 
                                               id="lockout_duration_minutes" 
                                               name="lockout_duration_minutes" 
                                               value="{{ old('lockout_duration_minutes', $config->lockout_duration_minutes) }}"
                                               min="1" max="1440" required>
                                        @error('lockout_duration_minutes')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <!-- Password Policy Settings -->
                                <div class="row mb-4">
                                    <div class="col-12">
                                        <h6 class="text-primary border-bottom pb-2 mb-3">
                                            <i class="fas fa-lock me-2"></i>Politique de mot de passe
                                        </h6>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label for="password_min_length" class="form-label">
                                            Longueur minimale du mot de passe
                                        </label>
                                        <input type="number" 
                                               class="form-control @error('password_min_length') is-invalid @enderror" 
                                               id="password_min_length" 
                                               name="password_min_length" 
                                               value="{{ old('password_min_length', $config->password_min_length) }}"
                                               min="8" max="128" required>
                                        @error('password_min_length')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label for="password_history_count" class="form-label">
                                            Historique des mots de passe
                                            <i class="fas fa-info-circle text-muted" data-bs-toggle="tooltip" 
                                               title="Nombre de mots de passe précédents à mémoriser pour éviter la réutilisation"></i>
                                        </label>
                                        <input type="number" 
                                               class="form-control @error('password_history_count') is-invalid @enderror" 
                                               id="password_history_count" 
                                               name="password_history_count" 
                                               value="{{ old('password_history_count', $config->password_history_count) }}"
                                               min="0" max="50" required>
                                        @error('password_history_count')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    
                                    <div class="col-12 mb-3">
                                        <label class="form-label">Exigences du mot de passe</label>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" 
                                                           id="password_require_uppercase" 
                                                           name="password_require_uppercase" 
                                                           value="1"
                                                           {{ old('password_require_uppercase', $config->password_require_uppercase) ? 'checked' : '' }}>
                                                    <label class="form-check-label" for="password_require_uppercase">
                                                        Exiger des lettres majuscules
                                                    </label>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" 
                                                           id="password_require_lowercase" 
                                                           name="password_require_lowercase" 
                                                           value="1"
                                                           {{ old('password_require_lowercase', $config->password_require_lowercase) ? 'checked' : '' }}>
                                                    <label class="form-check-label" for="password_require_lowercase">
                                                        Exiger des lettres minuscules
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" 
                                                           id="password_require_numbers" 
                                                           name="password_require_numbers" 
                                                           value="1"
                                                           {{ old('password_require_numbers', $config->password_require_numbers) ? 'checked' : '' }}>
                                                    <label class="form-check-label" for="password_require_numbers">
                                                        Exiger des chiffres
                                                    </label>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" 
                                                           id="password_require_special" 
                                                           name="password_require_special" 
                                                           value="1"
                                                           {{ old('password_require_special', $config->password_require_special) ? 'checked' : '' }}>
                                                    <label class="form-check-label" for="password_require_special">
                                                        Exiger des caractères spéciaux
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                        @error('password_requirements')
                                            <div class="text-danger small mt-1">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label for="password_expiry_days" class="form-label">
                                            Expiration du mot de passe (jours)
                                            <i class="fas fa-info-circle text-muted" data-bs-toggle="tooltip" 
                                               title="Nombre de jours avant expiration des mots de passe (0 = jamais)"></i>
                                        </label>
                                        <input type="number" 
                                               class="form-control @error('password_expiry_days') is-invalid @enderror" 
                                               id="password_expiry_days" 
                                               name="password_expiry_days" 
                                               value="{{ old('password_expiry_days', $config->password_expiry_days) }}"
                                               min="0" max="365" required>
                                        @error('password_expiry_days')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <!-- Cryptographic Settings -->
                                <div class="row mb-4">
                                    <div class="col-12">
                                        <h6 class="text-primary border-bottom pb-2 mb-3">
                                            <i class="fas fa-cogs me-2"></i>Paramètres cryptographiques
                                        </h6>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label for="pbkdf2_iterations" class="form-label">
                                            Itérations PBKDF2
                                            <i class="fas fa-info-circle text-muted" data-bs-toggle="tooltip" 
                                               title="Nombre d'itérations PBKDF2 pour le hachage des mots de passe (plus élevé = plus sécurisé mais plus lent)"></i>
                                        </label>
                                        <input type="number" 
                                               class="form-control @error('pbkdf2_iterations') is-invalid @enderror" 
                                               id="pbkdf2_iterations" 
                                               name="pbkdf2_iterations" 
                                               value="{{ old('pbkdf2_iterations', $config->pbkdf2_iterations) }}"
                                               min="10000" max="1000000" step="10000" required>
                                        @error('pbkdf2_iterations')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                        <div class="form-text">
                                            Recommandé : 100 000 - 500 000 itérations
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label for="session_timeout_minutes" class="form-label">
                                            Délai d'expiration de session (minutes)
                                            <i class="fas fa-info-circle text-muted" data-bs-toggle="tooltip" 
                                               title="Durée pendant laquelle les sessions utilisateur restent actives sans activité"></i>
                                        </label>
                                        <input type="number" 
                                               class="form-control @error('session_timeout_minutes') is-invalid @enderror" 
                                               id="session_timeout_minutes" 
                                               name="session_timeout_minutes" 
                                               value="{{ old('session_timeout_minutes', $config->session_timeout_minutes) }}"
                                               min="5" max="1440" required>
                                        @error('session_timeout_minutes')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <div class="d-flex justify-content-between">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>Mettre à jour la configuration
                                    </button>
                                    <button type="button" class="btn btn-outline-warning" data-bs-toggle="modal" data-bs-target="#resetModal">
                                        <i class="fas fa-undo me-2"></i>Réinitialiser aux valeurs par défaut
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-info-circle me-2"></i>Informations de configuration
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <h6 class="text-muted">Statut actuel</h6>
                                <div class="d-flex align-items-center">
                                    <span class="badge bg-success me-2">Actif</span>
                                    <small class="text-muted">Dernière mise à jour : {{ $config->updated_at->format('j M Y H:i') }}</small>
                                </div>
                            </div>
                            

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Reset Confirmation Modal -->
<div class="modal fade" id="resetModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Réinitialiser la configuration</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Êtes-vous sûr de vouloir réinitialiser toute la configuration de sécurité aux valeurs par défaut ?</p>
                <p class="text-warning"><strong>Attention :</strong> Cette action ne peut pas être annulée et appliquera immédiatement les paramètres de sécurité par défaut.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <form method="POST" action="{{ route('admin.security-config.reset') }}" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-undo me-2"></i>Réinitialiser aux valeurs par défaut
                    </button>
                </form>
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
    
    // Form validation feedback
    const form = document.getElementById('securityConfigForm');
    const inputs = form.querySelectorAll('input[type="number"]');
    
    inputs.forEach(input => {
        input.addEventListener('input', function() {
            validateInput(this);
        });
    });
    
    function validateInput(input) {
        const value = parseInt(input.value);
        const min = parseInt(input.getAttribute('min'));
        const max = parseInt(input.getAttribute('max'));
        
        if (value < min || value > max) {
            input.classList.add('is-invalid');
        } else {
            input.classList.remove('is-invalid');
        }
    }
});
</script>
@endpush