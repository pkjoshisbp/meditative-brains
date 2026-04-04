@extends('layouts.app-frontend')

@section('title', 'About Us — Mental Fitness Store | Our Vision & Mission')
@section('description', 'Mental Fitness Store is on a mission to provide all proven tools for mental happiness in one place. Discover our story, vision, and the science behind our premium wellness audio.')

@section('content')

<!-- Hero -->
<section class="py-5 text-white" style="background:linear-gradient(135deg,#064e3b 0%,#065f46 40%,#0c4a6e 100%);min-height:380px;display:flex;align-items:center;">
    <div class="container text-center">
        <span class="badge bg-success mb-3 px-3 py-2">OUR STORY</span>
        <h1 class="display-4 fw-bold mb-3">Striving for Mental Happiness</h1>
        <p class="lead col-lg-7 mx-auto opacity-90 mb-0">
            We are dedicated to providing all proven tools to strengthen and improve mental happiness in one place —
            so you can build a stronger, calmer, and more resilient mind every day.
        </p>
    </div>
</section>

<!-- Vision & Mission -->
<section class="py-5 bg-light">
    <div class="container">
        <div class="row g-5 align-items-center">
            <div class="col-lg-6">
                <span class="text-primary fw-semibold small text-uppercase letter-spacing-wide">Our Vision</span>
                <h2 class="display-6 fw-bold mt-2 mb-4">One Place for All Mental Wellness Tools</h2>
                <p class="lead text-muted mb-4">
                    The human mind is the most powerful instrument you own. Yet most people never receive the tools
                    to train it. We believe that mental fitness is just as important as physical fitness — and that
                    the right audio, practiced consistently, can rewire your thoughts and transform your life.
                </p>
                <p class="text-muted">
                    Mental Fitness Store brings together the world's most proven sound-based mental wellness techniques:
                    positive affirmations, binaural beats, solfeggio frequencies, sleep hypnosis, guided meditation,
                    and nature soundscapes — all crafted with studio-grade quality and backed by neuroscience.
                </p>
                <div class="mt-4 d-flex gap-3 flex-wrap">
                    <a href="{{ route('products') }}" class="btn btn-primary">
                        <i class="fas fa-music me-2"></i>Explore Our Audio
                    </a>
                    <a href="{{ route('subscription') }}" class="btn btn-outline-primary">
                        <i class="fas fa-star me-2"></i>View Plans
                    </a>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="row g-3">
                    <div class="col-6">
                        <div class="bg-white rounded-3 shadow-sm p-4 h-100">
                            <div class="mb-3" style="width:52px;height:52px;background:linear-gradient(135deg,#dbeafe,#bfdbfe);border-radius:14px;display:flex;align-items:center;justify-content:center;">
                                <i class="fas fa-brain fa-xl text-primary"></i>
                            </div>
                            <h6 class="fw-bold">Neuroscience-Backed</h6>
                            <p class="text-muted small mb-0">Every track is based on established research in neuroplasticity and sound therapy for mental well-being.</p>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="bg-white rounded-3 shadow-sm p-4 h-100">
                            <div class="mb-3" style="width:52px;height:52px;background:linear-gradient(135deg,#dcfce7,#bbf7d0);border-radius:14px;display:flex;align-items:center;justify-content:center;">
                                <i class="fas fa-heart fa-xl text-success"></i>
                            </div>
                            <h6 class="fw-bold">Made with Purpose</h6>
                            <p class="text-muted small mb-0">Each audio experience is intentionally designed to address real mental wellness challenges people face daily.</p>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="bg-white rounded-3 shadow-sm p-4 h-100">
                            <div class="mb-3" style="width:52px;height:52px;background:linear-gradient(135deg,#fef9c3,#fde68a);border-radius:14px;display:flex;align-items:center;justify-content:center;">
                                <i class="fas fa-mobile-alt fa-xl text-warning"></i>
                            </div>
                            <h6 class="fw-bold">Available Everywhere</h6>
                            <p class="text-muted small mb-0">Web, Android, and iOS — with offline mode so you can train your mind wherever you are.</p>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="bg-white rounded-3 shadow-sm p-4 h-100">
                            <div class="mb-3" style="width:52px;height:52px;background:linear-gradient(135deg,#ede9fe,#ddd6fe);border-radius:14px;display:flex;align-items:center;justify-content:center;">
                                <i class="fas fa-infinity fa-xl" style="color:#7c3aed;"></i>
                            </div>
                            <h6 class="fw-bold">Constantly Growing</h6>
                            <p class="text-muted small mb-0">New tracks added every week across all categories — your mental fitness library never stops expanding.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- What We Offer -->
