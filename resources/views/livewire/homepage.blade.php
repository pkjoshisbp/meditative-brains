<div>
<!-- Hero Section -->
<section class="hero-section text-white py-5" style="background: linear-gradient(135deg, #064e3b 0%, #065f46 35%, #0c4a6e 100%); min-height: 420px;">
    <div class="container">
        <div class="row align-items-center min-vh-50">
            <div class="col-lg-6">
                <h1 class="display-4 fw-bold mb-4">Transform Your Mind with Premium Audio</h1>
                <p class="lead mb-4">Discover our premium mental wellness audio: affirmations, sleep music, meditation tracks, and healing frequencies designed to train your mind and transform your life.</p>
                <div class="d-flex gap-3 flex-wrap">
                    <a href="{{ route('products') }}" class="btn btn-light btn-lg">
                        <i class="fas fa-music me-2"></i>Browse Music
                    </a>
                    <a href="#subscription" class="btn btn-outline-light btn-lg">
                        <i class="fas fa-star me-2"></i>Get Subscription
                    </a>
                </div>
            </div>
            <div class="col-lg-6 text-center">
                <div class="hero-image mt-4 mt-lg-0">
                    <div class="position-relative d-inline-block">
                        <i class="fas fa-brain fa-8x text-white" style="opacity:0.15;"></i>
                        <i class="fas fa-bolt position-absolute" style="top:44%;left:50%;transform:translate(-50%,-50%);font-size:3.5rem;color:#34d399;filter:drop-shadow(0 0 18px rgba(52,211,153,0.55));"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Categories Section -->
<section class="py-5 bg-light">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="display-5 fw-bold">Explore Our Categories</h2>
            <p class="lead text-muted">Choose from our carefully curated collection of wellness audio</p>
        </div>
        <div class="row g-4">
            @foreach($categories as $category)
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 border-0 shadow-sm category-card">
                        <div class="card-body text-center p-4">
                            <div class="category-icon mb-3">
                                @switch($category->name)
                                    @case('TTS Affirmations')
                                        <i class="fas fa-microphone-alt fa-3x text-primary"></i>
                                        @break
                                    @case('Sleep Aid Music')
                                        <i class="fas fa-moon fa-3x text-info"></i>
                                        @break
                                    @case('Meditation Music')
                                        <i class="fas fa-leaf fa-3x text-success"></i>
                                        @break
                                    @case('Binaural Beats')
                                        <i class="fas fa-wave-square fa-3x text-warning"></i>
                                        @break
                                    @case('Nature Sounds')
                                        <i class="fas fa-tree fa-3x text-success"></i>
                                        @break
                                    @case('Solfeggio Frequencies')
                                        <i class="fas fa-yin-yang fa-3x text-purple"></i>
                                        @break
                                    @default
                                        <i class="fas fa-music fa-3x text-primary"></i>
                                @endswitch
                            </div>
                            <h5 class="card-title">{{ $category->name === 'TTS Affirmations' ? 'Mental Wellness Audio' : $category->name }}</h5>
                            <p class="card-text text-muted">{{ $category->description }}</p>
                            <p class="small text-success">{{ $category->active_products_count }} tracks available</p>
                            <a href="{{ $category->name === 'TTS Affirmations' ? (Route::has('audio.catalog') ? route('audio.catalog') : url('/mind-audio')) : route('products', ['categoryId' => $category->id]) }}" class="btn btn-outline-primary">
                                Explore {{ $category->name === 'TTS Affirmations' ? 'Mental Wellness Audio' : $category->name }}
                            </a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

