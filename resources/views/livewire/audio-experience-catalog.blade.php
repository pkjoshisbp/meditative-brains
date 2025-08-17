<div>
    <div class="py-5 bg-light border-bottom mb-4">
        <div class="container">
            <h1 class="display-5 fw-bold mb-2">Meditative Minds Audio</h1>
            <p class="lead text-muted mb-0">Curated audio experiences for motivation, calm, focus, and transformation.</p>
        </div>
    </div>
    <div class="container pb-5">
        <div class="row g-4">
            <div class="col-lg-3">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body">
                        <h2 class="h6 fw-semibold mb-3">Filters</h2>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Search</label>
                            <input type="text" class="form-control form-control-sm" placeholder="Search..." wire:model.debounce.400ms="search">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Category</label>
                            <select class="form-select form-select-sm" wire:model="category">
                                <option value="">All Categories</option>
                                @foreach($categories as $c)
                                    <option value="{{ $c->name }}">{{ $c->display_name ?? $c->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Tag Contains</label>
                            <input type="text" class="form-control form-control-sm" placeholder="e.g. focus" wire:model.debounce.500ms="tag">
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label small">Min $</label>
                                <input type="number" class="form-control form-control-sm" wire:model.lazy="minPrice" min="0">
                            </div>
                            <div class="col-6">
                                <label class="form-label small">Max $</label>
                                <input type="number" class="form-control form-control-sm" wire:model.lazy="maxPrice" min="0">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Sort By</label>
                            <select class="form-select form-select-sm" wire:model="sortBy">
                                <option value="featured">Featured</option>
                                <option value="newest">Newest</option>
                                <option value="name">Name (A-Z)</option>
                                <option value="price_low">Price: Low to High</option>
                                <option value="price_high">Price: High to Low</option>
                            </select>
                        </div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-sm btn-outline-secondary flex-grow-1" wire:click="clearFilters">Clear</button>
                        </div>
                    </div>
                </div>
                @if($featured && count($featured))
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <h2 class="h6 fw-semibold mb-3">Featured</h2>
                            @foreach($featured as $p)
                                <div class="mb-3 pb-3 border-bottom small">
                                    <a href="{{ route('audio.detail', $p->slug) }}" class="fw-semibold text-decoration-none">{{ $p->name }}</a>
                                    <div class="text-muted">${{ number_format(($p->sale_price && $p->sale_price < $p->price)?$p->sale_price:$p->price,2) }}</div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
            <div class="col-lg-9">
                <section class="mb-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h2 class="h5 fw-semibold mb-0">All Audio Experiences</h2>
                        <span class="text-muted small">{{ $products->total() }} items</span>
                    </div>
                    <div class="row g-4">
                        @forelse($products as $p)
                            <div class="col-sm-6 col-lg-4">
                                <div class="card h-100 border-0 shadow-sm">
                                    <a href="{{ route('audio.detail', $p->slug) }}" class="text-decoration-none text-dark">
                                        <div class="ratio ratio-16x9 bg-light">
                                            @if($p->cover_image_url || $p->cover_image_path)
                                                <img src="{{ $p->cover_image_url ?? asset($p->cover_image_path) }}" alt="{{ $p->name }}" class="w-100 h-100 object-fit-cover rounded-top">
                                            @else
                                                <div class="d-flex align-items-center justify-content-center w-100 h-100 bg-secondary-subtle">
                                                    <i class="fas fa-headphones fa-2x text-secondary"></i>
                                                </div>
                                            @endif
                                        </div>
                                        <div class="card-body d-flex flex-column">
                                            <h3 class="h6 fw-semibold mb-1">{{ $p->name }}</h3>
                                            <p class="small text-muted flex-grow-1">{{ $p->short_description ?? Str::limit($p->description, 80) }}</p>
                                            <div class="mt-2 small fw-medium">
                                                @if($p->sale_price && $p->sale_price < $p->price)
                                                    <span class="text-muted text-decoration-line-through me-1">${{ number_format($p->price,2) }}</span>
                                                    <span class="text-success fw-semibold">${{ number_format($p->sale_price,2) }}</span>
                                                @else
                                                    <span class="fw-semibold">${{ number_format($p->price,2) }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    </a>
                                </div>
                            </div>
                        @empty
                            <div class="col-12">
                                <div class="alert alert-light border">No audio experiences match your filters.</div>
                            </div>
                        @endforelse
                    </div>
                    <div class="mt-4">{{ $products->links() }}</div>
                </section>
            </div>
        </div>
    </div>
</div>
