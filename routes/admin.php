<?php

use Illuminate\Support\Facades\Route;
use App\Livewire\Admin\Dashboard;
use App\Livewire\Admin\CategoryManager;
use App\Livewire\Admin\ProductManager;

Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', Dashboard::class)->name('dashboard');
    Route::get('/categories', CategoryManager::class)->name('categories');
    Route::get('/products', ProductManager::class)->name('products');
});