<section class="py-5">
    <div class="container">
        <div class="text-center mb-5">
            <span class="text-primary fw-semibold small text-uppercase">What We Offer</span>
            <h2 class="display-6 fw-bold mt-2">Proven Tools for a Stronger Mind</h2>
            <p class="lead text-muted col-lg-6 mx-auto">Every tool on our platform has been selected because it delivers measurable results for mental well-being.</p>
        </div>
        <div class="row g-4">
            <div class="col-md-6 col-lg-4">
                <div class="d-flex gap-3 p-4 bg-light rounded-3 h-100">
                    <div class="flex-shrink-0">
                        <div style="width:52px;height:52px;background:linear-gradient(135deg,#3b82f6,#2563eb);border-radius:14px;display:flex;align-items:center;justify-content:center;">
                            <i class="fas fa-microphone-alt text-white fa-lg"></i>
                        </div>
                    </div>
                    <div>
                        <h6 class="fw-bold">Positive Affirmations</h6>
                        <p class="text-muted small mb-0">
                            AI-powered text-to-speech affirmations personalised to your goals — confidence, happiness, success, stress relief, and more. Repeated listening rewires your subconscious beliefs.
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="d-flex gap-3 p-4 bg-light rounded-3 h-100">
                    <div class="flex-shrink-0">
                        <div style="width:52px;height:52px;background:linear-gradient(135deg,#8b5cf6,#7c3aed);border-radius:14px;display:flex;align-items:center;justify-content:center;">
                            <i class="fas fa-wave-square text-white fa-lg"></i>
                        </div>
                    </div>
                    <div>
                        <h6 class="fw-bold">Binaural Beats</h6>
                        <p class="text-muted small mb-0">
                            Specific audio frequencies that synchronise your brainwaves for focus, creativity, deep sleep, relaxation, and meditative states. A scientifically studied approach to mental tuning.
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="d-flex gap-3 p-4 bg-light rounded-3 h-100">
                    <div class="flex-shrink-0">
                        <div style="width:52px;height:52px;background:linear-gradient(135deg,#10b981,#059669);border-radius:14px;display:flex;align-items:center;justify-content:center;">
                            <i class="fas fa-leaf text-white fa-lg"></i>
                        </div>
                    </div>
                    <div>
                        <h6 class="fw-bold">Guided Meditation</h6>
                        <p class="text-muted small mb-0">
                            Step-by-step guided sessions that reduce cortisol, improve emotional regulation, and cultivate inner calm. Suitable for complete beginners to advanced practitioners.
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="d-flex gap-3 p-4 bg-light rounded-3 h-100">
                    <div class="flex-shrink-0">
                        <div style="width:52px;height:52px;background:linear-gradient(135deg,#0ea5e9,#0284c7);border-radius:14px;display:flex;align-items:center;justify-content:center;">
                            <i class="fas fa-moon text-white fa-lg"></i>
                        </div>
                    </div>
                    <div>
                        <h6 class="fw-bold">Sleep Aid Music</h6>
                        <p class="text-muted small mb-0">
                            Specially composed music that eases you into deep, restorative sleep. Combats insomnia, nighttime anxiety, and restless thoughts so you wake up refreshed and recharged.
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="d-flex gap-3 p-4 bg-light rounded-3 h-100">
                    <div class="flex-shrink-0">
                        <div style="width:52px;height:52px;background:linear-gradient(135deg,#f59e0b,#d97706);border-radius:14px;display:flex;align-items:center;justify-content:center;">
                            <i class="fas fa-yin-yang text-white fa-lg"></i>
                        </div>
                    </div>
                    <div>
                        <h6 class="fw-bold">Solfeggio Frequencies</h6>
                        <p class="text-muted small mb-0">
                            Ancient sacred tones like 432 Hz, 528 Hz and 963 Hz used for DNA repair, emotional healing, and spiritual attunement. Rediscovered and validated by modern sound researchers.
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="d-flex gap-3 p-4 bg-light rounded-3 h-100">
                    <div class="flex-shrink-0">
                        <div style="width:52px;height:52px;background:linear-gradient(135deg,#6d28d9,#4c1d95);border-radius:14px;display:flex;align-items:center;justify-content:center;">
                            <i class="fas fa-tree text-white fa-lg"></i>
                        </div>
                    </div>
                    <div>
                        <h6 class="fw-bold">Nature Soundscapes</h6>
                        <p class="text-muted small mb-0">
                            Rain on leaves, ocean waves, forest birdsong — immersive natural environments that reduce stress hormones, lower blood pressure, and restore focus naturally.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Our Values -->
