<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AudioStreamController;
use App\Http\Controllers\ProductPreviewController;

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
Route::get('/audio/signed-stream', [AudioStreamController::class, 'signedStream'])
    ->name('audio.signed-stream')
    ->middleware('signed');

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
});

// Authentication routes
Auth::routes();
