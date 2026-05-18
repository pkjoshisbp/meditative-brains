@php
    $p = $product;
    $isIndia = session('user_currency') === 'INR';
    $pricing = $p->getStudentPriceData(auth()->user());
    $hasDiscount = $pricing['student_applied'] || $p->hasDiscount();
    $priceInr = $pricing['base_inr'];
    $salePriceInr = $pricing['final_inr'];
@endphp

<div class="container py-4" style="max-width: 1200px;">
    @if (session()->has('message'))
        <div class="alert alert-success alert-dismissible fade show d-flex align-items-center gap-3" role="alert">
            <i class="fas fa-check-circle fa-lg"></i>
            <span class="flex-grow-1">{{ session('message') }}</span>
            <a href="{{ route('cart') }}" class="btn btn-sm btn-success">
                <i class="fas fa-shopping-cart me-1"></i>View Cart &rarr;
            </a>
            <button type="button" class="btn-close ms-2" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card shadow-sm">
                <div class="position-relative">
                    @if($p->productImageUrl('cover'))
                        <img src="{{ $p->productImageUrl('cover') }}" class="card-img-top" alt="{{ $p->name }}" style="height: 420px; object-fit: cover;">
                    @else
                        @php
                            $cn = strtolower($p->category->name ?? $p->category ?? '');
                            if (str_contains($cn, 'confidence') || str_contains($cn, 'hypnosis')) {
                                $p_img = 'confidence.jpg';
                            } elseif (str_contains($cn, 'relax') || str_contains($cn, 'bliss') || str_contains($cn, 'sleep')) {
                                $p_img = 'relaxation.jpg';
                            } elseif (str_contains($cn, 'motivat') || str_contains($cn, 'inspir') || str_contains($cn, 'quot')) {
                                $p_img = 'motivation.jpg';
                            } elseif (str_contains($cn, 'happin') || str_contains($cn, 'positive') || str_contains($cn, 'attitude')) {
                                $p_img = 'happiness.jpg';
                            } elseif (str_contains($cn, 'goal') || str_contains($cn, 'achiev') || str_contains($cn, 'time') || str_contains($cn, 'manage')) {
                                $p_img = 'goals.jpg';
                            } elseif (str_contains($cn, 'resilien') || str_contains($cn, 'failure')) {
                                $p_img = 'resilience.jpg';
                            } elseif (str_contains($cn, 'smok') || str_contains($cn, 'quit')) {
                                $p_img = 'quit-smoking.jpg';
                            } elseif (str_contains($cn, 'meditat')) {
                                $p_img = 'meditation.jpg';
                            } else {
                                $p_img = 'wellness.jpg';
                            }
                        @endphp
                        <img src="{{ asset('images/categories/' . $p_img) }}" class="card-img-top" alt="{{ $p->name }}" style="height: 420px; object-fit: cover;">
                    @endif
                </div>
                <div class="card-body">
                    <div class="mb-2 text-muted small">{{ $p->category->name ?? 'Audio Product' }}</div>
                    <h1 class="h2 mb-3">{{ $p->name }}</h1>
                    @if($p->short_description)
                        <p class="lead text-muted">{{ $p->short_description }}</p>
                    @endif

                    @if($p->description)
                        <div class="mb-4">{!! nl2br(e($p->description)) !!}</div>
                    @endif

                    @if($p->linkedAudiobook)
                        <div class="alert alert-info mb-0">
                            <strong>Linked audiobook:</strong> {{ $p->linkedAudiobook->adminSelectionLabel() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <div class="mb-3">
                        @if($isIndia)
                            @if($hasDiscount)
                                <div class="h2 text-success mb-0">&#8377;{{ number_format($salePriceInr, 0) }}</div>
                                <div class="text-muted text-decoration-line-through">&#8377;{{ number_format($priceInr, 0) }}</div>
                            @else
                                <div class="h2 mb-0">&#8377;{{ number_format($priceInr, 0) }}</div>
                            @endif
                        @else
                            @if($hasDiscount)
                                <div class="h2 text-success mb-0">${{ number_format($pricing['final_usd'], 2) }}</div>
                                <div class="text-muted text-decoration-line-through">${{ number_format($pricing['base_usd'], 2) }}</div>
                            @else
                                <div class="h2 mb-0">${{ number_format($pricing['final_usd'], 2) }}</div>
                            @endif
                        @endif
                        @if($pricing['student_applied'])
                            <div class="small text-success mt-2">Student pricing applied to this product.</div>
                        @elseif($pricing['student_available'])
                            <div class="small text-info mt-2">
                                <i class="fas fa-user-graduate me-1"></i>Student pricing available after verification.
                            </div>
                        @endif
                    </div>

                    @if($pricing['student_available'] && !$pricing['student_applied'])
                        <div class="alert alert-info py-2 small mb-3">
                            Eligible students can unlock special pricing from
                            @auth
                                <a href="{{ route('account.profile') }}" class="alert-link">their account profile</a>.
                            @else
                                <a href="{{ route('login') }}" class="alert-link">their account after login</a>.
                            @endauth
                        </div>
                    @endif

                    <p class="small text-muted mb-3">
                        <i class="fas fa-clock"></i> {{ $p->preview_duration }}s preview setting
                    </p>

                    <div class="d-grid gap-2">
                        <button class="btn btn-outline-primary" onclick="playPreview({{ $p->id }})">
                            <i class="fas fa-play"></i> Preview
                        </button>
                        <button wire:click="addToCart" class="btn btn-primary">
                            <i class="fas fa-cart-plus"></i> Add to Cart
                        </button>
                    </div>

                    @if($p->pdf_file_url)
                        <div class="small text-muted mt-3">
                            <i class="fas fa-file-pdf text-danger"></i> PDF is available after purchase.
                        </div>
                    @endif
                </div>
            </div>

            @if($p->audio_features)
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h2 class="h6 mb-3">Audio Features</h2>
                        <div class="d-flex flex-wrap gap-2">
                            @foreach($p->audio_features as $feature)
                                <span class="badge bg-secondary">{{ ucfirst(str_replace('_', ' ', $feature)) }}</span>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>

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
            body: JSON.stringify({ product_id: productId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.preview_url) {
                document.getElementById('audioPlayerContainer').innerHTML = `
                    <audio controls autoplay style="width: 100%;">
                        <source src="${data.preview_url}" type="audio/mpeg">
                        Your browser does not support the audio element.
                    </audio>
                    <p class="mt-2 text-muted">Preview Duration: ${data.duration ?? 'Custom'} seconds</p>
                `;
            } else {
                document.getElementById('audioPlayerContainer').innerHTML = '<p class="text-danger">Preview not available</p>';
            }
        })
        .catch(() => {
            document.getElementById('audioPlayerContainer').innerHTML = '<p class="text-danger">Error loading preview</p>';
        });
    }
    </script>
    @endpush
</div>