<section class="py-5" style="background:linear-gradient(135deg,#0f172a 0%,#1e293b 100%);">
    <div class="container">
        <div class="text-center text-white mb-5">
            <span class="badge bg-primary mb-3 px-3 py-2">OUR VALUES</span>
            <h2 class="display-6 fw-bold">What Drives Us</h2>
        </div>
        <div class="row g-4 text-center">
            <div class="col-md-4">
                <div class="p-4">
                    <div class="mb-3" style="width:64px;height:64px;background:rgba(52,211,153,0.15);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto;">
                        <i class="fas fa-heart fa-2x text-success"></i>
                    </div>
                    <h5 class="text-white fw-bold">Accessibility</h5>
                    <p style="color:#94a3b8;">Mental wellness tools should be accessible to everyone. We price our plans so every person — regardless of income — can access quality mental fitness content.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="p-4">
                    <div class="mb-3" style="width:64px;height:64px;background:rgba(96,165,250,0.15);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto;">
                        <i class="fas fa-flask fa-2x text-info"></i>
                    </div>
                    <h5 class="text-white fw-bold">Evidence-Based</h5>
                    <p style="color:#94a3b8;">We only include techniques with solid scientific backing. No pseudoscience, no empty promises — only tools with demonstrated effectiveness for mental well-being.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="p-4">
                    <div class="mb-3" style="width:64px;height:64px;background:rgba(251,191,36,0.15);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto;">
                        <i class="fas fa-users fa-2x text-warning"></i>
                    </div>
                    <h5 class="text-white fw-bold">Community</h5>
                    <p style="color:#94a3b8;">Mental fitness is a journey best shared. We are building a community of like-minded individuals committed to growth, positivity, and continuous self-improvement.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Company Info -->
<section class="py-5 bg-light">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-6">
                <span class="text-primary fw-semibold small text-uppercase">About the Company</span>
                <h2 class="display-6 fw-bold mt-2 mb-4">MYWEB SOLUTIONS</h2>
                <p class="lead text-muted mb-3">
                    Mental Fitness Store is a product of <strong>MYWEB SOLUTIONS</strong>, a technology company passionate about using digital innovation to improve human well-being.
                </p>
                <p class="text-muted mb-4">
                    We combine the latest in AI-powered audio synthesis, mobile technology, and wellness science to create a platform that makes genuine mental transformation accessible to everyone. Our team is spread across India and is united by a single mission: helping people live happier, more fulfilling lives through the right mental tools.
                </p>
                <div class="d-flex gap-4 flex-wrap">
                    <div class="text-center">
                        <div class="display-6 fw-bold text-primary">500+</div>
                        <small class="text-muted">Audio tracks</small>
                    </div>
                    <div class="text-center">
                        <div class="display-6 fw-bold text-success">6</div>
                        <small class="text-muted">Wellness categories</small>
                    </div>
                    <div class="text-center">
                        <div class="display-6 fw-bold text-warning">2</div>
                        <small class="text-muted">Platforms (Web + App)</small>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card border-0 shadow p-4">
                    <h5 class="fw-bold mb-4">Get in Touch</h5>
                    <div class="d-flex align-items-center mb-3">
                        <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width:42px;height:42px;">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <div>
                            <small class="text-muted">Email</small><br>
                            <a href="mailto:info@mentalfitness.store" class="fw-semibold text-decoration-none">info@mentalfitness.store</a>
                        </div>
                    </div>
                    <div class="d-flex align-items-center mb-3">
                        <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width:42px;height:42px;">
                            <i class="fas fa-phone"></i>
                        </div>
                        <div>
                            <small class="text-muted">Phone / WhatsApp</small><br>
                            <a href="tel:+919937253528" class="fw-semibold text-decoration-none">+91 9937253528</a>
                        </div>
                    </div>
                    <div class="d-flex align-items-center mb-4">
                        <div class="bg-info text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width:42px;height:42px;">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div>
                            <small class="text-muted">Support Hours</small><br>
                            <span class="fw-semibold">Mon – Sat, 10 AM – 6 PM IST</span>
                        </div>
                    </div>
                    <a href="{{ route('contact') }}" class="btn btn-primary w-100">
                        <i class="fas fa-paper-plane me-2"></i>Send Us a Message
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- CTA -->
<section class="py-5 bg-primary text-white text-center">
    <div class="container">
        <h2 class="display-6 fw-bold mb-3">Start Your Mental Fitness Journey Today</h2>
        <p class="lead opacity-75 mb-4 col-lg-6 mx-auto">Join thousands of people who have already taken control of their mental health with our proven audio tools.</p>
        <div class="d-flex gap-3 justify-content-center flex-wrap">
            <a href="{{ route('register') }}" class="btn btn-light btn-lg fw-semibold">
                <i class="fas fa-user-plus me-2"></i>Create Free Account
            </a>
            <a href="{{ route('subscription') }}" class="btn btn-outline-light btn-lg">
                <i class="fas fa-star me-2"></i>View Subscription Plans
            </a>
        </div>
    </div>
</section>

@endsection
