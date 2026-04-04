@extends('layouts.app-frontend')

@section('title', 'Login — Mental Fitness Store')

@section('content')
<div class="min-vh-100 d-flex align-items-stretch" style="background: linear-gradient(135deg,#0f172a 0%,#1e3a5f 100%);">
    <!-- Left panel - branding -->
    <div class="d-none d-lg-flex col-lg-6 flex-column align-items-center justify-content-center p-5 text-white" style="background:linear-gradient(160deg,#064e3b 0%,#065f46 40%,#0c4a6e 100%);">
        <div class="text-center">
            <div class="mb-4" style="width:88px;height:88px;background:rgba(255,255,255,0.15);border-radius:24px;display:flex;align-items:center;justify-content:center;margin:0 auto;backdrop-filter:blur(10px);">
                <i class="fas fa-brain fa-3x"></i>
            </div>
            <h1 class="display-5 fw-bold mb-3">Mental<span style="color:#34d399">Fitness</span></h1>
            <p class="lead opacity-75 mb-5">Train your mind. Transform your life.</p>

            <div class="row g-4 text-start">
                <div class="col-6">
                    <div class="p-3 rounded-3" style="background:rgba(255,255,255,0.08);backdrop-filter:blur(6px);">
                        <i class="fas fa-headphones fa-2x text-success mb-2"></i>
                        <h6 class="fw-bold">Premium Audio</h6>
                        <small class="opacity-75">Affirmations, sleep music & healing frequencies</small>
                    </div>
                </div>
                <div class="col-6">
                    <div class="p-3 rounded-3" style="background:rgba(255,255,255,0.08);backdrop-filter:blur(6px);">
                        <i class="fas fa-brain fa-2x text-info mb-2"></i>
                        <h6 class="fw-bold">Science-Based</h6>
                        <small class="opacity-75">Backed by neuroscience research</small>
                    </div>
                </div>
                <div class="col-6">
                    <div class="p-3 rounded-3" style="background:rgba(255,255,255,0.08);backdrop-filter:blur(6px);">
                        <i class="fas fa-mobile-alt fa-2x text-warning mb-2"></i>
                        <h6 class="fw-bold">Mobile App</h6>
                        <small class="opacity-75">Android & iOS with offline mode</small>
                    </div>
                </div>
                <div class="col-6">
                    <div class="p-3 rounded-3" style="background:rgba(255,255,255,0.08);backdrop-filter:blur(6px);">
                        <i class="fas fa-infinity fa-2x text-purple mb-2" style="color:#a78bfa"></i>
                        <h6 class="fw-bold">Unlimited Access</h6>
                        <small class="opacity-75">Full library with subscription</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Right panel - login form -->
    <div class="col-12 col-lg-6 d-flex align-items-center justify-content-center p-4 p-lg-5">
        <div class="w-100" style="max-width:420px;">
            <div class="text-center mb-5 d-lg-none">
                <a href="{{ route('home') }}" class="d-inline-flex align-items-center gap-2 text-decoration-none">
                    <div style="width:44px;height:44px;background:linear-gradient(135deg,#059669,#0891b2);border-radius:12px;display:flex;align-items:center;justify-content:center;">
                        <i class="fas fa-brain text-white"></i>
                    </div>
                    <span class="fw-bold fs-4 text-white">Mental<span style="color:#34d399">Fitness</span></span>
                </a>
            </div>

            <h2 class="fw-bold text-white mb-1">Welcome back</h2>
            <p style="color:#94a3b8;" class="mb-4">Sign in to continue your wellness journey</p>

            @if ($errors->any())
                <div class="alert alert-danger">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}">
                @csrf

                <div class="mb-3">
                    <label for="email" class="form-label text-light">Email address</label>
                    <div class="input-group">
                        <span class="input-group-text border-secondary" style="background:#2d3748;color:#94a3b8;">
                            <i class="fas fa-envelope"></i>
                        </span>
                        <input id="email" type="email"
                            class="form-control bg-dark border-secondary text-white @error('email') is-invalid @enderror"
                            name="email" value="{{ old('email') }}" required autocomplete="email" autofocus
                            placeholder="your@email.com">
                        @error('email')
                            <span class="invalid-feedback"><strong>{{ $message }}</strong></span>
                        @enderror
                    </div>
                </div>

                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label for="password" class="form-label text-light mb-0">Password</label>
                        @if (Route::has('password.request'))
                            <a href="{{ route('password.request') }}" class="small text-info text-decoration-none">Forgot password?</a>
                        @endif
                    </div>
                    <div class="input-group">
                        <span class="input-group-text border-secondary" style="background:#2d3748;color:#94a3b8;">
                            <i class="fas fa-lock"></i>
                        </span>
                        <input id="password" type="password"
                            class="form-control bg-dark border-secondary text-white @error('password') is-invalid @enderror"
                            name="password" required autocomplete="current-password" placeholder="••••••••">
                        @error('password')
                            <span class="invalid-feedback"><strong>{{ $message }}</strong></span>
                        @enderror
                    </div>
                </div>

                <div class="mb-4 d-flex align-items-center">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="remember" id="remember" {{ old('remember') ? 'checked' : '' }}>
                        <label class="form-check-label text-muted" for="remember">Keep me signed in</label>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100 btn-lg mb-3 fw-semibold">
                    <i class="fas fa-sign-in-alt me-2"></i>Sign In
                </button>

                <p class="text-center mb-0" style="color:#94a3b8;">
                    Don't have an account?
                    <a href="{{ route('register') }}" class="text-info fw-semibold text-decoration-none">Create one free</a>
                </p>
            </form>
        </div>
    </div>
</div>
@endsection
