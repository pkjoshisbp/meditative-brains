@extends('layouts.app-frontend')

@section('title', 'Delete Account — Mental Fitness Store')

@section('content')
<div class="py-5 bg-light border-bottom mb-4">
    <div class="container">
        <h1 class="display-5 fw-bold mb-2">Delete Account</h1>
        <p class="lead text-muted mb-0">Request deletion of your Mental Fitness Store account and associated personal data.</p>
    </div>
</div>

<div class="container pb-5">
    <div class="row g-4">
        <div class="col-lg-5">
            <h2 class="h4 mb-3">How to Request Account Deletion</h2>
            <p>Mental Fitness Store is operated by <strong>MYWEB SOLUTIONS</strong>. You can request deletion of your account using the form on this page or by emailing us directly.</p>

            <ol class="ps-3">
                <li class="mb-2">Enter the email address used for your Mental Fitness Store account.</li>
                <li class="mb-2">Submit the deletion request form or email <a href="mailto:privacy@mentalfitness.store">privacy@mentalfitness.store</a>.</li>
                <li class="mb-2">We may contact you to confirm account ownership before deleting data.</li>
                <li class="mb-2">After verification, we will process the request within 30 days unless a longer legal retention period applies.</li>
            </ol>

            <h2 class="h4 mt-4 mb-3">Data Deleted</h2>
            <ul class="ps-3">
                <li>Account profile information, including name, email, and mobile number where stored.</li>
                <li>Login credentials and active session/device records.</li>
                <li>App access records that are no longer required to provide service or comply with law.</li>
                <li>Support messages that are no longer needed after the request is completed.</li>
            </ul>

            <h2 class="h4 mt-4 mb-3">Data We May Keep</h2>
            <p>Some records may be retained where required for legal, tax, fraud-prevention, security, or dispute-resolution purposes.</p>
            <ul class="ps-3">
                <li>Payment, invoice, order, and subscription records may be retained for up to 7 years.</li>
                <li>Security logs may be retained for a limited period to protect the service and investigate abuse.</li>
                <li>Records needed for unresolved support, refund, legal, or compliance matters may be retained until the matter is closed.</li>
            </ul>

            <h2 class="h4 mt-4 mb-3">Contact</h2>
            <p class="mb-1">Privacy email: <a href="mailto:privacy@mentalfitness.store">privacy@mentalfitness.store</a></p>
            <p>Support email: <a href="mailto:support@mentalfitness.store">support@mentalfitness.store</a></p>
        </div>

        <div class="col-lg-7">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <h2 class="h4 mb-4">Account Deletion Request</h2>

                    @if(session('delete_account_success'))
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i>
                            Your account deletion request has been sent. We will contact you if verification is required.
                        </div>
                    @endif

                    <form action="{{ route('legal.delete-account.send') }}" method="POST">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Your Name</label>
                            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                                value="{{ old('name') }}" required>
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Account Email Address</label>
                            <input type="email" name="email" class="form-control @error('email') is-invalid @enderror"
                                value="{{ old('email') }}" required>
                            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-semibold">Additional Details</label>
                            <textarea name="message" rows="5" class="form-control @error('message') is-invalid @enderror"
                                placeholder="Optional: tell us if you have active purchases, subscriptions, or a specific request.">{{ old('message') }}</textarea>
                            @error('message')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="d-none" aria-hidden="true">
                            <input type="text" name="website" id="website" tabindex="-1" autocomplete="off" value="">
                        </div>

                        @php
                            $__a = rand(2, 9); $__b = rand(1, 6);
                            session(['delete_account_math_ans' => $__a + $__b]);
                        @endphp
                        <div class="mb-4">
                            <label class="form-label fw-semibold">
                                Spam Check: What is {{ $__a }} + {{ $__b }}?
                                <span class="text-danger">*</span>
                            </label>
                            <input type="number" name="math_answer"
                                class="form-control @error('math_answer') is-invalid @enderror"
                                required min="1" max="20">
                            @error('math_answer')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg w-100">
                            <i class="fas fa-user-slash me-2"></i>Request Account Deletion
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
