@extends('layouts.app-frontend')

@section('title', 'Create Account — Mental Fitness Store')

@section('content')
<div class="min-vh-100 d-flex align-items-stretch" style="background: linear-gradient(135deg,#0f172a 0%,#1e3a5f 100%);">
    <!-- Left panel - branding -->
    <div class="d-none d-lg-flex col-lg-6 flex-column align-items-center justify-content-center p-5 text-white" style="background:linear-gradient(160deg,#312e81 0%,#4c1d95 40%,#1e3a5f 100%);">
        <div class="text-center">
            <div class="mb-4" style="width:88px;height:88px;background:rgba(255,255,255,0.15);border-radius:24px;display:flex;align-items:center;justify-content:center;margin:0 auto;backdrop-filter:blur(10px);">
                <i class="fas fa-brain fa-3x"></i>
            </div>
            <h1 class="display-5 fw-bold mb-3">Start Your Journey</h1>
            <p class="lead opacity-75 mb-5">Join thousands who've transformed their mental wellness with our audio tools.</p>

            <div class="text-start">
                <div class="d-flex align-items-start mb-4">
                    <div class="me-3 flex-shrink-0" style="width:40px;height:40px;background:rgba(52,211,153,0.2);border-radius:50%;display:flex;align-items:center;justify-content:center;">
                        <i class="fas fa-check text-success"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold mb-0">Free account, no credit card needed</h6>
                        <small class="opacity-75">Start exploring with our free plan immediately</small>
                    </div>
                </div>
                <div class="d-flex align-items-start mb-4">
                    <div class="me-3 flex-shrink-0" style="width:40px;height:40px;background:rgba(96,165,250,0.2);border-radius:50%;display:flex;align-items:center;justify-content:center;">
                        <i class="fas fa-check text-info"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold mb-0">Access premium mental wellness audio</h6>
                        <small class="opacity-75">Affirmations, sleep music, binaural beats & more</small>
                    </div>
                </div>
                <div class="d-flex align-items-start mb-4">
                    <div class="me-3 flex-shrink-0" style="width:40px;height:40px;background:rgba(251,191,36,0.2);border-radius:50%;display:flex;align-items:center;justify-content:center;">
                        <i class="fas fa-check text-warning"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold mb-0">Mobile app for Android & iOS</h6>
                        <small class="opacity-75">Listen anywhere, even offline</small>
                    </div>
                </div>
                <div class="d-flex align-items-start">
                    <div class="me-3 flex-shrink-0" style="width:40px;height:40px;background:rgba(167,139,250,0.2);border-radius:50%;display:flex;align-items:center;justify-content:center;">
                        <i class="fas fa-check" style="color:#a78bfa"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold mb-0">Personalised mental wellness plan</h6>
                        <small class="opacity-75">Curated tracks based on your goals</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Right panel - register form -->
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

            <h2 class="fw-bold text-white mb-1">Create your account</h2>
            <p style="color:#94a3b8;" class="mb-4">Free forever — upgrade anytime</p>

            @if ($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0 ps-3">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('register') }}">
                @csrf

                <div class="mb-3">
                    <label for="name" class="form-label text-light">Full Name</label>
                    <div class="input-group">
                        <span class="input-group-text border-secondary" style="background:#2d3748;color:#94a3b8;"><i class="fas fa-user"></i></span>
                        <input id="name" type="text"
                            class="form-control bg-dark border-secondary text-white @error('name') is-invalid @enderror"
                            name="name" value="{{ old('name') }}" required autocomplete="name" autofocus
                            placeholder="Your full name">
                        @error('name')
                            <span class="invalid-feedback"><strong>{{ $message }}</strong></span>
                        @enderror
                    </div>
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label text-light">Email address</label>
                    <div class="input-group">
                        <span class="input-group-text border-secondary" style="background:#2d3748;color:#94a3b8;"><i class="fas fa-envelope"></i></span>
                        <input id="email" type="email"
                            class="form-control bg-dark border-secondary text-white @error('email') is-invalid @enderror"
                            name="email" value="{{ old('email') }}" required autocomplete="email"
                            placeholder="your@email.com">
                        @error('email')
                            <span class="invalid-feedback"><strong>{{ $message }}</strong></span>
                        @enderror
                    </div>
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label text-light">Password</label>
                    <div class="input-group">
                        <span class="input-group-text border-secondary" style="background:#2d3748;color:#94a3b8;"><i class="fas fa-lock"></i></span>
                        <input id="password" type="password"
                            class="form-control bg-dark border-secondary text-white @error('password') is-invalid @enderror"
                            name="password" required autocomplete="new-password" placeholder="Min. 8 characters">
                        @error('password')
                            <span class="invalid-feedback"><strong>{{ $message }}</strong></span>
                        @enderror
                    </div>
                </div>

                <div class="mb-4">
                    <label for="password-confirm" class="form-label text-light">Confirm Password</label>
                    <div class="input-group">
                        <span class="input-group-text border-secondary" style="background:#2d3748;color:#94a3b8;"><i class="fas fa-lock"></i></span>
                        <input id="password-confirm" type="password"
                            class="form-control bg-dark border-secondary text-white"
                            name="password_confirmation" required autocomplete="new-password" placeholder="Repeat password">
                    </div>
                </div>

                {{-- Honeypot: hidden from real users, bots fill it in --}}
                <div class="d-none" aria-hidden="true">
                    <input type="text" name="website" id="website" tabindex="-1" autocomplete="off" value="">
                </div>

                {{-- Math quiz spam check --}}
                @php
                    $__a = rand(2, 9); $__b = rand(1, 6);
                    session(['register_math_ans' => $__a + $__b]);
                @endphp
                <div class="mb-4">
                    <label class="form-label text-light">
                        Quick Check: What is {{ $__a }} + {{ $__b }}?
                        <span class="text-danger">*</span>
                    </label>
                    <div class="input-group">
                        <span class="input-group-text border-secondary" style="background:#2d3748;color:#94a3b8;"><i class="fas fa-calculator"></i></span>
                        <input type="number" name="math_answer"
                            class="form-control bg-dark border-secondary text-white @error('math_answer') is-invalid @enderror"
                            required placeholder="Enter the answer" min="1" max="20">
                        @error('math_answer')
                            <span class="invalid-feedback"><strong>{{ $message }}</strong></span>
                        @enderror
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100 btn-lg mb-3 fw-semibold">
                    <i class="fas fa-user-plus me-2"></i>Create Free Account
                </button>

                <p class="text-center small mb-0" style="color:#94a3b8;">
                    By creating an account you agree to our
                    <a href="{{ route('legal.terms') }}" class="text-info text-decoration-none">Terms</a> &amp;
                    <a href="{{ route('legal.privacy') }}" class="text-info text-decoration-none">Privacy Policy</a>.
                </p>
                <p class="text-center mt-3 mb-0" style="color:#94a3b8;">
                    Already have an account?
                    <a href="{{ route('login') }}" class="text-info fw-semibold text-decoration-none">Sign in</a>
                </p>
            </form>
        </div>
    </div>
</div>
@endsection
