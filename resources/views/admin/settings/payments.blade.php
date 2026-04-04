@extends('adminlte::page')

@section('title', 'Payment Settings')

@section('content_header')
    <h1><i class="fas fa-credit-card me-2"></i>Payment Settings</h1>
@stop

@section('content')

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">
        <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="alert alert-info">
    <i class="fas fa-info-circle me-2"></i>
    <strong>These values are stored in your <code>.env</code> file.</strong>
    Config cache is cleared automatically after saving. Webhook URLs to use:
    <ul class="mb-0 mt-1">
        <li><strong>Razorpay webhook:</strong> <code>{{ url('/api/razorpay/webhook') }}</code></li>
        <li><strong>PayPal webhook:</strong> <code>{{ url('/webhooks/paypal') }}</code></li>
    </ul>
</div>

<form method="POST" action="{{ route('admin.settings.payments.save') }}">
    @csrf

    {{-- Razorpay --}}
    <div class="card card-outline card-danger mb-4">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-rupee-sign me-2 text-danger"></i>Razorpay (India / INR)
            </h3>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold">Key ID <small class="text-muted">(Public)</small></label>
                    <input type="text" name="razorpay_key_id" class="form-control font-monospace"
                        value="{{ config('razorpay.key_id') }}"
                        placeholder="rzp_live_xxxxxxxxxxxxxxxx">
                    <div class="form-text">Get this from <a href="https://dashboard.razorpay.com/app/keys" target="_blank">Razorpay Dashboard → Settings → API Keys</a></div>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Key Secret <small class="text-muted">(Private)</small></label>
                    <div class="input-group">
                        <input type="password" id="rzp_secret" name="razorpay_key_secret" class="form-control font-monospace"
                            value="{{ config('razorpay.key_secret') }}"
                            placeholder="••••••••••••••••••••">
                        <button type="button" class="btn btn-outline-secondary" onclick="togglePass('rzp_secret', this)">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Webhook Secret</label>
                    <div class="input-group">
                        <input type="password" id="rzp_webhook" name="razorpay_webhook_secret" class="form-control font-monospace"
                            value="{{ config('razorpay.webhook_secret') }}"
                            placeholder="webhook_secret_here">
                        <button type="button" class="btn btn-outline-secondary" onclick="togglePass('rzp_webhook', this)">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div class="form-text">Set in <a href="https://dashboard.razorpay.com/app/webhooks" target="_blank">Razorpay → Webhooks</a>. Register URL: <code>{{ url('/api/razorpay/webhook') }}</code></div>
                </div>
            </div>
        </div>
    </div>

    {{-- PayPal --}}
    <div class="card card-outline card-primary mb-4">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fab fa-paypal me-2 text-primary"></i>PayPal (USD / International)
            </h3>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-bold">Mode</label>
                    <select name="paypal_mode" class="form-select">
                        <option value="sandbox" {{ config('paypal.mode') === 'sandbox' ? 'selected' : '' }}>Sandbox (Testing)</option>
                        <option value="live" {{ config('paypal.mode') === 'live' ? 'selected' : '' }}>Live (Production)</option>
                    </select>
                    <div class="form-text">Use <strong>Sandbox</strong> for testing, switch to <strong>Live</strong> when ready.</div>
                </div>
                <div class="col-md-8">
                    &nbsp;
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Client ID <small class="text-muted">(Public)</small></label>
                    <input type="text" name="paypal_client_id" class="form-control font-monospace"
                        value="{{ config('paypal.client_id') }}"
                        placeholder="AxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxC">
                    <div class="form-text">Get from <a href="https://developer.paypal.com/dashboard/applications" target="_blank">PayPal Developer → My Apps & Credentials</a></div>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Client Secret <small class="text-muted">(Private)</small></label>
                    <div class="input-group">
                        <input type="password" id="pp_secret" name="paypal_client_secret" class="form-control font-monospace"
                            value="{{ config('paypal.client_secret') }}"
                            placeholder="••••••••••••••••••••">
                        <button type="button" class="btn btn-outline-secondary" onclick="togglePass('pp_secret', this)">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Webhook ID</label>
                    <input type="text" name="paypal_webhook_id" class="form-control font-monospace"
                        value="{{ config('paypal.webhook_id') }}"
                        placeholder="XXXXXXXXXXXXXXXXX">
                    <div class="form-text">
                        Register webhook at <a href="https://developer.paypal.com/dashboard/webhooks" target="_blank">PayPal → Webhooks</a>.
                        URL: <code>{{ url('/webhooks/paypal') }}</code>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-success btn-lg">
            <i class="fas fa-save me-2"></i>Save Settings
        </button>
        <a href="{{ route('admin.dashboard') }}" class="btn btn-secondary btn-lg">Cancel</a>
    </div>
</form>

@stop

@section('js')
<script>
function togglePass(id, btn) {
    const input = document.getElementById(id);
    const icon = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
}
</script>
@stop
