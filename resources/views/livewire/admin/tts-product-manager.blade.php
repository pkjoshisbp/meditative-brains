<div>
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

    {{-- ══════════════════════════════════════════════════════════
         HOW IT WORKS — collapsible flow reference
    ══════════════════════════════════════════════════════════ --}}
    @if (!$showForm)
    <div class="card card-secondary card-outline mb-3">
        <div class="card-header" style="cursor:pointer;" data-toggle="collapse" data-target="#tts-how-it-works">
            <h3 class="card-title">
                <i class="fas fa-info-circle mr-2 text-info"></i>
                How TTS Audio Products Work
            </h3>
            <div class="card-tools">
                <button type="button" class="btn btn-tool" data-toggle="collapse" data-target="#tts-how-it-works">
                    <i class="fas fa-plus"></i>
                </button>
            </div>
        </div>
        <div class="collapse" id="tts-how-it-works">
            <div class="card-body pb-2">
                <div class="row">
                    {{-- Flow steps --}}
                    <div class="col-lg-7">
                        <h6 class="fw-bold text-secondary mb-3"><i class="fas fa-route mr-1"></i> Full Flow (Backend → Customer)</h6>
                        <div class="d-flex flex-column gap-2">

                            <div class="d-flex align-items-start">
                                <span class="badge badge-primary mr-3 mt-1" style="min-width:26px;font-size:13px;">1</span>
                                <div>
                                    <strong>Node.js TTS Backend (mentalfitness.store:3001)</strong><br>
                                    <small class="text-muted">
                                        Stores <em>categories</em> (e.g. "Positive Attitude") and <em>motivation messages</em> per category in MongoDB.
                                        Each message object has many text lines + speaker/SSML settings.
                                        Accessed via <code>/api/category</code> and <code>/api/motivationMessage</code>.
                                    </small>
                                </div>
                            </div>

                            <div class="d-flex align-items-start mt-2">
                                <span class="badge badge-info mr-3 mt-1" style="min-width:26px;font-size:13px;">2</span>
                                <div>
                                    <strong>TTS Products Page (here) — Sync + Link</strong><br>
                                    <small class="text-muted">
                                        On page load (when backend is connected) backend source categories are auto-synced into the <code>tts_audio_products</code> table.
                                        Each product keeps a source link via <code>backend_category_id</code>, but the storefront <em>Category</em> and product <em>Name</em> can now be managed separately in Laravel.
                                        The <em>Message Count</em> column shows how many motivation messages that category has.
                                        <br>Press <strong>Refresh Counts</strong> to re-fetch counts, or <strong>Manual Sync</strong> to sync new categories.
                                    </small>
                                </div>
                            </div>

                            <div class="d-flex align-items-start mt-2">
                                <span class="badge badge-warning mr-3 mt-1" style="min-width:26px;font-size:13px;">3</span>
                                <div>
                                    <strong>Audio Generation (TTS Messages page)</strong><br>
                                    <small class="text-muted">
                                        Go to <a href="{{ route('admin.tts.messages') }}" target="_blank">TTS Messages</a> to write/edit motivation messages for each category.
                                        The backend synthesises each message line into speech using Azure/Neural voices.
                                        The generated audio files are streamed from <code>mentalfitness.store:3001/api/tts/audio/&lt;id&gt;.mp3</code>.
                                    </small>
                                </div>
                            </div>

                            <div class="d-flex align-items-start mt-2">
                                <span class="badge badge-success mr-3 mt-1" style="min-width:26px;font-size:13px;">4</span>
                                <div>
                                    <strong>Storage: original/ &amp; encrypted/</strong><br>
                                    <small class="text-muted">
                                        <code>storage/app/audio/original/</code> — Raw generated <code>.mp3</code> files per category (used for admin preview &amp; as the source for encryption).<br>
                                        <code>storage/app/audio/encrypted/</code> — AES-256 <code>.enc</code> files served to paying customers via signed streaming URLs.
                                        The <strong>Audio Stream Controller</strong> decrypts on-the-fly; the key never hits the browser.
                                        Only users with a valid subscription/purchase can request a signed stream URL.
                                    </small>
                                </div>
                            </div>

                            <div class="d-flex align-items-start mt-2">
                                <span class="badge badge-secondary mr-3 mt-1" style="min-width:26px;font-size:13px;">5</span>
                                <div>
                                    <strong>Customer Listening</strong><br>
                                    <small class="text-muted">
                                        When a customer opens a product on the frontend, the player calls <code>/audio/signed-stream</code> with a short-lived signed token.
                                        The server checks their subscription/purchase, then decrypts the <code>.enc</code> file and streams the audio.
                                        Background music is layered in the browser via the JS player (not baked into the file).
                                    </small>
                                </div>
                            </div>

                        </div>
                    </div>

                    {{-- Quick-action guide --}}
                    <div class="col-lg-5 mt-3 mt-lg-0">
                        <h6 class="fw-bold text-secondary mb-3"><i class="fas fa-tasks mr-1"></i> Quick Reference</h6>
                        <table class="table table-sm table-bordered">
                            <thead class="thead-light"><tr><th>Goal</th><th>Action</th></tr></thead>
                            <tbody>
                                <tr>
                                    <td>Sync new backend categories</td>
                                    <td><span class="badge badge-primary">Manual Sync</span></td>
                                </tr>
                                <tr>
                                    <td>Fix "0 messages" count</td>
                                    <td><span class="badge badge-secondary">Refresh Counts</span></td>
                                </tr>
                                <tr>
                                    <td>Add/edit motivation messages</td>
                                    <td><a href="{{ route('admin.tts.messages') }}" target="_blank">TTS Messages →</a></td>
                                </tr>
                                <tr>
                                    <td>Generate audio from messages</td>
                                    <td>Edit product → Generate Audio tab</td>
                                </tr>
                                <tr>
                                    <td>Encrypt generated audio</td>
                                    <td>Generated .mp3 in <code>original/</code> → <code>encrypted/</code> via Encrypt button</td>
                                </tr>
                                <tr>
                                    <td>Sell / give access to product</td>
                                    <td><a href="{{ route('admin.subscriptions') }}">User Subscriptions →</a></td>
                                </tr>
                            </tbody>
                        </table>

                        <div class="callout callout-warning mt-2">
                            <h6><i class="fas fa-exclamation-triangle mr-1"></i> Message count shows 0?</h6>
                            <ol class="mb-0 pl-3 small">
                                <li>Confirm Backend Status is <strong>Connected</strong> above.</li>
                                <li>Click <strong>Refresh Counts</strong>.</li>
                                <li>If still 0 — go to <a href="{{ route('admin.tts.messages') }}" target="_blank">TTS Messages</a> and add messages to the linked category.</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>
        </div>
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
                        <button wire:click="fixLanguageCodes" class="btn btn-sm btn-warning ml-1"
                                onclick="return confirm('This will update language fields for \'en\' products based on available audio in the Node.js backend. Continue?')"
                                title="Fix language codes for products stored as 'en' by detecting the actual voice locale from the backend">
                            <i class="fas fa-language"></i> Fix Language Codes
                        </button>
                    </div>
                </div>
                @if ($backendConnected)
                    <div class="card-body">
                        <p class="text-success">Successfully connected to TTS backend on mentalfitness.store:3001</p>
                    </div>
                @else
                    <div class="card-body">
                        <p class="text-danger">Cannot connect to TTS backend. Please ensure the backend server is running on mentalfitness.store:3001.</p>
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
                                <th>Language</th>
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
                                        <br><small class="text-muted">Storefront category: {{ $product->category ?: 'Unassigned' }}</small>
                                        @if ($product->short_description)
                                            <br><small class="text-muted">{{ Str::limit($product->short_description, 50) }}</small>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge badge-secondary">{{ $product->language ?: 'N/A' }}</span>
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
                                            @if ($product->backend_category_name)
                                                <br><small class="text-muted">Source: {{ $product->backend_category_name }}</small>
                                            @endif
                                            @php $urlCount = is_array($product->audio_urls) ? count($product->audio_urls) : (is_string($product->audio_urls) ? count(json_decode($product->audio_urls, true) ?? []) : 0); @endphp
                                            @if ($urlCount > 0)
                                                <br><small class="text-success">{{ $urlCount }} URLs</small>
                                            @else
                                                <br><small class="text-warning">No local URLs</small>
                                                <br>
                                                <button wire:click="syncExistingAudioUrls({{ $product->id }})" class="btn btn-xs btn-outline-primary mt-1">
                                                    Sync Local URLs
                                                </button>
                                            @endif
                                        @else
                                            <span class="badge badge-secondary">Not Linked</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <button onclick="ttsRowPlay({{ $product->id }}, this)" 
                                                    class="btn btn-sm btn-info tts-row-play-btn"
                                                    data-product-id="{{ $product->id }}"
                                                    title="Play audio preview">
                                                <i class="fas fa-play"></i>
                                            </button>
                                            <button onclick="ttsStopPreview()" 
                                                    class="btn btn-sm btn-dark"
                                                    title="Stop playback">
                                                <i class="fas fa-stop"></i>
                                            </button>
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
                                        <small class="form-text text-muted">Customer-facing audio title, for example: Respiratory Healing &amp; Oxygenation</small>
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
                                        <small class="form-text text-muted">Storefront grouping, for example: Quit Smoking. If left empty, it will use the product name.</small>
                                    </div>

                                    <div class="form-group">
                                        <label for="backend_category_name">Backend Source Category</label>
                                        <input wire:model="backend_category_name" type="text" class="form-control @error('backend_category_name') is-invalid @enderror"
                                               id="backend_category_name" placeholder="Node/Mongo source category" readonly>
                                        @error('backend_category_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                        <small class="form-text text-muted">This is the source category used to fetch messages and generated tracks from the current Node backend.</small>
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
                                                <label for="background_music_track">Background Music Track</label>
                                                <select wire:model="background_music_track" class="form-control" id="background_music_track">
                                                    @forelse ($bgMusicFiles as $track)
                                                        <option value="{{ $track }}">{{ $track }}</option>
                                                    @empty
                                                        <option value="">No tracks found</option>
                                                    @endforelse
                                                </select>
                                                <small class="form-text text-muted d-block">Files scanned from storage/app/bg-music/original</small>
                                                <button type="button" wire:click="refreshBgMusicFiles" class="btn btn-sm btn-outline-secondary mt-1">Refresh Tracks</button>
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
                                                <div class="mb-2">
                                                    <div id="tts-custom-player" class="border rounded p-2" style="background:#181818;color:#eee;">
                                                        <div class="d-flex align-items-center mb-1">
                                                            <div class="flex-grow-1" style="position:relative;height:14px;" aria-label="Preview progress">
                                                                <div style="position:absolute;left:0;top:0;right:0;bottom:0;background:#333;border-radius:7px;overflow:hidden;">
                                                                    <div id="tts-progress-fill" style="width:0%;height:100%;background:linear-gradient(90deg,#00aaff,#00dd88);"></div>
                                                                </div>
                                                                <div id="tts-progress-handle" style="position:absolute;top:50%;left:0%;transform:translate(-50%,-50%);width:12px;height:12px;border-radius:50%;background:#fff;box-shadow:0 0 4px rgba(0,0,0,.6);"></div>
                                                            </div>
                                                            <div class="ml-2 small" style="width:70px;text-align:right;">
                                                                <span id="tts-time-elapsed-inline">0:00</span>/<span id="tts-time-total-inline">0:00</span>
                                                            </div>
                                                        </div>
                                                        <div class="small" id="bg-music-status" style="color:#9ecfff; font-weight:500;"></div>
                                                    </div>
                                                </div>
                                                <button type="button" wire:click="playExistingAudio" class="btn btn-success btn-sm">
                                                    <i class="fas fa-play"></i> Start Preview
                                                </button>
                                                <button type="button" onclick="ttsStopPreview()" class="btn btn-dark btn-sm ml-1" title="Stop">
                                                    <i class="fas fa-stop"></i> Stop
                                                </button>
                                                <button type="button" onclick="ttsPauseResumePreview()" id="tts-pause-btn" class="btn btn-secondary btn-sm ml-1" title="Pause/Resume">
                                                    <i class="fas fa-pause"></i> Pause
                                                </button>
                                                <button type="button" wire:click="generateAudioPreview" class="btn btn-outline-info btn-sm ml-2">
                                                    <i class="fas fa-sync"></i> Rebuild Preview
                                                </button>
                                                <button type="button" wire:click="syncExistingAudioUrls({{ $editingProduct->id }})" class="btn btn-outline-primary btn-sm ml-2">
                                                    <i class="fas fa-link"></i> Sync Local URLs
                                                </button>
                                                @if (!$editingProduct->preview_audio_url && !$editingProduct->audio_urls)
                                                    <small class="text-muted ml-2">(audio loaded live from backend)</small>
                                                @endif
                                                
                                                <a href="{{ route('admin.tts.messages', ['category_id' => $backend_category_id, 'category_name' => $backend_category_name ?: ($backendCategory['category'] ?? null)]) }}" target="_blank" class="btn btn-secondary btn-sm ml-2">
                                                    <i class="fas fa-external-link-alt"></i> Open TTS Messages
                                                </a>
                                                
                                                <small class="form-text text-muted mt-2">
                                                    <strong>Load Audio Preview:</strong> Uses existing audio files stored in the database.<br>
                                                    If no audio available, tries Node.js backend directly.<br>
                                                    If still unavailable, generate them in the <strong>TTS Messages</strong> section.
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
                                                    <div id="bg-music-status" class="mt-1 small text-muted"></div>
                                                    <div class="mt-2">
                                                        <div id="tts-progress-wrapper" style="display:none;">
                                                            <div class="d-flex justify-content-between"><small id="tts-progress-label">Preview Progress</small><small><span id="tts-time-elapsed">0:00</span> / <span id="tts-time-total">0:00</span></small></div>
                                                            <div class="progress" style="height:8px;">
                                                                <div id="tts-progress-bar" class="progress-bar bg-info" role="progressbar" style="width:0%"></div>
                                                            </div>
                                                        </div>
                                                    </div>
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
                                        <p><strong>Source Category:</strong> {{ $backend_category_name ?: ($backendCategory['category'] ?? 'N/A') }}</p>
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
            let isPaused = false;
            let currentPlayBtn = null; // currently active play button in row

            // ── Global mini-player bar ──────────────────────────────────────
            function showMiniPlayer(title) {
                let bar = document.getElementById('tts-mini-player');
                if (!bar) {
                    bar = document.createElement('div');
                    bar.id = 'tts-mini-player';
                    bar.style.cssText = 'position:fixed;bottom:0;left:0;right:0;z-index:9999;background:#1a1a2e;color:#eee;padding:8px 16px;display:flex;align-items:center;gap:10px;box-shadow:0 -2px 8px rgba(0,0,0,.4);font-size:13px;';
                    bar.innerHTML = `
                        <i class="fas fa-music text-info"></i>
                        <span id="tts-mini-title" style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"></span>
                        <button onclick="ttsPauseResumePreview()" id="tts-mini-pause" class="btn btn-sm btn-secondary" style="min-width:70px;">
                            <i class="fas fa-pause"></i> Pause
                        </button>
                        <button onclick="ttsStopPreview()" class="btn btn-sm btn-danger">
                            <i class="fas fa-stop"></i> Stop
                        </button>`;
                    document.body.appendChild(bar);
                }
                document.getElementById('tts-mini-title').textContent = '▶ Now Playing: ' + (title || 'Audio Preview');
                bar.style.display = 'flex';
            }

            function hideMiniPlayer() {
                const bar = document.getElementById('tts-mini-player');
                if (bar) bar.style.display = 'none';
            }

            function updatePauseBtn(paused) {
                ['tts-pause-btn', 'tts-mini-pause'].forEach(id => {
                    const btn = document.getElementById(id);
                    if (!btn) return;
                    btn.innerHTML = paused
                        ? '<i class="fas fa-play"></i> Resume'
                        : '<i class="fas fa-pause"></i> Pause';
                });
            }
            
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
                console.log('[TTS] Received playSequentialAudio event', event);
                playSequentialAudio(event.config);
            });
            // Row quick-play triggered by ttsRowPlay() JS function
            Livewire.on('quickPlayProduct', (event) => {
                Livewire.dispatch('quickPlayLivewire', { id: event.id });
            });
            // Proxy for custom play button (ensures server side picks latest data)
            Livewire.on('playExistingAudioProxy', () => {
                Livewire.dispatch('playExistingAudio');
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
                isPaused  = false;
                hideMiniPlayer();
                updatePauseBtn(false);
                // Restore all row play buttons to play icon
                document.querySelectorAll('.tts-row-play-btn').forEach(btn => {
                    btn.innerHTML = '<i class="fas fa-play"></i>';
                    btn.classList.remove('btn-warning');
                    btn.classList.add('btn-info');
                });
                currentPlayBtn = null;
            }
                // Expose globally for Stop button
                window.ttsStopPreview = stopAllAudio;

                window.ttsPauseResumePreview = function() {
                    if (!currentAudio) return;
                    if (isPaused) {
                        currentAudio.play().catch(() => {});
                        if (backgroundMusic) backgroundMusic.play().catch(() => {});
                        isPaused = false;
                        isPlaying = true;
                    } else {
                        currentAudio.pause();
                        if (backgroundMusic) backgroundMusic.pause();
                        isPaused = true;
                    }
                    updatePauseBtn(isPaused);
                };

                // Row-level play via JS (avoids Livewire round-trip just to stop first)
                window.ttsRowPlay = function(productId, btn) {
                    stopAllAudio();
                    // Mark new button as active
                    currentPlayBtn = btn;
                    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                    btn.classList.remove('btn-info');
                    btn.classList.add('btn-warning');
                    // Trigger Livewire
                    Livewire.dispatch('quickPlayProduct', { id: productId });
                };
            
        async function playSequentialAudio(config) {
                try {
                    isPlaying = true;
                    isPaused  = false;
                    console.log('Starting sequential audio playback with config:', config);
                    const urls = Array.isArray(config.audioUrls) ? config.audioUrls.filter(u => !!u) : [];
                    if (!urls.length) {
                        console.warn('No audio URLs provided.');
                        stopAllAudio();
                        return;
                    }
                    // Show mini player and update active row button
                    showMiniPlayer(config.previewTitle || 'Audio Preview');
                    if (currentPlayBtn) {
                        currentPlayBtn.innerHTML = '<i class="fas fa-pause"></i>';
                    }
            // Button states
                    const statusEl = document.getElementById('bg-music-status');
                    if (statusEl) statusEl.textContent = config.previewTitle ? ('Preview: ' + config.previewTitle) : 'Preview starting...';

                    // Progress elements
                    const progressWrapper = document.getElementById('tts-progress-wrapper');
                    const progressBar = document.getElementById('tts-progress-bar');
                    const timeElapsedEl = document.getElementById('tts-time-elapsed');
                    const timeTotalEl = document.getElementById('tts-time-total');
                    const totalPreviewSeconds = parseFloat(config.previewDuration || 0);
                    const totalPreviewMs = totalPreviewSeconds * 1000;
                    let startTs = null;
                    let rafId = null;

                    function fmt(sec){
                        const m = Math.floor(sec/60); const s = Math.floor(sec%60).toString().padStart(2,'0'); return `${m}:${s}`;
                    }
                    function tick(){
                        if (!isPlaying || !startTs || totalPreviewMs<=0) return;
                        const elapsed = Date.now() - startTs;
                        const clamped = Math.min(elapsed, totalPreviewMs);
                        const pct = (clamped/totalPreviewMs*100);
                        if (progressBar) progressBar.style.width = pct+"%";
                        if (timeElapsedEl) timeElapsedEl.textContent = fmt(clamped/1000);
                        const elapsedInline = document.getElementById('tts-time-elapsed-inline');
                        if (elapsedInline) elapsedInline.textContent = fmt(clamped/1000);
                        const totalInline = document.getElementById('tts-time-total-inline');
                        if (totalInline && !totalInline.textContent.includes(':')) totalInline.textContent = fmt(totalPreviewSeconds);
                        const handle = document.getElementById('tts-progress-handle');
                        if (handle) handle.style.left = pct+"%";
                        const fill = document.getElementById('tts-progress-fill');
                        if (fill) fill.style.width = pct+"%";
                        if (elapsed < totalPreviewMs && isPlaying) rafId = requestAnimationFrame(tick);
                    }
                    if (totalPreviewMs > 0 && progressWrapper) {
                        progressWrapper.style.display = 'block';
                        if (timeTotalEl) timeTotalEl.textContent = fmt(totalPreviewSeconds);
                        if (timeElapsedEl) timeElapsedEl.textContent = '0:00';
                        if (progressBar) progressBar.style.width = '0%';
                        const inlineElapsed = document.getElementById('tts-time-elapsed-inline');
                        if (inlineElapsed) inlineElapsed.textContent = '0:00';
                        const inlineTotal = document.getElementById('tts-time-total-inline');
                        if (inlineTotal) inlineTotal.textContent = fmt(totalPreviewSeconds);
                        const fill = document.getElementById('tts-progress-fill');
                        if (fill) fill.style.width = '0%';
                        const handle = document.getElementById('tts-progress-handle');
                        if (handle) handle.style.left = '0%';
                        startTs = Date.now();
                        rafId = requestAnimationFrame(tick);
                    } else if (progressWrapper) {
                        progressWrapper.style.display = 'none';
                    }

                    // Background music via secure issue endpoint (encrypted)
                    if (config.hasBackgroundMusic) {
                        const statusEl = document.getElementById('bg-music-status');
                        const typeSlug = (config.backgroundMusicTrack || config.backgroundMusicType || config.category || 'relaxing').toString();
                        if (statusEl) statusEl.textContent = (config.previewTitle ? config.previewTitle + ' – ' : '') + 'loading music...';
                        try {
                            const resp = await fetch(`/bg-music/issue?track=${encodeURIComponent(typeSlug)}`, { credentials: 'include' });
                            if (!resp.ok) throw new Error('Issue endpoint failed');
                            const data = await resp.json();
                            if (data.url) {
                                const audio = new Audio(data.url);
                                audio.loop = true;
                                // Ensure volume is a proper float between 0 and 1
                                const requestedVolRaw = (config.bgMusicVolume !== undefined && config.bgMusicVolume !== null)
                                    ? config.bgMusicVolume : 0.3;
                                let requestedVol = parseFloat(requestedVolRaw);
                                if (isNaN(requestedVol)) requestedVol = 0.3;
                                requestedVol = Math.min(1, Math.max(0, requestedVol));
                                audio.volume = requestedVol;
                                console.log('[TTS] BG music volume applied', { requested: requestedVolRaw, parsed: requestedVol, elementVolume: audio.volume });
                                await audio.play();
                                backgroundMusic = audio;
                                if (statusEl) statusEl.textContent = (config.previewTitle ? config.previewTitle + ' – ' : '') + typeSlug;
                                console.log('[TTS] Background music playing (secure)', typeSlug);
                            } else {
                                throw new Error('No URL in response');
                            }
                        } catch (e) {
                            console.warn('[TTS] Secure BG music failed', e);
                            if (statusEl) statusEl.textContent = (config.previewTitle ? config.previewTitle + ' – ' : '') + 'music unavailable';
                        }
                    }

                    if (config.silenceStart > 0) await sleep(config.silenceStart * 1000);

                    const previewDeadline = Date.now() + (config.previewDuration || 30) * 1000;
                    const repeatCount = Math.max(1, config.messageRepeatCount || 1);

                    // Overall preview deadline already enforced; we reuse existing totalPreviewMs variable (progress) for timeline.

                    outer: for (let i = 0; i < urls.length && isPlaying; i++) {
                        for (let r = 0; r < repeatCount && isPlaying; r++) {
                            if (config.enforceTimeline && Date.now() >= previewDeadline) break outer;
                            const url = urls[i];
                            console.log(`[TTS] Preparing clip ${i+1}/${urls.length} (repeat ${r+1}/${repeatCount})`, url);
                            currentAudio = new Audio();
                            currentAudio.src = url; // removed crossOrigin for simpler playback (was causing CORS block)
                            currentAudio.preload = 'auto';
                            currentAudio.addEventListener('loadeddata', ()=> console.log('[TTS] loadeddata', url));
                            currentAudio.addEventListener('canplaythrough', ()=> console.log('[TTS] canplaythrough', url));
                            currentAudio.addEventListener('stalled', ()=> console.warn('[TTS] stalled', url));
                            currentAudio.addEventListener('suspend', ()=> console.log('[TTS] suspend', url));
                            currentAudio.addEventListener('error', (e)=> console.error('[TTS] audio element error', url, e));
                            // Fade in AFTER play starts (Chrome blocks volume=0 with immediate set? we handle) 
                            const fadeInMs = 0; // disabled
                            const fadeOutMs = 0; // disabled
                            let playPromise;
                            try {
                                if (fadeInMs > 0) currentAudio.volume = 0;
                                playPromise = currentAudio.play();
                                if (playPromise && typeof playPromise.then === 'function') {
                                    playPromise.then(()=> console.log('[TTS] playing', url))
                                                .catch(err => {
                                                    console.warn('[TTS] play() blocked or failed', err.name, err.message);
                                                    if (err.name === 'NotAllowedError') {
                                                        showAutoplayPrompt(config);
                                                    }
                                                });
                                }
                            } catch(e){ console.warn('Play error', e); }
                            if (fadeInMs > 0) fadeAudio(currentAudio, 0, 1, fadeInMs);
                            await new Promise((resolve)=>{
                                currentAudio.onended = resolve;
                                currentAudio.onerror = resolve; // skip on error
                            });
                            if (!isPlaying) break outer;
                            if (fadeOutMs > 0) await fadeAudio(currentAudio, currentAudio.volume, 0, fadeOutMs);
                            if (r < repeatCount -1 && config.repeatInterval > 0) await sleep(config.repeatInterval * 1000);
                        }
                        if (i < urls.length -1 && config.messageInterval > 0) await sleep(config.messageInterval * 1000);
                    }
                    if (config.silenceEnd > 0) await sleep(config.silenceEnd * 1000);
                } catch(err){
                    console.error('Sequential playback error', err);
                } finally {
                    stopAllAudio();
                    // cleanup progress
                    const progressWrapper = document.getElementById('tts-progress-wrapper');
                    if (progressWrapper) {
                        setTimeout(()=>{ progressWrapper.style.display='none'; }, 400);
                    }
                    const statusEl2 = document.getElementById('bg-music-status');
                    if (statusEl2) statusEl2.textContent = 'Preview finished';
                }
            }

            function showAutoplayPrompt(config) {
                let prompt = document.getElementById('tts-autoplay-prompt');
                if (!prompt) {
                    prompt = document.createElement('div');
                    prompt.id = 'tts-autoplay-prompt';
                    prompt.style.position = 'fixed';
                    prompt.style.bottom = '20px';
                    prompt.style.right = '20px';
                    prompt.style.zIndex = 9999;
                    prompt.innerHTML = `
                        <div style="background:#222;color:#fff;padding:12px 16px;border-radius:6px;box-shadow:0 4px 12px rgba(0,0,0,0.3);max-width:260px;font-size:14px;">
                            Audio playback was blocked by the browser.<br><br>
                            <button id="tts-autoplay-btn" class="btn btn-sm btn-primary">Start Preview</button>
                            <button id="tts-autoplay-close" class="btn btn-sm btn-secondary ml-1">X</button>
                        </div>`;
                    document.body.appendChild(prompt);
                    document.getElementById('tts-autoplay-close').onclick = ()=> prompt.remove();
                    document.getElementById('tts-autoplay-btn').onclick = ()=> {
                        prompt.remove();
                        // Retry playback with same config (user gesture now)
                        stopAllAudio();
                        playSequentialAudio(config);
                    };
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
