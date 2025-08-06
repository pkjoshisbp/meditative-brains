<div class="container py-4" style="max-width: 1440px;">
    @if (session()->has('message'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('message') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row">
        <!-- Filters Sidebar -->
        <div class="col-lg-3 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Filters</h5>
                </div>
                <div class="card-body">
                    <!-- Search -->
                    <div class="mb-3">
                        <label class="form-label">Search</label>
                        <input type="text" wire:model.live="search" class="form-control" placeholder="Search products...">
                    </div>

                    <!-- Categories -->
                    <div class="mb-3">
                        <label class="form-label">Category</label>
                        <select wire:model.live="categoryId" class="form-select">
                            <option value="">All Categories</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}">
                                    {{ $category->name }} ({{ $category->active_products_count }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Audio Features -->
                    <div class="mb-3">
                        <label class="form-label">Audio Features</label>
                        @foreach($audioFeatureOptions as $key => $label)
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" wire:model.live="audioFeatures" value="{{ $key }}" id="feature_{{ $key }}">
                                <label class="form-check-label" for="feature_{{ $key }}">
                                    {{ $label }}
                                </label>
                            </div>
                        @endforeach
                    </div>

                    <!-- Sort -->
                    <div class="mb-3">
                        <label class="form-label">Sort By</label>
                        <select wire:model.live="sortBy" class="form-select">
                            <option value="featured">Featured</option>
                            <option value="newest">Newest</option>
                            <option value="popular">Most Popular</option>
                            <option value="price_low">Price: Low to High</option>
                            <option value="price_high">Price: High to Low</option>
                        </select>
                    </div>

                    <button wire:click="clearFilters" class="btn btn-outline-secondary btn-sm">Clear All Filters</button>
                </div>
            </div>
        </div>

        <!-- Products Grid -->
        <div class="col-lg-9">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>Music Catalog</h2>
                <span class="text-muted">{{ $products->total() }} products found</span>
            </div>

            <div class="row">
                @forelse($products as $product)
                    <div class="col-md-6 col-xl-4 mb-4">
                        <div class="card h-100 product-card">
                            <!-- Product Image -->
                            <div class="position-relative">
                                @if($product->getFirstMediaUrl('images'))
                                    <img src="{{ $product->getFirstMediaUrl('images', 'cover') }}" class="card-img-top" alt="{{ $product->name }}" style="height: 200px; object-fit: cover;">
                                @else
                                    <div class="card-img-top bg-light d-flex align-items-center justify-content-center" style="height: 200px;">
                                        <i class="fas fa-music fa-3x text-muted"></i>
                                    </div>
                                @endif
                                
                                @if($product->hasDiscount())
                                    <span class="badge bg-danger position-absolute top-0 end-0 m-2">
                                        -{{ $product->getDiscountPercentage() }}%
                                    </span>
                                @endif

                                @if($product->is_featured)
                                    <span class="badge bg-warning position-absolute top-0 start-0 m-2">
                                        Featured
                                    </span>
                                @endif
                            </div>

                            <div class="card-body d-flex flex-column">
                                <h6 class="card-title">{{ $product->name }}</h6>
                                <p class="card-text text-muted small mb-2">{{ $product->category->name }}</p>
                                
                                @if($product->short_description)
                                    <p class="card-text small">{{ Str::limit($product->short_description, 80) }}</p>
                                @endif

                                <!-- Audio Features -->
                                @if($product->audio_features)
                                    <div class="mb-2">
                                        @foreach($product->audio_features as $feature)
                                            <span class="badge bg-secondary me-1">{{ $audioFeatureOptions[$feature] ?? $feature }}</span>
                                        @endforeach
                                    </div>
                                @endif

                                <div class="mt-auto">
                                    <!-- Price -->
                                    <div class="mb-2">
                                        @if($product->hasDiscount())
                                            <span class="h6 text-primary">${{ number_format($product->sale_price, 2) }}</span>
                                            <span class="text-muted text-decoration-line-through ms-1">${{ number_format($product->price, 2) }}</span>
                                        @else
                                            <span class="h6 text-primary">${{ number_format($product->price, 2) }}</span>
                                        @endif
                                    </div>

                                    <!-- Preview Duration -->
                                    <p class="small text-muted mb-2">
                                        <i class="fas fa-clock"></i> {{ $product->preview_duration }}s preview
                                    </p>

                                    <!-- Action Buttons -->
                                    <div class="d-grid gap-2">
                                        <button class="btn btn-outline-primary btn-sm" onclick="playPreview({{ $product->id }})">
                                            <i class="fas fa-play"></i> Preview
                                        </button>
                                        <button wire:click="addToCart({{ $product->id }})" class="btn btn-primary btn-sm">
                                            <i class="fas fa-cart-plus"></i> Add to Cart
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-12">
                        <div class="text-center py-5">
                            <i class="fas fa-search fa-3x text-muted mb-3"></i>
                            <h4>No products found</h4>
                            <p class="text-muted">Try adjusting your search criteria or filters.</p>
                            <button wire:click="clearFilters" class="btn btn-primary">Clear Filters</button>
                        </div>
                    </div>
                @endforelse
            </div>

            <!-- Pagination -->
            <div class="d-flex justify-content-center">
                {{ $products->links() }}
            </div>
        </div>
    </div>


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


@push('scripts')
<script>
function playPreview(productId) {
    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('previewModal'));
    modal.show();
    
    // Get preview URL
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
</script>
@endpush

<style>
.product-card {
    transition: transform 0.2s;
}

.product-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}
</style>
</div>