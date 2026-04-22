<?php

namespace App\Livewire\Admin;

use App\Livewire\AdminComponent;
use App\Services\AudioSecurityService;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use Livewire\Attributes\On;
use App\Models\TtsAudioProduct;
use App\Models\TtsSourceCategory;
use App\Models\TtsMotivationMessage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TtsProductManager extends AdminComponent
{
    use WithPagination, WithFileUploads;

    private const PREVIEW_AUDIO_URL_MAX_LENGTH = 65535;
    
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
    public $language = 'en';
    public $backend_category_id = '';
    public $backend_category_name = '';
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
        'backend_category_name' => 'nullable|string|max:255',
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
    'background_music_track' => 'nullable|string|max:150'
    ];

    public function mount()
    {
        try {
            Log::info('TtsProductManager: Starting mount()');
            // MySQL is always available — no Node connection needed
            $this->backendConnected = true;
            $this->autoSyncCategories();
            Log::info('TtsProductManager: Auto-sync completed');
            $this->loadBgMusicFiles();
        } catch (\Exception $e) {
            Log::error('TtsProductManager mount error: ' . $e->getMessage());
            Log::error('TtsProductManager mount trace: ' . $e->getTraceAsString());
        }
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterActive(): void
    {
        $this->resetPage();
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
            // Preserve already selected or stored track if present
            if ($this->background_music_track && in_array($this->background_music_track, $this->bgMusicFiles)) {
                // keep
            } elseif ($this->editingProduct && $this->editingProduct->background_music_track && in_array($this->editingProduct->background_music_track, $this->bgMusicFiles)) {
                $this->background_music_track = $this->editingProduct->background_music_track;
            } elseif ($this->bgMusicFiles) {
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

    /** MySQL is always available — Node no longer used. */
    public function checkBackendConnection()
    {
        $this->backendConnected = true;
    }

    protected function supportsBackendCategoryName(): bool
    {
        return TtsAudioProduct::supportsBackendCategoryName();
    }

    protected function mergeBackendCategoryName(array $data, ?string $backendCategoryName): array
    {
        if ($this->supportsBackendCategoryName()) {
            $data['backend_category_name'] = $backendCategoryName;
        }

        return $data;
    }

    /**
     * Sync TtsAudioProducts from MySQL tts_source_categories + tts_motivation_messages.
     * No Node dependency.
     */
    public function autoSyncCategories()
    {
        $this->refreshMessageCounts();

        $syncCount = 0;
        $this->syncMessages = [];

        $categories = TtsSourceCategory::withCount('messages')->get();

        foreach ($categories as $category) {
            $messageCount = $category->messages_count;
            [$catalogCategory, $productTitle] = $this->deriveCatalogFieldsFromBackendCategory($category->category);

            $existing = TtsAudioProduct::where('backend_category_id', $category->mongo_id ?: $category->id)->first();

            if (!$existing) {
                $productData = [
                    'name'                  => $productTitle,
                    'description'           => '',
                    'short_description'     => '',
                    'category'              => $catalogCategory,
                    'audio_type'            => 'tts',
                    'language'              => 'en',
                    'price'                 => 9.99,
                    'tags'                  => json_encode(['tts', 'audio', 'motivation']),
                    'preview_duration'      => 30,
                    'sort_order'            => 0,
                    'is_active'             => true,
                    'is_featured'           => false,
                    'backend_category_id'   => $category->mongo_id ?: (string)$category->id,
                    'total_messages_count'  => $messageCount,
                ];
                TtsAudioProduct::create($this->mergeBackendCategoryName($productData, $category->category));
                $syncCount++;
                $this->syncMessages[] = "Auto-synced category: {$category->category} ({$messageCount} messages)";
            } else {
                $updates = ['total_messages_count' => $messageCount];
                if ($this->supportsBackendCategoryName() && empty($existing->backend_category_name)) {
                    $updates['backend_category_name'] = $category->category;
                }
                $existing->update($updates);
            }
        }

        if ($syncCount > 0) {
            session()->flash('success', "Auto-synced {$syncCount} new categories from MySQL.");
        }
    }

    /**
     * Count messages per source category from MySQL.
     */
    protected function fetchBackendMessageCounts(): array
    {
        // Group by source_category_id, sum individual message text rows
        $rows = TtsMotivationMessage::selectRaw('source_category_id, SUM(JSON_LENGTH(messages)) as cnt')
            ->groupBy('source_category_id')
            ->get();

        $counts = [];
        foreach ($rows as $row) {
            $cat = TtsSourceCategory::find($row->source_category_id);
            if (!$cat) continue;
            $key = $cat->mongo_id ?: (string)$cat->id;
            $counts[$key] = (int)$row->cnt;
        }
        return $counts;
    }

    /**
     * Create/update a TtsAudioProduct from MySQL source category + messages.
     */
    public function generateProductFromMessages($categoryId)
    {
        try {
            // $categoryId may be a mongo_id string or a MySQL numeric id
            $category = TtsSourceCategory::where('mongo_id', $categoryId)
                ->orWhere('id', is_numeric($categoryId) ? $categoryId : 0)
                ->first();

            if (!$category) {
                session()->flash('error', 'Category not found in MySQL. Please run the import first.');
                return;
            }

            $messages = TtsMotivationMessage::where('source_category_id', $category->id)->get();
            $totalMessages = $messages->sum(fn($m) => count($m->messages ?? []));

            if ($totalMessages === 0) {
                session()->flash('error', 'No messages found for this category in MySQL. Import messages first.');
                return;
            }

            [$catalogCategory, $productTitle] = $this->deriveCatalogFieldsFromBackendCategory($category->category);
            $backendCatId = $category->mongo_id ?: (string)$category->id;

            $existingProduct = TtsAudioProduct::where('backend_category_id', $backendCatId)->first();

            $productData = [
                'name'                  => $productTitle,
                'description'           => "A collection of {$totalMessages} motivational messages in the {$category->category} category.",
                'short_description'     => "Motivational audio pack with {$totalMessages} inspiring messages",
                'category'              => $catalogCategory,
                'audio_type'            => 'tts',
                'language'              => 'en',
                'price'                 => 9.99,
                'tags'                  => json_encode(['tts', 'audio', 'motivation', strtolower(str_replace(' ', '-', $category->category))]),
                'preview_duration'      => 30,
                'sort_order'            => 0,
                'is_active'             => true,
                'is_featured'           => false,
                'backend_category_id'   => $backendCatId,
                'total_messages_count'  => $totalMessages,
                'bg_music_volume'       => 0.30,
                'message_repeat_count'  => 2,
                'repeat_interval'       => 2.00,
                'message_interval'      => 10.00,
                'fade_in_duration'      => 0.5,
                'fade_out_duration'     => 0.5,
                'enable_silence_padding'=> true,
                'silence_start'         => 1.0,
                'silence_end'           => 1.0,
                'has_background_music'  => false,
                'background_music_type' => 'relaxing',
            ];
            $productData = $this->mergeBackendCategoryName($productData, $category->category);

            if ($existingProduct) {
                $existingProduct->update($productData);
                session()->flash('success', "Product updated with {$totalMessages} messages from MySQL.");
            } else {
                TtsAudioProduct::create($productData);
                session()->flash('success', "New product created with {$totalMessages} messages: {$category->category}");
            }

            Log::info('Generated product from MySQL messages', ['category' => $category->category, 'messages' => $totalMessages]);
        } catch (\Exception $e) {
            session()->flash('error', 'Error generating product: ' . $e->getMessage());
            Log::error('generateProductFromMessages error: ' . $e->getMessage());
        }
    }

    public function manualSync()
    {
        $this->backendConnected = true;
        $this->autoSyncCategories();
        $this->dispatch('refreshPage');
    }

    public function refreshMessageCounts()
    {
        try {
            $counts = $this->fetchBackendMessageCounts();
            $updated = 0;
            foreach ($counts as $catId => $count) {
                $updated += TtsAudioProduct::where('backend_category_id', $catId)
                    ->update(['total_messages_count' => $count]);
            }
            if ($updated) {
                Log::info('Refreshed message counts for ' . $updated . ' products from MySQL.');
            }
        } catch (\Exception $e) {
            session()->flash('error', 'Failed refreshing counts: ' . $e->getMessage());
        }
    }

    /**
     * Scan all products and update language/speaker fields from MySQL message records.
     */
    public function fixLanguageCodes(): void
    {
        $products = TtsAudioProduct::whereNotNull('backend_category_id')->get();

        $updated = 0;
        $skipped = 0;

        foreach ($products as $product) {
            try {
                $cat = $this->findSourceCategory(
                    (string) $product->backend_category_id,
                    $product->name
                );

                if (!$cat) { $skipped++; continue; }

                $best = TtsMotivationMessage::where('source_category_id', $cat->id)
                    ->whereNotNull('audio_urls')
                    ->where('engine', '!=', 'vits')
                    ->orderByRaw('JSON_LENGTH(audio_urls) DESC')
                    ->first();

                if (!$best) { $skipped++; continue; }

                $newLang    = $best->language ?? 'en';
                $newSpeaker = $best->speaker  ?? '';

                if ($product->language === $newLang) { $skipped++; continue; }

                $product->update([
                    'language'         => $newLang,
                    'backend_language' => $newLang,
                    'backend_speaker'  => $newSpeaker ?: $product->backend_speaker,
                ]);
                $updated++;
            } catch (\Exception $e) {
                Log::warning('fixLanguageCodes: error for product ' . $product->id, ['error' => $e->getMessage()]);
                $skipped++;
            }
        }

        session()->flash('success', "Language codes fixed: {$updated} updated, {$skipped} skipped.");
    }

    public function create()
    {
        $this->resetFormState();
        $this->showForm = true;
    }

    public function edit($productId)
    {
        $this->editingProduct = TtsAudioProduct::findOrFail($productId);
        $this->loadBgMusicFiles();
        $this->loadProductData();
        $this->loadBackendData();
        $this->showForm = true;
    }

    public function syncExistingAudioUrls(int $productId): void
    {
        $product = TtsAudioProduct::findOrFail($productId);

        try {
            $audioUrls = $this->fetchAudioUrlsFromMysql($product);
            if (empty($audioUrls)) {
                session()->flash('error', 'No generated audio was found in MySQL for this product/category yet.');
                return;
            }

            $syncedCount = $this->secureAndPersistProductAudioUrls($product, $audioUrls);
            if ($syncedCount === 0) {
                session()->flash('error', 'Audio was found, but no local URLs could be created.');
                return;
            }

            if ($this->editingProduct && $this->editingProduct->id === $product->id) {
                $this->editingProduct->refresh();
                $this->loadProductData();
            }

            session()->flash('success', "Synced {$syncedCount} local audio URL(s) for this product.");
        } catch (\Throwable $e) {
            session()->flash('error', 'Failed syncing local audio URLs: ' . $e->getMessage());
            Log::error('syncExistingAudioUrls failed', [
                'product_id' => $product->id,
                'error' => $e->getMessage(),
            ]);
        }
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
        $this->tags = $this->editingProduct->tags
            ? (is_array($this->editingProduct->tags)
                ? implode(',', $this->editingProduct->tags)
                : (str_starts_with(trim($this->editingProduct->tags), '[')
                    ? implode(',', json_decode($this->editingProduct->tags, true) ?? [])
                    : $this->editingProduct->tags))
            : '';
        $this->preview_duration = $this->editingProduct->preview_duration;
        $this->sort_order = $this->editingProduct->sort_order;
        $this->is_active = $this->editingProduct->is_active;
        $this->is_featured = $this->editingProduct->is_featured;
        $this->background_music_url = $this->editingProduct->background_music_url ?? '';
        $this->cover_image_path = $this->editingProduct->cover_image_path ?? '';
        $this->meta_title = $this->editingProduct->meta_title ?? '';
        $this->meta_description = $this->editingProduct->meta_description ?? '';
        $this->meta_keywords = $this->editingProduct->meta_keywords ?? '';
        $this->language = $this->editingProduct->language ?? 'en';
        $this->backend_category_id = $this->editingProduct->backend_category_id ?? '';
        $this->backend_category_name = $this->editingProduct->backend_category_name ?? '';
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

        Log::info('Loaded product audio settings', [
            'product_id' => $this->editingProduct->id,
            'bg_music_track_model' => $this->editingProduct->background_music_track,
            'component_track' => $this->background_music_track,
        ]);
    }

    protected function loadBackendData()
    {
        if (!$this->backend_category_id) return;

        try {
            $cat = $this->findSourceCategory(
                $this->backend_category_id,
                $this->editingProduct?->name
            );

            if ($cat) {
                $this->backendCategory = ['_id' => $cat->mongo_id ?: $cat->id, 'category' => $cat->category];
                $msgs = TtsMotivationMessage::where('source_category_id', $cat->id)->limit(5)->get();
                $this->backendMessages = $msgs->map(fn($m) => [
                    'messages'  => $m->messages ?? [],
                    'language'  => $m->language,
                    'speaker'   => $m->speaker,
                    'audioUrls' => $m->audio_urls ?? [],
                ])->toArray();
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
            $resolvedBackgroundMusicUrl = $this->resolveBackgroundMusicUrlFromTrack($this->background_music_track);
            $data = [
                'name' => $this->name,
                'description' => $this->description,
                'short_description' => $this->short_description,
                'category' => $this->category ?: $this->name,
                'audio_type' => 'tts',
                'language' => $this->editingProduct ? ($this->language ?: $this->editingProduct->language) : $this->language,
                'price' => $this->price,
                'tags' => $this->tags
                    ? (str_starts_with(trim($this->tags), '[')
                        ? $this->tags
                        : json_encode(array_map('trim', explode(',', $this->tags))))
                    : json_encode([]),
                'preview_duration' => $this->preview_duration,
                'sort_order' => $this->sort_order,
                'is_active' => $this->is_active,
                'is_featured' => $this->is_featured,
                'background_music_url' => $this->has_background_music
                    ? ($this->background_music_url ?: $resolvedBackgroundMusicUrl)
                    : null,
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
            $data = $this->mergeBackendCategoryName(
                $data,
                $this->backend_category_name ?: ($this->backendCategory['category'] ?? null)
            );

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

            $this->resetFormState();
            $this->showForm = false;
            
        } catch (\Exception $e) {
            session()->flash('error', 'Error saving product: ' . $e->getMessage());
            Log::error('Product save error: ' . $e->getMessage());
            Log::error('Product save error trace: ' . $e->getTraceAsString());
        }
    }

    private function deriveCatalogFieldsFromBackendCategory(string $backendCategoryName): array
    {
        $backendCategoryName = trim($backendCategoryName);
        if ($backendCategoryName === '') {
            return ['', ''];
        }

        if (str_contains($backendCategoryName, ' - ')) {
            [$catalogCategory, $productTitle] = explode(' - ', $backendCategoryName, 2);
            return [trim($catalogCategory), trim($productTitle)];
        }

        return [$backendCategoryName, $backendCategoryName];
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
        $this->resetFormState();
        $this->showForm = false;
    }

    protected function resetFormState(): void
    {
        $this->editingProduct = null;
        $this->name = '';
        $this->description = '';
        $this->short_description = '';
        $this->category = '';
        $this->price = '';
        $this->sale_price = '';
        $this->tags = '';
        $this->preview_duration = 30;
        $this->sort_order = 0;
        $this->is_active = true;
        $this->is_featured = false;
        $this->background_music_url = '';
        $this->cover_image = null;
        $this->cover_image_path = '';
        $this->meta_title = '';
        $this->meta_description = '';
        $this->meta_keywords = '';
        $this->language = 'en';
        $this->backend_category_id = '';
        $this->backend_category_name = '';
        $this->total_messages_count = 0;
        $this->bg_music_volume = 0.30;
        $this->message_repeat_count = 2;
        $this->repeat_interval = 2.00;
        $this->message_interval = 10.00;
        $this->fade_in_duration = 0.5;
        $this->fade_out_duration = 0.5;
        $this->enable_silence_padding = true;
        $this->silence_start = 1.0;
        $this->silence_end = 1.0;
        $this->has_background_music = false;
        $this->background_music_type = 'relaxing';
        $this->background_music_track = '';
        $this->audio_urls = '';
        $this->preview_audio_url = '';
        $this->backendMessages = [];
        $this->backendCategory = null;
        $this->loadBgMusicFiles();
    }

    protected function resolveBackgroundMusicUrlFromTrack(?string $track): ?string
    {
        $track = trim((string) $track);
        if ($track === '') {
            return null;
        }

        $dir = storage_path('app/bg-music/original');
        if (!is_dir($dir)) {
            return null;
        }

        foreach ((array) scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            if (strcasecmp(pathinfo($item, PATHINFO_FILENAME), pathinfo($track, PATHINFO_FILENAME)) !== 0) {
                continue;
            }
            return route('bg.music.stream', ['variant' => 'original', 'file' => $item]);
        }

        return null;
    }

    public function generateAudioPreview($productId = null)
    {
        $product = $productId ? TtsAudioProduct::find($productId) : $this->editingProduct;
        
        if (!$product) {
            session()->flash('error', 'No product selected.');
            return;
        }

        try {
            // First priority: Check if we have audio_urls (now cast to array in model)
            if ($product->audio_urls) {
                $raw = $product->audio_urls; // already array via cast or maybe JSON string legacy
                if (is_string($raw)) {
                    $decoded = json_decode($raw, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $raw = $decoded;
                    } else {
                        $raw = [];
                    }
                }
                $audioUrls = [];
                if (is_array($raw)) {
                    foreach ($raw as $item) {
                        if (is_string($item)) { // direct URL
                            $audioUrls[] = $item; continue;
                        }
                        if (is_array($item)) {
                            $candidate = $item['url'] ?? $item['audio_url'] ?? $item['src'] ?? $item['path'] ?? null;
                            if (is_string($candidate)) { $audioUrls[] = $candidate; continue; }
                        }
                    }
                }
                $audioUrls = array_values(array_unique(array_filter($audioUrls)));
                
                if (!empty($audioUrls)) {
                    // Normalize legacy domains to new domain for playback
                    $audioUrls = array_map([$this, 'normalizeAdminAudioUrl'], $audioUrls);
                    // Refresh any expired signed URLs before playing
                    $audioUrls = $this->refreshExpiredSignedUrls($product, $audioUrls);
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
                        'backgroundMusicTrack' => $this->background_music_track ?: ($product->background_music_track ?? null),
                        'previewTitle' => $product->name ?? 'Preview'
                        ,'enforceTimeline' => true
                    ];
                    
                    $previewUrl = $this->getPersistablePreviewUrl($audioUrls[0] ?? null, $product->preview_audio_url);
                    if ($previewUrl !== $product->preview_audio_url) {
                        $product->update([
                            'preview_audio_url' => $previewUrl
                        ]);
                    }
                    
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
                $previewUrl = $this->normalizeAdminAudioUrl($product->preview_audio_url);
                if (!str_starts_with($previewUrl, 'http')) {
                    $previewUrl = url($previewUrl);
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

            // Third priority: Try fetching audio from MySQL message records (backend_category_id fallback)
            if ($product->backend_category_id) {
                $audioUrls = $this->fetchAudioUrlsFromMysql($product);
                if (!empty($audioUrls)) {
                    $audioConfig = [
                        'audioUrls' => $audioUrls,
                        'messageRepeatCount' => $product->message_repeat_count ?? 2,
                        'repeatInterval' => $product->repeat_interval ?? 2.0,
                        'messageInterval' => $product->message_interval ?? 10.0,
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
                        'backgroundMusicTrack' => $this->background_music_track ?: ($product->background_music_track ?? null),
                        'previewTitle' => $product->name ?? 'Preview',
                        'enforceTimeline' => true,
                    ];
                    session()->flash('success', 'Playing audio from backend (' . count($audioUrls) . ' clips).');
                    $this->dispatch('playSequentialAudio', config: $audioConfig);
                    return;
                }
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

    /**
     * Quick play from the product list row (without opening the edit form).
     */
    public function quickPlay(int $productId): void
    {
        $this->generateAudioPreview($productId);
    }

    #[On('quickPlayLivewire')]
    public function quickPlayLivewire(int $id): void
    {
        $this->generateAudioPreview($id);
    }

    /**
     * Normalise audio URL – replace legacy hostnames with the current server.
     */
    protected function normalizeAdminAudioUrl(string $url): string
    {
        $legacyPrefixes = [
            'https://meditative-brains.com:3001',
            'http://meditative-brains.com:3001',
            'https://motivation.mywebsolutions.co.in:3000',
            'http://motivation.mywebsolutions.co.in:3000',
            'https://motivation.mywebsolutions.co.in:3001',
            'http://motivation.mywebsolutions.co.in:3001',
        ];
        $newBase = 'https://mentalfitness.store:3001';
        foreach ($legacyPrefixes as $old) {
            if (str_starts_with($url, $old)) {
                return $newBase . substr($url, strlen($old));
            }
        }
        return $url;
    }

    /**
     * Fetch audio URLs from MySQL tts_motivation_messages for a product.
     */
    /**
     * Check if a signed URL has expired.
     */
    private function isSignedUrlExpired(string $url): bool
    {
        if (!str_contains($url, '/audio/signed-stream')) return false;
        parse_str((string) parse_url($url, PHP_URL_QUERY), $params);
        $expires = isset($params['expires']) ? (int) $params['expires'] : 0;
        return $expires > 0 && $expires < time();
    }

    /**
     * Refresh expired signed URLs in a list by re-signing from the stored encrypted file path.
     */
    private function refreshExpiredSignedUrls(TtsAudioProduct $product, array $urls): array
    {
        $anyRefreshed = false;
        $refreshed = array_map(function (string $url) use (&$anyRefreshed) {
            if (!$this->isSignedUrlExpired($url)) return $url;
            $newUrl = $this->resignStoredUrl($url);
            if ($newUrl) { $anyRefreshed = true; return $newUrl; }
            return $url;
        }, $urls);

        // Persist refreshed URLs back to DB so next play is instant
        if ($anyRefreshed) {
            try {
                $product->update(['audio_urls' => array_values($refreshed)]);
            } catch (\Throwable $e) {
                Log::warning('refreshExpiredSignedUrls: failed to persist', ['product_id' => $product->id, 'error' => $e->getMessage()]);
            }
        }
        return $refreshed;
    }

    /**
     * Extract encrypted path from a signed URL and generate a fresh 5-year signed URL.
     */
    private function resignStoredUrl(string $signedUrl): ?string
    {
        try {
            parse_str((string) parse_url($signedUrl, PHP_URL_QUERY), $params);
            $encodedPath = $params['path'] ?? null;
            if (!is_string($encodedPath) || $encodedPath === '') return null;
            $encryptedPath = base64_decode($encodedPath, true);
            if (!is_string($encryptedPath) || $encryptedPath === '') return null;
            if (!\Illuminate\Support\Facades\Storage::disk('local')->exists($encryptedPath)) return null;
            $preview = isset($params['preview']) && is_numeric($params['preview']) ? (int) $params['preview'] : null;
            return app(AudioSecurityService::class)->generateSignedUrl($encryptedPath, $preview, 60 * 24 * 365 * 5);
        } catch (\Throwable $e) {
            Log::warning('resignStoredUrl failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Admin action: refresh all expired signed URLs across every product.
     */
    public function refreshSignedUrls(): void
    {
        $products = TtsAudioProduct::whereNotNull('audio_urls')->get();
        $refreshedProducts = 0;
        $refreshedUrls = 0;
        $skipped = 0;

        foreach ($products as $product) {
            $raw = $product->audio_urls;
            if (is_string($raw)) $raw = json_decode($raw, true) ?? [];
            if (!is_array($raw) || empty($raw)) { $skipped++; continue; }

            $newUrls = [];
            $changed = false;
            foreach ($raw as $url) {
                if (!is_string($url)) { $newUrls[] = $url; continue; }
                if ($this->isSignedUrlExpired($url)) {
                    $fresh = $this->resignStoredUrl($url);
                    if ($fresh) { $newUrls[] = $fresh; $changed = true; $refreshedUrls++; continue; }
                }
                $newUrls[] = $url;
            }

            if ($changed) {
                try {
                    $product->update(['audio_urls' => array_values($newUrls)]);
                    $refreshedProducts++;
                } catch (\Throwable $e) {
                    Log::warning('refreshSignedUrls: failed to update product', ['id' => $product->id, 'error' => $e->getMessage()]);
                }
            } else {
                $skipped++;
            }
        }

        session()->flash('success', "Refreshed {$refreshedUrls} URL(s) across {$refreshedProducts} product(s). {$skipped} product(s) had no expired URLs.");
    }

    /**
     * Resolve a TtsSourceCategory from a backend_category_id.
     * Falls back to product-name-based lookup when the mongo_id no longer
     * matches (e.g. after a backend re-sync that issued new IDs).
     */
    private function findSourceCategory(string $backendCatId, ?string $productName = null): ?TtsSourceCategory
    {
        // 1. Exact mongo_id or numeric id match
        $cat = TtsSourceCategory::where('mongo_id', $backendCatId)
            ->orWhere('id', is_numeric($backendCatId) ? (int) $backendCatId : 0)
            ->first();
        if ($cat) return $cat;

        // 2. Name-based fallback — extract base name before any parenthesis
        if ($productName) {
            $baseName = trim(preg_replace('/\s*\(.*$/s', '', $productName));
            if ($baseName !== '') {
                $cat = TtsSourceCategory::where('category', $baseName)
                    ->orWhere('category', 'LIKE', $baseName . ' %')
                    ->first();
                if ($cat) return $cat;
            }
        }

        return null;
    }

    protected function fetchAudioUrlsFromMysql(TtsAudioProduct $product): array
    {
        $cat = $this->findSourceCategory(
            (string) $product->backend_category_id,
            $product->name
        );

        if (!$cat) return [];

        $preferredLang = $product->backend_language ?: $product->language ?: 'en';

        // Try preferred language first, then any available
        $record = TtsMotivationMessage::where('source_category_id', $cat->id)
            ->where('language', $preferredLang)
            ->whereNotNull('audio_urls')
            ->orderByRaw('JSON_LENGTH(audio_urls) DESC')
            ->first();

        if (!$record) {
            $record = TtsMotivationMessage::where('source_category_id', $cat->id)
                ->whereNotNull('audio_urls')
                ->orderByRaw('JSON_LENGTH(audio_urls) DESC')
                ->first();
        }

        $urls = $record?->audio_urls ?? [];
        return array_values(array_filter($urls, fn($u) => is_string($u) && trim($u) !== ''));
    }

    protected function secureAndPersistProductAudioUrls(TtsAudioProduct $product, array $audioUrls): int
    {
        $securedUrls = [];

        foreach (array_values($audioUrls) as $index => $audioUrl) {
            if (!is_string($audioUrl) || trim($audioUrl) === '') {
                continue;
            }

            $normalizedUrl = $this->normalizeAdminAudioUrl($audioUrl);
            if (str_contains($normalizedUrl, '/audio/signed-stream')) {
                $securedUrls[] = $normalizedUrl;
                continue;
            }

            $securedUrl = $this->mirrorProductTrackToSecureStorage($product, $index, $normalizedUrl);
            if ($securedUrl) {
                $securedUrls[] = $securedUrl;
            }
        }

        $securedUrls = array_values(array_unique(array_filter($securedUrls)));
        if (empty($securedUrls)) {
            return 0;
        }

        $product->update([
            'audio_urls' => $securedUrls,
            'preview_audio_url' => $this->getPersistablePreviewUrl($securedUrls[0] ?? null, $product->preview_audio_url),
        ]);

        Log::info('Synced local product audio URLs', [
            'product_id' => $product->id,
            'count' => count($securedUrls),
        ]);

        return count($securedUrls);
    }

    private function getPersistablePreviewUrl(?string $candidate, ?string $fallback = null): ?string
    {
        if (!is_string($candidate) || trim($candidate) === '') {
            return $fallback;
        }

        return strlen($candidate) <= self::PREVIEW_AUDIO_URL_MAX_LENGTH ? $candidate : $fallback;
    }

    private function mirrorProductTrackToSecureStorage(TtsAudioProduct $product, int $index, string $sourceUrl): ?string
    {
        try {
            $extension = pathinfo(parse_url($sourceUrl, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION);
            $extension = $extension !== '' ? strtolower($extension) : 'aac';
            $originalRelative = $this->buildProductOriginalRelativePath($product, $index, $sourceUrl, $extension);
            $originalStoragePath = 'audio/original/' . $originalRelative;

            if (!Storage::disk('local')->exists($originalStoragePath)) {
                $response = Http::timeout(90)->get($sourceUrl);
                if (!$response->successful()) {
                    Log::warning('Unable to mirror raw product audio from admin sync', [
                        'product_id' => $product->id,
                        'index' => $index,
                        'status' => $response->status(),
                        'source_url' => $sourceUrl,
                    ]);
                    return null;
                }

                Storage::disk('local')->put($originalStoragePath, $response->body());
            }

            /** @var AudioSecurityService $audioSecurityService */
            $audioSecurityService = app(AudioSecurityService::class);
            $encryptedPath = $audioSecurityService->encryptOriginalFile($originalRelative, $product->id);
            // Use 5-year expiry for URLs stored in the database
            return $audioSecurityService->generateSignedUrl($encryptedPath, null, 60 * 24 * 365 * 5);
        } catch (\Throwable $e) {
            Log::warning('Failed securing product track from admin sync', [
                'product_id' => $product->id,
                'index' => $index,
                'source_url' => Str::limit($sourceUrl, 160),
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    private function buildProductOriginalRelativePath(TtsAudioProduct $product, int $index, string $sourceUrl, string $extension): string
    {
        $locale = $this->normalizeLocaleForStorage($product->backend_language ?: $product->language ?: 'en-US');
        $category = Str::slug($product->category ?: 'default');
        $productSlug = Str::slug($product->slug ?: $product->name ?: ('product-' . $product->id));
        $speaker = $this->extractSpeakerFromSourceUrl($sourceUrl) ?: 'unknown-speaker';
        $fileName = $this->buildTrackFileNameFromSourceUrl($sourceUrl, $index, $extension);

        return sprintf(
            'tts-products/%s/%s/%s/%s/%s',
            $locale,
            $category,
            $productSlug,
            $speaker,
            $fileName
        );
    }

    private function buildTrackFileNameFromSourceUrl(string $sourceUrl, int $index, string $extension): string
    {
        $path = parse_url($sourceUrl, PHP_URL_PATH) ?? '';
        $baseName = pathinfo($path, PATHINFO_FILENAME);
        $baseName = Str::limit(trim($baseName), 180, '');

        if ($baseName !== '') {
            $slugged = Str::slug($baseName, '-');
            if ($slugged !== '') {
                return $slugged . '.' . $extension;
            }
        }

        return sprintf('track-%02d.%s', $index + 1, $extension);
    }

    private function normalizeLocaleForStorage(string $language): string
    {
        $normalized = trim(str_replace('-', '_', $language));
        if ($normalized === '') {
            return 'en_US';
        }

        $parts = array_values(array_filter(explode('_', $normalized)));
        if (count($parts) === 1) {
            $base = strtolower($parts[0]);
            $region = $base === 'en' ? 'US' : strtoupper($parts[0]);
            return $base . '_' . $region;
        }

        return strtolower($parts[0]) . '_' . strtoupper($parts[1]);
    }

    private function extractSpeakerFromSourceUrl(string $sourceUrl): ?string
    {
        $path = parse_url($sourceUrl, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            return null;
        }

        $segments = array_values(array_filter(explode('/', trim($path, '/'))));
        $storageRootIndex = array_search('audio-cache', $segments, true);
        $speakerOffset = 3;
        if ($storageRootIndex === false) {
            $storageRootIndex = array_search('products-audio', $segments, true);
            $speakerOffset = 4;
        }

        if ($storageRootIndex !== false) {
            $speaker = $segments[$storageRootIndex + $speakerOffset] ?? null;
            if (is_string($speaker) && $speaker !== '') {
                return $speaker;
            }
        }

        return null;
    }

    protected function getViewData(): array
    {
        try {
            Log::info('TtsProductManager: Starting getViewData()');
            
            $products = TtsAudioProduct::query()
                ->when($this->search, function ($query) {
                    $query->where(function ($q) {
                        $q->where('name', 'like', '%' . $this->search . '%')
                          ->orWhere('description', 'like', '%' . $this->search . '%')
                          ->orWhere('tags', 'like', '%' . $this->search . '%');
                    });
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
