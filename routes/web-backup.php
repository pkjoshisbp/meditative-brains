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

Route::get('/', App\Livewire\Homepage::class)->name('home');

Route::get('/products', App\Livewire\ProductCatalog::class)->name('products');

// Authentication Routes
Auth::routes();

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');

// Audio streaming routes
Route::get('/audio/stream', [App\Http\Controllers\AudioStreamController::class, 'stream'])->name('audio.stream');
Route::post('/audio/preview-url', [App\Http\Controllers\AudioStreamController::class, 'generatePreviewUrl'])->name('audio.preview-url');
Route::post('/audio/full-url', [App\Http\Controllers\AudioStreamController::class, 'generateFullAudioUrl'])->name('audio.full-url')->middleware('auth');

// Admin Routes
// Audio streaming with signed URLs
Route::get('/audio/signed-stream', [AudioStreamController::class, 'signedStream'])
    ->name('audio.signed-stream')
    ->middleware('signed');

// Product preview URLs
Route::post('/audio/preview-url', [ProductPreviewController::class, 'getPreviewUrl'])
    ->name('audio.preview-url');

// Authentication routes
require __DIR__.'/auth.php';

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

Route::get('/', function () {
    return view('welcome');
});

Auth::routes();

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');

Auth::routes();

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
