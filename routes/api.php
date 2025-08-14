<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\FlutterApiController;
use App\Http\Controllers\Api\MusicLibraryController;

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
    Route::get('/categories', [MusicLibraryController::class, 'ttsCategories']);
    
    Route::middleware('auth:sanctum')->group(function () {
        // TTS-specific routes will be added in Part 2
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
