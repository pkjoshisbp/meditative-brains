<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\TtsAudioProduct;

class TtsGroupedCatalogController extends Controller
{
    public function index(Request $request)
    {
        $q = TtsAudioProduct::active();
        if ($search = $request->query('search')) {
            $s = '%'.strtolower($search).'%';
            $q->where(function($qq) use ($s){
                $qq->whereRaw('LOWER(name) LIKE ?',[$s])
                   ->orWhereRaw('LOWER(description) LIKE ?',[$s])
                   ->orWhereRaw('LOWER(tags) LIKE ?',[$s]);
            });
        }
        $products = $q->orderBy('group_key')->orderBy('name')->get();
        $groups = [];
        foreach ($products as $p) {
            $gk = $p->group_key ?: ('g-'.$p->id);
            if (!isset($groups[$gk])) {
                $groups[$gk] = [
                    'group_key' => $gk,
                    'title' => $p->name,
                    'variants' => []
                ];
            }
            $groups[$gk]['variants'][] = [
                'id' => $p->id,
                'name' => $p->name,
                'slug' => $p->slug,
                'price' => $p->price,
                'sale_price' => $p->sale_price,
                'language' => $p->language,
                'category' => $p->category,
                'has_preview' => (bool)$p->preview_audio_url,
                'preview_audio_url' => $p->preview_audio_url,
                'is_featured' => (bool)$p->is_featured
            ];
        }
        return response()->json(array_values($groups));
    }
}
