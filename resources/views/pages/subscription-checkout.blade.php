@extends('layouts.app-frontend')

@section('title', 'Subscription Checkout — Mental Fitness Store')

@section('content')
@php
    $isIndia = session('user_currency') === 'INR';
    $usesCcavenue = $isIndia || session('payment_gateway') === 'ccavenue';
    $currencySymbol = $isIndia ? '₹' : '$';
    $displayBase = $isIndia ? $pricing['final_inr'] : $pricing['final_usd'];
    $displayDiscount = $isIndia ? $discountInr : $discountUsd;
    $displayFinal = $isIndia ? $finalInr : $finalUsd;
    $periodLabel = $billingInterval === 'yearly' ? 'per year' : 'per month';
@endphp

<div class="py-5 bg-light border-bottom mb-5">
    <div class="container">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
            <div>
                <h1 class="display-6 fw-bold mb-2">Checkout</h1>
                <p class="lead text-muted mb-0">Review your plan and apply a promo code before continuing to {{ $usesCcavenue ? 'CCAvenue' : 'PayPal' }}.</p>
            </div>
            <a href="{{ route('subscription', ['interval' => $billingInterval]) }}" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to Plans
            </a>
        </div>
    </div>
</div>

<div class="container pb-5">
    @if($errors->has('promo_code'))
        <div class="alert alert-danger">{{ $errors->first('promo_code') }}</div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    @if(session('message'))
        <div class="alert alert-info">{{ session('message') }}</div>
    @endif

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                        <div>
                            <div class="text-muted text-uppercase small fw-semibold">{{ $plan->name }}</div>
                            <h2 class="h3 fw-bold mb-1">{{ ucfirst($billingInterval) }} Subscription</h2>
                            @if($plan->description)
                                <p class="text-muted mb-0">{{ $plan->description }}</p>
                            @endif
                        </div>
                        @if($plan->is_featured)
                            <span class="badge bg-primary">Most Popular</span>
                        @endif
                    </div>

                    <ul class="list-unstyled mb-0">
                        @foreach($plan->features ?? [] as $feature)
                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>{{ $feature }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h3 class="h5 fw-bold mb-4">Order Summary</h3>

                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Plan price</span>
                        <span>{{ $currencySymbol }}{{ $isIndia ? number_format($displayBase, 0) : number_format($displayBase, 2) }}</span>
                    </div>

                    @if($displayDiscount > 0)
                        <div class="d-flex justify-content-between mb-2 text-success">
                            <span>Promo discount</span>
                            <span>-{{ $currencySymbol }}{{ $isIndia ? number_format($displayDiscount, 0) : number_format($displayDiscount, 2) }}</span>
                        </div>
                    @endif

                    <div class="small text-muted mb-3">{{ $periodLabel }}</div>
                    <hr>
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <span class="fw-bold fs-5">Total</span>
                        <span class="fw-bold fs-4 text-primary">{{ $currencySymbol }}{{ $isIndia ? number_format($displayFinal, 0) : number_format($displayFinal, 2) }}</span>
                    </div>

                    <form method="GET" action="{{ route('subscription.checkout.show') }}" class="mb-4">
                        <input type="hidden" name="plan" value="{{ $plan->id }}">
                        <input type="hidden" name="interval" value="{{ $billingInterval }}">
                        <label class="form-label fw-semibold">Promo code</label>
                        <div class="input-group">
                            <input type="text" name="promo" class="form-control" placeholder="Enter promo code" value="{{ $promoInput }}">
                            <button class="btn btn-outline-secondary" type="submit">Apply</button>
                        </div>
                        @if($promoCode)
                            <div class="small text-success mt-2">
                                <i class="fas fa-badge-percent me-1"></i>
                                Promo <strong>{{ $promoCode->code }}</strong> applied.
                            </div>
                        @endif
                    </form>

                    @if($usesCcavenue)
                        <form method="POST" action="{{ route('subscription.checkout.ccavenue.start') }}">
                            @csrf
                            <input type="hidden" name="plan_id" value="{{ $plan->id }}">
                            <input type="hidden" name="billing_interval" value="{{ $billingInterval }}">
                            <input type="hidden" name="promo_code" value="{{ $promoInput }}">
                            <button type="submit" class="btn btn-primary w-100 btn-lg">
                                <i class="fas fa-lock me-2"></i>Continue to CCAvenue
                            </button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('subscription.checkout') }}">
                            @csrf
                            <input type="hidden" name="plan_id" value="{{ $plan->id }}">
                            <input type="hidden" name="billing_interval" value="{{ $billingInterval }}">
                            <input type="hidden" name="promo_code" value="{{ $promoInput }}">
                            <button type="submit" class="btn btn-primary w-100 btn-lg">
                                <i class="fab fa-paypal me-2"></i>Continue to PayPal
                            </button>
                        </form>
                    @endif

                    <div class="small text-muted text-center mt-3">
                        @if($usesCcavenue)
                            CCAvenue hosts the payment flow securely for India payments in INR.
                        @else
                            PayPal hosts the payment page securely. Merchant branding is passed as Mental Fitness.
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection