<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'Meditative Brains - Meditative Minds Audio & Sleep Aid Music' }}</title>
    
    <!-- SEO Meta Tags -->
    <meta name="description" content="{{ $description ?? 'Discover premium Meditative Minds audio experiences: affirmations, sleep aid music, meditation tracks, and healing frequencies for personal development and wellness.' }}">
    <meta name="keywords" content="{{ $keywords ?? 'TTS affirmations, sleep music, meditation, binaural beats, solfeggio frequencies, nature sounds' }}">
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:title" content="{{ $title ?? 'Meditative Brains' }}">
    <meta property="og:description" content="{{ $description ?? 'Premium meditation and wellness audio content' }}">
    <meta property="og:image" content="{{ asset('images/og-image.jpg') }}">

    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="{{ url()->current() }}">
    <meta property="twitter:title" content="{{ $title ?? 'Meditative Brains' }}">
    <meta property="twitter:description" content="{{ $description ?? 'Premium meditation and wellness audio content' }}">
    <meta property="twitter:image" content="{{ asset('images/og-image.jpg') }}">

    <!-- Fonts -->
    <link rel="dns-prefetch" href="//fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,600" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    @vite(['resources/sass/app.scss', 'resources/js/app.js'])
    @livewireStyles
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="{{ route('home') }}">
                <div class="logo-container me-3">
                    <i class="fas fa-brain fa-2x text-primary"></i>
                </div>
                <div class="d-flex flex-column justify-content-center">
                    <span class="fw-bold fs-4 lh-1">Meditative Brains</span>
                    <small class="text-muted lh-1">Premium Wellness Audio</small>
                </div>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('home') ? 'active' : '' }}" href="{{ route('home') }}">
                            <i class="fas fa-home me-1"></i>Home
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('products') ? 'active' : '' }}" href="{{ route('products') }}">
                            <i class="fas fa-music me-1"></i>Browse Music
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->is('mind-audio') ? 'active' : '' }}" href="{{ route('audio.catalog') }}">
                            <i class="fas fa-headphones me-1"></i>Meditative Minds Audio
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-list me-1"></i>Categories
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="{{ route('audio.catalog') }}">
                                <i class="fas fa-microphone-alt me-2 text-primary"></i>Meditative Minds Audio
                            </a></li>
                            <li><a class="dropdown-item" href="{{ route('products', ['categoryId' => 2]) }}">
                                <i class="fas fa-moon me-2 text-info"></i>Sleep Aid Music
                            </a></li>
                            <li><a class="dropdown-item" href="{{ route('products', ['categoryId' => 3]) }}">
                                <i class="fas fa-leaf me-2 text-success"></i>Meditation Music
                            </a></li>
                            <li><a class="dropdown-item" href="{{ route('products', ['categoryId' => 4]) }}">
                                <i class="fas fa-wave-square me-2 text-warning"></i>Binaural Beats
                            </a></li>
                            <li><a class="dropdown-item" href="{{ route('products', ['categoryId' => 5]) }}">
                                <i class="fas fa-tree me-2 text-success"></i>Nature Sounds
                            </a></li>
                            <li><a class="dropdown-item" href="{{ route('products', ['categoryId' => 6]) }}">
                                <i class="fas fa-yin-yang me-2 text-purple"></i>Solfeggio Frequencies
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="{{ route('products') }}">
                                <i class="fas fa-th-large me-2"></i>View All Categories
                            </a></li>
                        </ul>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#subscription">
                            <i class="fas fa-star me-1"></i>Subscription
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#about">
                            <i class="fas fa-info-circle me-1"></i>About
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#contact">
                            <i class="fas fa-envelope me-1"></i>Contact
                        </a>
                    </li>
                </ul>
                
                <ul class="navbar-nav">
                    @guest
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('login') }}">
                                <i class="fas fa-sign-in-alt me-1"></i>Login
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('register') }}">
                                <i class="fas fa-user-plus me-1"></i>Register
                            </a>
                        </li>
                    @else
                        <li class="nav-item">
                            <a class="nav-link position-relative" href="#cart">
                                <i class="fas fa-shopping-cart me-1"></i>Cart
                                @php
                                    $cartCount = auth()->user()->cartItems()->count();
                                @endphp
                                @if($cartCount > 0)
                                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                        {{ $cartCount }}
                                    </span>
                                @endif
                            </a>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user-circle me-2"></i>{{ Auth::user()->name }}
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="{{ route('home') }}">
                                    <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                                </a></li>
                                <li><a class="dropdown-item" href="#my-purchases">
                                    <i class="fas fa-download me-2"></i>My Downloads
                                </a></li>
                                <li><a class="dropdown-item" href="#subscription-manage">
                                    <i class="fas fa-star me-2"></i>My Subscription
                                </a></li>
                                @if(Auth::user()->email === 'admin@meditative-brains.com')
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="{{ route('admin.dashboard') }}">
                                        <i class="fas fa-cog me-2"></i>Admin Panel
                                    </a></li>
                                @endif
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a class="dropdown-item" href="{{ route('logout') }}"
                                       onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                                        <i class="fas fa-sign-out-alt me-2"></i>Logout
                                    </a>
                                    <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                                        @csrf
                                    </form>
                                </li>
                            </ul>
                        </li>
                    @endguest
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main>
        {{ $slot }}
    </main>

    <!-- Footer -->
    <footer class="bg-dark text-light py-5 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-lg-4 mb-4">
                    <div class="d-flex align-items-center mb-3">
                        <i class="fas fa-brain fa-2x text-primary me-3"></i>
                        <div>
                            <h5 class="mb-0">Meditative Brains</h5>
                            <small class="text-muted">Premium Wellness Audio</small>
                        </div>
                    </div>
                    <p class="mb-3">Transform your life with our premium collection of TTS affirmations, sleep aid music, meditation tracks, and healing frequencies.</p>
                    <div class="d-flex gap-3">
                        <a href="#" class="text-light fs-4"><i class="fab fa-facebook"></i></a>
                        <a href="#" class="text-light fs-4"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="text-light fs-4"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="text-light fs-4"><i class="fab fa-youtube"></i></a>
                        <a href="#" class="text-light fs-4"><i class="fab fa-spotify"></i></a>
                    </div>
                </div>
                
                <div class="col-lg-2 col-md-4 mb-4">
                    <h6 class="text-primary mb-3">Categories</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="{{ route('products', ['categoryId' => 1]) }}" class="text-light text-decoration-none">TTS Affirmations</a></li>
                        <li class="mb-2"><a href="{{ route('products', ['categoryId' => 2]) }}" class="text-light text-decoration-none">Sleep Aid Music</a></li>
                        <li class="mb-2"><a href="{{ route('products', ['categoryId' => 3]) }}" class="text-light text-decoration-none">Meditation Music</a></li>
                        <li class="mb-2"><a href="{{ route('products', ['categoryId' => 4]) }}" class="text-light text-decoration-none">Binaural Beats</a></li>
                        <li class="mb-2"><a href="{{ route('products', ['categoryId' => 5]) }}" class="text-light text-decoration-none">Nature Sounds</a></li>
                        <li class="mb-2"><a href="{{ route('products', ['categoryId' => 6]) }}" class="text-light text-decoration-none">Solfeggio Frequencies</a></li>
                    </ul>
                </div>
                
                <div class="col-lg-2 col-md-4 mb-4">
                    <h6 class="text-primary mb-3">Account</h6>
                    <ul class="list-unstyled">
                        @guest
                            <li class="mb-2"><a href="{{ route('login') }}" class="text-light text-decoration-none">Login</a></li>
                            <li class="mb-2"><a href="{{ route('register') }}" class="text-light text-decoration-none">Register</a></li>
                        @else
                            <li class="mb-2"><a href="{{ route('home') }}" class="text-light text-decoration-none">My Dashboard</a></li>
                            <li class="mb-2"><a href="#my-purchases" class="text-light text-decoration-none">My Downloads</a></li>
                        @endguest
                        <li class="mb-2"><a href="#subscription" class="text-light text-decoration-none">Subscription Plans</a></li>
                        <li class="mb-2"><a href="#support" class="text-light text-decoration-none">Support</a></li>
                    </ul>
                </div>
                
                <div class="col-lg-2 col-md-4 mb-4">
                    <h6 class="text-primary mb-3">Company</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="#about" class="text-light text-decoration-none">About Us</a></li>
                        <li class="mb-2"><a href="#contact" class="text-light text-decoration-none">Contact</a></li>
                        <li class="mb-2"><a href="#privacy" class="text-light text-decoration-none">Privacy Policy</a></li>
                        <li class="mb-2"><a href="#terms" class="text-light text-decoration-none">Terms of Service</a></li>
                        <li class="mb-2"><a href="#refund" class="text-light text-decoration-none">Refund Policy</a></li>
                    </ul>
                </div>
                
                <div class="col-lg-2">
                    <h6 class="text-primary mb-3" id="contact">Contact Info</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2">
                            <i class="fas fa-envelope me-2"></i>
                            <a href="mailto:support@meditative-brains.com" class="text-light text-decoration-none">support@meditative-brains.com</a>
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-phone me-2"></i>
                            <span class="text-light">+1 (555) 123-4567</span>
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-clock me-2"></i>
                            <span class="text-light">24/7 Support</span>
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-globe me-2"></i>
                            <span class="text-light">meditative-brains.com</span>
                        </li>
                    </ul>
                </div>
            </div>
            
            <hr class="my-4">
            
            <div class="row align-items-center">
                <div class="col-md-6">
                    <p class="mb-0">© {{ date('Y') }} Meditative Brains. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <div class="d-flex justify-content-md-end gap-3">
                        <i class="fab fa-cc-visa fa-2x text-muted"></i>
                        <i class="fab fa-cc-mastercard fa-2x text-muted"></i>
                        <i class="fab fa-cc-paypal fa-2x text-muted"></i>
                        <i class="fab fa-cc-stripe fa-2x text-muted"></i>
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    @livewireScripts
    @stack('scripts')
</body>
</html>
