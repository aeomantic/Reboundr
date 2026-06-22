<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Job Tracker Dashboard</title>
    <style>
        body { font-family: sans-serif; max-width: 800px; margin: 40px auto; padding: 0 20px; color: #222; }
        h1 { margin-bottom: 4px; }
        .event { border: 1px solid #ddd; border-radius: 8px; padding: 16px; margin-bottom: 16px; }
        .event h2 { margin: 0 0 4px; font-size: 18px; }
        .meta { color: #666; font-size: 14px; margin-bottom: 8px; }
        .badge { display: inline-block; background: #eef; color: #335; padding: 2px 8px; border-radius: 4px; font-size: 12px; margin-right: 6px; }
        .empty { color: #888; }
    </style>
</head>
<body>
    <h1>Your Job Tracker</h1>
    <p class="meta">Logged in as {{ auth()->user()->name }} ({{ auth()->user()->email }})</p>

    @if ($jobEvents->isEmpty())
        <p class="empty">No job-related emails found yet. Run <code>php artisan gmail:fetch-job-emails</code> to check your inbox.</p>
    @else
        @foreach ($jobEvents as $event)
            <div class="event">
                <h2>{{ $event->role ?? 'Unknown role' }} @if($event->company) at {{ $event->company }} @endif</h2>
                <div class="meta">
                    <span class="badge">{{ $event->email_type ?? 'other' }}</span>
                    <span class="badge">{{ $event->location_type ?? 'unspecified' }}</span>
                    @if ($event->event_datetime)
                        <span class="badge">{{ $event->event_datetime->format('D, M j Y \a\t g:i A') }}</span>
                    @endif
                </div>
                @if ($event->location_detail)
                    <p>📍 {{ $event->location_detail }}</p>
                @endif
                <p>{{ $event->summary }}</p>
                <p class="meta">From subject: "{{ $event->subject }}"</p>
            </div>
        @endforeach
    @endif
</body>
</html>