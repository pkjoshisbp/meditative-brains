@extends('layouts.app-frontend')

@section('title', 'Contact Us — Mental Fitness Store')

@section('content')
<div class="py-5 bg-light border-bottom mb-4">
    <div class="container">
        <h1 class="display-5 fw-bold mb-2">Contact Us</h1>
        <p class="lead text-muted mb-0">We're here to help — reach out any time</p>
    </div>
</div>
<div class="container pb-5">
    <div class="row g-4">
        <div class="col-lg-5">
            <h2 class="h4 mb-4">Get in Touch</h2>

            <div class="d-flex align-items-start mb-4">
                <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3 flex-shrink-0" style="width:48px;height:48px;">
                    <i class="fas fa-envelope"></i>
                </div>
                <div>
                    <h6 class="mb-1">Email</h6>
                    <a href="mailto:info@mentalfitness.store" class="text-decoration-none">info@mentalfitness.store</a>
                    <p class="text-muted small mb-0">For general enquiries and support</p>
                </div>
            </div>

            <div class="d-flex align-items-start mb-4">
                <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center me-3 flex-shrink-0" style="width:48px;height:48px;">
                    <i class="fas fa-phone"></i>
                </div>
                <div>
                    <h6 class="mb-1">Phone / WhatsApp</h6>
                    <a href="tel:+919937253528" class="text-decoration-none">+91 9937253528</a>
                    <p class="text-muted small mb-0">Mon – Sat, 10 AM – 6 PM IST</p>
                </div>
            </div>

            <div class="d-flex align-items-start mb-4">
                <div class="bg-info text-white rounded-circle d-flex align-items-center justify-content-center me-3 flex-shrink-0" style="width:48px;height:48px;">
                    <i class="fas fa-building"></i>
                </div>
                <div>
                    <h6 class="mb-1">Company</h6>
                    <p class="mb-0">MYWEB SOLUTIONS</p>
                    <p class="text-muted small mb-0">Mental Fitness Store</p>
                </div>
            </div>

            <hr class="my-4">

            <h2 class="h5 mb-3">Frequently Asked Questions</h2>
            <div class="accordion" id="contactFaq">
                <div class="accordion-item border-0 mb-2 shadow-sm rounded">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed rounded" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                            How do I access my purchased products?
                        </button>
                    </h2>
                    <div id="faq1" class="accordion-collapse collapse" data-bs-parent="#contactFaq">
                        <div class="accordion-body">
                            After purchase, your products are available immediately in your account dashboard under "My Products". You can stream or download them from there.
                        </div>
                    </div>
                </div>
                <div class="accordion-item border-0 mb-2 shadow-sm rounded">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed rounded" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                            Do you offer refunds?
                        </button>
                    </h2>
                    <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#contactFaq">
                        <div class="accordion-body">
                            All sales are final. We provide a free preview on every product so you can try before you buy. Please read our <a href="{{ route('legal.refund') }}">Refund Policy</a> for full details.
                        </div>
                    </div>
                </div>
                <div class="accordion-item border-0 shadow-sm rounded">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed rounded" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                            I'm having technical issues. What should I do?
                        </button>
                    </h2>
                    <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#contactFaq">
                        <div class="accordion-body">
                            Email us at <a href="mailto:info@mentalfitness.store">info@mentalfitness.store</a> with a description of the issue and your order details. We'll get back to you as soon as possible.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <h2 class="h4 mb-4">Send Us a Message</h2>
                    @if(session('contact_success'))
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i>
                            Thank you! Your message has been sent. We'll reply within 1–2 business days.
                        </div>
                    @endif
                    <form action="{{ route('contact.send') }}" method="POST">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Your Name</label>
                            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                                placeholder="John Doe" value="{{ old('name') }}" required>
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Email Address</label>
                            <input type="email" name="email" class="form-control @error('email') is-invalid @enderror"
                                placeholder="you@example.com" value="{{ old('email') }}" required>
                            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Subject</label>
                            <select name="subject" class="form-select @error('subject') is-invalid @enderror" required>
                                <option value="" disabled {{ old('subject') ? '' : 'selected' }}>Select a topic</option>
                                <option value="Technical Support" {{ old('subject') == 'Technical Support' ? 'selected' : '' }}>Technical Support</option>
                                <option value="Billing Enquiry" {{ old('subject') == 'Billing Enquiry' ? 'selected' : '' }}>Billing Enquiry</option>
                                <option value="Product Question" {{ old('subject') == 'Product Question' ? 'selected' : '' }}>Product Question</option>
                                <option value="Subscription" {{ old('subject') == 'Subscription' ? 'selected' : '' }}>Subscription</option>
                                <option value="Other" {{ old('subject') == 'Other' ? 'selected' : '' }}>Other</option>
                            </select>
                            @error('subject')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-semibold">Message</label>
                            <textarea name="message" rows="5" class="form-control @error('message') is-invalid @enderror"
                                placeholder="Describe your question or issue..." required>{{ old('message') }}</textarea>
                            @error('message')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        {{-- Honeypot: hidden from real users, bots fill it in --}}
                        <div class="d-none" aria-hidden="true">
                            <input type="text" name="website" id="website" tabindex="-1" autocomplete="off" value="">
                        </div>

                        {{-- Math quiz spam check --}}
                        @php
                            $__a = rand(2, 9); $__b = rand(1, 6);
                            session(['contact_math_ans' => $__a + $__b]);
                        @endphp
                        <div class="mb-4">
                            <label class="form-label fw-semibold">
                                Spam Check: What is {{ $__a }} + {{ $__b }}?
                                <span class="text-danger">*</span>
                            </label>
                            <input type="number" name="math_answer"
                                class="form-control @error('math_answer') is-invalid @enderror"
                                required placeholder="Enter the answer" min="1" max="20">
                            @error('math_answer')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg w-100">
                            <i class="fas fa-paper-plane me-2"></i>Send Message
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
