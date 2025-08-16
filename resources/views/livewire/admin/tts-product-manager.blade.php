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

    @if (session()->has('info'))
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            {{ session('info') }}
            @if (session()->has('info_detail'))
                <br><small>{{ session('info_detail') }}</small>
            @endif
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    {{-- Auto-sync Messages --}}
    @if (!empty($syncMessages))
        <div class="alert alert-info">
            <h5><i class="icon fas fa-sync"></i> Auto-Sync Results</h5>
            @foreach ($syncMessages as $message)
                <div>{{ $message }}</div>
            @endforeach
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
                        <button wire:click="refreshMessageCounts" class="btn btn-sm btn-secondary ml-1">
                            <i class="fas fa-redo"></i> Refresh Counts
                        </button>
                    </div>
                </div>
                @if ($backendConnected)
                    <div class="card-body">
                        <p class="text-success">Successfully connected to TTS backend on meditative-brains.com:3001</p>
                    </div>
                @else
                    <div class="card-body">
                        <p class="text-danger">Cannot connect to TTS backend. Please ensure the backend server is running on meditative-brains.com:3001.</p>
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
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </div>

                {{-- Products Table --}}
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Cover</th>
                                <th>Name</th>
                                <th>Price</th>
                                <th>Messages</th>
                                <th>Status</th>
                                <th>Backend Category</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($products as $product)
                                <tr>
                                    <td>
                                        @if ($product->cover_image_path)
                                            <img src="{{ asset('storage/' . $product->cover_image_path) }}" 
                                                 alt="Cover" class="img-thumbnail" style="max-width: 50px;">
                                        @else
                                            <div class="bg-secondary d-flex align-items-center justify-content-center" 
                                                 style="width: 50px; height: 50px;">
                                                <i class="fas fa-image text-white"></i>
                                            </div>
                                        @endif
                                    </td>
                                    <td>
                                        <strong>{{ $product->name }}</strong>
                                        @if ($product->short_description)
                                            <br><small class="text-muted">{{ Str::limit($product->short_description, 50) }}</small>
                                        @endif
                                    </td>
                                    <td>
                                        <strong>${{ number_format($product->price, 2) }}</strong>
                                        @if ($product->sale_price)
                                            <br><small class="text-success">${{ number_format($product->sale_price, 2) }}</small>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge badge-info">{{ $product->total_messages_count }} messages</span>
                                    </td>
                                    <td>
                                        @if ($product->is_active)
                                            <span class="badge badge-success">Active</span>
                                        @else
                                            <span class="badge badge-secondary">Inactive</span>
                                        @endif
                                        @if ($product->is_featured)
                                            <br><span class="badge badge-warning">Featured</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($product->backend_category_id)
                                            <span class="badge badge-primary">Linked</span>
                                        @else
                                            <span class="badge badge-secondary">Not Linked</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <button wire:click="edit({{ $product->id }})" 
                                                    class="btn btn-sm btn-warning">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button wire:click="toggleActive({{ $product->id }})" 
                                                    class="btn btn-sm {{ $product->is_active ? 'btn-secondary' : 'btn-success' }}">
                                                <i class="fas {{ $product->is_active ? 'fa-eye-slash' : 'fa-eye' }}"></i>
                                            </button>
                                            <button wire:click="delete({{ $product->id }})" 
                                                    class="btn btn-sm btn-danger"
                                                    onclick="return confirm('Are you sure you want to delete this product?')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted">
                                        No products found. 
                                        @if ($backendConnected)
                                            Try syncing with the backend to import categories.
                                        @endif
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Pagination --}}
                <div class="d-flex justify-content-center">
                    {{ $products->links() }}
                </div>
            </div>
        </div>
    @else
        {{-- Product Form --}}
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    {{ $editingProduct ? 'Edit Product' : 'Create Product' }}
                </h3>
                <div class="card-tools">
                    <button wire:click="cancel" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </div>

            <form wire:submit.prevent="save">
                <div class="card-body">
                    <div class="row">
                        {{-- Basic Information --}}
                        <div class="col-md-8">
                            <div class="card">
                                <div class="card-header">
                                    <h4>Basic Information</h4>
                                </div>
                                <div class="card-body">
                                    <div class="form-group">
                                        <label for="name">Name *</label>
                                        <input wire:model="name" type="text" class="form-control @error('name') is-invalid @enderror" id="name">
                                        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>

                                    <div class="form-group">
                                        <label for="short_description">Short Description</label>
                                        <textarea wire:model="short_description" class="form-control @error('short_description') is-invalid @enderror" 
                                                  id="short_description" rows="2" maxlength="500"></textarea>
                                        @error('short_description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>

                                    <div class="form-group">
                                        <label for="description">Full Description</label>
                                        <textarea wire:model="description" class="form-control @error('description') is-invalid @enderror" 
                                                  id="description" rows="4"></textarea>
                                        @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>

                                    <div class="form-group">
                                        <label for="category">Category</label>
                                        <input wire:model="category" type="text" class="form-control @error('category') is-invalid @enderror" 
                                               id="category" placeholder="Audio category">
                                        @error('category') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                        <small class="form-text text-muted">If left empty, will use the product name</small>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="price">Price *</label>
                                                <div class="input-group">
                                                    <div class="input-group-prepend">
                                                        <span class="input-group-text">$</span>
                                                    </div>
                                                    <input wire:model="price" type="number" step="0.01" 
                                                           class="form-control @error('price') is-invalid @enderror" id="price">
                                                </div>
                                                @error('price') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="sale_price">Sale Price</label>
                                                <div class="input-group">
                                                    <div class="input-group-prepend">
                                                        <span class="input-group-text">$</span>
                                                    </div>
                                                    <input wire:model="sale_price" type="number" step="0.01" 
                                                           class="form-control @error('sale_price') is-invalid @enderror" id="sale_price">
                                                </div>
                                                @error('sale_price') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="tags">Tags</label>
                                        <input wire:model="tags" type="text" class="form-control @error('tags') is-invalid @enderror" 
                                               id="tags" placeholder="tts, audio, motivation">
                                        @error('tags') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                        <small class="form-text text-muted">Separate tags with commas</small>
                                    </div>
                                </div>
                            </div>

                            {{-- Audio Settings --}}
                            <div class="card">
                                <div class="card-header">
                                    <h4>Audio Settings</h4>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="preview_duration">Preview Duration (seconds) *</label>
                                                <input wire:model="preview_duration" type="number" min="10" max="300" 
                                                       class="form-control @error('preview_duration') is-invalid @enderror" id="preview_duration">
                                                @error('preview_duration') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="sort_order">Sort Order *</label>
                                                <input wire:model="sort_order" type="number" min="0" 
                                                       class="form-control @error('sort_order') is-invalid @enderror" id="sort_order">
                                                @error('sort_order') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="message_repeat_count">Message Repeat Count *</label>
                                                <input wire:model="message_repeat_count" type="number" min="1" max="10" 
                                                       class="form-control @error('message_repeat_count') is-invalid @enderror" id="message_repeat_count">
                                                @error('message_repeat_count') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                                <small class="form-text text-muted">How many times each message repeats</small>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="repeat_interval">Repeat Interval (seconds) *</label>
                                                <input wire:model="repeat_interval" type="number" step="0.1" min="0" max="60" 
                                                       class="form-control @error('repeat_interval') is-invalid @enderror" id="repeat_interval">
                                                @error('repeat_interval') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                                <small class="form-text text-muted">Pause between message repeats</small>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="message_interval">Message Interval (seconds) *</label>
                                                <input wire:model="message_interval" type="number" step="0.1" min="0" max="300" 
                                                       class="form-control @error('message_interval') is-invalid @enderror" id="message_interval">
                                                @error('message_interval') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                                <small class="form-text text-muted">Pause between different messages</small>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="bg_music_volume">Background Music Volume *</label>
                                                <input wire:model="bg_music_volume" type="number" step="0.01" min="0" max="1" 
                                                       class="form-control @error('bg_music_volume') is-invalid @enderror" id="bg_music_volume">
                                                @error('bg_music_volume') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                                <small class="form-text text-muted">Volume level (0.0 to 1.0)</small>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="fade_in_duration">Fade In Duration (seconds) *</label>
                                                <input wire:model="fade_in_duration" type="number" step="0.1" min="0" max="10" 
                                                       class="form-control @error('fade_in_duration') is-invalid @enderror" id="fade_in_duration">
                                                @error('fade_in_duration') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="fade_out_duration">Fade Out Duration (seconds) *</label>
                                                <input wire:model="fade_out_duration" type="number" step="0.1" min="0" max="10" 
                                                       class="form-control @error('fade_out_duration') is-invalid @enderror" id="fade_out_duration">
                                                @error('fade_out_duration') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="silence_start">Silence Start (seconds) *</label>
                                                <input wire:model="silence_start" type="number" step="0.1" min="0" max="10" 
                                                       class="form-control @error('silence_start') is-invalid @enderror" id="silence_start">
                                                @error('silence_start') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="silence_end">Silence End (seconds) *</label>
                                                <input wire:model="silence_end" type="number" step="0.1" min="0" max="10" 
                                                       class="form-control @error('silence_end') is-invalid @enderror" id="silence_end">
                                                @error('silence_end') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="background_music_url">Background Music URL</label>
                                        <input wire:model="background_music_url" type="url" 
                                               class="form-control @error('background_music_url') is-invalid @enderror" id="background_music_url"
                                               placeholder="https://example.com/background-music.mp3">
                                        @error('background_music_url') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                        <small class="form-text text-muted">Direct URL to background music file</small>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <div class="custom-control custom-switch">
                                                    <input wire:model="has_background_music" type="checkbox" class="custom-control-input" id="has_background_music">
                                                    <label class="custom-control-label" for="has_background_music">Enable Background Music</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="background_music_type">Background Music Type</label>
                                                <select wire:model="background_music_type" class="form-control" id="background_music_type">
                                                    <option value="relaxing">Relaxing</option>
                                                    <option value="meditation">Meditation</option>
                                                    <option value="ambient">Ambient</option>
                                                    <option value="nature">Nature Sounds</option>
                                                    <option value="instrumental">Instrumental</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <div class="custom-control custom-switch">
                                                    <input wire:model="enable_silence_padding" type="checkbox" class="custom-control-input" id="enable_silence_padding">
                                                    <label class="custom-control-label" for="enable_silence_padding">Enable Silence Padding</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Audio Preview Section --}}
                                    @if ($editingProduct && $backend_category_id)
                                        <div class="card mt-3">
                                            <div class="card-header">
                                                <h5>Audio Preview</h5>
                                            </div>
                                            <div class="card-body">
                                                @if ($editingProduct->preview_audio_url || $editingProduct->audio_urls)
                                                    <div class="mb-3">
                                                        @if ($editingProduct->preview_audio_url)
                                                            @php
                                                                $audioUrl = $editingProduct->preview_audio_url;
                                                                if (!str_starts_with($audioUrl, 'http')) {
                                                                    $audioUrl = 'https://motivation.mywebsolutions.co.in:3000/' . ltrim($audioUrl, '/');
                                                                }
                                                            @endphp
                                                            <audio controls class="w-100">
                                                                <source src="{{ $audioUrl }}" type="audio/mpeg">
                                                                Your browser does not support the audio element.
                                                            </audio>
                                                        @elseif ($editingProduct->audio_urls)
                                                            @php
                                                                $audioUrls = json_decode($editingProduct->audio_urls, true);
                                                                $firstAudio = !empty($audioUrls) ? $audioUrls[0] : null;
                                                            @endphp
                                                            @if ($firstAudio)
                                                                <audio controls class="w-100">
                                                                    <source src="{{ $firstAudio }}" type="audio/mpeg">
                                                                    Your browser does not support the audio element.
                                                                </audio>
                                                                <small class="text-muted">Playing first audio from collection</small>
                                                            @endif
                                                        @endif
                                                    </div>
                                                    <button type="button" wire:click="playExistingAudio" class="btn btn-success btn-sm">
                                                        <i class="fas fa-play"></i> Play Audio Preview
                                                    </button>
                                                @else
                                                    <p class="text-muted">No audio files available yet.</p>
                                                @endif
                                                
                                                <button type="button" wire:click="generateAudioPreview" class="btn btn-info btn-sm ml-2">
                                                    <i class="fas fa-headphones"></i> Load Audio Preview
                                                </button>
                                                
                                                <a href="{{ route('admin.tts.messages') }}" target="_blank" class="btn btn-secondary btn-sm ml-2">
                                                    <i class="fas fa-external-link-alt"></i> Open TTS Messages
                                                </a>
                                                
                                                <small class="form-text text-muted mt-2">
                                                    <strong>Load Audio Preview:</strong> Uses existing audio files stored in the database.<br>
                                                    If no audio available, generate them first in the <strong>TTS Messages</strong> section.
                                                </small>
                                                
                                                {{-- Debug Info --}}
                                                <div class="mt-3 p-3 bg-light border rounded">
                                                    <h6>Debug Info (Current Values):</h6>
                                                    <small>
                                                        <strong>BG Music Volume:</strong> {{ $bg_music_volume }}<br>
                                                        <strong>Has BG Music:</strong> {{ $has_background_music ? 'Yes' : 'No' }}<br>
                                                        <strong>BG Music URL:</strong> {{ $background_music_url ?: 'Not set' }}<br>
                                                        <strong>BG Music Type:</strong> {{ $background_music_type }}<br>
                                                        <strong>Message Repeat:</strong> {{ $message_repeat_count }}<br>
                                                        <strong>Total Messages:</strong> {{ $total_messages_count }}
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>

                        {{-- Sidebar --}}
                        <div class="col-md-4">
                            {{-- Image Upload --}}
                            <div class="card">
                                <div class="card-header">
                                    <h4>Cover Image</h4>
                                </div>
                                <div class="card-body">
                                    <div class="form-group">
                                        <input wire:model="cover_image" type="file" accept="image/*" 
                                               class="form-control-file @error('cover_image') is-invalid @enderror">
                                        @error('cover_image') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>

                                    @if ($cover_image)
                                        <div class="mt-2">
                                            <img src="{{ $cover_image->temporaryUrl() }}" class="img-thumbnail" style="max-width: 200px;">
                                        </div>
                                    @elseif ($cover_image_path)
                                        <div class="mt-2">
                                            <img src="{{ asset('storage/' . $cover_image_path) }}" class="img-thumbnail" style="max-width: 200px;">
                                        </div>
                                    @endif
                                </div>
                            </div>

                            {{-- Product Status --}}
                            <div class="card">
                                <div class="card-header">
                                    <h4>Product Status</h4>
                                </div>
                                <div class="card-body">
                                    <div class="form-group">
                                        <div class="custom-control custom-switch">
                                            <input wire:model="is_active" type="checkbox" class="custom-control-input" id="is_active">
                                            <label class="custom-control-label" for="is_active">Active</label>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <div class="custom-control custom-switch">
                                            <input wire:model="is_featured" type="checkbox" class="custom-control-input" id="is_featured">
                                            <label class="custom-control-label" for="is_featured">Featured</label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Backend Category Info --}}
                            @if ($editingProduct && $backend_category_id && $backendCategory)
                                <div class="card">
                                    <div class="card-header">
                                        <h4>Backend Category</h4>
                                    </div>
                                    <div class="card-body">
                                        <p><strong>Name:</strong> {{ $backendCategory['name'] ?? 'N/A' }}</p>
                                        <p><strong>Description:</strong> {{ $backendCategory['description'] ?? 'N/A' }}</p>
                                        <p><strong>Messages:</strong> {{ $total_messages_count }} total @if(!empty($backendMessages)) (showing {{ count($backendMessages) }}) @endif</p>
                                        
                                        @if (!empty($backendMessages))
                                            <h6>Sample Messages ({{ count($backendMessages) }}/{{ $total_messages_count }}):</h6>
                                            <div style="max-height: 200px; overflow-y: auto;">
                                                @foreach ($backendMessages as $message)
                                                    <div class="border-bottom pb-1 mb-1">
                                                        <small>{{ Str::limit($message['text'] ?? 'No text', 100) }}</small>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endif

                            {{-- SEO Settings --}}
                            <div class="card">
                                <div class="card-header">
                                    <h4>SEO Settings</h4>
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
                                               id="meta_keywords" maxlength="255">
                                        @error('meta_keywords') <div class="invalid-feedback">{{ $message }}</div> @enderror
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
                </div>
            </form>
        </div>
    @endif

    {{-- JavaScript for Audio Preview --}}
    <script>
        document.addEventListener('livewire:init', () => {
            let currentAudio = null;
            let backgroundMusic = null;
            let isPlaying = false;
            
            // Legacy single audio preview support
            Livewire.on('playAudioPreview', (event) => {
                stopAllAudio();
                currentAudio = new Audio(event.url);
                currentAudio.play().catch(e => {
                    console.error('Error playing audio:', e);
                    alert('Unable to play audio. Please check the audio file.');
                });
            });
            
            // Advanced sequential audio playback with background music
            Livewire.on('playSequentialAudio', (event) => {
                stopAllAudio();
                playSequentialAudio(event.config);
            });
            
            function stopAllAudio() {
                if (currentAudio) {
                    currentAudio.pause();
                    currentAudio.currentTime = 0;
                    currentAudio = null;
                }
                if (backgroundMusic) {
                    backgroundMusic.pause();
                    backgroundMusic.currentTime = 0;
                    backgroundMusic = null;
                }
                isPlaying = false;
            }
            
            async function playSequentialAudio(config) {
                try {
                    isPlaying = true;
                    console.log('Starting sequential audio playback with config:', config);
                    
                    // Start background music if enabled
                    if (config.hasBackgroundMusic && config.backgroundMusicUrl) {
                        try {
                            backgroundMusic = new Audio(config.backgroundMusicUrl);
                            backgroundMusic.loop = true;
                            backgroundMusic.volume = config.bgMusicVolume || 0.3;
                            await backgroundMusic.play();
                            console.log('Background music started');
                        } catch (e) {
                            console.warn('Could not play background music:', e);
                        }
                    }
                    
                    // Add initial silence
                    if (config.silenceStart > 0) {
                        await sleep(config.silenceStart * 1000);
                    }
                    
                    const startTime = Date.now();
                    const previewDuration = (config.previewDuration || 30) * 1000;
                    
                    // Play messages sequentially
                    for (let i = 0; i < config.audioUrls.length && isPlaying; i++) {
                        // Check if we've exceeded preview duration
                        if (Date.now() - startTime >= previewDuration) {
                            console.log('Preview duration exceeded, stopping playback');
                            break;
                        }
                        
                        const audioUrl = config.audioUrls[i];
                        console.log(`Playing message ${i + 1}/${config.audioUrls.length}: ${audioUrl}`);
                        
                        // Play each message multiple times (repeat count)
                        for (let repeat = 0; repeat < (config.messageRepeatCount || 1) && isPlaying; repeat++) {
                            if (Date.now() - startTime >= previewDuration) break;
                            
                            // Play the message
                            currentAudio = new Audio(audioUrl);
                            
                            // Apply fade in
                            if (config.fadeInDuration > 0) {
                                currentAudio.volume = 0;
                                await fadeAudio(currentAudio, 0, 1, config.fadeInDuration * 1000);
                            }
                            
                            await playAudio(currentAudio);
                            
                            // Apply fade out
                            if (config.fadeOutDuration > 0 && currentAudio) {
                                await fadeAudio(currentAudio, 1, 0, config.fadeOutDuration * 1000);
                            }
                            
                            // Pause between repeats (except on last repeat)
                            if (repeat < config.messageRepeatCount - 1 && config.repeatInterval > 0) {
                                await sleep(config.repeatInterval * 1000);
                            }
                        }
                        
                        // Pause between different messages (except on last message)
                        if (i < config.audioUrls.length - 1 && config.messageInterval > 0) {
                            await sleep(config.messageInterval * 1000);
                        }
                    }
                    
                    // Add final silence
                    if (config.silenceEnd > 0) {
                        await sleep(config.silenceEnd * 1000);
                    }
                    
                    console.log('Sequential audio playback completed');
                    
                } catch (error) {
                    console.error('Error in sequential audio playback:', error);
                } finally {
                    stopAllAudio();
                }
            }
            
            function playAudio(audio) {
                return new Promise((resolve, reject) => {
                    if (!isPlaying) {
                        resolve();
                        return;
                    }
                    
                    audio.onended = () => resolve();
                    audio.onerror = () => reject(new Error('Audio playback failed'));
                    
                    audio.play().catch(reject);
                });
            }
            
            function fadeAudio(audio, startVolume, endVolume, duration) {
                return new Promise((resolve) => {
                    if (!audio || !isPlaying) {
                        resolve();
                        return;
                    }
                    
                    const steps = 20;
                    const stepTime = duration / steps;
                    const volumeStep = (endVolume - startVolume) / steps;
                    
                    let currentStep = 0;
                    audio.volume = startVolume;
                    
                    const fadeInterval = setInterval(() => {
                        if (!isPlaying || !audio || currentStep >= steps) {
                            clearInterval(fadeInterval);
                            resolve();
                            return;
                        }
                        
                        currentStep++;
                        audio.volume = Math.max(0, Math.min(1, startVolume + (volumeStep * currentStep)));
                        
                        if (currentStep >= steps) {
                            clearInterval(fadeInterval);
                            resolve();
                        }
                    }, stepTime);
                });
            }
            
            function sleep(ms) {
                return new Promise(resolve => {
                    if (!isPlaying) {
                        resolve();
                        return;
                    }
                    setTimeout(resolve, ms);
                });
            }
            
            // Stop audio when navigating away
            window.addEventListener('beforeunload', stopAllAudio);
        });
    </script>
</div>
