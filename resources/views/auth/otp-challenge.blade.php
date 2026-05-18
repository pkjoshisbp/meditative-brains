@extends('layouts.app-frontend')

@section('title', 'Verify Login Code — Mental Fitness Store')

@section('content')
<div class="min-vh-100 d-flex align-items-center justify-content-center px-3" style="background:linear-gradient(135deg,#0f172a 0%,#1e3a5f 100%);">
    <div class="card border-0 shadow-lg w-100" style="max-width:460px;background:rgba(15,23,42,0.92);backdrop-filter:blur(12px);">
        <div class="card-body p-4 p-lg-5">
            <div class="text-center mb-4">
                <div class="mx-auto mb-3" style="width:64px;height:64px;border-radius:18px;background:linear-gradient(135deg,#059669,#0891b2);display:flex;align-items:center;justify-content:center;">
                    <i class="fas fa-shield-alt text-white fa-lg"></i>
                </div>
                <h2 class="text-white fw-bold mb-2">Enter your login code</h2>
                <p class="mb-0" style="color:#94a3b8;">We sent a 6-digit code to {{ $deliverySummary }}.</p>
            </div>

            @if (session('status'))
                <div class="alert alert-info">{{ session('status') }}</div>
            @endif

            @if ($errors->any())
                <div class="alert alert-danger">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('login.otp.verify') }}">
                @csrf
                <div class="mb-4">
                    <label for="code" class="form-label text-light">One-time code</label>
                    <input id="code" name="code" type="text" inputmode="numeric" maxlength="6"
                        class="form-control form-control-lg bg-dark border-secondary text-white text-center @error('code') is-invalid @enderror"
                        value="{{ old('code') }}" placeholder="123456" autocomplete="one-time-code" autofocus>
                </div>

                <button type="submit" class="btn btn-primary w-100 btn-lg fw-semibold mb-3">
                    Verify and sign in
                </button>
            </form>

            <form method="POST" action="{{ route('login.otp.resend') }}" class="mb-3">
                @csrf
                <button type="submit" class="btn btn-outline-light w-100">Send a new code</button>
            </form>

            <p class="text-center mb-0">
                <a href="{{ route('login') }}" class="text-info text-decoration-none">Back to password login</a>
            </p>
        </div>
    </div>
</div>
@endsection