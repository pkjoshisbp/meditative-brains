<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AudioStreamController;
use App\Http\Controllers\ProductPreviewController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\AccountController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

// Frontend Routes
Route::get('/', App\Livewire\Homepage::class)->name('home');
Route::get('/products', App\Livewire\ProductCatalog::class)->name('products');
Route::get('/simple-test', function () {
    return view('test-page');
})->name('simple-test');

// Audio streaming with signed URLs
Route::get('/audio/stream', [AudioStreamController::class, 'stream'])->name('audio.stream');
Route::get('/audio/signed-stream', [AudioStreamController::class, 'signedStream'])
    ->name('audio.signed-stream')
    ->middleware('signed');

// Public Audio Experiences (Meditative Minds Audio) - avoid /audio directory conflict
Route::get('/mind-audio', App\Livewire\AudioExperienceCatalog::class)->name('audio.catalog');
Route::get('/mind-audio/{slug}', App\Livewire\AudioExperienceDetail::class)->name('audio.detail');

// Background music secure issue endpoint (admin only for now)
Route::middleware(['auth'])->get('/bg-music/issue', [App\Http\Controllers\BgMusicStreamController::class, 'issue'])
    ->name('bg-music.issue');

// Product preview URLs
Route::post('/audio/preview-url', [ProductPreviewController::class, 'getPreviewUrl'])
    ->name('audio.preview-url');

// Admin Routes (protected by auth middleware)
Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', function () {
        return view('admin.dashboard');
    })->name('dashboard');
    
    Route::get('/categories', App\Livewire\Admin\CategoryManager::class)->name('categories');
    Route::get('/products', App\Livewire\Admin\ProductManager::class)->name('products');
    Route::get('/products-simple', App\Livewire\Admin\ProductManagerSimple::class)->name('products.simple');
    Route::get('/test', App\Livewire\Admin\TestComponent::class)->name('test');
    Route::get('/test-fixed', App\Livewire\Admin\TestComponentFixed::class)->name('test-fixed');
    
    // TTS Integration Routes
    Route::get('/tts/messages', App\Livewire\Admin\MotivationMessageForm::class)->name('tts.messages');
    Route::get('/tts/messages-working', App\Livewire\Admin\MotivationMessageFormWorking::class)->name('tts.messages-working');
    Route::get('/tts/categories', App\Livewire\Admin\CategoryManagement::class)->name('tts.categories');
    Route::get('/tts/generator', App\Livewire\Admin\AudioGenerator::class)->name('tts.generator');
    Route::get('/tts/test', App\Livewire\Admin\TtsTest::class)->name('tts.test');
    
    // TTS Products Management Routes  
    Route::get('/tts/products', App\Livewire\Admin\TtsProductManager::class)->name('tts.products');

    // Audiobook Generator
    Route::get('/tts/audiobook', App\Livewire\Admin\AudioBookGenerator::class)->name('tts.audiobook');

    // Attention Guide Manager
    Route::get('/tts/attention-guides', App\Livewire\Admin\AttentionGuideManager::class)->name('tts.attention-guides');

    // BG Music Manager
    Route::get('/bg-music', [App\Http\Controllers\BgMusicStreamController::class, 'adminIndex'])->name('bg-music');
    Route::post('/bg-music/upload', [App\Http\Controllers\BgMusicStreamController::class, 'adminUpload'])->name('bg-music.upload');
    Route::post('/bg-music/delete', [App\Http\Controllers\BgMusicStreamController::class, 'adminDelete'])->name('bg-music.delete');

    // Store Management
    Route::get('/orders', App\Livewire\Admin\OrderManager::class)->name('orders');
    Route::get('/customers', App\Livewire\Admin\CustomerManager::class)->name('customers');

    // Subscription Management
    Route::get('/subscriptions', App\Livewire\Admin\SubscriptionManager::class)->name('subscriptions');
    Route::get('/subscriptions/plans', App\Livewire\Admin\SubscriptionPlanManager::class)->name('subscriptions.plans');

    // Payment Settings
    Route::get('/settings/payments', function () {
        return view('admin.settings.payments');
    })->name('settings.payments');

    Route::post('/settings/payments', function (\Illuminate\Http\Request $request) {
        $request->validate([
            'razorpay_key_id'      => 'nullable|string',
            'razorpay_key_secret'  => 'nullable|string',
            'razorpay_webhook_secret' => 'nullable|string',
            'paypal_client_id'     => 'nullable|string',
            'paypal_client_secret' => 'nullable|string',
            'paypal_mode'          => 'required|in:sandbox,live',
            'paypal_webhook_id'    => 'nullable|string',
        ]);

        $envPath = base_path('.env');
        $env = file_get_contents($envPath);

        $updates = [
            'RAZORPAY_KEY_ID'         => $request->razorpay_key_id,
            'RAZORPAY_KEY_SECRET'     => $request->razorpay_key_secret,
            'RAZORPAY_WEBHOOK_SECRET' => $request->razorpay_webhook_secret,
            'PAYPAL_CLIENT_ID'        => $request->paypal_client_id,
            'PAYPAL_CLIENT_SECRET'    => $request->paypal_client_secret,
            'PAYPAL_MODE'             => $request->paypal_mode,
            'PAYPAL_WEBHOOK_ID'       => $request->paypal_webhook_id,
        ];

        foreach ($updates as $key => $value) {
            $value = $value ?? '';
            if (preg_match("/^{$key}=.*/m", $env)) {
                $env = preg_replace("/^{$key}=.*/m", "{$key}={$value}", $env);
            } else {
                $env .= "\n{$key}={$value}";
            }
        }

        file_put_contents($envPath, $env);

        \Artisan::call('config:clear');

        return redirect()->route('admin.settings.payments')
            ->with('success', 'Payment settings saved. Config cache cleared.');
    })->name('settings.payments.save');
});