<!-- Featured Products Section -->
@if($featuredProducts->count() > 0)
<section class="py-5">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="display-5 fw-bold">Featured Tracks</h2>
            <p class="lead text-muted">Hand-picked premium audio for maximum impact</p>
        </div>
        <div class="row g-4">
            @foreach($featuredProducts as $product)
                <div class="col-md-6 col-lg-3">
                    <div class="card h-100 product-card border-0 shadow-sm">
                        <div class="position-relative">
                            @if($product->getFirstMediaUrl('images'))
                                <img src="{{ $product->getFirstMediaUrl('images', 'cover') }}" class="card-img-top" alt="{{ $product->name }}" style="height: 200px; object-fit: cover;">
                            @else
                                @php
                                    $catSlug = strtolower($product->category->name ?? $product->category ?? '');
                                    if (str_contains($catSlug, 'confidence') || str_contains($catSlug, 'hypnosis')) { $h_img = 'confidence.jpg'; }
                                    elseif (str_contains($catSlug, 'relax') || str_contains($catSlug, 'bliss') || str_contains($catSlug, 'sleep')) { $h_img = 'relaxation.jpg'; }
                                    elseif (str_contains($catSlug, 'motivat') || str_contains($catSlug, 'inspir') || str_contains($catSlug, 'quot')) { $h_img = 'motivation.jpg'; }
                                    elseif (str_contains($catSlug, 'happin') || str_contains($catSlug, 'positive') || str_contains($catSlug, 'attitude')) { $h_img = 'happiness.jpg'; }
                                    elseif (str_contains($catSlug, 'goal') || str_contains($catSlug, 'achiev') || str_contains($catSlug, 'time') || str_contains($catSlug, 'manage')) { $h_img = 'goals.jpg'; }
                                    elseif (str_contains($catSlug, 'resilien') || str_contains($catSlug, 'failure')) { $h_img = 'resilience.jpg'; }
                                    elseif (str_contains($catSlug, 'smok') || str_contains($catSlug, 'quit')) { $h_img = 'quit-smoking.jpg'; }
                                    elseif (str_contains($catSlug, 'meditat')) { $h_img = 'meditation.jpg'; }
                                    else { $h_img = 'wellness.jpg'; }
                                @endphp
                                <img src="{{ asset('images/categories/' . $h_img) }}" class="card-img-top" alt="{{ $product->name }}" style="height: 200px; object-fit: cover;">
                            @endif
                            
                            <span class="badge bg-warning position-absolute top-0 start-0 m-2">
                                <i class="fas fa-star me-1"></i>Featured
                            </span>

                            @if($product->hasDiscount())
                                <span class="badge bg-danger position-absolute top-0 end-0 m-2">
                                    -{{ $product->getDiscountPercentage() }}%
                                </span>
                            @endif
                        </div>

                        <div class="card-body d-flex flex-column">
                            <h6 class="card-title">{{ $product->name }}</h6>
                            <p class="card-text text-muted small mb-2">{{ $product->category->name }}</p>
                            
                            @if($product->short_description)
                                <p class="card-text small">{{ Str::limit($product->short_description, 60) }}</p>
                            @endif

                            <div class="mt-auto">
                                <div class="mb-2">
                                    @php $isIndia = session('user_currency') === 'INR'; @endphp
                                    @if($isIndia)
                                        @if($product->hasDiscount() && $product->inr_sale_price)
                                            <span class="h6 text-primary">&#8377;{{ number_format($product->inr_sale_price, 0) }}</span>
                                            <span class="text-muted text-decoration-line-through ms-1">&#8377;{{ number_format($product->inr_price ?: $product->price * 100, 0) }}</span>
                                        @else
                                            <span class="h6 text-primary">&#8377;{{ number_format($product->inr_price ?: $product->price * 100, 0) }}</span>
                                        @endif
                                    @else
                                        @if($product->hasDiscount())
                                            <span class="h6 text-primary">${{ number_format($product->sale_price, 2) }}</span>
                                            <span class="text-muted text-decoration-line-through ms-1">${{ number_format($product->price, 2) }}</span>
                                        @else
                                            <span class="h6 text-primary">${{ number_format($product->price, 2) }}</span>
                                        @endif
                                    @endif
                                </div>

                                <div class="d-grid gap-2">
                                    <button class="btn btn-outline-primary btn-sm" onclick="playPreview({{ $product->id }})">
                                        <i class="fas fa-play"></i> Preview
                                    </button>
                                    <button class="btn btn-primary btn-sm" onclick="addToCart({{ $product->id }})">
                                        <i class="fas fa-cart-plus"></i> Add to Cart
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="text-center mt-4">
            <a href="{{ route('products') }}" class="btn btn-primary btn-lg">View All Products</a>
        </div>
    </div>
</section>
@endif

