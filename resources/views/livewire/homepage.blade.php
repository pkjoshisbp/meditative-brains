<div>
<!-- Hero Section -->
<section class="hero-section bg-gradient-to-r from-purple-900 to-blue-900 text-white py-5" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
    <div class="container">
        <div class="row align-items-center min-vh-50">
            <div class="col-lg-6">
                <h1 class="display-4 fw-bold mb-4">Transform Your Mind with Premium Audio</h1>
                <p class="lead mb-4">Discover our collection of Meditative Minds audio experiences: affirmations, sleep aid music, meditation tracks, and healing frequencies designed to enhance your wellness journey.</p>
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
                    <i class="fas fa-brain fa-10x text-white opacity-75"></i>
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
                            <h5 class="card-title">{{ $category->name === 'TTS Affirmations' ? 'Meditative Minds Audio' : $category->name }}</h5>
                            <p class="card-text text-muted">{{ $category->description }}</p>
                            <p class="small text-success">{{ $category->active_products_count }} tracks available</p>
                            <a href="{{ $category->name === 'TTS Affirmations' ? (Route::has('audio.catalog') ? route('audio.catalog') : url('/mind-audio')) : route('products', ['categoryId' => $category->id]) }}" class="btn btn-outline-primary">
                                Explore {{ $category->name === 'TTS Affirmations' ? 'Meditative Minds Audio' : $category->name }}
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
                                <div class="card-img-top bg-gradient d-flex align-items-center justify-content-center" style="height: 200px; background: linear-gradient(45deg, #f093fb 0%, #f5576c 100%);">
                                    <i class="fas fa-music fa-3x text-white"></i>
                                </div>
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
                                    @if($product->hasDiscount())
                                        <span class="h6 text-primary">${{ number_format($product->sale_price, 2) }}</span>
                                        <span class="text-muted text-decoration-line-through ms-1">${{ number_format($product->price, 2) }}</span>
                                    @else
                                        <span class="h6 text-primary">${{ number_format($product->price, 2) }}</span>
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
                                <div class="card-img-top bg-gradient d-flex align-items-center justify-content-center" style="height: 180px; background: linear-gradient(45deg, #a8edea 0%, #fed6e3 100%);">
                                    <i class="fas fa-music fa-2x text-dark"></i>
                                </div>
                            @endif
                            
                            <span class="badge bg-success position-absolute top-0 start-0 m-2">
                                <i class="fas fa-sparkles me-1"></i>New
                            </span>
                        </div>

                        <div class="card-body">
                            <h6 class="card-title">{{ $product->name }}</h6>
                            <p class="card-text text-muted small">{{ $product->category->name }}</p>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="h6 text-primary mb-0">${{ number_format($product->getCurrentPrice(), 2) }}</span>
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
<section id="subscription" class="py-5 bg-primary text-white">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <h2 class="display-5 fw-bold mb-3">Unlimited Access to Everything</h2>
                <p class="lead mb-4">Get unlimited access to our entire catalog with a monthly or yearly subscription. Cancel anytime.</p>
                <ul class="list-unstyled">
                    <li class="mb-2"><i class="fas fa-check me-2"></i>Access to all premium tracks</li>
                    <li class="mb-2"><i class="fas fa-check me-2"></i>New releases added weekly</li>
                    <li class="mb-2"><i class="fas fa-check me-2"></i>High-quality audio downloads</li>
                    <li class="mb-2"><i class="fas fa-check me-2"></i>Cancel anytime</li>
                </ul>
            </div>
            <div class="col-lg-4 text-center">
                <div class="subscription-pricing">
                    <div class="pricing-card bg-white text-dark rounded p-4 mb-3">
                        <h4>Monthly Plan</h4>
                        <div class="h2 text-primary">$19.99<small class="text-muted">/month</small></div>
                        <button class="btn btn-primary">Start Free Trial</button>
                    </div>
                    <div class="pricing-card bg-white text-dark rounded p-4">
                        <h4>Yearly Plan</h4>
                        <div class="h2 text-success">$199.99<small class="text-muted">/year</small></div>
                        <small class="text-success">Save $39.89!</small><br>
                        <button class="btn btn-success">Get Yearly Plan</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- About Section -->
<section id="about" class="py-5">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <h2 class="display-5 fw-bold mb-4">About Meditative Brains</h2>
                <p class="lead">We're dedicated to creating premium audio content that enhances your mental wellness and personal development journey.</p>
                <p>Our expertly crafted TTS affirmations, sleep aid music, and healing frequencies are designed using the latest research in neuroscience and sound therapy. Each track is carefully produced to provide maximum therapeutic benefit.</p>
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
    // Implementation for add to cart
    fetch('/cart/add', {
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
        if (data.success) {
            // Show success message
            const toast = document.createElement('div');
            toast.className = 'toast-notification';
            toast.innerHTML = 'Product added to cart!';
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 3000);
        }
    });
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
