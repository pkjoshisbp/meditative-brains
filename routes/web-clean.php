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
});

// Authentication routes
require __DIR__.'/auth.php';