<!-- New Releases Section -->
@if($newProducts->count() > 0)
<section class="py-5 bg-light">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="display-5 fw-bold">New Releases</h2>
            <p class="lead text-muted">Latest additions to our wellness audio collection</p>
        </div>
        <div class="row g-4">
            @foreach($newProducts as $product)
                <div class="col-md-6 col-lg-3">
                    <div class="card h-100 product-card border-0 shadow-sm">
                        <div class="position-relative">
                            @if($product->getFirstMediaUrl('images'))
                                <img src="{{ $product->getFirstMediaUrl('images', 'cover') }}" class="card-img-top" alt="{{ $product->name }}" style="height: 180px; object-fit: cover;">
                            @else
                                @php
                                    $catSlugN = strtolower($product->category->name ?? $product->category ?? '');
                                    if (str_contains($catSlugN, 'confidence') || str_contains($catSlugN, 'hypnosis')) { $n_img = 'confidence.jpg'; }
                                    elseif (str_contains($catSlugN, 'relax') || str_contains($catSlugN, 'bliss') || str_contains($catSlugN, 'sleep')) { $n_img = 'relaxation.jpg'; }
                                    elseif (str_contains($catSlugN, 'motivat') || str_contains($catSlugN, 'inspir') || str_contains($catSlugN, 'quot')) { $n_img = 'motivation.jpg'; }
                                    elseif (str_contains($catSlugN, 'happin') || str_contains($catSlugN, 'positive') || str_contains($catSlugN, 'attitude')) { $n_img = 'happiness.jpg'; }
                                    elseif (str_contains($catSlugN, 'goal') || str_contains($catSlugN, 'achiev') || str_contains($catSlugN, 'time') || str_contains($catSlugN, 'manage')) { $n_img = 'goals.jpg'; }
                                    elseif (str_contains($catSlugN, 'resilien') || str_contains($catSlugN, 'failure')) { $n_img = 'resilience.jpg'; }
                                    elseif (str_contains($catSlugN, 'smok') || str_contains($catSlugN, 'quit')) { $n_img = 'quit-smoking.jpg'; }
                                    elseif (str_contains($catSlugN, 'meditat')) { $n_img = 'meditation.jpg'; }
                                    else { $n_img = 'wellness.jpg'; }
                                @endphp
                                <img src="{{ asset('images/categories/' . $n_img) }}" class="card-img-top" alt="{{ $product->name }}" style="height: 180px; object-fit: cover;">
                            @endif
                            
                            <span class="badge bg-success position-absolute top-0 start-0 m-2">
                                <i class="fas fa-sparkles me-1"></i>New
                            </span>
                        </div>

                        <div class="card-body">
                            <h6 class="card-title">{{ $product->name }}</h6>
                            <p class="card-text text-muted small">{{ $product->category->name }}</p>
                            <div class="d-flex justify-content-between align-items-center">
                                @php $isIndia = session('user_currency') === 'INR'; @endphp
                                @if($isIndia)
                                    <span class="h6 text-primary mb-0">&#8377;{{ number_format($product->inr_price ?: $product->getCurrentPrice() * 100, 0) }}</span>
                                @else
                                    <span class="h6 text-primary mb-0">${{ number_format($product->getCurrentPrice(), 2) }}</span>
                                @endif
                                <button class="btn btn-outline-primary btn-sm" onclick="playPreview({{ $product->id }})">
                                    <i class="fas fa-play"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif

