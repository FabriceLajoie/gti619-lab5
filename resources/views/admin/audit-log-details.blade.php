@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Détails du journal d'audit</h1>
                <p class="text-gray-600 mt-2">Informations détaillées pour l'entrée de journal d'audit #{{ $auditLog->id }}</p>
            </div>
            <a href="{{ route('admin.audit-logs') }}" 
               class="bg-gray-600 text-white px-4 py-2 rounded-md hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500">
                Retour aux journaux d'audit
            </a>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold text-gray-900">{{ $auditLog->formatted_event_type }}</h2>
                <span class="inline-flex px-3 py-1 text-sm font-semibold rounded-full {{ $auditLog->severity_css_class }}">
                    Gravité {{ ucfirst($auditLog->severity) }}
                </span>
            </div>
        </div>

        <div class="px-6 py-6">
            <dl class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <dt class="text-sm font-medium text-gray-500 mb-1">ID de l'événement</dt>
                    <dd class="text-sm text-gray-900">{{ $auditLog->id }}</dd>
                </div>

                <div>
                    <dt class="text-sm font-medium text-gray-500 mb-1">Type d'événement</dt>
                    <dd class="text-sm text-gray-900">{{ $auditLog->event_type }}</dd>
                </div>

                <div>
                    <dt class="text-sm font-medium text-gray-500 mb-1">Utilisateur</dt>
                    <dd class="text-sm text-gray-900">
                        @if($auditLog->user)
                            <div>{{ $auditLog->user->name }}</div>
                            <div class="text-gray-500">{{ $auditLog->user->email }}</div>
                            <div class="text-gray-500">ID utilisateur : {{ $auditLog->user->id }}</div>
                        @else
                            <span class="text-gray-400">Aucun utilisateur associé</span>
                        @endif
                    </dd>
                </div>

                <div>
                    <dt class="text-sm font-medium text-gray-500 mb-1">Adresse IP</dt>
                    <dd class="text-sm text-gray-900">{{ $auditLog->ip_address ?? 'N/A' }}</dd>
                </div>

                <div class="md:col-span-2">
                    <dt class="text-sm font-medium text-gray-500 mb-1">Agent utilisateur</dt>
                    <dd class="text-sm text-gray-900 break-all">{{ $auditLog->user_agent ?? 'N/A' }}</dd>
                </div>

                <div>
                    <dt class="text-sm font-medium text-gray-500 mb-1">Créé le</dt>
                    <dd class="text-sm text-gray-900">
                        {{ $auditLog->created_at->format('Y-m-d H:i:s') }}
                        <span class="text-gray-500">({{ $auditLog->created_at->diffForHumans() }})</span>
                    </dd>
                </div>

                <div>
                    <dt class="text-sm font-medium text-gray-500 mb-1">Mis à jour le</dt>
                    <dd class="text-sm text-gray-900">
                        {{ $auditLog->updated_at->format('Y-m-d H:i:s') }}
                        <span class="text-gray-500">({{ $auditLog->updated_at->diffForHumans() }})</span>
                    </dd>
                </div>
            </dl>
        </div>

        @if($auditLog->details && !empty($auditLog->details))
            <div class="px-6 py-4 border-t border-gray-200">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Détails de l'événement</h3>
                <div class="bg-gray-50 rounded-md p-4">
                    <pre class="text-sm text-gray-700 whitespace-pre-wrap">{{ json_encode($auditLog->details, JSON_PRETTY_PRINT) }}</pre>
                </div>
            </div>
        @endif

        <!-- Related Events (if user exists) -->
        @if($auditLog->user)
            <div class="px-6 py-4 border-t border-gray-200">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Événements récents pour cet utilisateur</h3>
                @php
                    $relatedEvents = \App\Models\AuditLog::where('user_id', $auditLog->user_id)
                        ->where('id', '!=', $auditLog->id)
                        ->orderBy('created_at', 'desc')
                        ->limit(5)
                        ->get();
                @endphp

                @if($relatedEvents->count() > 0)
                    <div class="space-y-2">
                        @foreach($relatedEvents as $event)
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-md">
                                <div>
                                    <div class="text-sm font-medium text-gray-900">{{ $event->formatted_event_type }}</div>
                                    <div class="text-xs text-gray-500">{{ $event->created_at->format('Y-m-d H:i:s') }}</div>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full {{ $event->severity_css_class }}">
                                        {{ ucfirst($event->severity) }}
                                    </span>
                                    <a href="{{ route('admin.audit-log-details', $event) }}" 
                                       class="text-blue-600 hover:text-blue-900 text-sm">Voir</a>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-gray-500 text-sm">Aucun autre événement récent trouvé pour cet utilisateur.</p>
                @endif
            </div>
        @endif
    </div>
</div>
@endsection