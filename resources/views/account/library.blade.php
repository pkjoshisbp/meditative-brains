@extends('account.layout')
@section('title', 'My Library')

@section('account-content')
<div>
    <h2 class="fw-bold mb-1">My Library</h2>
    <p class="text-muted mb-4">Your purchased and subscribed audio and ebook content.</p>

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
    @if($libraryProducts->isEmpty() && $libraryTtsProducts->isEmpty() && !$activeSubscription)
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
    @if($readingProducts->isNotEmpty() || $readingTtsProducts->isNotEmpty())
    <div class="mb-4">
        <div class="d-flex align-items-center justify-content-between mb-2">
            <h3 class="h5 fw-bold mb-0">Books & Reading</h3>
            <span class="small text-muted">PDF and HTML editions</span>
        </div>
        <div class="row g-3">
            @foreach($readingProducts as $product)
            <div class="col-sm-6 col-xl-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body">
                        <h6 class="fw-bold mb-2">{{ $product->name }}</h6>
                        <div class="d-flex gap-2 flex-wrap">
                            @if($product->pdf_file_path)
                                <a href="{{ route('account.library.products.pdf', $product) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-file-pdf me-1"></i>Download PDF
                                </a>
                            @endif
                            @if($product->html_book_path || $product->html_book_url)
                                <a href="{{ route('account.library.products.read', $product) }}" class="btn btn-sm btn-outline-secondary">
                                    <i class="fas fa-book-reader me-1"></i>Read HTML
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            @endforeach

            @foreach($readingTtsProducts as $product)
            <div class="col-sm-6 col-xl-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body">
                        <h6 class="fw-bold mb-2">{{ $product->name }}</h6>
                        <div class="d-flex gap-2 flex-wrap">
                            @if($product->pdf_file_path)
                                <a href="{{ route('account.library.tts-products.pdf', $product) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-file-pdf me-1"></i>Download PDF
                                </a>
                            @endif
                            @if($product->html_book_path || $product->html_book_url)
                                <a href="{{ route('account.library.tts-products.read', $product) }}" class="btn btn-sm btn-outline-secondary">
                                    <i class="fas fa-book-reader me-1"></i>Read HTML
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    <h3 class="h5 fw-bold mb-3">Audio & Product Library</h3>
    <div class="row g-3">
        @foreach($libraryProducts as $product)
        @php $productAudioUrl = $product->hasAudioPreviewSource() ? $product->resolvePreviewUrl() : null; @endphp
        <div class="col-sm-6 col-xl-4">
            <div class="card h-100 border-0 shadow-sm">
                @if($product->productImageUrl('cover'))
                <img src="{{ $product->productImageUrl('cover') }}"
                    class="card-img-top" alt="{{ $product->name }}"
                    style="height:160px;object-fit:cover;">
                @else
                <div class="d-flex align-items-center justify-content-center"
                    style="height:160px;background:linear-gradient(135deg,#0f172a,#1e3a5f);">
                    <i class="fas {{ $product->isPdfOnlyBook() ? 'fa-book-open' : 'fa-headphones' }} fa-3x text-white opacity-50"></i>
                </div>
                @endif
                <div class="card-body d-flex flex-column">
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <h6 class="fw-bold mb-0">{{ $product->name }}</h6>
                        @if($product->isPdfOnlyBook())
                            <span class="badge bg-danger">PDF</span>
                        @elseif($product->isBookWithAudio())
                            <span class="badge bg-info">Book + Audio</span>
                        @else
                            <span class="badge bg-primary">Audio</span>
                        @endif
                    </div>
                    <p class="small text-muted flex-grow-1 mb-3">{{ Str::limit($product->description, 80) }}</p>
                    <div class="d-flex gap-2 flex-wrap">
                        @if(!$product->isPdfOnlyBook() || $product->linked_audiobook_id || $product->audio_path)
                            <a href="{{ route('products.show', $product->slug) }}" class="btn btn-sm btn-outline-primary flex-fill">
                                <i class="fas fa-eye me-1"></i>Details
                            </a>
                        @else
                            <a href="{{ route('products.show', $product->slug) }}" class="btn btn-sm btn-outline-secondary flex-fill">
                                <i class="fas fa-eye me-1"></i>Details
                            </a>
                        @endif
                        @if($product->pdf_file_path)
                            <a href="{{ route('account.library.products.pdf', $product) }}" class="btn btn-sm btn-outline-primary flex-fill">
                                <i class="fas fa-file-pdf me-1"></i>PDF
                            </a>
                        @endif
                        @if($product->html_book_path || $product->html_book_url)
                            <a href="{{ route('account.library.products.read', $product) }}" class="btn btn-sm btn-outline-secondary flex-fill">
                                <i class="fas fa-book-reader me-1"></i>Read
                            </a>
                        @endif
                    </div>
                    @if($productAudioUrl)
                        <audio controls preload="none" class="w-100 mt-3">
                            <source src="{{ $productAudioUrl }}" type="audio/mpeg">
                        </audio>
                    @endif
                </div>
            </div>
        </div>
        @endforeach

        @foreach($libraryTtsProducts as $product)
        @php
            $ttsAudioUrl = $product->linkedAudiobook?->resolvePreviewUrl() ?: ($product->preview_audio_url ?: null);
        @endphp
        <div class="col-sm-6 col-xl-4">
            <div class="card h-100 border-0 shadow-sm">
                @if($product->cover_image_path)
                <img src="{{ asset('storage/' . $product->cover_image_path) }}"
                    class="card-img-top" alt="{{ $product->name }}"
                    style="height:160px;object-fit:cover;">
                @else
                <div class="d-flex align-items-center justify-content-center"
                    style="height:160px;background:linear-gradient(135deg,#3a2f22,#c8a96e);">
                    <i class="fas fa-book-open fa-3x text-white opacity-50"></i>
                </div>
                @endif
                <div class="card-body d-flex flex-column">
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <h6 class="fw-bold mb-0">{{ $product->name }}</h6>
                        @if(in_array($product->product_type, ['ebook_pdf', 'ebook_bundle']))
                            <span class="badge bg-primary">eBook</span>
                        @endif
                    </div>
                    <p class="small text-muted flex-grow-1 mb-3">{{ Str::limit($product->description, 80) }}</p>
                    <div class="d-flex gap-2 flex-wrap">
                        @if($product->linked_audiobook_id)
                            <a href="{{ route('audio.detail', $product->slug) }}" class="btn btn-sm btn-outline-primary flex-fill">
                                <i class="fas fa-eye me-1"></i>Details
                            </a>
                        @elseif($product->product_type === 'audio')
                            <a href="{{ route('audio.detail', $product->slug) }}" class="btn btn-sm btn-outline-primary flex-fill">
                                <i class="fas fa-eye me-1"></i>Details
                            </a>
                        @endif
                        @if($product->pdf_file_path)
                            <a href="{{ route('account.library.tts-products.pdf', $product) }}" class="btn btn-sm btn-outline-primary flex-fill">
                                <i class="fas fa-file-pdf me-1"></i>PDF
                            </a>
                        @endif
                        @if($product->html_book_path)
                            <a href="{{ route('account.library.tts-products.read', $product) }}" class="btn btn-sm btn-outline-secondary flex-fill">
                                <i class="fas fa-book-reader me-1"></i>Read
                            </a>
                        @endif
                    </div>
                    @if($ttsAudioUrl)
                        <audio controls preload="none" class="w-100 mt-3">
                            <source src="{{ $ttsAudioUrl }}" type="audio/mpeg">
                        </audio>
                    @endif
                </div>
            </div>
        </div>
        @endforeach
    </div>
    @if($libraryProducts->isEmpty() && $libraryTtsProducts->isEmpty())
    <div class="mt-4 p-4 bg-light rounded text-center text-muted">
        <i class="fas fa-info-circle me-2"></i>
        No individual products purchased yet. Your subscription gives you access to all content — browse via the
        <a href="{{ route('products') }}">Products page</a>.
    </div>
    @endif
    @endif
</div>
@endsection
