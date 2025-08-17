<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\TtsAudioProduct;
use App\Models\TtsCategory;
use Livewire\WithPagination;
use Illuminate\Support\Str;

class AudioExperienceCatalog extends Component
{
    use WithPagination;

    public $search = '';
    public $category = '';
    public $sortBy = 'featured';
    public $tag = '';
    public $minPrice = 0;
    public $maxPrice = 1000;
    public $showFilters = true;
    public $categories = [];
    public $featured = [];
    public $allProducts = [];

    protected $queryString = [
        'search' => ['except' => ''],
        'category' => ['except' => ''],
        'sortBy' => ['except' => 'featured'],
        'tag' => ['except' => ''],
    ];

    public function updatingSearch(){ $this->resetPage(); }
    public function updatedSearch(){ $this->resetPage(); }
    public function updatingCategory(){ $this->resetPage(); }
    public function updatingSortBy(){ $this->resetPage(); }
    public function updatingTag(){ $this->resetPage(); }

    public function clearFilters(){
        $this->reset(['search','category','sortBy','tag','minPrice','maxPrice']);
        $this->sortBy='featured';
    }

    public function mount()
    {
        // Load categories only for navigation/filter context
        $this->categories = TtsCategory::active()->ordered()->get();

        // Preload featured subset (will be recalculated on render for pagination consistency)
        $this->featured = TtsAudioProduct::active()->where('is_featured', true)->orderBy('sort_order')->take(12)->get();
    }

    protected function baseQuery(){
        $q = TtsAudioProduct::query()->active();
        if($this->search){
            $term = trim($this->search);
            if(strlen($term) >= 2){
                $s = '%'.strtolower($term).'%';
                $q->where(function($qq) use($s){
                    $qq->whereRaw('LOWER(name) LIKE ?', [$s])
                       ->orWhereRaw('LOWER(slug) LIKE ?', [$s])
                       ->orWhereRaw('LOWER(short_description) LIKE ?', [$s])
                       ->orWhereRaw('LOWER(description) LIKE ?', [$s])
                       ->orWhereRaw('LOWER(tags) LIKE ?', [$s]);
                });
            }
        }
        if($this->category){
            $q->where('category',$this->category); // category stores name
        }
        if($this->tag){
            $t = strtolower($this->tag);
            $q->where(function($qq) use($t){
                $qq->whereRaw('LOWER(tags) LIKE ?', ['%'.$t.'%']);
            });
        }
        if($this->minPrice !== null && $this->maxPrice !== null){
            $q->whereBetween('price', [$this->minPrice, $this->maxPrice]);
        }
        switch($this->sortBy){
            case 'price_low': $q->orderBy('price','asc'); break;
            case 'price_high': $q->orderBy('price','desc'); break;
            case 'newest': $q->orderBy('created_at','desc'); break;
            case 'name': $q->orderBy('name'); break;
            case 'featured': default:
                $q->orderBy('is_featured','desc')->orderBy('sort_order')->orderBy('name');
        }
        return $q;
    }

    public function getPaginatedProperty(){
        return $this->baseQuery()->paginate(12);
    }

    public function render()
    {
        // Provide paginated collection to view
        $products = $this->paginated;
        return view('livewire.audio-experience-catalog', [
            'products' => $products,
        ])
            ->layout('layouts.app-frontend', [
                'title' => 'Meditative Minds Audio',
                'description' => 'Curated Meditative Minds audio experiences: affirmations, meditation, sleep, and healing sound. '
            ]);
    }

    /**
     * Highlight current search term inside a text snippet (safe HTML returned).
     */
    public function highlight($text)
    {
        if(!$text){ return ''; }
        $snippet = Str::limit($text, 100);
        if(!$this->search || strlen($this->search) < 2){
            return e($snippet);
        }
        $term = preg_quote($this->search, '/');
        $escaped = e($snippet);
        return preg_replace("/($term)/i", '<mark>$1</mark>', $escaped);
    }
}
