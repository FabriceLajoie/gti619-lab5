@extends('layouts.app')

@section('title', 'Créer un utilisateur')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-2">
                            <li class="breadcrumb-item"><a href="{{ route('admin.users') }}">Utilisateurs</a></li>
                            <li class="breadcrumb-item active">Créer un utilisateur</li>
                        </ol>
                    </nav>
                    <h1 class="h3 mb-0">Créer un utilisateur</h1>
                </div>
                <div>
                    <a href="{{ route('admin.users') }}" class="btn btn-secondary">
                        Retour aux utilisateurs
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
                            <form method="POST" action="{{ route('admin.users.store') }}">
                                @csrf

                                <div class="mb-3">
                                    <label for="name" class="form-label">Nom <span class="text-danger">*</span></label>
                                    <input type="text" 
                                           class="form-control @error('name') is-invalid @enderror" 
                                           id="name" 
                                           name="name" 
                                           value="{{ old('name') }}" 
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
                                           value="{{ old('email') }}" 
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
                                            <option value="{{ $role->id }}" {{ old('role_id') == $role->id ? 'selected' : '' }}>
                                                {{ $role->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('role_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label for="password" class="form-label">Mot de passe <span class="text-danger">*</span></label>
                                    <input type="password" 
                                           class="form-control @error('password') is-invalid @enderror" 
                                           id="password" 
                                           name="password" 
                                           required>
                                    @error('password')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <div class="form-text">
                                        Le mot de passe doit respecter les exigences de sécurité configurées.
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="password_confirmation" class="form-label">Confirmer le mot de passe <span class="text-danger">*</span></label>
                                    <input type="password" 
                                           class="form-control @error('password_confirmation') is-invalid @enderror" 
                                           id="password_confirmation" 
                                           name="password_confirmation" 
                                           required>
                                    @error('password_confirmation')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" 
                                               type="checkbox" 
                                               id="must_change_password" 
                                               name="must_change_password" 
                                               value="1"
                                               {{ old('must_change_password') ? 'checked' : '' }}>
                                        <label class="form-check-label" for="must_change_password">
                                            Forcer le changement de mot de passe à la première connexion
                                        </label>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-end gap-2">
                                    <a href="{{ route('admin.users') }}" class="btn btn-secondary">
                                        Annuler
                                    </a>
                                    <button type="submit" class="btn btn-primary">
                                        Créer l'utilisateur
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
                                Exigences de mot de passe
                            </h5>
                        </div>
                        <div class="card-body">
                            <p class="text-muted small mb-2">Le mot de passe doit respecter les critères suivants :</p>
                            <ul class="small">
                                @php
                                    $passwordService = app(\App\Services\PasswordPolicyService::class);
                                    $requirements = $passwordService->getPasswordRequirementsText();
                                @endphp
                                @foreach($requirements as $requirement)
                                    <li>{{ $requirement }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>


                </div>
            </div>
        </div>
    </div>
</div>
@endsection
