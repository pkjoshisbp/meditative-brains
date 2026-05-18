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
                                            <input type="number" wire:model="preview_duration" class="form-control @error('preview_duration') is-invalid @enderror" min="10" max="1800">
                                            @error('preview_duration') <span class="invalid-feedback">{{ $message }}</span> @enderror
                                        </div>
                                    </div>
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Student Price USD</label>
                                            <div class="input-group">
                                                <span class="input-group-text">$</span>
                                                <input type="number" wire:model="student_price" class="form-control @error('student_price') is-invalid @enderror" step="0.01" min="0">
                                            </div>
                                            @error('student_price') <span class="invalid-feedback">{{ $message }}</span> @enderror
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Student Price INR</label>
                                            <div class="input-group">
                                                <span class="input-group-text">₹</span>
                                                <input type="number" wire:model="student_inr_price" class="form-control @error('student_inr_price') is-invalid @enderror" step="0.01" min="0">
                                            </div>
                                            @error('student_inr_price') <span class="invalid-feedback">{{ $message }}</span> @enderror
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
                                        @if($preview_file)
                                            <div class="mt-2">
                                                <img src="{{ $preview_file->temporaryUrl() }}" alt="Preview image upload" class="img-thumbnail" style="max-width: 220px; max-height: 160px; object-fit: cover;">
                                            </div>
                                        @elseif($existingPreviewImageUrl)
                                            <div class="mt-2">
                                                <img src="{{ $existingPreviewImageUrl }}" alt="Current preview image" class="img-thumbnail" style="max-width: 220px; max-height: 160px; object-fit: cover;">
                                            </div>
                                        @endif
                                    </div>

                                    <hr>

                                    <div class="mb-3">
                                        <label class="form-label">Linked Audiobook</label>
                                        <select wire:model.live="linked_audiobook_id" class="form-control @error('linked_audiobook_id') is-invalid @enderror">
                                            <option value="">No linked audiobook</option>
                                            @foreach($audiobooks as $audiobook)
                                                <option value="{{ $audiobook->id }}">
                                                    {{ $audiobook->adminSelectionLabel() }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('linked_audiobook_id') <span class="invalid-feedback">{{ $message }}</span> @enderror
                                        <small class="text-muted">Link an audiobook generated in the TTS audiobook manager.</small>
                                        <div class="mt-1">
                                            <a href="{{ route('admin.tts.audiobook') }}" target="_blank" class="btn btn-sm btn-outline-secondary">
                                                <i class="fas fa-book-open"></i> Open Audiobook Manager
                                            </a>
                                        </div>
                                    </div>

                                    @if($linkedAudiobookPreviewUrl)
                                        <div class="mb-3 p-3 border rounded bg-light">
                                            <div><strong>{{ $linkedAudiobookPreviewTitle }}</strong></div>
                                            <small class="text-muted d-block mb-2">{{ $linkedAudiobookChapterCount }} chapter(s)</small>
                                            <audio controls preload="none" class="w-100" src="{{ $linkedAudiobookPreviewUrl }}"></audio>
                                        </div>
                                    @elseif($linked_audiobook_id)
                                        <div class="mb-3 p-3 border rounded bg-light">
                                            <small class="text-muted">Linked audiobook found, but no playable chapter preview is available yet.</small>
                                        </div>
                                    @endif

                                    <div class="mb-3">
                                        <label class="form-label">PDF Book</label>
                                        <input type="file" wire:model="pdf_book" class="form-control @error('pdf_book') is-invalid @enderror" accept="application/pdf,.pdf">
                                        @error('pdf_book') <span class="invalid-feedback">{{ $message }}</span> @enderror
                                        <small class="text-muted">Upload the PDF book linked to this product.</small>
                                    </div>

                                    @if($pdf_file_url)
                                        <div class="mb-3 p-3 border rounded bg-light">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <strong>Current PDF</strong>
                                                <a href="{{ $pdf_file_url }}" target="_blank" class="btn btn-sm btn-outline-primary">Open PDF</a>
                                            </div>
                                            <iframe src="{{ $pdf_file_url }}#toolbar=0&navpanes=0" style="width: 100%; height: 320px; border: 1px solid #ddd;"></iframe>
                                        </div>
                                    @endif
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
                                <th>Audiobook</th>
                                <th>PDF</th>
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
                                        @if($product->student_price)
                                            <br><small class="text-success">Student: ${{ number_format($product->student_price, 2) }}</small>
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
                                        @if($product->linkedAudiobook)
                                            <span class="badge bg-info text-dark">Linked</span>
                                            <div><small class="text-muted">{{ $product->linkedAudiobook->book_title }}</small></div>
                                        @else
                                            <span class="badge bg-secondary">None</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($product->pdf_file_path)
                                            <span class="badge bg-primary">Attached</span>
                                        @else
                                            <span class="badge bg-secondary">None</span>
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
                                    <td colspan="8" class="text-center">No products found</td>
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
