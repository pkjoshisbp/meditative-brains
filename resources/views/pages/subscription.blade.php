@extends('layouts.app-frontend')

@section('title', 'Subscription Plans — Mental Fitness Store')

@section('content')
@php $isIndia = session('user_currency') === 'INR'; @endphp
<div class="py-5 bg-light border-bottom mb-5">
    <div class="container text-center">
        <h1 class="display-5 fw-bold mb-2">Subscription Plans</h1>
        <p class="lead text-muted">Unlimited access to premium mental wellness audio</p>
        <div class="small text-info mt-2">
            <i class="fas fa-user-graduate me-1"></i>Student pricing is available on eligible plans after account verification.
        </div>
    </div>
</div>

<div class="container pb-5">
    @if(session('message'))
        <div class="alert alert-info alert-dismissible fade show">
            {{ session('message') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row justify-content-center mb-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-body d-flex justify-content-between align-items-center gap-3 flex-wrap">
                    <div class="fw-semibold">Billing interval</div>
                    <div class="btn-group" role="group" aria-label="Billing interval">
                        <a href="{{ route('subscription', ['interval' => 'monthly']) }}"
                           class="btn {{ $billingInterval === 'monthly' ? 'btn-primary' : 'btn-outline-primary' }}">
                            Monthly
                        </a>
                        <a href="{{ route('subscription', ['interval' => 'yearly']) }}"
                           class="btn {{ $billingInterval === 'yearly' ? 'btn-primary' : 'btn-outline-primary' }}">
                            Yearly
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 justify-content-center mb-5">
        @forelse($plans as $entry)
            @php
                $plan = $entry['plan'];
                $displayAmount = $isIndia ? $entry['pricing']['final_inr'] : $entry['pricing']['final_usd'];
                $currencySymbol = $isIndia ? '₹' : '$';
                $periodLabel = $billingInterval === 'yearly' ? 'per year' : 'per month';
            @endphp
            <div class="col-md-4">
                <div class="card h-100 shadow-sm {{ $plan->is_featured ? 'border-primary border-2' : 'border-0' }}">
                    @if($plan->is_featured)
                        <div class="card-header bg-primary text-white text-center py-2 fw-semibold">Most Popular</div>
                    @endif
                    <div class="card-body text-center p-4 d-flex flex-column">
                        <h5 class="text-muted text-uppercase fw-semibold small mb-2">{{ $plan->name }}</h5>
                        @if($plan->description)
                            <p class="small text-muted mb-3">{{ $plan->description }}</p>
                        @endif
                        <div class="display-6 fw-bold mb-1">
                            {{ $currencySymbol }}{{ $isIndia ? number_format($displayAmount, 0) : number_format($displayAmount, 2) }}
                        </div>
                        <p class="text-muted mb-2">{{ $periodLabel }}</p>

                        <ul class="list-unstyled text-start mb-4 flex-grow-1">
                            @foreach($plan->features ?? [] as $feature)
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i>{{ $feature }}</li>
                            @endforeach
                            @if(empty($plan->features))
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Premium access included</li>
                            @endif
                        </ul>

                        @auth
                            <a href="{{ route('subscription.checkout.show', ['plan' => $plan->id, 'interval' => $billingInterval]) }}"
                               class="btn {{ $plan->is_featured ? 'btn-primary' : 'btn-outline-primary' }} w-100">
                                {{ $billingInterval === 'yearly' ? 'Get Yearly' : 'Subscribe Now' }}
                            </a>
                        @else
                            <a href="{{ route('login') }}" class="btn {{ $plan->is_featured ? 'btn-primary' : 'btn-outline-primary' }} w-100">
                                Login to Subscribe
                            </a>
                        @endauth
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12">
                <div class="alert alert-light border text-center mb-0">No active subscription plans are available right now.</div>
            </div>
        @endforelse
    </div>

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
                            @if($isIndia)
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