<!-- Subscription CTA Section -->
<section id="subscription" class="py-5" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);">
    <div class="container">
        <div class="text-center text-white mb-5">
            <span class="badge bg-primary mb-3 px-3 py-2">SUBSCRIPTION PLANS</span>
            <h2 class="display-5 fw-bold mb-3">Unlock Your Full Mental Potential</h2>
            <p class="lead text-light opacity-75 mb-0">Choose the plan that fits your journey. Cancel any time, no questions asked.</p>
        </div>

        @php $isIndia = session('user_currency') === 'INR'; @endphp
        <div class="row g-4 justify-content-center">

            {{-- Free / Limited Plan --}}
            <div class="col-sm-6 col-lg-3">
                <div class="card h-100 border-0 shadow text-center p-4" style="background:#1e293b;border:1px solid rgba(255,255,255,0.08)!important;">
                    <div class="mb-3"><span class="badge bg-secondary px-3 py-2">FREE</span></div>
                    <h5 class="text-white fw-bold">Trial</h5>
                    <div class="display-5 fw-bold text-white my-3">₹0<small class="fs-6 fw-normal" style="color:#94a3b8;">/forever</small></div>
                    <ul class="list-unstyled text-start text-light small mb-4 flex-grow-1">
                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i>3 free audio tracks</li>
                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i>30-second previews</li>
                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Mobile app access</li>
                        <li class="mb-2" style="color:#64748b;"><i class="fas fa-times text-danger me-2"></i>Downloads</li>
                        <li class="mb-2" style="color:#64748b;"><i class="fas fa-times text-danger me-2"></i>Unlimited access</li>
                    </ul>
                    <a href="{{ route('register') }}" class="btn btn-outline-light w-100">Start Free</a>
                </div>
            </div>

            {{-- Starter Plan --}}
            <div class="col-sm-6 col-lg-3">
                <div class="card h-100 border-0 shadow text-center p-4" style="background:#1e293b;border:1px solid rgba(255,255,255,0.08)!important;">
                    <div class="mb-3"><span class="badge bg-info px-3 py-2">STARTER</span></div>
                    <h5 class="text-white fw-bold">Essential</h5>
                    <div class="display-5 fw-bold text-info my-3">
                        @if($isIndia) ₹490 @else $4.90 @endif
                        <small class="fs-6 fw-normal" style="color:#94a3b8;">/month</small>
                    </div>
                    <ul class="list-unstyled text-start text-light small mb-4 flex-grow-1">
                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i>5 products of your choice</li>
                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Stream &amp; download</li>
                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Mobile app access</li>
                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Email support</li>
                        <li class="mb-2" style="color:#64748b;"><i class="fas fa-times text-danger me-2"></i>Unlimited products</li>
                    </ul>
                    <a href="{{ route('subscription') }}" class="btn btn-info w-100">Get Started</a>
                </div>
            </div>

            {{-- Monthly All-Access --}}
            <div class="col-sm-6 col-lg-3 position-relative">
                <div class="position-absolute top-0 start-50 translate-middle" style="z-index:2;">
                    <span class="badge bg-warning text-dark px-3 py-2 shadow fw-bold">MOST POPULAR</span>
                </div>
                <div class="card h-100 border-0 shadow-lg text-center p-4" style="background:linear-gradient(135deg,#2563eb,#1d4ed8);border:2px solid #3b82f6!important;transform:scale(1.04);">
                    <div class="mb-3 mt-2"><span class="badge bg-primary px-3 py-2">ALL ACCESS</span></div>
                    <h5 class="text-white fw-bold">Monthly</h5>
                    <div class="display-5 fw-bold text-white my-3">
                        @if($isIndia) ₹1,999 @else $19.99 @endif
                        <small class="fs-6 fw-normal text-white opacity-75">/month</small>
                    </div>
                    <ul class="list-unstyled text-start text-light small mb-4 flex-grow-1">
                        <li class="mb-2"><i class="fas fa-check text-warning me-2"></i>Unlimited product access</li>
                        <li class="mb-2"><i class="fas fa-check text-warning me-2"></i>Stream &amp; download all</li>
                        <li class="mb-2"><i class="fas fa-check text-warning me-2"></i>New releases first access</li>
                        <li class="mb-2"><i class="fas fa-check text-warning me-2"></i>Mobile app + offline</li>
                        <li class="mb-2"><i class="fas fa-check text-warning me-2"></i>Priority support</li>
                    </ul>
                    <a href="{{ route('subscription') }}" class="btn btn-warning text-dark fw-bold w-100">Start Free Trial</a>
                </div>
            </div>

            {{-- Yearly Plan --}}
            <div class="col-sm-6 col-lg-3">
                <div class="card h-100 border-0 shadow text-center p-4" style="background:#1e293b;border:1px solid rgba(52,211,153,0.3)!important;">
                    <div class="mb-3"><span class="badge bg-success px-3 py-2">YEARLY</span></div>
                    <h5 class="text-white fw-bold">Annual</h5>
                    <div class="display-5 fw-bold text-success my-3">
                        @if($isIndia) ₹19,999 @else $199.99 @endif
                        <small class="fs-6 fw-normal" style="color:#94a3b8;">/year</small>
                    </div>
                    @if($isIndia)
                        <p class="text-success small fw-bold mb-3"><i class="fas fa-tag me-1"></i>Save ₹4,000 vs monthly!</p>
                    @else
                        <p class="text-success small fw-bold mb-3"><i class="fas fa-tag me-1"></i>Save $39.89 vs monthly!</p>
                    @endif
                    <ul class="list-unstyled text-start text-light small mb-4 flex-grow-1">
                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Everything in Monthly</li>
                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i>2 months free</li>
                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Exclusive yearly content</li>
                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Dedicated support</li>
                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Early feature access</li>
                    </ul>
                    <a href="{{ route('subscription') }}" class="btn btn-success w-100">Get Yearly Plan</a>
                </div>
            </div>
        </div>

        <p class="text-center mt-4 small" style="color:#94a3b8;">All plans include our mobile app for Android &amp; iOS. Secure payments via Razorpay &amp; PayPal.</p>
    </div>
