@extends('layouts.app-frontend')

@section('title', 'Your Cart — Mental Fitness Store')

@section('content')
@php
    $isIndia = session('user_currency') === 'INR';
    $usesRazorpay = $isIndia || session('payment_gateway') === 'razorpay';
@endphp
<div class="py-4 bg-light border-bottom">
    <div class="container">
        <h1 class="h3 fw-bold mb-0"><i class="fas fa-shopping-cart me-2 text-primary"></i>Your Cart</h1>
    </div>
</div>

<div class="container py-5">
    @if(session('message'))
        <div class="alert alert-success alert-dismissible fade show">
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

    @if($cartItems->isEmpty())
        <div class="text-center py-5">
            <i class="fas fa-shopping-cart fa-5x text-muted mb-4" style="opacity:0.2;"></i>
            <h3 class="fw-bold mb-2">Your cart is empty</h3>
            <p class="text-muted mb-4">Discover our premium mental wellness audio collection and add tracks you love.</p>
            <a href="{{ route('products') }}" class="btn btn-primary btn-lg me-2">
                <i class="fas fa-music me-2"></i>Browse Products
            </a>
            <a href="{{ route('subscription') }}" class="btn btn-outline-primary btn-lg">
                <i class="fas fa-star me-2"></i>View Plans
            </a>
        </div>
    @else
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0 pt-4 pb-0 px-4">
                        <h5 class="fw-bold mb-0">Cart Items ({{ $cartItems->count() }})</h5>
                    </div>
                    <div class="card-body p-0">
                        @foreach($cartItems as $item)
                        <div class="d-flex align-items-center p-4 border-bottom">
                            <div class="me-3 flex-shrink-0">
                                @if(isset($item->product) && $item->product->productImageUrl('thumb'))
                                    <img src="{{ $item->product->productImageUrl('thumb') }}" class="rounded" style="width:72px;height:72px;object-fit:cover;" alt="{{ $item->product->name }}">
                                @else
                                    <div class="rounded d-flex align-items-center justify-content-center bg-primary bg-opacity-10" style="width:72px;height:72px;">
                                        <i class="fas fa-music fa-2x text-primary"></i>
                                    </div>
                                @endif
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="mb-1 fw-semibold">{{ $item->product->name ?? 'Product' }}</h6>
                                <p class="text-muted small mb-0">{{ $item->product->category->name ?? '' }}</p>
                                @if(isset($item->product->short_description))
                                    <p class="text-muted small mb-0">{{ Str::limit($item->product->short_description, 60) }}</p>
                                @endif
                            </div>
                            <div class="text-end ms-3">
                                @if($isIndia)
                                    <span class="fw-bold text-primary">₹{{ number_format($item->price_inr ?? 0, 0) }}</span>
                                @else
                                    <span class="fw-bold text-primary">${{ number_format($item->price_usd ?? 0, 2) }}</span>
                                @endif
                                <form method="POST" action="{{ route('cart.remove') }}" class="mt-2">
                                    @csrf
                                    <input type="hidden" name="product_id" value="{{ $item->product_id }}">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        <i class="fas fa-trash-alt"></i> Remove
                                    </button>
                                </form>
                            </div>
                        </div>
                        @endforeach
                    </div>
                    <div class="card-footer bg-white border-0 p-4 d-flex justify-content-between">
                        <a href="{{ route('products') }}" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-1"></i> Continue Shopping
                        </a>
                        <form method="POST" action="{{ route('cart.clear') }}">
                            @csrf
                            <button type="submit" class="btn btn-outline-danger" onclick="return confirm('Clear your entire cart?')">
                                <i class="fas fa-trash me-1"></i> Clear Cart
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card border-0 shadow-sm sticky-top" style="top:80px;">
                    <div class="card-body p-4">
                        <h5 class="fw-bold mb-4">Order Summary</h5>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Subtotal ({{ $cartItems->count() }} item{{ $cartItems->count() > 1 ? 's' : '' }})</span>
                            @if($isIndia)
                                <span class="fw-semibold">₹{{ number_format($total, 0) }}</span>
                            @else
                                <span class="fw-semibold">${{ number_format($total, 2) }}</span>
                            @endif
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between mb-4">
                            <span class="fw-bold fs-5">Total</span>
                            @if($isIndia)
                                <span class="fw-bold fs-5 text-primary">₹{{ number_format($total, 0) }}</span>
                            @else
                                <span class="fw-bold fs-5 text-primary">${{ number_format($total, 2) }}</span>
                            @endif
                        </div>

                        @auth
                            @if($usesRazorpay)
                                <button type="button" class="btn btn-primary w-100 btn-lg mb-3" id="cart-razorpay-button">
                                    <i class="fas fa-bolt me-2"></i>Proceed to Razorpay
                                </button>
                            @else
                                <form method="POST" action="{{ route('cart.checkout') }}">
                                    @csrf
                                    <button type="submit" class="btn btn-primary w-100 btn-lg mb-3" id="checkoutBtn">
                                        <i class="fas fa-lock me-2"></i>Proceed to Checkout
                                    </button>
                                </form>
                            @endif
                        @else
                            <a href="{{ route('login') }}" class="btn btn-primary w-100 btn-lg mb-3">
                                <i class="fas fa-sign-in-alt me-2"></i>Login to Checkout
                            </a>
                        @endauth

                        <div class="alert alert-info small mb-3 py-2">
                            <i class="fas fa-star me-1"></i>
                            <strong>Better value:</strong> <a href="{{ route('subscription') }}" class="alert-link">See subscription plans</a> for unlimited access.
                        </div>

                        <div class="text-center text-muted small">
                            <i class="fas fa-shield-alt me-1"></i>
                            @if($usesRazorpay)
                                Secure checkout via Razorpay for India / INR orders
                            @else
                                Secure checkout via PayPal for international / USD orders
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

