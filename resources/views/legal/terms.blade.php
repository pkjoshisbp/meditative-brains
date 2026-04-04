@extends('layouts.app-frontend')

@section('title', 'Terms & Conditions — Mental Fitness Store')

@section('content')
<div class="py-5 bg-light border-bottom mb-4">
    <div class="container">
        <h1 class="display-5 fw-bold mb-2">Terms &amp; Conditions</h1>
        <p class="lead text-muted mb-0">Last updated: {{ date('F Y') }}</p>
    </div>
</div>
<div class="container pb-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">

            <h2>1. Acceptance of Terms</h2>
            <p>By accessing or using Mental Fitness Store ("the Site"), operated by <strong>MYWEB SOLUTIONS</strong>, you agree to be bound by these Terms &amp; Conditions. If you do not agree to these terms, please do not use the Site.</p>

            <h2>2. Products &amp; Services</h2>
            <p>Mental Fitness Store offers digital audio products, audio books (PDF eBook, Bundle, Audio-only), and subscription plans for personal use. All purchases grant a non-exclusive, non-transferable licence for personal, non-commercial use only.</p>

            <h2>3. Digital Downloads &amp; Audio Access</h2>
            <ul>
                <li><strong>PDF eBooks</strong> are delivered as downloadable files to your registered email and account.</li>
                <li><strong>Audio products</strong> are streamed through the Mental Fitness Store mobile/desktop application only. Direct download links are not provided.</li>
                <li><strong>Bundle purchases</strong> include both a PDF eBook download and audio access via the app.</li>
            </ul>

            <h2>4. Subscription Plans</h2>
            <p>Subscription plans grant access to a set number of audio products per billing cycle. Unused slots do not carry over. Each product version (e.g., Hindi, US accent) counts as a separate product slot.</p>
            <p>Subscriptions renew automatically unless cancelled at least 24 hours before the renewal date.</p>

            <h2>5. Pricing &amp; Currency</h2>
            <p>Prices are displayed in USD ($) for international users and in Indian Rupees (₹) for users in India. The applicable exchange rate is $1 = ₹100 as set by the store. We accept PayPal for international payments and Razorpay for Indian payments.</p>

            <h2>6. Refund Policy</h2>
            <p>Due to the digital nature of our products, all sales are final. If you experience a technical issue preventing access to your purchased product, please contact us within 7 days of purchase at <a href="mailto:support@mentalfitness.store">support@mentalfitness.store</a>.</p>

            <h2>7. Intellectual Property</h2>
            <p>All audio content, text, and materials on this Site are the property of Mental Fitness Store / MYWEB SOLUTIONS or its licensors. You may not reproduce, redistribute, or create derivative works without prior written consent.</p>

            <h2>8. User Accounts</h2>
            <p>You are responsible for maintaining the confidentiality of your account credentials. You agree not to share your account or purchased content with others.</p>

            <h2>9. Limitation of Liability</h2>
            <p>Mental Fitness Store and MYWEB SOLUTIONS shall not be liable for any indirect, incidental, or consequential damages arising from the use of or inability to use our products or services.</p>

            <h2>10. Governing Law</h2>
            <p>These Terms are governed by the laws of India. Any disputes shall be subject to the exclusive jurisdiction of courts in India.</p>

            <h2>11. Changes to Terms</h2>
            <p>We reserve the right to update these Terms at any time. Continued use of the Site after changes constitutes acceptance of the new Terms.</p>

            <h2>12. Contact</h2>
            <p>For questions about these Terms, please contact:<br>
            <strong>MYWEB SOLUTIONS</strong><br>
            Email: <a href="mailto:support@mentalfitness.store">support@mentalfitness.store</a><br>
            Website: <a href="https://mentalfitness.store">mentalfitness.store</a></p>

        </div>
    </div>
</div>
@endsection
