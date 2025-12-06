@extends('layouts.app')

@section('title', 'Activité utilisateur - ' . $user->name)

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
                            <li class="breadcrumb-item active">Activité</li>
                        </ol>
                    </nav>
                    <h1 class="h3 mb-0">Activité utilisateur</h1>
                </div>
                <div>
                    <a href="{{ route('admin.users.details', $user) }}" class="btn btn-secondary">
                        Retour aux détails
                    </a>
                </div>
            </div>

            <!-- User Info Card -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h5 class="mb-1">{{ $user->name }}</h5>
                            <p class="text-muted mb-0">{{ $user->email }}</p>
                        </div>
                        <div class="col-md-4 text-md-end">
                            <span class="badge bg-info fs-6">{{ $user->role->name ?? 'Aucun rôle' }}</span>
                            @if($user->isLocked())
                                <span class="badge bg-danger fs-6 ms-2">Verrouillé</span>
                            @else
                                <span class="badge bg-success fs-6 ms-2">Actif</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" action="{{ route('admin.users.activity', $user) }}" class="row g-3">
                        <div class="col-md-4">
                            <label for="event_type" class="form-label">Type d'événement</label>
                            <select class="form-select" id="event_type" name="event_type">
                                <option value="">Tous les événements</option>
                                @foreach($eventTypes as $type)
                                    <option value="{{ $type }}" {{ request('event_type') === $type ? 'selected' : '' }}>
                                        {{ ucfirst(str_replace('_', ' ', $type)) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="start_date" class="form-label">Date de début</label>
                            <input type="date" 
                                   class="form-control" 
                                   id="start_date" 
                                   name="start_date" 
                                   value="{{ request('start_date') }}">
                        </div>
                        <div class="col-md-3">
                            <label for="end_date" class="form-label">Date de fin</label>
                            <input type="date" 
                                   class="form-control" 
                                   id="end_date" 
                                   name="end_date" 
                                   value="{{ request('end_date') }}">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                Filtrer
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Activity Table -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        Historique d'activité ({{ $activities->total() }})
                    </h5>
                </div>
                <div class="card-body p-0">
                    @if($activities->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Date et heure</th>
                                        <th>Événement</th>
                                        <th>Détails</th>
                                        <th>Adresse IP</th>
                                        <th>User Agent</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($activities as $activity)
                                        <tr>
                                            <td>
                                                <div>{{ $activity->created_at->format('j M Y') }}</div>
                                                <div class="text-muted small">{{ $activity->created_at->format('H:i:s') }}</div>
                                            </td>
                                            <td>
                                                <span class="badge bg-{{ $activity->severity_color }} text-dark">
                                                    {{ $activity->formatted_event_type }}
                                                </span>
                                            </td>
                                            <td>
                                                @if($activity->details && is_array($activity->details))
                                                    @if(isset($activity->details['message']))
                                                        {{ Str::limit($activity->details['message'], 50) }}
                                                    @elseif(isset($activity->details['reason']))
                                                        {{ Str::limit($activity->details['reason'], 50) }}
                                                    @else
                                                        <span class="text-muted">-</span>
                                                    @endif
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td>
                                                <code class="small">{{ $activity->ip_address ?? 'N/A' }}</code>
                                            </td>
                                            <td>
                                                <span class="text-muted small" title="{{ $activity->user_agent }}">
                                                    {{ Str::limit($activity->user_agent, 30) }}
                                                </span>
                                            </td>
                                            <td>
                                                <a href="{{ route('admin.audit-log-details', $activity) }}" 
                                                   class="btn btn-sm btn-secondary">
                                                    Détails
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        @if($activities->hasPages())
                            <div class="card-footer">
                                {{ $activities->links() }}
                            </div>
                        @endif
                    @else
                        <div class="text-center py-5">
                            <h5 class="mt-3 text-muted">Aucune activité trouvée</h5>
                            <p class="text-muted">Aucun journal d'audit ne correspond aux critères de recherche.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
