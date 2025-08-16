<?php

namespace App\Livewire\Admin;

use App\Livewire\AdminComponent;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use Livewire\Attributes\On;
use App\Models\TtsAudioProduct;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class TtsProductManager extends AdminComponent
{
    use WithPagination, WithFileUploads;
    
    protected string $pageTitle = 'TTS Product Manager';
    protected string $pageHeader = 'TTS Audio Products';

    // Form properties
    public $name = '';
    public $description = '';
    public $short_description = '';
    public $category = '';
    public $price = '';
    public $sale_price = '';
    public $tags = '';
    public $preview_duration = 30;
    public $sort_order = 0;
    public $is_active = true;
    public $is_featured = false;
    public $background_music_url = '';
    public $cover_image;
    public $cover_image_path = '';
    public $meta_title = '';
    public $meta_description = '';
    public $meta_keywords = '';
    
    // TTS specific
    public $backend_category_id = '';
    public $total_messages_count = 0;
    
    // Audio settings
    public $bg_music_volume = 0.30;
    public $message_repeat_count = 2;
    public $repeat_interval = 2.00;
    public $message_interval = 10.00;
    public $fade_in_duration = 0.5;
    public $fade_out_duration = 0.5;
    public $enable_silence_padding = true;
    public $silence_start = 1.0;
    public $silence_end = 1.0;
    public $has_background_music = false;
    public $background_music_type = 'relaxing';
    // Background music dynamic files
    public $bgMusicFiles = [];
    public $background_music_track = '';
    
    // Audio URLs
    public $audio_urls = '';
    public $preview_audio_url = '';
    
    // Component state
    public $editingProduct = null;
    public $showForm = false;
    public $search = '';
    public $filterActive = '';
    
    // Backend integration
    public $backendMessages = [];
    public $backendCategory = null;
    public $syncMessages = [];
    public $backendConnected = false;

    protected $rules = [
        'name' => 'required|string|max:255',
        'description' => 'nullable|string',
        'short_description' => 'nullable|string|max:500',
        'category' => 'nullable|string|max:100',
        'price' => 'required|numeric|min:0',
        'sale_price' => 'nullable|numeric|min:0',
        'tags' => 'nullable|string',
        'preview_duration' => 'required|integer|min:10|max:300',
        'sort_order' => 'required|integer|min:0',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'background_music_url' => 'nullable|url',
        'cover_image_path' => 'nullable|string',
        'meta_title' => 'nullable|string|max:255',
        'meta_description' => 'nullable|string|max:500',
        'meta_keywords' => 'nullable|string|max:255',
        'backend_category_id' => 'nullable|string',
        'bg_music_volume' => 'required|numeric|min:0|max:1',
        'message_repeat_count' => 'required|integer|min:1|max:10',
        'repeat_interval' => 'required|numeric|min:0|max:60',
        'message_interval' => 'required|numeric|min:0|max:300',
        'fade_in_duration' => 'required|numeric|min:0|max:10',
        'fade_out_duration' => 'required|numeric|min:0|max:10',
        'silence_start' => 'required|numeric|min:0|max:10',
        'silence_end' => 'required|numeric|min:0|max:10',
        'has_background_music' => 'boolean',
        'enable_silence_padding' => 'boolean',
    ];

    public function mount()
    {
        try {
            Log::info('TtsProductManager: Starting mount()');
            $this->checkBackendConnection();
            Log::info('TtsProductManager: Backend connection checked, connected: ' . ($this->backendConnected ? 'YES' : 'NO'));
            $this->autoSyncCategories();
            Log::info('TtsProductManager: Auto-sync completed');
            $this->loadBgMusicFiles();
        } catch (\Exception $e) {
            Log::error('TtsProductManager mount error: ' . $e->getMessage());
            Log::error('TtsProductManager mount trace: ' . $e->getTraceAsString());
            // Don't let mount errors break the component
            $this->backendConnected = false;
        }
    }

    /**
     * Scan storage for bg-music/original files and populate list.
     */
    public function loadBgMusicFiles()
    {
        try {
            $dir = storage_path('app/bg-music/original');
            if (!is_dir($dir)) {
                $this->bgMusicFiles = [];
                $this->bgMusicDebug = 'Missing directory: '.$dir;
                return;
            }
            $items = @scandir($dir);
            if ($items === false) {
                $this->bgMusicFiles = [];
                $this->bgMusicDebug = 'scandir failed for '.$dir;
                return;
            }
            $tracks = [];
            $debug = [];
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') continue;
                $full = $dir.DIRECTORY_SEPARATOR.$item;
                if (is_link($full)) {
                    $debug[] = 'link:'.$item.'->'.readlink($full);
                }
                $real = realpath($full);
                if ($real && is_file($real)) {
                    $ext = strtolower(pathinfo($item, PATHINFO_EXTENSION));
                    if (in_array($ext, ['mp3','wav','m4a','aac','ogg'])) {
                        $tracks[pathinfo($item, PATHINFO_FILENAME)] = true;
                    }
                }
            }
            ksort($tracks);
            $this->bgMusicFiles = array_keys($tracks);
            if (!$this->background_music_track && $this->bgMusicFiles) {
                $this->background_music_track = $this->bgMusicFiles[0];
            }
            Log::info('Loaded bg music tracks', ['count' => count($this->bgMusicFiles)]);
        } catch (\Exception $e) {
            Log::warning('Failed loading bg music files: '.$e->getMessage());
            $this->bgMusicFiles = [];
        }
    }

    public function refreshBgMusicFiles()
    {
        $this->loadBgMusicFiles();
        session()->flash('info', 'Background music track list refreshed.');
    }

    #[On('refreshBgMusicAuto')]
    public function ensureBgMusicLoaded()
    {
        if (empty($this->bgMusicFiles)) {
            $this->loadBgMusicFiles();
        }
    }

    public function checkBackendConnection()
    {
        try {
            $response = Http::timeout(5)->get('https://meditative-brains.com:3001/api/category');
            $this->backendConnected = $response->successful();
        } catch (\Exception $e) {
            $this->backendConnected = false;
            Log::warning('TTS Backend connection failed: ' . $e->getMessage());
        }
    }

    public function autoSyncCategories()
    {
        if (!$this->backendConnected) {
            return;
        }

        try {
            $response = Http::get('https://meditative-brains.com:3001/api/category');
            
            if ($response->successful()) {
                $categories = $response->json();
                $syncCount = 0;
                $this->syncMessages = [];
                
                // Pre-fetch all messages once to avoid N+1 calls and build counts map
                $messageCounts = $this->fetchBackendMessageCounts();
                
                foreach ($categories as $category) {
                    $existing = TtsAudioProduct::where('backend_category_id', $category['_id'])->first();
                    $messageCount = $messageCounts[$category['_id']] ?? 0;
                    
                    if (!$existing) {
                        
                        TtsAudioProduct::create([
                            'name' => $category['category'],
                            'description' => $category['description'] ?? '',
                            'short_description' => substr($category['description'] ?? '', 0, 200),
                            'category' => $category['category'], // Required field
                            'audio_type' => 'tts', // Required field  
                            'language' => 'en', // Required field
                            'price' => 9.99,
                            'sale_price' => null,
                            'tags' => 'tts,audio,motivation',
                            'preview_duration' => 30,
                            'sort_order' => 0,
                            'is_active' => true,
                            'is_featured' => false,
                            'backend_category_id' => $category['_id'],
                            'total_messages_count' => $messageCount,
                        ]);
                        
                        $syncCount++;
                        $this->syncMessages[] = "Auto-synced category: {$category['category']} ({$messageCount} messages)";
                    } else {
                        $existing->update(['total_messages_count' => $messageCount]);
                    }
                }
                
                if ($syncCount > 0) {
                    session()->flash('success', "Auto-synced {$syncCount} new categories from TTS backend.");
                }
            }
        } catch (\Exception $e) {
            Log::error('Auto-sync failed: ' . $e->getMessage());
        }
    }

    /**
     * Fetch all messages from backend and return an associative array of counts keyed by category id.
     */
    protected function fetchBackendMessageCounts(): array
    {
        $counts = [];
        if (!$this->backendConnected) return $counts;

        try {
            $endpoints = [
                'https://meditative-brains.com:3001/api/messages',
                'https://meditative-brains.com:3001/api/motivationMessage'
            ];

            $messages = [];
            foreach ($endpoints as $ep) {
                try {
                    $resp = Http::timeout(10)->get($ep);
                    if (!$resp->successful()) continue;
                    $json = $resp->json();
                    if (is_array($json) && isset($json['data']) && is_array($json['data'])) {
                        $json = $json['data'];
                    }
                    if (is_array($json)) {
                        // Basic heuristic: ensure first element looks like a message doc
                        if (empty($json) || (isset($json[0]) && is_array($json[0]))) {
                            $messages = $json;
                            break;
                        }
                    }
                } catch (\Exception $inner) {
                    Log::warning('Message endpoint failed: ' . $inner->getMessage());
                }
            }

            foreach ($messages as $m) {
                if (!is_array($m)) continue; // skip malformed

                // Determine category id (product linkage key)
                $catId = $m['categoryId'] ?? $m['category_id'] ?? null;
                if (!$catId && isset($m['category'])) {
                    if (is_array($m['category'])) {
                        $catId = $m['category']['_id'] ?? $m['category']['id'] ?? null;
                    } elseif (is_string($m['category'])) {
                        $catId = $m['category'];
                    }
                }
                // Ensure catId is scalar (string/int) else skip to avoid Illegal offset type
                if (!is_scalar($catId)) continue;

                // Count logic:
                // 1. If a message document contains an array of message segments (e.g. messages, lines, items) count its length.
                // 2. Else if it has audio_urls (array) count those.
                // 3. Else count the document as 1.
                $increment = 1;
                foreach (['messages','message_list','items','lines'] as $segKey) {
                    if (isset($m[$segKey]) && is_array($m[$segKey]) && count($m[$segKey]) > 0) {
                        $increment = count($m[$segKey]);
                        break;
                    }
                }
                if ($increment === 1 && isset($m['audio_urls']) && is_array($m['audio_urls']) && count($m['audio_urls']) > 0) {
                    $increment = count($m['audio_urls']);
                }

                $catIdKey = (string)$catId; // safe key
                $counts[$catIdKey] = ($counts[$catIdKey] ?? 0) + $increment;
            }

            Log::info('Fetched backend message counts (expanded) for ' . count($counts) . ' categories');
            return $counts;
        } catch (\Exception $e) {
            Log::error('Failed fetching backend message counts: ' . $e->getMessage());
            return $counts;
        }
    }

    public function generateProductFromMessages($categoryId)
    {
        if (!$this->backendConnected) {
            session()->flash('error', 'Backend connection required to generate products from messages.');
            return;
        }

        try {
            // Get category details
            $categoryResponse = Http::get('https://meditative-brains.com:3001/api/category');
            if (!$categoryResponse->successful()) {
                session()->flash('error', 'Failed to fetch categories from backend.');
                return;
            }

            $categories = $categoryResponse->json();
            $category = collect($categories)->firstWhere('_id', $categoryId);
            
            if (!$category) {
                session()->flash('error', 'Category not found in backend.');
                return;
            }

            // Get messages for this category
            $messageResponse = Http::get("https://meditative-brains.com:3001/api/motivationMessage/category/{$categoryId}");
            if (!$messageResponse->successful()) {
                session()->flash('error', 'Failed to fetch messages for this category.');
                return;
            }

            $messages = $messageResponse->json();
            
            if (empty($messages)) {
                session()->flash('error', 'No messages found for this category.');
                return;
            }

            // Create or update the product with proper message data
            $existingProduct = TtsAudioProduct::where('backend_category_id', $categoryId)->first();
            
            $productData = [
                'name' => $category['category'] . ' - Motivational Audio Pack',
                'description' => "A collection of " . count($messages) . " motivational messages in the " . $category['category'] . " category. " . ($category['description'] ?? ''),
                'short_description' => "Motivational audio pack with " . count($messages) . " inspiring messages",
                'category' => $category['category'],
                'audio_type' => 'tts',
                'language' => 'en',
                'price' => 9.99,
                'sale_price' => null,
                'tags' => 'tts,audio,motivation,' . strtolower(str_replace(' ', '-', $category['category'])),
                'preview_duration' => 30,
                'sort_order' => 0,
                'is_active' => true,
                'is_featured' => false,
                'backend_category_id' => $categoryId,
                'total_messages_count' => count($messages),
                
                // Default audio settings
                'bg_music_volume' => 0.30,
                'message_repeat_count' => 2,
                'repeat_interval' => 2.00,
                'message_interval' => 10.00,
                'fade_in_duration' => 0.5,
                'fade_out_duration' => 0.5,
                'enable_silence_padding' => true,
                'silence_start' => 1.0,
                'silence_end' => 1.0,
                'has_background_music' => false,
                'background_music_type' => 'relaxing',
            ];

            if ($existingProduct) {
                $existingProduct->update($productData);
                $product = $existingProduct;
                session()->flash('success', "Product updated with {$productData['total_messages_count']} messages from backend.");
            } else {
                $product = TtsAudioProduct::create($productData);
                session()->flash('success', "New product created with {$productData['total_messages_count']} messages from category: {$category['category']}");
            }

            // Optionally generate audio URLs placeholder (for future TTS processing)
            $audioUrls = [];
            foreach ($messages as $index => $message) {
                $audioUrls[] = "https://meditative-brains.com:3001/api/tts/audio/{$message['_id']}.mp3";
            }
            
            $product->update([
                'audio_urls' => json_encode($audioUrls)
            ]);

            Log::info("Generated product from messages - Category: {$category['category']}, Messages: " . count($messages));
            
        } catch (\Exception $e) {
            session()->flash('error', 'Error generating product from messages: ' . $e->getMessage());
            Log::error('Generate product from messages error: ' . $e->getMessage());
        }
    }

    public function manualSync()
    {
        $this->checkBackendConnection();
        $this->autoSyncCategories();
        $this->dispatch('refreshPage');
    }

    public function refreshMessageCounts()
    {
        if (!$this->backendConnected) {
            session()->flash('error', 'Backend not connected.');
            return;
        }
        try {
            $counts = $this->fetchBackendMessageCounts();
            $updated = 0;
            foreach ($counts as $catId => $count) {
                $updated += TtsAudioProduct::where('backend_category_id', $catId)
                    ->update(['total_messages_count' => $count]);
            }
            session()->flash('success', 'Refreshed message counts for ' . $updated . ' products.');
        } catch (\Exception $e) {
            session()->flash('error', 'Failed refreshing counts: ' . $e->getMessage());
        }
    }

    public function create()
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit($productId)
    {
        $this->editingProduct = TtsAudioProduct::findOrFail($productId);
        $this->loadProductData();
        $this->loadBackendData();
        $this->showForm = true;
    }

    protected function loadProductData()
    {
        if (!$this->editingProduct) return;

        $this->name = $this->editingProduct->name;
        $this->description = $this->editingProduct->description ?? '';
        $this->short_description = $this->editingProduct->short_description ?? '';
        $this->category = $this->editingProduct->category ?? '';
        $this->price = $this->editingProduct->price;
        $this->sale_price = $this->editingProduct->sale_price;
        $this->tags = $this->editingProduct->tags ?? '';
        $this->preview_duration = $this->editingProduct->preview_duration;
        $this->sort_order = $this->editingProduct->sort_order;
        $this->is_active = $this->editingProduct->is_active;
        $this->is_featured = $this->editingProduct->is_featured;
        $this->background_music_url = $this->editingProduct->background_music_url ?? '';
        $this->cover_image_path = $this->editingProduct->cover_image_path ?? '';
        $this->meta_title = $this->editingProduct->meta_title ?? '';
        $this->meta_description = $this->editingProduct->meta_description ?? '';
        $this->meta_keywords = $this->editingProduct->meta_keywords ?? '';
        $this->backend_category_id = $this->editingProduct->backend_category_id ?? '';
        $this->total_messages_count = $this->editingProduct->total_messages_count ?? 0;
        
        // Audio settings
        $this->bg_music_volume = $this->editingProduct->bg_music_volume ?? 0.30;
        $this->message_repeat_count = $this->editingProduct->message_repeat_count ?? 2;
        $this->repeat_interval = $this->editingProduct->repeat_interval ?? 2.00;
        $this->message_interval = $this->editingProduct->message_interval ?? 10.00;
        $this->fade_in_duration = $this->editingProduct->fade_in_duration ?? 0.5;
        $this->fade_out_duration = $this->editingProduct->fade_out_duration ?? 0.5;
        $this->enable_silence_padding = $this->editingProduct->enable_silence_padding ?? true;
        $this->silence_start = $this->editingProduct->silence_start ?? 1.0;
        $this->silence_end = $this->editingProduct->silence_end ?? 1.0;
    $this->has_background_music = $this->editingProduct->has_background_music ?? false;
    $this->background_music_type = $this->editingProduct->background_music_type ?? 'relaxing';
    $this->background_music_track = $this->editingProduct->background_music_track ?? $this->background_music_track;
        
        // Audio URLs
        $this->audio_urls = $this->editingProduct->audio_urls ?? '';
        $this->preview_audio_url = $this->editingProduct->preview_audio_url ?? '';
    }

    protected function loadBackendData()
    {
        if (!$this->backend_category_id || !$this->backendConnected) {
            return;
        }

        try {
            // Load category details
            $categoryResponse = Http::get("https://meditative-brains.com:3001/api/category");
            if ($categoryResponse->successful()) {
                $categories = $categoryResponse->json();
                $this->backendCategory = collect($categories)->firstWhere('_id', $this->backend_category_id);
            }

            // Load messages (limited to 5 for preview)
            $messageResponse = Http::get("https://meditative-brains.com:3001/api/motivationMessage/category/{$this->backend_category_id}");
            if ($messageResponse->successful()) {
                $this->backendMessages = array_slice($messageResponse->json(), 0, 5);
            }
        } catch (\Exception $e) {
            Log::error('Failed to load backend data: ' . $e->getMessage());
        }
    }

    public function save()
    {
        // Debug: Log current property values
        Log::info('TTS Product Save - Current Properties:', [
            'bg_music_volume' => $this->bg_music_volume,
            'has_background_music' => $this->has_background_music,
            'background_music_url' => $this->background_music_url,
            'background_music_type' => $this->background_music_type,
        ]);

        $this->validate();

        try {
            $data = [
                'name' => $this->name,
                'description' => $this->description,
                'short_description' => $this->short_description,
                'category' => $this->category ?: $this->name,
                'audio_type' => 'tts',
                'language' => 'en',
                'price' => $this->price,
                'tags' => $this->tags,
                'preview_duration' => $this->preview_duration,
                'sort_order' => $this->sort_order,
                'is_active' => $this->is_active,
                'is_featured' => $this->is_featured,
                'background_music_url' => $this->background_music_url ?: null,
                'cover_image_path' => $this->cover_image_path ?: null,
                'meta_title' => $this->meta_title,
                'meta_description' => $this->meta_description,
                'meta_keywords' => $this->meta_keywords,
                'backend_category_id' => $this->backend_category_id ?: null,
                'total_messages_count' => $this->total_messages_count,
                // Audio settings
                'bg_music_volume' => $this->bg_music_volume,
                'message_repeat_count' => $this->message_repeat_count,
                'repeat_interval' => $this->repeat_interval,
                'message_interval' => $this->message_interval,
                'fade_in_duration' => $this->fade_in_duration,
                'fade_out_duration' => $this->fade_out_duration,
                'enable_silence_padding' => $this->enable_silence_padding,
                'silence_start' => $this->silence_start,
                'silence_end' => $this->silence_end,
                'has_background_music' => $this->has_background_music,
                'background_music_type' => $this->background_music_type,
                'background_music_track' => $this->background_music_track ?: null,
                // Audio URLs
                'audio_urls' => $this->audio_urls,
                'preview_audio_url' => $this->preview_audio_url,
            ];

            // Debug: Log data being saved
            Log::info('TTS Product Save - Data Array:', $data);

            // Handle cover image upload
            if ($this->cover_image) {
                $path = $this->cover_image->store('tts-products', 'public');
                $data['cover_image_path'] = $path;
            }

            if ($this->editingProduct) {
                $this->editingProduct->update($data);
                session()->flash('success', 'Product updated successfully!');
                Log::info('TTS Product Save - Updated product ID: ' . $this->editingProduct->id);
            } else {
                $product = TtsAudioProduct::create($data);
                session()->flash('success', 'Product created successfully!');
                Log::info('TTS Product Save - Created product ID: ' . $product->id);
            }

            $this->resetForm();
            $this->showForm = false;
            
        } catch (\Exception $e) {
            session()->flash('error', 'Error saving product: ' . $e->getMessage());
            Log::error('Product save error: ' . $e->getMessage());
            Log::error('Product save error trace: ' . $e->getTraceAsString());
        }
    }

    public function delete($productId)
    {
        try {
            $product = TtsAudioProduct::findOrFail($productId);
            
            // Delete cover image if exists
            if ($product->cover_image_path) {
                Storage::disk('public')->delete($product->cover_image_path);
            }
            
            $product->delete();
            session()->flash('success', 'Product deleted successfully!');
            
        } catch (\Exception $e) {
            session()->flash('error', 'Error deleting product: ' . $e->getMessage());
            Log::error('Product delete error: ' . $e->getMessage());
        }
    }

    public function toggleActive($productId)
    {
        try {
            $product = TtsAudioProduct::findOrFail($productId);
            $product->update(['is_active' => !$product->is_active]);
            
            $status = $product->is_active ? 'activated' : 'deactivated';
            session()->flash('success', "Product {$status} successfully!");
            
        } catch (\Exception $e) {
            session()->flash('error', 'Error updating product status: ' . $e->getMessage());
        }
    }

    public function cancel()
    {
        try {
            $dir = storage_path('app/bg-music/original');
            $debug = [];
            if (!is_dir($dir)) {
                $this->bgMusicFiles = [];
                $this->bgMusicDebug = 'Missing dir: '.$dir;
                return;
            }
            $items = @scandir($dir);
            if ($items === false) {
                $this->bgMusicFiles = [];
                $this->bgMusicDebug = 'scandir failed: '.$dir;
                return;
            }
            $tracks = [];
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') continue;
                $full = $dir.DIRECTORY_SEPARATOR.$item;
                if (is_link($full)) {
                    $debug[] = 'link '.$item.' -> '.readlink($full);
                }
                $real = realpath($full);
                if ($real && is_file($real)) {
                    $ext = strtolower(pathinfo($item, PATHINFO_EXTENSION));
                    if (in_array($ext, ['mp3','wav','m4a','aac','ogg'])) {
                        $tracks[pathinfo($item, PATHINFO_FILENAME)] = true;
                    }
                }
            }
            ksort($tracks);
            $this->bgMusicFiles = array_keys($tracks);
            if (!$this->background_music_track && $this->bgMusicFiles) {
                $this->background_music_track = $this->bgMusicFiles[0];
            }
            $this->bgMusicDebug = 'scanned='.count($items).' valid='.count($this->bgMusicFiles).' '.implode(' | ',$debug);
            Log::info('Loaded bg music tracks (scandir)', ['count' => count($this->bgMusicFiles)]);
        } catch (\Exception $e) {
            Log::warning('Failed loading bg music files: '.$e->getMessage());
            $this->bgMusicFiles = [];
            $this->bgMusicDebug = 'exception: '.$e->getMessage();
        }
            Log::info('Loaded bg music tracks', ['count' => count($this->bgMusicFiles), 'dir' => $dir]);
        $this->fade_out_duration = 0.5;
        $this->enable_silence_padding = true;
        $this->silence_start = 1.0;
        $this->silence_end = 1.0;
        $this->has_background_music = false;
        $this->background_music_type = 'relaxing';
    $this->background_music_track = '';
    $this->loadBgMusicFiles();
        $this->audio_urls = '';
        $this->preview_audio_url = '';
        
        $this->backendMessages = [];
        $this->backendCategory = null;
    }

    public function generateAudioPreview($productId = null)
    {
        $product = $productId ? TtsAudioProduct::find($productId) : $this->editingProduct;
        
        if (!$product) {
            session()->flash('error', 'No product selected.');
            return;
        }

        try {
            // First priority: Check if we have audio_urls array (these are already complete URLs)
            if ($product->audio_urls) {
                $raw = json_decode($product->audio_urls, true);
                $audioUrls = [];
                if (is_array($raw)) {
                    foreach ($raw as $item) {
                        // Accept direct string
                        if (is_string($item)) {
                            $audioUrls[] = $item;
                            continue;
                        }
                        // Accept object/assoc array with possible keys
                        if (is_array($item)) {
                            $candidate = $item['url']
                                ?? $item['audio_url']
                                ?? $item['src']
                                ?? $item['path']
                                ?? null;
                            if (is_string($candidate)) {
                                $audioUrls[] = $candidate;
                                continue;
                            }
                        }
                    }
                }
                // De-duplicate & filter empties
                $audioUrls = array_values(array_unique(array_filter($audioUrls)));
                
                if (!empty($audioUrls)) {
                    // Normalize legacy domain to new domain for playback (temporary until Mongo updated)
                    $legacyHost = 'motivation.mywebsolutions.co.in:3000';
                    $newHost = 'meditative-brains.com:3001';
                    $audioUrls = array_map(function($u) use ($legacyHost, $newHost) {
                        if (is_string($u) && str_contains($u, $legacyHost)) {
                            return str_replace($legacyHost, $newHost, $u);
                        }
                        return $u;
                    }, $audioUrls);
                    // Prepare audio configuration for the frontend
                    $audioConfig = [
                        'audioUrls' => $audioUrls,
                        'messageRepeatCount' => $product->message_repeat_count ?? 2,
                        'repeatInterval' => $product->repeat_interval ?? 2.0,
                        'messageInterval' => $product->message_interval ?? 10.0,
                        // fade durations kept for backward compatibility but ignored in JS now
                        'fadeInDuration' => 0,
                        'fadeOutDuration' => 0,
                        'silenceStart' => $product->silence_start ?? 1.0,
                        'silenceEnd' => $product->silence_end ?? 1.0,
                        'hasBackgroundMusic' => $product->has_background_music ?? false,
                        'backgroundMusicUrl' => $product->background_music_url,
                        'bgMusicVolume' => isset($product->bg_music_volume) ? (float)$product->bg_music_volume : 0.3,
                        'previewDuration' => $product->preview_duration ?? 30,
                        'category' => $product->category ?? null,
                        'backgroundMusicType' => $product->background_music_type ?? null,
                        'backgroundMusicTrack' => $this->background_music_track ?: ($product->background_music_track ?? null)
                        ,'enforceTimeline' => true
                    ];
                    
                    // Update the preview_audio_url field for future use (use first URL)
                    $product->update([
                        'preview_audio_url' => $audioUrls[0]
                    ]);
                    
                    Log::info('Audio preview dispatch', [
                        'product_id' => $product->id,
                        'audio_url_count' => count($audioUrls),
                        'preview_duration' => $audioConfig['previewDuration']
                    ]);
                    // Add marker log for frontend correlation
                    Log::info('Dispatching playSequentialAudio Livewire event for product '.$product->id);
                    session()->flash('success', 'Starting audio preview with ' . count($audioUrls) . ' audio clips.');
                    $this->dispatch('playSequentialAudio', config: $audioConfig);
                    return;
                }
            }

            // Second priority: Check if preview audio URL exists (might be relative path)
            if ($product->preview_audio_url) {
                $previewUrl = $product->preview_audio_url;
                
                // Only add domain if it's a relative path
                if (!str_starts_with($previewUrl, 'http')) {
                    $previewUrl = 'https://motivation.mywebsolutions.co.in:3000/' . ltrim($previewUrl, '/');
                }
                
                // Simple single audio preview (fallback)
                $audioConfig = [
                    'audioUrls' => [$previewUrl],
                    'messageRepeatCount' => 1,
                    'repeatInterval' => 0,
                    'messageInterval' => 0,
                    'hasBackgroundMusic' => false,
                    'previewDuration' => $product->preview_duration ?? 30
                ];
                
                session()->flash('success', 'Playing existing audio preview.');
                $this->dispatch('playSequentialAudio', config: $audioConfig);
                return;
            }

            // No audio available
            session()->flash('error', 'No audio URLs available for this product. Please generate audio files first using the TTS Messages section.');
            
        } catch (\Exception $e) {
            session()->flash('error', 'Error accessing audio preview: ' . $e->getMessage());
            Log::error('Audio preview error: ' . $e->getMessage());
        }
    }

    public function playExistingAudio()
    {
        // Just call the same method since it handles existing audio correctly
        $this->generateAudioPreview();
    }

    protected function getViewData(): array
    {
        try {
            Log::info('TtsProductManager: Starting getViewData()');
            
            $products = TtsAudioProduct::query()
                ->when($this->search, function ($query) {
                    $query->where('name', 'like', '%' . $this->search . '%')
                          ->orWhere('description', 'like', '%' . $this->search . '%')
                          ->orWhere('tags', 'like', '%' . $this->search . '%');
                })
                ->when($this->filterActive !== '', function ($query) {
                    $query->where('is_active', $this->filterActive);
                })
                ->orderBy('sort_order')
                ->orderBy('created_at', 'desc')
                ->paginate(10);

            Log::info('TtsProductManager: Found ' . $products->count() . ' products');
            
            return [
                'products' => $products
            ];
        } catch (\Exception $e) {
            Log::error('TtsProductManager getViewData error: ' . $e->getMessage());
            Log::error('TtsProductManager getViewData trace: ' . $e->getTraceAsString());
            
            // Return empty data to prevent complete failure
            return [
                'products' => new \Illuminate\Pagination\LengthAwarePaginator([], 0, 10)
            ];
        }
    }
}
