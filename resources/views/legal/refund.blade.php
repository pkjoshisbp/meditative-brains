@extends('layouts.app-frontend')

@section('title', 'Refund Policy — Mental Fitness Store')

@section('content')
<div class="py-5 bg-light border-bottom mb-4">
    <div class="container">
        <h1 class="display-5 fw-bold mb-2">Refund Policy</h1>
        <p class="lead text-muted mb-0">Last updated: {{ date('F Y') }}</p>
    </div>
</div>
<div class="container pb-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">

            <div class="alert alert-info mb-4">
                <i class="fas fa-info-circle me-2"></i>
                <strong>Summary:</strong> All sales are final. We encourage you to use the free preview feature before purchasing.
            </div>

            <h2>No Refund Policy</h2>
            <p>All purchases on Mental Fitness Store are <strong>final and non-refundable</strong>. This applies to all of our digital products and services, including:</p>
            <ul>
                <li>Individual audio product purchases</li>
                <li>PDF eBook downloads</li>
                <li>PDF + Audio bundle purchases</li>
                <li>Monthly and yearly subscription plans</li>
                <li>Starter Plan purchases</li>
            </ul>

            <h2>Why No Refunds?</h2>
            <p>All our products are digital goods that are delivered instantly upon purchase. Once you have access to the content, we are unable to "un-deliver" the product, which is why we maintain a strict no-refund policy.</p>

            <h2>Preview Before You Buy</h2>
            <p>We strongly encourage all customers to use our <strong>free preview feature</strong> before making any purchase. Every product on our platform includes a free preview so you can:</p>
            <ul>
                <li>Listen to a portion of the audio before purchasing</li>
                <li>Evaluate the voice, tone, and content</li>
                <li>Confirm the product suits your needs</li>
            </ul>
            <p>The preview feature is available on every product page — simply click the <strong>"Play Preview"</strong> button.</p>

            <h2>Technical Issues</h2>
            <p>If you experience a genuine technical issue that prevents access to content you have purchased, please contact us within <strong>7 days</strong> of purchase. We will investigate and do our best to resolve the issue:</p>
            <ul>
                <li>Email: <a href="mailto:info@mentalfitness.store">info@mentalfitness.store</a></li>
                <li>Phone: <a href="tel:+919937253528">+91 9937253528</a></li>
            </ul>

            <h2>Subscription Cancellations</h2>
            <p>You may cancel your subscription at any time. Cancellation takes effect at the end of the current billing period. No refund is issued for the unused portion of the current period.</p>

            <h2>Changes to This Policy</h2>
            <p>We reserve the right to update this Refund Policy at any time. Changes will be effective upon posting to the Site.</p>

            <h2>Contact Us</h2>
            <p>For any questions regarding this policy:<br>
            <strong>MYWEB SOLUTIONS</strong><br>
            Email: <a href="mailto:info@mentalfitness.store">info@mentalfitness.store</a><br>
            Phone: <a href="tel:+919937253528">+91 9937253528</a></p>

        </div>
    </div>
</div>
@endsection
