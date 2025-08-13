<div>
@section('title', 'Category Management')

@section('content_header')
    <h1>TTS Category Management</h1>
@stop

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <!-- Flash Messages -->
            @if (session()->has('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle"></i> {{ session('success') }}
                    <button type="button" class="close" data-dismiss="alert">
                        <span>&times;</span>
                    </button>
                </div>
            @endif

            @if (session()->has('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle"></i> {{ session('error') }}
                    <button type="button" class="close" data-dismiss="alert">
                        <span>&times;</span>
                    </button>
                </div>
            @endif

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-tags"></i> TTS Category Management
                    </h3>
                    <div class="card-tools">
                        <button class="btn btn-outline-info btn-sm" wire:click="testConnection">
                            <i class="fas fa-wifi"></i> Test API Connection
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Add New Category Form -->
                    <div class="row mb-4">
                        <div class="col-md-8">
                            <div class="input-group">
                                <input type="text" wire:model="categoryName" class="form-control" 
                                       placeholder="Enter new category name">
                                <div class="input-group-append">
                                    <button class="btn btn-primary" wire:click="addCategory" type="button">
                                        <i class="fas fa-plus"></i> Add Category
                                    </button>
                                </div>
                            </div>
                            @error('categoryName') 
                                <small class="text-danger">{{ $message }}</small> 
                            @enderror
                        </div>
                        <div class="col-md-4">
                            <button class="btn btn-secondary" wire:click="loadCategories" type="button">
                                <i class="fas fa-sync"></i> Refresh List
                            </button>
                        </div>
                    </div>

                    <!-- Categories List -->
                    @if(!empty($categories))
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Category Name</th>
                                        <th>Language</th>
                                        <th>Created At</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($categories as $category)
                                        <tr>
                                            <td>
                                                <code>{{ Str::limit($category['_id'] ?? '', 10) }}</code>
                                            </td>
                                            <td>
                                                @if($editingCategoryId === $category['_id'])
                                                    <div class="input-group input-group-sm">
                                                        <input type="text" wire:model="editingCategoryName" 
                                                               class="form-control">
                                                        <div class="input-group-append">
                                                            <button class="btn btn-success btn-sm" 
                                                                    wire:click="updateCategory">
                                                                <i class="fas fa-check"></i>
                                                            </button>
                                                            <button class="btn btn-secondary btn-sm" 
                                                                    wire:click="cancelEdit">
                                                                <i class="fas fa-times"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                    @error('editingCategoryName') 
                                                        <small class="text-danger">{{ $message }}</small> 
                                                    @enderror
                                                @else
                                                    <strong>{{ $category['category'] ?? 'Unnamed' }}</strong>
                                                @endif
                                            </td>
                                            <td>
                                                <span class="badge badge-info">
                                                    {{ $category['language'] ?? 'en-US' }}
                                                </span>
                                            </td>
                                            <td>
                                                @if(isset($category['createdAt']))
                                                    {{ \Carbon\Carbon::parse($category['createdAt'])->format('M j, Y g:i A') }}
                                                @else
                                                    <span class="text-muted">Unknown</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($editingCategoryId !== $category['_id'])
                                                    <div class="btn-group btn-group-sm" role="group">
                                                        <button wire:click="editCategory('{{ $category['_id'] }}')" 
                                                                class="btn btn-warning btn-sm" title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button wire:click="deleteCategory('{{ $category['_id'] }}')" 
                                                                class="btn btn-danger btn-sm" 
                                                                onclick="return confirm('Are you sure you want to delete this category? This action cannot be undone.')"
                                                                title="Delete">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            No categories found. Add a new category or check your API connection.
                        </div>
                    @endif
                </div>
                
                @if(!empty($categories))
                    <div class="card-footer">
                        <small class="text-muted">
                            <i class="fas fa-info-circle"></i>
                            Total categories: {{ count($categories) }}
                        </small>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@stop
</div>
