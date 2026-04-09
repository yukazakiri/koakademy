<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Reminder: {{ $event->title }}</title>
    <style>
        .container {
            max-width: 600px;
            margin: 0 auto;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.6;
            color: #333;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
            border-radius: 8px 8px 0 0;
        }
        .content {
            background: white;
            padding: 30px;
            border: 1px solid #e1e8ed;
            border-radius: 0 0 8px 8px;
        }
        .event-details {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 20px;
            margin: 20px 0;
        }
        .detail-row {
            display: flex;
            margin-bottom: 10px;
            align-items: center;
        }
        .detail-label {
            font-weight: 600;
            color: #4a5568;
            min-width: 120px;
        }
        .detail-value {
            color: #2d3748;
        }
        .time-highlight {
            background: #fef5e7;
            border: 1px solid #f6ad55;
            border-radius: 6px;
            padding: 15px;
            margin: 20px 0;
            text-align: center;
        }
        .button {
            display: inline-block;
            background: #4299e1;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            text-align: center;
        }
        .button:hover {
            background: #3182ce;
        }
        .footer {
            text-align: center;
            color: #718096;
            font-size: 14px;
            margin-top: 30px;
            padding: 20px 0;
            border-top: 1px solid #e2e8f0;
        }
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .badge-event {
            background: #bee3f8;
            color: #2a69ac;
        }
        .badge-academic {
            background: #c6f6d5;
            color: #22543d;
        }
        .badge-administrative {
            background: #e9d8fd;
            color: #553c9a;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📅 Event Reminder</h1>
            <p>Don't forget about your upcoming event!</p>
        </div>

        <div class="content">
            <div class="time-highlight">
                <h2>⏰ {{ $event->title }} is {{ $timeUntilEvent }}!</h2>
                <p><strong>{{ $timeInfo }}</strong></p>
            </div>

            <div class="event-details">
                <div class="detail-row">
                    <span class="detail-label">Event:</span>
                    <span class="detail-value"><strong>{{ $event->title }}</strong></span>
                </div>

                @if($event->type && $event->type !== 'event')
                    <div class="detail-row">
                        <span class="detail-label">Type:</span>
                        <span class="detail-value">
                            <span class="badge badge-{{ $event->type === 'academic_calendar' ? 'academic' : ($event->type === 'administrative' ? 'administrative' : 'event') }}">
                                {{ $event->type === 'academic_calendar' ? 'Academic Calendar' : ($event->type === 'resource_booking' ? 'Resource Booking' : ucfirst($event->type)) }}
                            </span>
                        </span>
                    </div>
                @endif

                @if($event->category)
                    <div class="detail-row">
                        <span class="detail-label">Category:</span>
                        <span class="detail-value">{{ ucfirst($event->category) }}</span>
                    </div>
                @endif

                @if($event->location)
                    <div class="detail-row">
                        <span class="detail-label">Location:</span>
                        <span class="detail-value">📍 {{ $event->location }}</span>
                    </div>
                @endif

                <div class="detail-row">
                    <span class="detail-label">Start:</span>
                    <span class="detail-value">
                        {{ $event->start_datetime->format('F j, Y') }}
                        @unless($event->is_all_day)
                            at {{ $event->start_datetime->format('g:i A') }}
                        @endunless
                    </span>
                </div>

                @unless($event->is_all_day)
                    <div class="detail-row">
                        <span class="detail-label">End:</span>
                        <span class="detail-value">
                            {{ $event->end_datetime->format('F j, Y') }}
                            at {{ $event->end_datetime->format('g:i A') }}
                        </span>
                    </div>
                @endunless

                @if($event->creator)
                    <div class="detail-row">
                        <span class="detail-label">Organizer:</span>
                        <span class="detail-value">{{ $event->creator->name }}</span>
                    </div>
                @endif

                @if($event->description)
                    <div class="detail-row">
                        <span class="detail-label">Description:</span>
                        <div class="detail-value">
                            {!! nl2br(e($event->description)) !!}
                        </div>
                    </div>
                @endif
            </div>

            @if($reminder->message)
                <div class="event-details">
                    <p><strong>Custom Message:</strong></p>
                    <p>{{ $reminder->message }}</p>
                </div>
            @endif

            <div style="text-align: center; margin: 30px 0;">
                <a href="{{ $eventUrl }}" class="button">View Event Details</a>
            </div>

            <p>Hi {{ $user->name }},</p>
            <p>This is a friendly reminder about the upcoming event <strong>{{ $event->title }}</strong> {{ $timeInfo }}.</p>
            
            @if($event->requires_rsvp)
                <p><strong>📋 RSVP Required:</strong> This event requires an RSVP. Please confirm your attendance if you haven't already.</p>
            @endif

            @if($event->location)
                <p><strong>📍 Location:</strong> {{ $event->location }}</p>
            @endif

            <p>We look forward to seeing you there!</p>
        </div>

        <div class="footer">
            <p>This reminder was sent {{ $reminder->minutes_before }} minutes before the event.</p>
            <p>You are receiving this because you are subscribed to event reminders.</p>
        </div>
    </div>
</body>
</html>