</section>

<!-- About Section -->
<section id="about" class="py-5">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <h2 class="display-5 fw-bold mb-4">About Mental Fitness Store</h2>
                <p class="lead">We're dedicated to creating premium audio content that supports your mental fitness and personal development journey.</p>
                <p>Our expertly crafted affirmations, sleep aid music, and healing frequencies are designed using the latest research in neuroscience and sound therapy. Each track is carefully produced to train your mind and deliver real transformation.</p>
                <div class="row mt-4">
                    <div class="col-sm-6">
                        <div class="d-flex align-items-center mb-3">
                            <i class="fas fa-award fa-2x text-primary me-3"></i>
                            <div>
                                <h6 class="mb-0">Premium Quality</h6>
                                <small class="text-muted">Studio-grade recordings</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="d-flex align-items-center mb-3">
                            <i class="fas fa-brain fa-2x text-success me-3"></i>
                            <div>
                                <h6 class="mb-0">Science-Based</h6>
                                <small class="text-muted">Researched frequencies</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 text-center">
                <div class="about-image">
                    <i class="fas fa-headphones fa-10x text-primary opacity-25"></i>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Audio Preview Modal -->
<div class="modal fade" id="previewModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Audio Preview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <div id="audioPlayerContainer">
                    <p>Loading preview...</p>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function playPreview(productId) {
    const modal = new bootstrap.Modal(document.getElementById('previewModal'));
    modal.show();
    
    fetch('/audio/preview-url', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({
            product_id: productId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.preview_url) {
            document.getElementById('audioPlayerContainer').innerHTML = `
                <audio controls autoplay style="width: 100%;">
                    <source src="${data.preview_url}" type="audio/mpeg">
                    Your browser does not support the audio element.
                </audio>
                <p class="mt-2 text-muted">Preview Duration: ${data.duration} seconds</p>
            `;
        } else {
            document.getElementById('audioPlayerContainer').innerHTML = `
                <p class="text-danger">Preview not available</p>
            `;
        }
    })
    .catch(error => {
        document.getElementById('audioPlayerContainer').innerHTML = `
            <p class="text-danger">Error loading preview</p>
        `;
    });
}

function addToCart(productId) {
    fetch('/cart/add', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({ product_id: productId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showCartToast(data.message || 'Product added to cart!');
        } else {
            showCartToast(data.message || 'Please log in to add items to cart.', true);
        }
    })
    .catch(() => showCartToast('Please log in to add items to cart.', true));
}

function showCartToast(message, isError = false) {
    const existing = document.getElementById('cartToastBar');
    if (existing) existing.remove();
    const bar = document.createElement('div');
    bar.id = 'cartToastBar';
    bar.style.cssText = 'position:fixed;bottom:24px;right:24px;z-index:9999;min-width:280px;';
    bar.innerHTML = `<div class="alert ${isError ? 'alert-warning' : 'alert-success'} shadow d-flex align-items-center gap-3 mb-0">
        <i class="fas ${isError ? 'fa-exclamation-circle' : 'fa-check-circle'} fa-lg"></i>
        <div class="flex-grow-1">${message}</div>
        ${!isError ? '<a href="/cart" class="btn btn-sm btn-success">View Cart &rarr;</a>' : '<a href="/login" class="btn btn-sm btn-primary">Login</a>'}
    </div>`;
    document.body.appendChild(bar);
    setTimeout(() => { if (bar.parentNode) bar.remove(); }, 5000);
}
}
</script>
@endpush

<style>
.hero-section {
    min-height: 60vh;
}

.category-card:hover {
    transform: translateY(-5px);
    transition: transform 0.3s ease;
}

.product-card:hover {
    transform: translateY(-3px);
    transition: transform 0.2s ease;
}

.text-purple {
    color: #6f42c1 !important;
}

.toast-notification {
    position: fixed;
    top: 20px;
    right: 20px;
    background: #28a745;
    color: white;
    padding: 15px 20px;
    border-radius: 5px;
    z-index: 1050;
    animation: slideIn 0.3s ease;
}

@keyframes slideIn {
    from { transform: translateX(100%); }
    to { transform: translateX(0); }
}
</style>
</div>
