<p>Hello {{ $subscription->user->name ?? 'there' }},</p>
<p>Your trial for plan <strong>{{ $subscription->plan_type }}</strong> has ended.</p>
<p>Upgrade now to regain access: <a href="{{ url('/pricing') }}">View Plans</a></p>
<p>We hope to see you back soon!</p>
<p>Thanks,<br/>Support Team</p>
