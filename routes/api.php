<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\FlutterApiController;
use App\Http\Controllers\Api\MusicLibraryController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\TtsBackendController;
use App\Http\Controllers\Api\EntitlementController;
use App\Http\Controllers\Api\TtsGroupedCatalogController;
use Illuminate\Support\Facades\URL;
use App\Http\Controllers\Api\DownloadController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\TrialAnalyticsController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Basic auth endpoints (Sanctum token issuance)
Route::post('/login', [AuthController::class,'login']);
Route::middleware('auth:sanctum')->get('/me', [AuthController::class,'user']);

// Music Library API Routes for Flutter
Route::prefix('music-library')->group(function () {
    // Public routes (no authentication required)
    Route::get('/', [MusicLibraryController::class, 'index']);
    Route::get('/preview/{productId}', [MusicLibraryController::class, 'preview']);
    
    // Protected routes (authentication required)
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/my-library', [MusicLibraryController::class, 'myLibrary']);
        Route::get('/check-access', [MusicLibraryController::class, 'checkAccess']);
        Route::get('/full-audio/{productId}', [MusicLibraryController::class, 'getFullAudio']);
    });
});

// TTS Categories API Routes for Flutter
Route::prefix('tts')->group(function () {
    // Public routes
    Route::get('/categories', [MusicLibraryController::class, 'ttsCategories']);
    Route::get('/voices', [TtsBackendController::class, 'getAvailableVoices']);
    Route::get('/category-pricing', [TtsBackendController::class, 'getCategoryPricing']);
    
    // Protected routes (authentication required)
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/category/{category}/messages', [TtsBackendController::class, 'getCategoryMessages']);
        Route::post('/generate-audio', [TtsBackendController::class, 'generateAudio']);
        Route::post('/search', [TtsBackendController::class, 'searchMessages']);
        Route::get('/user-stats', [TtsBackendController::class, 'getUserStats']);
    // Language + product access filtered endpoints for Flutter
    Route::get('/languages', [TtsBackendController::class, 'getAvailableLanguages']);
    Route::get('/language/{language}/products', [TtsBackendController::class, 'getProductsByLanguage']);
    Route::get('/products/{product}/detail', [TtsBackendController::class, 'getTtsProductDetail']);
    Route::get('/background-music', [TtsBackendController::class, 'listBackgroundMusic']);
    Route::get('/background-music/stream/{variant}/{file}', [TtsBackendController::class, 'streamBackgroundMusic'])->name('bg.music.stream');
    Route::get('/encryption-key', [TtsBackendController::class, 'getEncryptionKey']);
        
        // TTS Product Management
        Route::get('/products/catalog', [TtsBackendController::class, 'getTtsProductsCatalog']);
        Route::post('/products/preview', [TtsBackendController::class, 'generatePreviewAudio']);
        Route::post('/products/bulk-preview', [TtsBackendController::class, 'generateBulkPreview']);
        Route::get('/products/user-purchases', [TtsBackendController::class, 'getUserTtsProducts']);
        
        // Audio Service Management
        Route::get('/audio-service/status', [TtsBackendController::class, 'getAudioServiceStatus']);
    });
});

// Payment API Routes for Flutter
Route::prefix('payment')->group(function () {
    // Public routes
    Route::get('/plans', [PaymentController::class, 'getSubscriptionPlans']);
    Route::post('/webhook', [PaymentController::class, 'webhook']); // PayPal webhook
    
    // Protected routes (authentication required)
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/create-product-payment', [PaymentController::class, 'createProductPayment']);
        Route::post('/create-subscription-payment', [PaymentController::class, 'createSubscriptionPayment']);
        Route::post('/handle-success', [PaymentController::class, 'handleSuccess']);
        Route::get('/history', [PaymentController::class, 'purchaseHistory']);
        Route::post('/cancel-subscription', [PaymentController::class, 'cancelSubscription']);
        Route::get('/status', [PaymentController::class, 'getPaymentStatus']);
        
        // TTS Product Payments
        Route::post('/create-tts-product-payment', [PaymentController::class, 'createTtsProductPayment']);
        Route::post('/handle-tts-product-success', [PaymentController::class, 'handleTtsProductSuccess']);
        Route::get('/tts-product-history', [PaymentController::class, 'getTtsProductHistory']);
    });
});

// Flutter App API Routes
Route::prefix('flutter')->name('api.flutter.')->group(function () {
    // Categories and Products
    Route::get('/categories', [FlutterApiController::class, 'getCategories'])->name('categories');
    Route::get('/categories/{categoryId}/products', [FlutterApiController::class, 'getProductsByCategory'])->name('products.by-category');
    Route::get('/products', [FlutterApiController::class, 'getAllProducts'])->name('products.all');
    Route::get('/products/search', [FlutterApiController::class, 'searchProducts'])->name('products.search');
    
    // Audio Streaming
    Route::get('/audio/{productId}/preview', [FlutterApiController::class, 'getAudioPreview'])->name('audio.preview');
    Route::get('/audio/{productId}/download', [FlutterApiController::class, 'getAudioDownload'])->name('audio.download');
    
    // TTS Generation
    Route::post('/tts/generate', [FlutterApiController::class, 'generateTTS'])->name('tts.generate');
});

// New unified catalog & entitlement endpoints
Route::middleware(['auth:sanctum','device.limit'])->group(function(){
    Route::get('/entitlements', [EntitlementController::class,'summary']);
    Route::post('/devices/register', [EntitlementController::class,'registerDevice']);
    Route::post('/devices/heartbeat', [EntitlementController::class,'heartbeat']);
    Route::delete('/devices/{uuid}', [EntitlementController::class,'revokeDevice']);
    Route::post('/downloads/request', [EntitlementController::class,'requestDownload']);
    Route::post('/downloads/complete', [EntitlementController::class,'completeDownload']);
    Route::delete('/downloads/prune', [EntitlementController::class,'pruneDownloads']);
});

Route::get('/tts-grouped', [TtsGroupedCatalogController::class,'index']);

// Secure signed download route (auth + signature, 10 min expiry)
Route::middleware(['auth:sanctum','device.limit'])->get('/secure/download/{download}', [DownloadController::class,'serve'])->name('secure.download');

// Trial status endpoint (after auth setup)
Route::middleware('auth:sanctum')->get('/trial/status', [\App\Http\Controllers\Api\TrialController::class,'status']);
Route::middleware(['auth:sanctum','role:trial,admin'])->get('/trial/analytics', [TrialAnalyticsController::class,'summary']);
