<p>Hello {{ $subscription->user->name ?? 'there' }},</p>
<p>Your trial for plan <strong>{{ $subscription->plan_type }}</strong> ends on <strong>{{ $subscription->ends_at->toDayDateTimeString() }}</strong>.</p>
<p>You have {{ $subscription->daysRemaining() }} day(s) left. Upgrade now to keep uninterrupted access.</p>
<p><a href="{{ url('/pricing') }}">Choose a plan</a></p>
<p>Thanks,<br/>Support Team</p>
