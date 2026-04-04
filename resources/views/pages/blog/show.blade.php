@extends('layouts.app-frontend')

@section('title', $post['title'] . ' — Mental Fitness Store Blog')
@section('description', $post['excerpt'])
@section('keywords', $post['keywords'])

@section('content')

<!-- Breadcrumb + Hero -->
<section class="py-4 bg-light border-bottom">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 small">
                <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('blog') }}">Blog</a></li>
                <li class="breadcrumb-item active text-truncate" style="max-width:260px;">{{ $post['title'] }}</li>
            </ol>
        </nav>
    </div>
</section>

<div class="container py-5">
    <div class="row g-5">
        <!-- Article -->
        <div class="col-lg-8">
            <article itemscope itemtype="https://schema.org/BlogPosting">
                <header class="mb-4">
                    <span class="badge bg-{{ $post['category_color'] }} mb-3">{{ $post['category'] }}</span>
                    <h1 class="display-6 fw-bold mb-3" itemprop="headline">{{ $post['title'] }}</h1>
                    <div class="d-flex align-items-center text-muted small gap-3 flex-wrap mb-4">
                        <span><i class="fas fa-user-circle me-1"></i>{{ $post['author'] }}</span>
                        <span><i class="fas fa-calendar me-1"></i><time itemprop="datePublished">{{ $post['date'] }}</time></span>
                        <span><i class="fas fa-clock me-1"></i>{{ $post['read_time'] }} read</span>
                    </div>
                </header>

                <!-- Hero image -->
                <div class="mb-4 rounded-3 overflow-hidden" style="max-height:380px;">
                    <img src="{{ asset('images/blog/' . $post['image']) }}"
                         alt="{{ $post['image_alt'] }}"
                         class="img-fluid w-100"
                         style="object-fit:cover;max-height:380px;"
                         onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                    <div class="d-none align-items-center justify-content-center bg-primary bg-opacity-10 rounded-3 py-5">
                        <i class="fas fa-headphones fa-5x text-primary opacity-30"></i>
                    </div>
                </div>

                <!-- Lead excerpt -->
                <p class="lead text-muted mb-4 border-start border-4 border-primary ps-4 py-1">{{ $post['excerpt'] }}</p>

                <!-- Content blocks -->
                <div itemprop="articleBody">
                    @foreach($post['content'] as $block)
                        @if($block['type'] === 'p')
                            <p class="mb-4">{!! $block['text'] !!}</p>
                        @elseif($block['type'] === 'h2')
                            <h2 class="h4 fw-bold mt-5 mb-3">{{ $block['text'] }}</h2>
                        @elseif($block['type'] === 'h3')
                            <h3 class="h5 fw-bold mt-4 mb-3">{{ $block['text'] }}</h3>
                        @elseif($block['type'] === 'ul')
                            <ul class="mb-4">
                                @foreach($block['items'] as $item)
                                    <li class="mb-2">{!! $item !!}</li>
                                @endforeach
                            </ul>
                        @elseif($block['type'] === 'ol')
                            <ol class="mb-4">
                                @foreach($block['items'] as $item)
                                    <li class="mb-2">{!! $item !!}</li>
                                @endforeach
                            </ol>
                        @endif
                    @endforeach
                </div>

                <!-- Tags -->
                <div class="mt-5 pt-4 border-top">
                    <span class="text-muted small me-2"><i class="fas fa-tags me-1"></i>Topics:</span>
                    @foreach(explode(', ', $post['keywords']) as $kw)
                        <span class="badge bg-light text-muted border me-1 mb-1">{{ trim($kw) }}</span>
                    @endforeach
                </div>

                <!-- Share -->
                <div class="mt-4 d-flex align-items-center gap-3 flex-wrap">
                    <span class="text-muted small fw-semibold">Share:</span>
                    <a href="https://twitter.com/intent/tweet?text={{ urlencode($post['title']) }}&url={{ urlencode(url()->current()) }}"
                       target="_blank" rel="noopener noreferrer"
                       class="btn btn-sm btn-outline-secondary">
                        <i class="fab fa-twitter me-1"></i>Twitter
                    </a>
                    <a href="https://wa.me/?text={{ urlencode($post['title'] . ' ' . url()->current()) }}"
                       target="_blank" rel="noopener noreferrer"
                       class="btn btn-sm btn-outline-secondary">
                        <i class="fab fa-whatsapp me-1"></i>WhatsApp
                    </a>
                </div>
            </article>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- CTA card -->
            <div class="card border-0 shadow mb-4" style="background:linear-gradient(135deg,#064e3b,#0c4a6e);">
                <div class="card-body p-4 text-white text-center">
                    <i class="fas fa-headphones fa-3x mb-3 opacity-75"></i>
                    <h5 class="fw-bold">Ready to Train Your Mind?</h5>
                    <p class="small opacity-75 mb-3">Explore our premium mental wellness audio library with 500+ tracks.</p>
                    <a href="{{ route('products') }}" class="btn btn-light btn-sm w-100 mb-2">
                        <i class="fas fa-music me-1"></i>Browse Audio
                    </a>
                    <a href="{{ route('subscription') }}" class="btn btn-outline-light btn-sm w-100">
                        <i class="fas fa-star me-1"></i>View Plans
                    </a>
                </div>
            </div>

            <!-- Related posts -->
            @if($related->count())
            <div class="card border-0 shadow">
                <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
                    <h6 class="fw-bold mb-0"><i class="fas fa-bookmark me-2 text-primary"></i>More Articles</h6>
                </div>
                <div class="card-body p-0">
                    @foreach($related as $r)
                    <a href="{{ route('blog.show', $r['slug']) }}" class="d-flex align-items-start gap-3 p-3 text-decoration-none text-dark border-bottom hover-bg-light">
                        <div class="flex-shrink-0 rounded overflow-hidden" style="width:60px;height:60px;">
                            <img src="{{ asset('images/blog/' . $r['image']) }}"
                                 alt="{{ $r['image_alt'] }}"
                                 class="img-fluid w-100 h-100"
                                 style="object-fit:cover;"
                                 onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%2260%22 height=%2260%22><rect fill=%22%23e9ecef%22 width=%2260%22 height=%2260%22/></svg>'">
                        </div>
                        <div>
                            <span class="badge bg-{{ $r['category_color'] }} mb-1" style="font-size:0.6rem;">{{ $r['category'] }}</span>
                            <p class="mb-1 fw-semibold small" style="line-height:1.3;">{{ Str::limit($r['title'], 65) }}</p>
                            <p class="text-muted mb-0" style="font-size:0.73rem;"><i class="fas fa-clock me-1"></i>{{ $r['read_time'] }}</p>
                        </div>
                    </a>
                    @endforeach
                </div>
            </div>
            @endif
        </div>
    </div>
</div>

@endsection
