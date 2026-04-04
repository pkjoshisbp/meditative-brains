@extends('layouts.app-frontend')

@section('title', 'Your Cart — Mental Fitness Store')

@section('content')
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
                                @if(isset($item->product) && $item->product->getFirstMediaUrl('images'))
                                    <img src="{{ $item->product->getFirstMediaUrl('images', 'thumb') }}" class="rounded" style="width:72px;height:72px;object-fit:cover;" alt="{{ $item->product->name }}">
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
                                @php $isIndia = session('user_currency') === 'INR'; @endphp
                                @if($isIndia)
                                    <span class="fw-bold text-primary">₹{{ number_format(($item->price ?? 0) * 83, 0) }}</span>
                                @else
                                    <span class="fw-bold text-primary">${{ number_format($item->price ?? 0, 2) }}</span>
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
                                <span class="fw-semibold">₹{{ number_format($total * 83, 0) }}</span>
                            @else
                                <span class="fw-semibold">${{ number_format($total, 2) }}</span>
                            @endif
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between mb-4">
                            <span class="fw-bold fs-5">Total</span>
                            @if($isIndia)
                                <span class="fw-bold fs-5 text-primary">₹{{ number_format($total * 83, 0) }}</span>
                            @else
                                <span class="fw-bold fs-5 text-primary">${{ number_format($total, 2) }}</span>
                            @endif
                        </div>

                        @auth
                            <button class="btn btn-primary w-100 btn-lg mb-3" id="checkoutBtn">
                                <i class="fas fa-lock me-2"></i>Proceed to Checkout
                            </button>
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
                            <i class="fas fa-shield-alt me-1"></i> Secure checkout via Razorpay &amp; PayPal
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
