<div>
    @if (session()->has('message'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('message') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if (session()->has('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="card">
        <div class="card-header">
            <div class="row">
                <div class="col-md-6">
                    <h3 class="card-title">Products</h3>
                </div>
                <div class="col-md-6 text-end">
                    @if($showForm)
                        <button wire:click="cancel" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    @else
                        <button wire:click="create" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Add Product
                        </button>
                    @endif
                </div>
            </div>
        </div>

        @if($showForm)
            <div class="card-body">
                <h4>{{ $editingProduct ? 'Edit Product' : 'Create New Product' }}</h4>
                
                <form wire:submit.prevent="save">
                    <div class="row">
                        <div class="col-md-8">
                            <!-- Basic Information -->
                            <div class="card">
                                <div class="card-header">
                                    <h5>Basic Information</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row mb-3">
                                        <div class="col-md-8">
                                            <label class="form-label">Product Name *</label>
                                            <input type="text" wire:model="name" class="form-control @error('name') is-invalid @enderror">
                                            @error('name') <span class="invalid-feedback">{{ $message }}</span> @enderror
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Category *</label>
                                            <select wire:model="category_id" class="form-control @error('category_id') is-invalid @enderror">
                                                <option value="">Select Category</option>
                                                @foreach($categories as $category)
                                                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                                                @endforeach
                                            </select>
                                            @error('category_id') <span class="invalid-feedback">{{ $message }}</span> @enderror
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Short Description</label>
                                        <input type="text" wire:model="short_description" class="form-control @error('short_description') is-invalid @enderror" maxlength="500">
                                        @error('short_description') <span class="invalid-feedback">{{ $message }}</span> @enderror
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Description</label>
                                        <textarea wire:model="description" class="form-control @error('description') is-invalid @enderror" rows="4"></textarea>
                                        @error('description') <span class="invalid-feedback">{{ $message }}</span> @enderror
                                    </div>

                                    <div class="row mb-3">
                                        <div class="col-md-4">
                                            <label class="form-label">Price *</label>
                                            <div class="input-group">
                                                <span class="input-group-text">$</span>
                                                <input type="number" wire:model="price" class="form-control @error('price') is-invalid @enderror" step="0.01" min="0">
                                            </div>
                                            @error('price') <span class="invalid-feedback">{{ $message }}</span> @enderror
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Sale Price</label>
                                            <div class="input-group">
                                                <span class="input-group-text">$</span>
                                                <input type="number" wire:model="sale_price" class="form-control @error('sale_price') is-invalid @enderror" step="0.01" min="0">
                                            </div>
                                            @error('sale_price') <span class="invalid-feedback">{{ $message }}</span> @enderror
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Preview Duration (seconds) *</label>
                                            <input type="number" wire:model="preview_duration" class="form-control @error('preview_duration') is-invalid @enderror" min="10" max="120">
                                            @error('preview_duration') <span class="invalid-feedback">{{ $message }}</span> @enderror
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Audio Files -->
                            <div class="card">
                                <div class="card-header">
                                    <h5>Audio Files</h5>
                                </div>
                                <div class="card-body">
                                    <!-- Selected Original File -->
                                    <div class="mb-3">
                                        <label class="form-label">Main Audio File</label>
                                        <div class="d-flex align-items-center gap-2">
                                            @if($selectedOriginalFile)
                                                <span class="badge bg-success">
                                                    <i class="fas fa-file-audio"></i> {{ basename($selectedOriginalFile) }}
                                                </span>
                                                <button type="button" wire:click="$set('selectedOriginalFile', '')" class="btn btn-sm btn-outline-danger">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            @else
                                                <span class="text-muted">No file selected</span>
                                            @endif
                                            <button type="button" wire:click="$set('showFileBrowser', true)" class="btn btn-sm btn-primary">
                                                <i class="fas fa-folder-open"></i> Browse Server Files
                                            </button>
                                        </div>
                                        <small class="text-muted">Select an audio file from the server storage. Files will be encrypted when saved.</small>
                                    </div>

                                    <!-- Legacy File Upload -->
                                    <div class="mb-3">
                                        <label class="form-label">Or Upload New File</label>
                                        <input type="file" wire:model="full_file" class="form-control @error('full_file') is-invalid @enderror" accept=".mp3,.wav,.ogg">
                                        @error('full_file') <span class="invalid-feedback">{{ $message }}</span> @enderror
                                        <small class="text-muted">Upload a new audio file (will be encrypted automatically)</small>
                                    </div>

                                    <!-- Preview File -->
                                    <div class="mb-3">
                                        <label class="form-label">Preview Image</label>
                                        <input type="file" wire:model="preview_file" class="form-control @error('preview_file') is-invalid @enderror" accept="image/*">
                                        @error('preview_file') <span class="invalid-feedback">{{ $message }}</span> @enderror
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <!-- Audio Features -->
                            <div class="card">
                                <div class="card-header">
                                    <h5>Audio Features</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="form-label">Audio Type</label>
                                        <input type="text" wire:model="audio_type" class="form-control" placeholder="e.g., Meditation Music">
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Features</label>
                                        @foreach($audioFeatureOptions as $key => $label)
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" wire:model="audio_features" value="{{ $key }}" id="feature_{{ $key }}">
                                                <label class="form-check-label" for="feature_{{ $key }}">
                                                    {{ $label }}
                                                </label>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>

                            <!-- Settings -->
                            <div class="card">
                                <div class="card-header">
                                    <h5>Settings</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" wire:model="is_active" id="is_active">
                                            <label class="form-check-label" for="is_active">
                                                Active
                                            </label>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" wire:model="is_featured" id="is_featured">
                                            <label class="form-check-label" for="is_featured">
                                                Featured
                                            </label>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Sort Order</label>
                                        <input type="number" wire:model="sort_order" class="form-control" min="0">
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Tags</label>
                                        <input type="text" wire:model="tags" class="form-control" placeholder="Comma separated tags">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-12">
                            <div class="d-flex justify-content-end gap-2">
                                <button type="button" wire:click="cancel" class="btn btn-secondary">Cancel</button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> {{ $editingProduct ? 'Update' : 'Create' }} Product
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        @else
            <div class="card-body">
                <!-- Filters -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <input type="text" wire:model.live="search" class="form-control" placeholder="Search products...">
                    </div>
                    <div class="col-md-4">
                        <select wire:model.live="filterCategory" class="form-control">
                            <option value="">All Categories</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <!-- Products Table -->
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Category</th>
                                <th>Price</th>
                                <th>Status</th>
                                <th>Audio File</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($products as $product)
                                <tr>
                                    <td>
                                        <strong>{{ $product->name }}</strong>
                                        @if($product->is_featured)
                                            <span class="badge bg-warning ms-1">Featured</span>
                                        @endif
                                    </td>
                                    <td>{{ $product->category->name }}</td>
                                    <td>
                                        @if($product->sale_price)
                                            <span class="text-primary">${{ number_format($product->sale_price, 2) }}</span>
                                            <span class="text-muted text-decoration-line-through ms-1">${{ number_format($product->price, 2) }}</span>
                                        @else
                                            <span class="text-primary">${{ number_format($product->price, 2) }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($product->is_active)
                                            <span class="badge bg-success">Active</span>
                                        @else
                                            <span class="badge bg-secondary">Inactive</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($product->audio_path)
                                            <span class="badge bg-success">
                                                <i class="fas fa-lock"></i> Encrypted
                                            </span>
                                        @else
                                            <span class="badge bg-warning">No File</span>
                                        @endif
                                    </td>
                                    <td>
                                        <button wire:click="edit({{ $product->id }})" class="btn btn-sm btn-primary">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button wire:click="delete({{ $product->id }})" class="btn btn-sm btn-danger" 
                                                onclick="return confirm('Are you sure you want to delete this product?')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center">No products found</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{ $products->links() }}
            </div>
        @endif
    </div>

    <!-- File Browser Modal -->
    <div>DEBUG: showFileBrowser = {{ $showFileBrowser ? 'TRUE' : 'FALSE' }}</div>
    @if($showFileBrowser)
        <div class="modal fade show" style="display: block;" tabindex="-1">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Select Audio File</h5>
                    <button type="button" class="btn-close" wire:click="$set('showFileBrowser', false)"></button>
                </div>
                <div class="modal-body">
                    @livewire('admin.audio-file-browser')
                </div>
            </div>
        </div>
    </div>
    <div class="modal-backdrop fade show"></div>
@endif

<script>
// Auto-close alerts after 5 seconds
setTimeout(function() {
    var alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        var bsAlert = new bootstrap.Alert(alert);
        bsAlert.close();
    });
}, 5000);
</script>
</div>
