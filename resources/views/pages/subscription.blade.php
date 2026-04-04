@extends('layouts.app-frontend')

@section('title', 'Subscription Plans — Mental Fitness Store')

@section('content')
<div class="py-5 bg-light border-bottom mb-5">
    <div class="container text-center">
        <h1 class="display-5 fw-bold mb-2">Subscription Plans</h1>
        <p class="lead text-muted">Unlimited access to premium mental wellness audio</p>
    </div>
</div>

<div class="container pb-5">

    {{-- Plan cards --}}
    <div class="row g-4 justify-content-center mb-5">

        {{-- Starter --}}
        <div class="col-md-4">
            <div class="card h-100 shadow-sm border-0">
                <div class="card-body text-center p-4">
                    <h5 class="text-muted text-uppercase fw-semibold small mb-3">Starter</h5>
                    <div class="display-6 fw-bold mb-1">
                        @if(session('user_currency') === 'INR')
                            ₹490
                        @else
                            $4.90
                        @endif
                    </div>
                    <p class="text-muted mb-4">per month</p>
                    <ul class="list-unstyled text-start mb-4">
                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Access to 5 products of your choice</li>
                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Stream &amp; download</li>
                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Mobile app access</li>
                        <li class="mb-2 text-muted"><i class="fas fa-times text-danger me-2"></i>Unlimited products</li>
                    </ul>
                    <a href="{{ route('register') }}" class="btn btn-outline-primary w-100">Get Started</a>
                </div>
            </div>
        </div>

        {{-- Monthly All-Access --}}
        <div class="col-md-4">
            <div class="card h-100 shadow border-primary" style="border-width:2px!important;">
                <div class="card-header bg-primary text-white text-center py-2 fw-semibold">
                    Most Popular
                </div>
                <div class="card-body text-center p-4">
                    <h5 class="text-muted text-uppercase fw-semibold small mb-3">All Access</h5>
                    <div class="display-6 fw-bold mb-1">
                        @if(session('user_currency') === 'INR')
                            ₹1,999
                        @else
                            $19.99
                        @endif
                    </div>
                    <p class="text-muted mb-4">per month</p>
                    <ul class="list-unstyled text-start mb-4">
                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Unlimited product access</li>
                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Stream &amp; download</li>
                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Mobile app access</li>
                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i>New releases first</li>
                    </ul>
                    <a href="{{ route('register') }}" class="btn btn-primary w-100">Subscribe Now</a>
                </div>
            </div>
        </div>

        {{-- Yearly --}}
        <div class="col-md-4">
            <div class="card h-100 shadow-sm border-0">
                <div class="card-header bg-success text-white text-center py-2 fw-semibold">
                    @if(session('user_currency') === 'INR')
                        Save ₹4,000!
                    @else
                        Best Value
                    @endif
                </div>
                <div class="card-body text-center p-4">
                    <h5 class="text-muted text-uppercase fw-semibold small mb-3">Yearly</h5>
                    <div class="display-6 fw-bold mb-1">
                        @if(session('user_currency') === 'INR')
                            ₹19,999
                        @else
                            $199.99
                        @endif
                    </div>
                    <p class="text-muted mb-4">per year</p>
                    <ul class="list-unstyled text-start mb-4">
                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Everything in All Access</li>
                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Priority support</li>
                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Early access to new titles</li>
                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Best per-month rate</li>
                    </ul>
                    <a href="{{ route('register') }}" class="btn btn-success w-100">Get Yearly</a>
                </div>
            </div>
        </div>
    </div>

    {{-- FAQ --}}
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <h2 class="h4 text-center mb-4">Frequently Asked Questions</h2>
            <div class="accordion" id="subFaq">
                <div class="accordion-item mb-2 border-0 shadow-sm rounded">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed rounded" type="button" data-bs-toggle="collapse" data-bs-target="#sf1">
                            Can I cancel anytime?
                        </button>
                    </h2>
                    <div id="sf1" class="accordion-collapse collapse" data-bs-parent="#subFaq">
                        <div class="accordion-body">Yes. You can cancel your subscription at any time from your account settings. Your access continues until the end of the current billing period.</div>
                    </div>
                </div>
                <div class="accordion-item mb-2 border-0 shadow-sm rounded">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed rounded" type="button" data-bs-toggle="collapse" data-bs-target="#sf2">
                            What payment methods are accepted?
                        </button>
                    </h2>
                    <div id="sf2" class="accordion-collapse collapse" data-bs-parent="#subFaq">
                        <div class="accordion-body">
                            @if(session('user_currency') === 'INR')
                                We accept payments via Razorpay — UPI, debit/credit cards, net banking, and wallets.
                            @else
                                We accept PayPal and all major credit/debit cards via PayPal.
                            @endif
                        </div>
                    </div>
                </div>
                <div class="accordion-item border-0 shadow-sm rounded">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed rounded" type="button" data-bs-toggle="collapse" data-bs-target="#sf3">
                            Are there refunds for subscriptions?
                        </button>
                    </h2>
                    <div id="sf3" class="accordion-collapse collapse" data-bs-parent="#subFaq">
                        <div class="accordion-body">All subscription payments are non-refundable. Please review our <a href="{{ route('legal.refund') }}">Refund Policy</a> for full details.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection
