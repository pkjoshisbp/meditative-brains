@extends('adminlte::page')

@section('title', 'Admin Dashboard')

@section('content_header')
    <h1>Admin Dashboard</h1>
@stop

@section('content')
    <div class="row">
        <div class="col-lg-3 col-6">
            <div class="small-box bg-info">
                <div class="inner">
                    <h3>{{ \App\Models\Product::count() }}</h3>
                    <p>Products</p>
                </div>
                <div class="icon">
                    <i class="fas fa-music"></i>
                </div>
                <a href="{{ route('admin.products') }}" class="small-box-footer">
                    More info <i class="fas fa-arrow-circle-right"></i>
                </a>
            </div>
        </div>

        <div class="col-lg-3 col-6">
            <div class="small-box bg-success">
                <div class="inner">
                    <h3>{{ \App\Models\ProductCategory::count() }}</h3>
                    <p>Categories</p>
                </div>
                <div class="icon">
                    <i class="fas fa-folder"></i>
                </div>
                <a href="{{ route('admin.categories') }}" class="small-box-footer">
                    More info <i class="fas fa-arrow-circle-right"></i>
                </a>
            </div>
        </div>

        <div class="col-lg-3 col-6">
            <div class="small-box bg-warning">
                <div class="inner">
                    <h3>{{ \App\Models\Product::where('is_active', true)->count() }}</h3>
                    <p>Active Products</p>
                </div>
                <div class="icon">
                    <i class="fas fa-check-circle"></i>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-6">
            <div class="small-box bg-danger">
                <div class="inner">
                    <h3>{{ \App\Models\Product::where('is_featured', true)->count() }}</h3>
                    <p>Featured Products</p>
                </div>
                <div class="icon">
                    <i class="fas fa-star"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Quick Actions</h3>
                </div>
                <div class="card-body">
                    <a href="{{ route('admin.products') }}" class="btn btn-primary me-2 mb-2">
                        <i class="fas fa-plus"></i> Add New Product
                    </a>
                    <a href="{{ route('admin.categories') }}" class="btn btn-secondary me-2 mb-2">
                        <i class="fas fa-folder-plus"></i> Manage Categories
                    </a>
                    <a href="{{ route('products') }}" class="btn btn-info me-2 mb-2" target="_blank">
                        <i class="fas fa-external-link-alt"></i> View Frontend
                    </a>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Recent Products</h3>
                </div>
                <div class="card-body">
                    @php
                        $recentProducts = \App\Models\Product::with('category')
                            ->orderBy('created_at', 'desc')
                            ->limit(5)
                            ->get();
                    @endphp

                    @if($recentProducts->count() > 0)
                        <div class="list-group">
                            @foreach($recentProducts as $product)
                                <div class="list-group-item">
                                    <strong>{{ $product->name }}</strong>
                                    <span class="badge bg-secondary ms-2">{{ $product->category->name }}</span>
                                    @if($product->audio_path)
                                        <span class="badge bg-success ms-1">
                                            <i class="fas fa-lock"></i> Encrypted
                                        </span>
                                    @endif
                                    <br>
                                    <small class="text-muted">
                                        Created {{ $product->created_at->diffForHumans() }}
                                    </small>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-muted">No products created yet.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
@stop

@section('css')
    <style>
        .small-box {
            border-radius: 10px;
        }
    </style>
@stop