@if($usesRazorpay && auth()->check() && $cartItems->isNotEmpty())
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const button = document.getElementById('cart-razorpay-button');

    if (!button) {
        return;
    }

    button.addEventListener('click', function () {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
        button.disabled = true;

        fetch('{{ route('cart.checkout.razorpay.create') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            },
            body: JSON.stringify({})
        })
            .then(function (response) {
                return response.json().then(function (data) {
                    if (!response.ok) {
                        throw new Error(data.message || 'Could not create Razorpay order.');
                    }

                    return data;
                });
            })
            .then(function (data) {
                const razorpay = new Razorpay({
                    key: data.key_id,
                    amount: data.amount,
                    currency: data.currency,
                    name: 'Mental Fitness Store',
                    description: 'Cart purchase',
                    order_id: data.order_id,
                    handler: function (response) {
                        fetch('{{ route('cart.checkout.razorpay.verify') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({
                                razorpay_order_id: response.razorpay_order_id,
                                razorpay_payment_id: response.razorpay_payment_id,
                                razorpay_signature: response.razorpay_signature
                            })
                        })
                            .then(function (verifyResponse) {
                                return verifyResponse.json().then(function (verifyData) {
                                    if (!verifyResponse.ok || !verifyData.success) {
                                        throw new Error(verifyData.message || 'Payment verification failed.');
                                    }

                                    window.location.href = verifyData.redirect || '{{ route('account.library') }}';
                                });
                            })
                            .catch(function (error) {
                                button.disabled = false;
                                alert(error.message || 'Payment verification failed.');
                            });
                    },
                    theme: {
                        color: '#0d6efd'
                    }
                });

                razorpay.on('payment.failed', function (event) {
                    button.disabled = false;
                    alert(event.error.description || 'Payment failed.');
                });

                razorpay.open();
            })
            .catch(function (error) {
                button.disabled = false;
                alert(error.message || 'Payment service unavailable. Please try again.');
            });
    });
});
</script>
@endif
@endsection
