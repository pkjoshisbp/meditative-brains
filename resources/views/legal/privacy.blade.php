@extends('layouts.app-frontend')

@section('title', 'Privacy Policy — Mental Fitness Store')

@section('content')
<div class="py-5 bg-light border-bottom mb-4">
    <div class="container">
        <h1 class="display-5 fw-bold mb-2">Privacy Policy</h1>
        <p class="lead text-muted mb-0">Last updated: {{ date('F Y') }}</p>
    </div>
</div>
<div class="container pb-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">

            <p>Mental Fitness Store, operated by <strong>MYWEB SOLUTIONS</strong>, is committed to protecting your privacy. This Privacy Policy explains how we collect, use, and safeguard your information when you visit <a href="https://mentalfitness.store">mentalfitness.store</a>.</p>

            <h2>1. Information We Collect</h2>
            <h3>1.1 Information You Provide</h3>
            <ul>
                <li>Name and email address when registering an account</li>
                <li>Payment information (processed securely by PayPal or Razorpay — we do not store card details)</li>
                <li>Communication you send us</li>
            </ul>
            <h3>1.2 Information Collected Automatically</h3>
            <ul>
                <li>IP address (used to determine your country for pricing and payment gateway selection)</li>
                <li>Browser type and device information</li>
                <li>Pages visited and time spent on the Site</li>
                <li>Purchase and subscription history</li>
            </ul>

            <h2>2. How We Use Your Information</h2>
            <ul>
                <li>To create and manage your account</li>
                <li>To process payments and deliver purchased products</li>
                <li>To determine your location for correct currency display (INR/USD) and payment routing</li>
                <li>To send order confirmations and important account notifications</li>
                <li>To improve our products and services</li>
                <li>To comply with legal obligations</li>
            </ul>

            <h2>3. Cookies</h2>
            <p>We use session cookies to maintain your login session, remember your currency preference (INR/USD), and improve your browsing experience. We do not use third-party advertising cookies.</p>

            <h2>4. Payment Processing</h2>
            <p>Payments are processed by:</p>
            <ul>
                <li><strong>Razorpay</strong> — for users in India (subject to <a href="https://razorpay.com/privacy/" target="_blank" rel="noopener">Razorpay's Privacy Policy</a>)</li>
                <li><strong>PayPal</strong> — for international users (subject to <a href="https://www.paypal.com/us/legalhub/privacy-full" target="_blank" rel="noopener">PayPal's Privacy Policy</a>)</li>
            </ul>
            <p>We do not store your payment card details on our servers.</p>

            <h2>5. Data Sharing</h2>
            <p>We do not sell or rent your personal information to third parties. We may share data with:</p>
            <ul>
                <li>Payment processors (PayPal, Razorpay) to complete transactions</li>
                <li>Email service providers to send transactional emails</li>
                <li>Legal authorities when required by law</li>
            </ul>

            <h2>6. Data Retention</h2>
            <p>We retain your account and purchase data for as long as your account is active, or as required by law. You may request deletion of your account by contacting us.</p>

            <h2>7. Your Rights</h2>
            <p>You have the right to:</p>
            <ul>
                <li>Access the personal data we hold about you</li>
                <li>Request correction of inaccurate data</li>
                <li>Request deletion of your data (subject to legal retention requirements)</li>
                <li>Opt out of marketing communications</li>
            </ul>

            <h2>8. Security</h2>
            <p>We use industry-standard SSL encryption for data transmission. Access to personal data is restricted to authorised staff only.</p>

            <h2>9. Children's Privacy</h2>
            <p>Our services are not directed to children under 13. We do not knowingly collect personal information from children.</p>

            <h2>10. Changes to This Policy</h2>
            <p>We may update this Privacy Policy periodically. We will notify you of significant changes by posting a notice on the Site or by email.</p>

            <h2>11. Contact Us</h2>
            <p>For privacy-related questions or requests:<br>
            <strong>MYWEB SOLUTIONS</strong><br>
            Email: <a href="mailto:privacy@mentalfitness.store">privacy@mentalfitness.store</a><br>
            Website: <a href="https://mentalfitness.store">mentalfitness.store</a></p>

        </div>
    </div>
</div>
@endsection