// Authentication routes
Auth::routes();

// Customer Account Panel
Route::middleware(['auth'])->prefix('my-account')->name('account.')->group(function () {
    Route::get('/',           [AccountController::class, 'dashboard'])->name('dashboard');
    Route::get('/library',    [AccountController::class, 'library'])->name('library');
    Route::get('/orders',     [AccountController::class, 'orders'])->name('orders');
    Route::get('/profile',    [AccountController::class, 'profile'])->name('profile');
    Route::post('/profile',   [AccountController::class, 'updateProfile'])->name('profile.update');
    Route::post('/password',  [AccountController::class, 'updatePassword'])->name('password.update');
});

// Payment Routes
Route::middleware(['auth'])->prefix('payment')->name('payment.')->group(function () {
    Route::post('/razorpay/create-order', [PaymentController::class, 'razorpayCreateOrder'])->name('razorpay.create');
    Route::post('/razorpay/verify', [PaymentController::class, 'razorpayVerify'])->name('razorpay.verify');
    Route::get('/paypal/create-order', [PaymentController::class, 'paypalCreateOrder'])->name('paypal.create');
    Route::get('/paypal/success', [PaymentController::class, 'paypalSuccess'])->name('paypal.success');
    Route::get('/paypal/cancel', [PaymentController::class, 'paypalCancel'])->name('paypal.cancel');
});
// Razorpay webhook (no auth, verified by signature)
Route::post('/webhooks/razorpay', [PaymentController::class, 'razorpayWebhook'])->name('webhooks.razorpay');

// Legal Pages
Route::get('/terms', function () { return view('legal.terms'); })->name('legal.terms');
Route::get('/privacy', function () { return view('legal.privacy'); })->name('legal.privacy');
Route::get('/refund-policy', function () { return view('legal.refund'); })->name('legal.refund');
Route::get('/contact', function () { return view('pages.contact'); })->name('contact');
Route::post('/contact', function (\Illuminate\Http\Request $request) {
    $request->validate([
        'name'        => 'required|string|max:100',
        'email'       => 'required|email|max:150',
        'subject'     => 'required|string|max:150',
        'message'     => 'required|string|max:2000',
        'website'     => 'max:0',
        'math_answer' => ['required', 'numeric', function ($attribute, $value, $fail) {
            if ((int) $value !== (int) session('contact_math_ans')) {
                $fail('Incorrect answer. Please solve the math problem correctly.');
            }
            session()->forget('contact_math_ans');
        }],
    ], [
        'website.max'         => 'Spam detected.',
        'math_answer.required' => 'Please answer the spam check question.',
    ]);
    \Illuminate\Support\Facades\Mail::raw(
        "Name: {$request->name}\nEmail: {$request->email}\nSubject: {$request->subject}\n\n{$request->message}",
        function ($m) use ($request) {
            $m->to('info@mentalfitness.store')
              ->replyTo($request->email, $request->name)
              ->subject("Contact Form: {$request->subject}");
        }
    );
    return redirect()->route('contact')->with('contact_success', true);
})->name('contact.send');
Route::get('/subscription', function () { return view('pages.subscription'); })->name('subscription');

// About & Blog
Route::get('/about', function () { return view('pages.about'); })->name('about');
Route::get('/blog', function () {
    $posts = require resource_path('views/pages/blog/posts.php');
    return view('pages.blog.index', compact('posts'));
})->name('blog');
Route::get('/blog/{slug}', function ($slug) {
    $posts = require resource_path('views/pages/blog/posts.php');
    $post  = collect($posts)->firstWhere('slug', $slug);
    if (!$post) abort(404);
    $related = collect($posts)->where('slug', '!=', $slug)->take(3)->values();
    return view('pages.blog.show', compact('slug', 'post', 'related'));
})->name('blog.show');

// Cart
Route::get('/cart', [App\Http\Controllers\CartController::class, 'index'])->name('cart');
Route::post('/cart/add', [App\Http\Controllers\CartController::class, 'add'])->name('cart.add');
Route::post('/cart/remove', [App\Http\Controllers\CartController::class, 'remove'])->name('cart.remove');
Route::post('/cart/clear', [App\Http\Controllers\CartController::class, 'clear'])->name('cart.clear');

// Sitemap
Route::get('/sitemap.xml', function () {
    $content = view('sitemap')->render();
    return response($content, 200)->header('Content-Type', 'application/xml');
})->name('sitemap');
