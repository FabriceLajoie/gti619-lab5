@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0">
                        <i class="fas fa-shield-alt me-2"></i>
                        Ré-authentification Requise
                    </h4>
                </div>

                <div class="card-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Pour votre sécurité, veuillez ressaisir votre mot de passe pour continuer cette opération sensible.
                    </div>

                    <form method="POST" action="{{ route('reauth.authenticate') }}">
                        @csrf

                        <div class="mb-3">
                            <label for="password" class="form-label">
                                <i class="fas fa-lock me-1"></i>
                                Mot de Passe Actuel
                            </label>
                            <input id="password" 
                                   type="password" 
                                   class="form-control @error('password') is-invalid @enderror" 
                                   name="password" 
                                   required 
                                   autocomplete="current-password"
                                   autofocus>

                            @error('password')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-check me-2"></i>
                                Vérifier le mot de passe
                            </button>
                            
                            <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-2"></i>
                                Annuler
                            </a>
                        </div>
                    </form>
                </div>

                @auth
                <div class="card-footer text-muted">
                    <small>
                        <i class="fas fa-user me-1"></i>
                        Connecté en tant que : <strong>{{ Auth::user()->name }}</strong>
                    </small>
                </div>
                @endauth
            </div>
        </div>
    </div>
</div>
@endsection