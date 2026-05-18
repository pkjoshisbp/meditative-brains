@extends('adminlte::page')

@section('title', 'SMS Gateway')

@section('content_header')
    <h1><i class="fas fa-mobile-alt me-2"></i>SMS Gateway</h1>
@stop

@section('content')
<div class="alert alert-info">
    <i class="fas fa-info-circle me-2"></i>
    The Flutter OTP app should authenticate to the Ratchet WebSocket server with the static SMS gateway secret shown below.
    If the app uses an old copied value, the server will reject it with an invalid token error.
</div>

<div class="card card-outline card-primary mb-4">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-plug me-2"></i>Connection Details</h3>
    </div>
    <div class="card-body">
        <div class="mb-3">
            <label class="form-label fw-bold">WebSocket URL</label>
            <div class="input-group">
                <input type="text" id="sms_gateway_ws_url" class="form-control font-monospace" value="{{ $smsGatewayWsUrl }}" readonly>
                <button type="button" class="btn btn-outline-secondary" onclick="copyField('sms_gateway_ws_url', 'wsCopied')">
                    <i class="fas fa-copy"></i> Copy
                </button>
            </div>
            <div id="wsCopied" class="form-text text-success" style="display:none;">Copied to clipboard.</div>
        </div>

        <div class="mb-0">
            <label class="form-label fw-bold">SMS Gateway Secret</label>
            <div class="input-group">
                <input type="password" id="sms_gateway_secret" class="form-control font-monospace" value="{{ $smsGatewaySecret }}" readonly>
                <button type="button" class="btn btn-outline-secondary" onclick="toggleSecret()">
                    <i id="smsGatewaySecretIcon" class="fas fa-eye"></i>
                </button>
                <button type="button" class="btn btn-outline-primary" onclick="copyField('sms_gateway_secret', 'secretCopied')">
                    <i class="fas fa-copy"></i> Copy
                </button>
            </div>
            <div id="secretCopied" class="form-text text-success" style="display:none;">Copied to clipboard.</div>
        </div>
    </div>
</div>

<div class="card card-outline card-secondary mb-4">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-list-ol me-2"></i>Flutter App Sequence</h3>
    </div>
    <div class="card-body">
        <ol class="mb-3">
            <li>Connect the Flutter app to the WebSocket URL above.</li>
            <li>Send an auth message using the exact copied SMS Gateway secret.</li>
            <li>If the app supports it, send the gateway registration message after auth. The server now also auto-registers gateway-secret logins for compatibility.</li>
        </ol>

        <p class="fw-bold mb-2">Auth message</p>
        <pre class="bg-light border rounded p-3"><code>{"action":"auth","token":"{{ $smsGatewaySecret }}"}</code></pre>

        <p class="text-muted mb-2">Legacy clients may also send the secret as <code>secret</code>, <code>smsGatewaySecret</code>, or <code>apiKey</code>.</p>

        <p class="fw-bold mb-2">Register message</p>
        <pre class="bg-light border rounded p-3"><code>{"action":"sms.gateway.register"}</code></pre>
    </div>
</div>

<div class="card card-outline card-warning">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-exclamation-triangle me-2"></i>What Your Log Means</h3>
    </div>
    <div class="card-body">
        <p class="mb-2">The log line <code>Invalid token</code> means the Flutter app is connecting successfully but the token sent in the auth payload does not match the current <code>SMS_GATEWAY_SECRET</code> and is not a valid Sanctum personal access token.</p>
        <p class="mb-0">The usual fix is to copy the current secret from this page into the app, confirm the app is using <code>{{ $smsGatewayWsUrl }}</code>, and reconnect.</p>
    </div>
</div>
@stop

@section('js')
<script>
function toggleSecret() {
    const input = document.getElementById('sms_gateway_secret');
    const icon = document.getElementById('smsGatewaySecretIcon');
    const showing = input.type === 'text';
    input.type = showing ? 'password' : 'text';
    icon.classList.toggle('fa-eye', showing);
    icon.classList.toggle('fa-eye-slash', !showing);
}

function copyField(id, noticeId) {
    const input = document.getElementById(id);
    navigator.clipboard.writeText(input.value).then(function () {
        const notice = document.getElementById(noticeId);
        notice.style.display = 'block';
        setTimeout(function () {
            notice.style.display = 'none';
        }, 1800);
    });
}
</script>
@stop