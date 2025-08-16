<div>
    {{-- DEBUG: Component is rendering --}}
    <div class="alert alert-info">
        <strong>Debug:</strong> TTS Product Manager component is loading... 
        Products count: {{ $products->count() ?? 'N/A' }}
        Backend connected: {{ $backendConnected ? 'Yes' : 'No' }}
    </div>
    
    {{-- Flash Messages --}}
    @if (session()->has('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    @if (session()->has('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    {{-- Backend Connection Status --}}
    <div class="row mb-3">
        <div class="col-md-12">
            <div class="card {{ $backendConnected ? 'card-success' : 'card-danger' }}">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas {{ $backendConnected ? 'fa-check-circle' : 'fa-times-circle' }}"></i>
                        TTS Backend Status: {{ $backendConnected ? 'Connected' : 'Disconnected' }}
                    </h3>
                    <div class="card-tools">
                        <button wire:click="manualSync" class="btn btn-sm btn-primary">
                            <i class="fas fa-sync"></i> Manual Sync
                        </button>
                    </div>
                </div>
                @if ($backendConnected)
                    <div class="card-body">
                        <p class="text-success">Successfully connected to TTS backend</p>
                    </div>
                @else
                    <div class="card-body">
                        <p class="text-danger">Cannot connect to TTS backend</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    @if (!$showForm)
        {{-- Products List --}}
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">TTS Audio Products</h3>
                <div class="card-tools">
                    <button wire:click="create" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add Product
                    </button>
                </div>
            </div>

            <div class="card-body">
                {{-- Search and Filters --}}
                <div class="row mb-3">
                    <div class="col-md-6">
                        <input wire:model.live="search" type="text" class="form-control" placeholder="Search products...">
                    </div>
                    <div class="col-md-3">
                        <select wire:model.live="filterActive" class="form-control">
                            <option value="">All Status</option>
                            <option value="1">Active Only</option>
                            <option value="0">Inactive Only</option>
                        </select>
                    </div>
                </div>

                {{-- Products Table --}}
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Category</th>
                                <th>Price</th>
                                <th>Messages</th>
                                <th>Backend Status</th>
                                <th>Audio Status</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($products as $product)
                                <tr>
                                    <td>
                                        <div>
                                            <strong>{{ $product->name }}</strong>
                                            @if($product->short_description)
                                                <br><small class="text-muted">{{ Str::limit($product->short_description, 50) }}</small>
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge badge-secondary">{{ $product->category }}</span>
                                    </td>
                                    <td>
                                        ${{ number_format($product->price, 2) }}
                                        @if($product->sale_price)
                                            <br><small class="text-success">${{ number_format($product->sale_price, 2) }}</small>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge badge-info">{{ $product->total_messages_count ?? 0 }} messages</span>
                                    </td>
                                    <td>
                                        @if($product->backend_category_id)
                                            <span class="badge badge-success">
                                                <i class="fas fa-link"></i> Linked
                                            </span>
                                        @else
                                            <span class="badge badge-warning">
                                                <i class="fas fa-unlink"></i> Manual
                                            </span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($product->audio_urls || $product->preview_audio_url)
                                            <span class="badge badge-success">
                                                <i class="fas fa-volume-up"></i> Ready
                                            </span>
                                        @else
                                            <span class="badge badge-warning">
                                                <i class="fas fa-volume-mute"></i> No Audio
                                            </span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($product->is_active)
                                            <span class="badge badge-success">Active</span>
                                        @else
                                            <span class="badge badge-secondary">Inactive</span>
                                        @endif
                                        @if ($product->is_featured)
                                            <br><span class="badge badge-primary">Featured</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <button wire:click="edit({{ $product->id }})" class="btn btn-sm btn-warning" title="Edit Product">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            @if($product->backend_category_id)
                                                <button wire:click="generateProductFromMessages('{{ $product->backend_category_id }}')" 
                                                        class="btn btn-sm btn-primary" title="Refresh from Backend Messages">
                                                    <i class="fas fa-sync"></i>
                                                </button>
                                            @endif
                                            @if($product->audio_urls || $product->preview_audio_url)
                                                <button wire:click="generateAudioPreview({{ $product->id }})" class="btn btn-sm btn-info" title="Preview Audio">
                                                    <i class="fas fa-play"></i>
                                                </button>
                                            @endif
                                            <button wire:click="toggleActive({{ $product->id }})" 
                                                    class="btn btn-sm {{ $product->is_active ? 'btn-secondary' : 'btn-success' }}"
                                                    title="{{ $product->is_active ? 'Deactivate' : 'Activate' }}">
                                                <i class="fas {{ $product->is_active ? 'fa-pause' : 'fa-play' }}"></i>
                                            </button>
                                            <button wire:click="delete({{ $product->id }})" 
                                                    class="btn btn-sm btn-danger" 
                                                    onclick="return confirm('Are you sure you want to delete this product?')"
                                                    title="Delete Product">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted">
                                        <div class="py-4">
                                            <i class="fas fa-inbox fa-3x mb-3"></i>
                                            <h5>No products found</h5>
                                            <p>Create your first TTS product or check your backend connection.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Pagination --}}
                @if($products->hasPages())
                    <div class="d-flex justify-content-center">
                        {{ $products->links() }}
                    </div>
                @endif
            </div>
        </div>
    @else
        {{-- Product Form --}}
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    {{ $editingProduct ? 'Edit Product: ' . $editingProduct->name : 'Create New Product' }}
                </h3>
                <div class="card-tools">
                    <button wire:click="cancel" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </div>

            <form wire:submit.prevent="save">
                <div class="card-body">
                    {{-- Basic Information Tab --}}
                    <div class="row">
                        <div class="col-md-8">
                            {{-- Product Details --}}
                            <div class="card">
                                <div class="card-header">
                                    <h4 class="card-title">Product Information</h4>
                                </div>
                                <div class="card-body">
                                    <div class="form-group">
                                        <label for="name">Product Name <span class="text-danger">*</span></label>
                                        <input wire:model="name" type="text" class="form-control @error('name') is-invalid @enderror" 
                                               id="name" required>
                                        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>

                                    <div class="form-group">
                                        <label for="short_description">Short Description</label>
                                        <textarea wire:model="short_description" class="form-control @error('short_description') is-invalid @enderror" 
                                                  id="short_description" rows="2" maxlength="500"></textarea>
                                        @error('short_description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                        <small class="form-text text-muted">Brief description for product cards (max 500 characters)</small>
                                    </div>

                                    <div class="form-group">
                                        <label for="description">Full Description</label>
                                        <textarea wire:model="description" class="form-control @error('description') is-invalid @enderror" 
                                                  id="description" rows="5"></textarea>
                                        @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="category">Category <span class="text-danger">*</span></label>
                                                <input wire:model="category" type="text" class="form-control @error('category') is-invalid @enderror" 
                                                       id="category" required>
                                                @error('category') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="tags">Tags</label>
                                                <input wire:model="tags" type="text" class="form-control @error('tags') is-invalid @enderror" 
                                                       id="tags" placeholder="motivation, meditation, tts">
                                                @error('tags') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                                <small class="form-text text-muted">Comma-separated tags</small>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="price">Price <span class="text-danger">*</span></label>
                                                <div class="input-group">
                                                    <div class="input-group-prepend">
                                                        <span class="input-group-text">$</span>
                                                    </div>
                                                    <input wire:model="price" type="number" step="0.01" class="form-control @error('price') is-invalid @enderror" 
                                                           id="price" required>
                                                    @error('price') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="sale_price">Sale Price</label>
                                                <div class="input-group">
                                                    <div class="input-group-prepend">
                                                        <span class="input-group-text">$</span>
                                                    </div>
                                                    <input wire:model="sale_price" type="number" step="0.01" class="form-control @error('sale_price') is-invalid @enderror" 
                                                           id="sale_price">
                                                    @error('sale_price') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="sort_order">Sort Order</label>
                                                <input wire:model="sort_order" type="number" class="form-control @error('sort_order') is-invalid @enderror" 
                                                       id="sort_order">
                                                @error('sort_order') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-check mb-3">
                                                <input wire:model="is_active" type="checkbox" class="form-check-input" id="is_active">
                                                <label class="form-check-label" for="is_active">
                                                    <strong>Active</strong> - Product is visible to customers
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-check mb-3">
                                                <input wire:model="is_featured" type="checkbox" class="form-check-input" id="is_featured">
                                                <label class="form-check-label" for="is_featured">
                                                    <strong>Featured</strong> - Show in featured products section
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Audio Settings --}}
                            <div class="card mt-3">
                                <div class="card-header">
                                    <h4 class="card-title">Audio Settings</h4>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="message_repeat_count">Message Repeat Count</label>
                                                <input wire:model="message_repeat_count" type="number" min="1" max="10" 
                                                       class="form-control @error('message_repeat_count') is-invalid @enderror" 
                                                       id="message_repeat_count">
                                                @error('message_repeat_count') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                                <small class="form-text text-muted">How many times each message repeats</small>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="repeat_interval">Repeat Interval (seconds)</label>
                                                <input wire:model="repeat_interval" type="number" step="0.1" min="0" max="60" 
                                                       class="form-control @error('repeat_interval') is-invalid @enderror" 
                                                       id="repeat_interval">
                                                @error('repeat_interval') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                                <small class="form-text text-muted">Pause between message repeats</small>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="message_interval">Message Interval (seconds)</label>
                                                <input wire:model="message_interval" type="number" step="0.1" min="0" max="300" 
                                                       class="form-control @error('message_interval') is-invalid @enderror" 
                                                       id="message_interval">
                                                @error('message_interval') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                                <small class="form-text text-muted">Pause between different messages</small>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="preview_duration">Preview Duration (seconds)</label>
                                                <input wire:model="preview_duration" type="number" min="10" max="300" 
                                                       class="form-control @error('preview_duration') is-invalid @enderror" 
                                                       id="preview_duration">
                                                @error('preview_duration') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                                <small class="form-text text-muted">How long audio previews should play</small>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="fade_in_duration">Fade In Duration (seconds)</label>
                                                <input wire:model="fade_in_duration" type="number" step="0.1" min="0" max="10" 
                                                       class="form-control @error('fade_in_duration') is-invalid @enderror" 
                                                       id="fade_in_duration">
                                                @error('fade_in_duration') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="fade_out_duration">Fade Out Duration (seconds)</label>
                                                <input wire:model="fade_out_duration" type="number" step="0.1" min="0" max="10" 
                                                       class="form-control @error('fade_out_duration') is-invalid @enderror" 
                                                       id="fade_out_duration">
                                                @error('fade_out_duration') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-check mb-3">
                                        <input wire:model="enable_silence_padding" type="checkbox" class="form-check-input" id="enable_silence_padding">
                                        <label class="form-check-label" for="enable_silence_padding">
                                            <strong>Enable Silence Padding</strong>
                                        </label>
                                    </div>

                                    @if($enable_silence_padding)
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="silence_start">Start Silence (seconds)</label>
                                                    <input wire:model="silence_start" type="number" step="0.1" min="0" max="10" 
                                                           class="form-control @error('silence_start') is-invalid @enderror" 
                                                           id="silence_start">
                                                    @error('silence_start') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="silence_end">End Silence (seconds)</label>
                                                    <input wire:model="silence_end" type="number" step="0.1" min="0" max="10" 
                                                           class="form-control @error('silence_end') is-invalid @enderror" 
                                                           id="silence_end">
                                                    @error('silence_end') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            {{-- Background Music --}}
                            <div class="card mt-3">
                                <div class="card-header">
                                    <h4 class="card-title">Background Music</h4>
                                </div>
                                <div class="card-body">
                                    <div class="form-check mb-3">
                                        <input wire:model="has_background_music" type="checkbox" class="form-check-input" id="has_background_music">
                                        <label class="form-check-label" for="has_background_music">
                                            <strong>Enable Background Music</strong>
                                        </label>
                                    </div>

                                    @if($has_background_music)
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="background_music_type">Music Type</label>
                                                    <select wire:model="background_music_type" class="form-control @error('background_music_type') is-invalid @enderror" 
                                                            id="background_music_type">
                                                        <option value="relaxing">Relaxing</option>
                                                        <option value="meditation">Meditation</option>
                                                        <option value="nature">Nature Sounds</option>
                                                        <option value="ambient">Ambient</option>
                                                    </select>
                                                    @error('background_music_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="bg_music_volume">Music Volume (0.0 - 1.0)</label>
                                                    <input wire:model="bg_music_volume" type="number" step="0.01" min="0" max="1" 
                                                           class="form-control @error('bg_music_volume') is-invalid @enderror" 
                                                           id="bg_music_volume">
                                                    @error('bg_music_volume') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                                </div>
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label for="background_music_url">Background Music URL</label>
                                            <input wire:model="background_music_url" type="url" class="form-control @error('background_music_url') is-invalid @enderror" 
                                                   id="background_music_url" placeholder="https://example.com/music.mp3">
                                            @error('background_music_url') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4">
                            {{-- Backend Integration --}}
                            @if($backendConnected)
                                <div class="card">
                                    <div class="card-header">
                                        <h4 class="card-title">TTS Backend Integration</h4>
                                    </div>
                                    <div class="card-body">
                                        @if($editingProduct && $editingProduct->backend_category_id)
                                            <div class="alert alert-success">
                                                <i class="fas fa-link"></i> <strong>Linked to Backend</strong>
                                                <br>Category: {{ $backendCategory['category'] ?? 'Unknown' }}
                                                <br>Messages: {{ $total_messages_count }} available
                                            </div>

                                            {{-- Preview Messages --}}
                                            @if(count($backendMessages) > 0)
                                                <h6>Preview Messages (first 5):</h6>
                                                <div class="list-group list-group-flush">
                                                    @foreach($backendMessages as $message)
                                                        <div class="list-group-item p-2">
                                                            <small>"{{ Str::limit($message['motivationMsg'], 60) }}"</small>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @endif

                                            {{-- Audio Preview Controls --}}
                                            <div class="mt-3">
                                                <h6>Audio Controls:</h6>
                                                @if($editingProduct->audio_urls || $editingProduct->preview_audio_url)
                                                    <button type="button" wire:click="generateAudioPreview" class="btn btn-info btn-sm btn-block">
                                                        <i class="fas fa-play"></i> Preview Audio
                                                    </button>
                                                @else
                                                    <div class="alert alert-warning">
                                                        <small>No audio files generated yet. Audio will be available after TTS processing.</small>
                                                    </div>
                                                @endif
                                            </div>
                                        @else
                                            <div class="alert alert-info">
                                                <i class="fas fa-info-circle"></i> This is a manual product (not linked to backend messages)
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endif

                            {{-- SEO Settings --}}
                            <div class="card mt-3">
                                <div class="card-header">
                                    <h4 class="card-title">SEO Settings</h4>
                                </div>
                                <div class="card-body">
                                    <div class="form-group">
                                        <label for="meta_title">Meta Title</label>
                                        <input wire:model="meta_title" type="text" class="form-control @error('meta_title') is-invalid @enderror" 
                                               id="meta_title" maxlength="255">
                                        @error('meta_title') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>

                                    <div class="form-group">
                                        <label for="meta_description">Meta Description</label>
                                        <textarea wire:model="meta_description" class="form-control @error('meta_description') is-invalid @enderror" 
                                                  id="meta_description" rows="3" maxlength="500"></textarea>
                                        @error('meta_description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>

                                    <div class="form-group">
                                        <label for="meta_keywords">Meta Keywords</label>
                                        <input wire:model="meta_keywords" type="text" class="form-control @error('meta_keywords') is-invalid @enderror" 
                                               id="meta_keywords">
                                        @error('meta_keywords') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                        <small class="form-text text-muted">Comma-separated keywords</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-footer">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> 
                        {{ $editingProduct ? 'Update Product' : 'Create Product' }}
                    </button>
                    <button type="button" wire:click="cancel" class="btn btn-secondary ml-2">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    @if($editingProduct && ($editingProduct->audio_urls || $editingProduct->preview_audio_url))
                        <button type="button" wire:click="generateAudioPreview" class="btn btn-info ml-2">
                            <i class="fas fa-play"></i> Test Audio Preview
                        </button>
                    @endif
                </div>
            </form>
        </div>
    @endif
</div>

{{-- Audio Preview JavaScript --}}
<script>
document.addEventListener('DOMContentLoaded', function() {
    let currentAudio = null;
    let backgroundMusic = null;
    let audioContext = null;
    let gainNode = null;
    let bgGainNode = null;

    // Listen for Livewire audio preview events
    window.addEventListener('playSequentialAudio', function(event) {
        const config = event.detail.config;
        playSequentialAudio(config);
    });

    function playSequentialAudio(config) {
        // Stop any currently playing audio
        stopAllAudio();

        // Validate configuration
        if (!config.audioUrls || config.audioUrls.length === 0) {
            alert('No audio URLs provided for preview');
            return;
        }

        console.log('Starting sequential audio preview with config:', config);

        // Initialize Web Audio API
        if (!audioContext) {
            audioContext = new (window.AudioContext || window.webkitAudioContext)();
            gainNode = audioContext.createGain();
            bgGainNode = audioContext.createGain();
            gainNode.connect(audioContext.destination);
            bgGainNode.connect(audioContext.destination);
        }

        // Start background music if enabled
        if (config.hasBackgroundMusic && config.backgroundMusicUrl) {
            playBackgroundMusic(config.backgroundMusicUrl, config.bgMusicVolume || 0.3);
        }

        // Play sequential messages
        playMessageSequence(config.audioUrls, config, 0);
    }

    function playBackgroundMusic(url, volume) {
        backgroundMusic = new Audio(url);
        backgroundMusic.volume = volume;
        backgroundMusic.loop = true;
        
        backgroundMusic.play().then(() => {
            console.log('Background music started');
        }).catch(error => {
            console.warn('Background music failed to play:', error);
        });
    }

    function playMessageSequence(audioUrls, config, messageIndex) {
        if (messageIndex >= audioUrls.length) {
            console.log('All messages played');
            return;
        }

        const audioUrl = audioUrls[messageIndex];
        console.log(`Playing message ${messageIndex + 1}/${audioUrls.length}: ${audioUrl}`);

        // Add silence before message if configured
        const silenceDelay = (messageIndex === 0 ? config.silenceStart : 0) || 0;
        
        setTimeout(() => {
            playMessageWithRepeats(audioUrl, config, () => {
                // After this message and its repeats, play next message
                const nextMessageDelay = config.messageInterval || 10;
                setTimeout(() => {
                    playMessageSequence(audioUrls, config, messageIndex + 1);
                }, nextMessageDelay * 1000);
            });
        }, silenceDelay * 1000);
    }

    function playMessageWithRepeats(audioUrl, config, onComplete) {
        let repeatCount = 0;
        const maxRepeats = config.messageRepeatCount || 2;
        
        function playRepeat() {
            if (repeatCount >= maxRepeats) {
                onComplete();
                return;
            }

            const audio = new Audio(audioUrl);
            currentAudio = audio;

            // Apply fade effects
            audio.volume = 0;
            audio.play().then(() => {
                // Fade in
                fadeInAudio(audio, config.fadeInDuration || 0.5);

                audio.onended = () => {
                    repeatCount++;
                    
                    if (repeatCount < maxRepeats) {
                        // Wait for repeat interval, then play again
                        const repeatDelay = config.repeatInterval || 2;
                        setTimeout(playRepeat, repeatDelay * 1000);
                    } else {
                        onComplete();
                    }
                };
            }).catch(error => {
                console.error('Audio playback failed:', error);
                onComplete();
            });
        }

        playRepeat();
    }

    function fadeInAudio(audio, duration) {
        const steps = 20;
        const stepTime = (duration * 1000) / steps;
        const volumeStep = 1.0 / steps;
        let currentStep = 0;

        const fadeInterval = setInterval(() => {
            currentStep++;
            audio.volume = Math.min(currentStep * volumeStep, 1.0);
            
            if (currentStep >= steps) {
                clearInterval(fadeInterval);
            }
        }, stepTime);
    }

    function fadeOutAudio(audio, duration, callback) {
        const steps = 20;
        const stepTime = (duration * 1000) / steps;
        const volumeStep = audio.volume / steps;
        let currentStep = 0;

        const fadeInterval = setInterval(() => {
            currentStep++;
            audio.volume = Math.max(audio.volume - volumeStep, 0);
            
            if (currentStep >= steps || audio.volume <= 0) {
                clearInterval(fadeInterval);
                if (callback) callback();
            }
        }, stepTime);
    }

    function stopAllAudio() {
        if (currentAudio) {
            currentAudio.pause();
            currentAudio = null;
        }
        
        if (backgroundMusic) {
            backgroundMusic.pause();
            backgroundMusic = null;
        }
        
        console.log('All audio stopped');
    }

    // Global function to stop audio (can be called from anywhere)
    window.stopTTSPreview = stopAllAudio;
});
</script>
