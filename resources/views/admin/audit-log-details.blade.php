@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Audit Log Details</h1>
                <p class="text-gray-600 mt-2">Detailed information for audit log entry #{{ $auditLog->id }}</p>
            </div>
            <a href="{{ route('admin.audit-logs') }}" 
               class="bg-gray-600 text-white px-4 py-2 rounded-md hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500">
                Back to Audit Logs
            </a>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold text-gray-900">{{ $auditLog->formatted_event_type }}</h2>
                <span class="inline-flex px-3 py-1 text-sm font-semibold rounded-full {{ $auditLog->severity_css_class }}">
                    {{ ucfirst($auditLog->severity) }} Severity
                </span>
            </div>
        </div>

        <div class="px-6 py-6">
            <dl class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <dt class="text-sm font-medium text-gray-500 mb-1">Event ID</dt>
                    <dd class="text-sm text-gray-900">{{ $auditLog->id }}</dd>
                </div>

                <div>
                    <dt class="text-sm font-medium text-gray-500 mb-1">Event Type</dt>
                    <dd class="text-sm text-gray-900">{{ $auditLog->event_type }}</dd>
                </div>

                <div>
                    <dt class="text-sm font-medium text-gray-500 mb-1">User</dt>
                    <dd class="text-sm text-gray-900">
                        @if($auditLog->user)
                            <div>{{ $auditLog->user->name }}</div>
                            <div class="text-gray-500">{{ $auditLog->user->email }}</div>
                            <div class="text-gray-500">User ID: {{ $auditLog->user->id }}</div>
                        @else
                            <span class="text-gray-400">No user associated</span>
                        @endif
                    </dd>
                </div>

                <div>
                    <dt class="text-sm font-medium text-gray-500 mb-1">IP Address</dt>
                    <dd class="text-sm text-gray-900">{{ $auditLog->ip_address ?? 'N/A' }}</dd>
                </div>

                <div class="md:col-span-2">
                    <dt class="text-sm font-medium text-gray-500 mb-1">User Agent</dt>
                    <dd class="text-sm text-gray-900 break-all">{{ $auditLog->user_agent ?? 'N/A' }}</dd>
                </div>

                <div>
                    <dt class="text-sm font-medium text-gray-500 mb-1">Created At</dt>
                    <dd class="text-sm text-gray-900">
                        {{ $auditLog->created_at->format('Y-m-d H:i:s') }}
                        <span class="text-gray-500">({{ $auditLog->created_at->diffForHumans() }})</span>
                    </dd>
                </div>

                <div>
                    <dt class="text-sm font-medium text-gray-500 mb-1">Updated At</dt>
                    <dd class="text-sm text-gray-900">
                        {{ $auditLog->updated_at->format('Y-m-d H:i:s') }}
                        <span class="text-gray-500">({{ $auditLog->updated_at->diffForHumans() }})</span>
                    </dd>
                </div>
            </dl>
        </div>

        @if($auditLog->details && !empty($auditLog->details))
            <div class="px-6 py-4 border-t border-gray-200">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Event Details</h3>
                <div class="bg-gray-50 rounded-md p-4">
                    <pre class="text-sm text-gray-700 whitespace-pre-wrap">{{ json_encode($auditLog->details, JSON_PRETTY_PRINT) }}</pre>
                </div>
            </div>
        @endif

        <!-- Related Events (if user exists) -->
        @if($auditLog->user)
            <div class="px-6 py-4 border-t border-gray-200">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Recent Events for This User</h3>
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
                                       class="text-blue-600 hover:text-blue-900 text-sm">View</a>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-gray-500 text-sm">No other recent events found for this user.</p>
                @endif
            </div>
        @endif
    </div>
</div>
@endsection