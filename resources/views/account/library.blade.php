@extends('account.layout')
@section('title', 'My Library')

@section('account-content')
<div>
    <h2 class="fw-bold mb-1">My Library</h2>
    <p class="text-muted mb-4">Your purchased and subscribed audio content.</p>

    {{-- Subscription access notice --}}
    @if($activeSubscription)
    <div class="alert alert-success d-flex align-items-center gap-3 mb-4">
        <i class="fas fa-crown text-warning fa-lg"></i>
        <div>
            <div class="fw-bold">{{ ucfirst($activeSubscription->plan_type) }} Plan Active</div>
            <div class="small">You have full access to all content included in your plan.</div>
        </div>
    </div>
    @endif

    {{-- Purchased products --}}
    @if($purchasedProducts->isEmpty() && !$activeSubscription)
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5 text-muted">
            <i class="fas fa-headphones fa-3x mb-3 d-block opacity-25"></i>
            <h5 class="fw-semibold">Your library is empty</h5>
            <p class="mb-3">Purchase individual products or subscribe for unlimited access.</p>
            <div class="d-flex gap-2 justify-content-center flex-wrap">
                <a href="{{ route('products') }}" class="btn btn-primary">
                    <i class="fas fa-shopping-bag me-1"></i>Browse Products
                </a>
                <a href="{{ route('subscription') }}" class="btn btn-outline-primary">
                    <i class="fas fa-crown me-1"></i>View Plans
                </a>
            </div>
        </div>
    </div>
    @else
    <div class="row g-3">
        @foreach($purchasedProducts as $product)
        <div class="col-sm-6 col-xl-4">
            <div class="card h-100 border-0 shadow-sm">
                @if($product->image)
                <img src="{{ asset('storage/' . $product->image) }}"
                    class="card-img-top" alt="{{ $product->name }}"
                    style="height:160px;object-fit:cover;">
                @else
                <div class="d-flex align-items-center justify-content-center"
                    style="height:160px;background:linear-gradient(135deg,#0f172a,#1e3a5f);">
                    <i class="fas fa-headphones fa-3x text-white opacity-50"></i>
                </div>
                @endif
                <div class="card-body d-flex flex-column">
                    <h6 class="fw-bold mb-1">{{ $product->name }}</h6>
                    <p class="small text-muted flex-grow-1 mb-3">{{ Str::limit($product->description, 80) }}</p>
                    <div class="d-flex gap-2">
                        <a href="{{ route('products.show', $product->id) }}" class="btn btn-sm btn-primary flex-fill">
                            <i class="fas fa-headphones me-1"></i>Listen
                        </a>
                    </div>
                </div>
            </div>
        </div>
        @endforeach
    </div>
    @if($purchasedProducts->isEmpty())
    <div class="mt-4 p-4 bg-light rounded text-center text-muted">
        <i class="fas fa-info-circle me-2"></i>
        No individual products purchased yet. Your subscription gives you access to all content — browse via the
        <a href="{{ route('products') }}">Products page</a>.
    </div>
    @endif
    @endif
</div>
@endsection
