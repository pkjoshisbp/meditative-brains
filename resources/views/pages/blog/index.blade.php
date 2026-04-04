@extends('layouts.app-frontend')

@section('title', 'Blog — Mental Wellness Insights | Mental Fitness Store')
@section('description', 'Explore expert articles on binaural beats, affirmations, sleep music, meditation, solfeggio frequencies, and nature sounds to strengthen your mental wellness journey.')
@section('keywords', 'mental wellness blog, binaural beats guide, affirmations science, sleep music, meditation tips, solfeggio frequencies, nature sounds therapy')

@section('content')

<!-- Hero -->
<section class="py-5 text-white" style="background:linear-gradient(135deg,#0c4a6e 0%,#065f46 100%);min-height:280px;display:flex;align-items:center;">
    <div class="container text-center">
        <span class="badge bg-success mb-3 px-3 py-2">MENTAL WELLNESS BLOG</span>
        <h1 class="display-5 fw-bold mb-3">Insights for a Stronger Mind</h1>
        <p class="lead col-lg-6 mx-auto opacity-80 mb-0">
            Evidence-based articles on binaural beats, affirmations, sleep science, meditation, and sound therapy
            to support your mental fitness journey.
        </p>
    </div>
</section>

<!-- Posts Grid -->
<section class="py-5 bg-light">
    <div class="container">
        <!-- Featured post -->
        @php $featured = $posts[0]; @endphp
        <div class="row g-4 mb-5">
            <div class="col-lg-8">
                <div class="card border-0 shadow h-100 overflow-hidden">
                    <div class="row g-0 h-100">
                        <div class="col-md-5">
                            <img src="{{ asset('images/blog/' . $featured['image']) }}"
                                 alt="{{ $featured['image_alt'] }}"
                                 class="img-fluid h-100 w-100"
                                 style="object-fit:cover;min-height:280px;"
                                 onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                            <div class="d-none align-items-center justify-content-center bg-primary bg-opacity-10 h-100" style="min-height:280px;">
                                <i class="fas fa-headphones fa-4x text-primary opacity-50"></i>
                            </div>
                        </div>
                        <div class="col-md-7">
                            <div class="card-body p-4 d-flex flex-column h-100">
                                <div class="mb-2">
                                    <span class="badge bg-{{ $featured['category_color'] }} me-2">{{ $featured['category'] }}</span>
                                    <span class="badge bg-warning text-dark">Featured</span>
                                </div>
                                <h2 class="h4 fw-bold mb-3">
                                    <a href="{{ route('blog.show', $featured['slug']) }}" class="text-decoration-none text-dark stretched-link">
                                        {{ $featured['title'] }}
                                    </a>
                                </h2>
                                <p class="text-muted mb-4 flex-grow-1">{{ $featured['excerpt'] }}</p>
                                <div class="d-flex align-items-center text-muted small mt-auto">
                                    <i class="fas fa-user-circle me-2"></i>{{ $featured['author'] }}
                                    <span class="mx-2">·</span>
                                    <i class="fas fa-calendar me-1"></i>{{ $featured['date'] }}
                                    <span class="mx-2">·</span>
                                    <i class="fas fa-clock me-1"></i>{{ $featured['read_time'] }} read
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="d-flex flex-column gap-3 h-100">
                    @foreach(array_slice($posts, 1, 2) as $post)
                    <div class="card border-0 shadow flex-grow-1 overflow-hidden">
                        <div class="row g-0">
                            <div class="col-4">
                                <img src="{{ asset('images/blog/' . $post['image']) }}"
                                     alt="{{ $post['image_alt'] }}"
                                     class="img-fluid h-100 w-100"
                                     style="object-fit:cover;"
                                     onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                                <div class="d-none align-items-center justify-content-center bg-light h-100">
                                    <i class="fas fa-music text-muted"></i>
                                </div>
                            </div>
                            <div class="col-8">
                                <div class="card-body p-3">
                                    <span class="badge bg-{{ $post['category_color'] }} mb-1 small">{{ $post['category'] }}</span>
                                    <h6 class="fw-bold mb-1">
                                        <a href="{{ route('blog.show', $post['slug']) }}" class="text-decoration-none text-dark stretched-link">
                                            {{ $post['title'] }}
                                        </a>
                                    </h6>
                                    <p class="text-muted small mb-0" style="font-size:0.78rem;">
                                        <i class="fas fa-clock me-1"></i>{{ $post['read_time'] }} · {{ $post['date'] }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- All posts -->
        <h3 class="fw-bold mb-4">All Articles</h3>
        <div class="row g-4">
            @foreach($posts as $post)
            <div class="col-md-6 col-lg-4">
                <div class="card border-0 shadow-sm h-100 overflow-hidden">
                    <div class="position-relative" style="height:200px;overflow:hidden;">
                        <img src="{{ asset('images/blog/' . $post['image']) }}"
                             alt="{{ $post['image_alt'] }}"
                             class="img-fluid w-100 h-100"
                             style="object-fit:cover;transition:transform 0.3s;"
                             onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                        <div class="d-none align-items-center justify-content-center bg-primary bg-opacity-10 w-100 h-100">
                            <i class="fas fa-headphones fa-3x text-primary opacity-50"></i>
                        </div>
                        <div class="position-absolute top-0 start-0 m-2">
                            <span class="badge bg-{{ $post['category_color'] }}">{{ $post['category'] }}</span>
                        </div>
                    </div>
                    <div class="card-body d-flex flex-column p-4">
                        <h5 class="fw-bold mb-2">
                            <a href="{{ route('blog.show', $post['slug']) }}" class="text-decoration-none text-dark stretched-link">
                                {{ $post['title'] }}
                            </a>
                        </h5>
                        <p class="text-muted small mb-3 flex-grow-1">{{ Str::limit($post['excerpt'], 120) }}</p>
                        <div class="d-flex align-items-center text-muted small mt-auto">
                            <i class="fas fa-clock me-1"></i>{{ $post['read_time'] }} read
                            <span class="ms-auto">{{ $post['date'] }}</span>
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>

<!-- CTA -->
<section class="py-5 bg-primary text-white text-center">
    <div class="container">
        <h2 class="fw-bold mb-3">Ready to Start Your Mental Fitness Journey?</h2>
        <p class="lead opacity-75 mb-4">Explore our premium audio library — affirmations, binaural beats, sleep music, and more.</p>
        <div class="d-flex gap-3 justify-content-center flex-wrap">
            <a href="{{ route('products') }}" class="btn btn-light btn-lg fw-semibold">
                <i class="fas fa-music me-2"></i>Browse Audio
            </a>
            <a href="{{ route('subscription') }}" class="btn btn-outline-light btn-lg">
                <i class="fas fa-star me-2"></i>View Plans
            </a>
        </div>
    </div>
</section>

@endsection
