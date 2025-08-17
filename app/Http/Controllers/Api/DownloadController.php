<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserDownload;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadController extends Controller
{
    public function serve(Request $request, UserDownload $download)
    {
        $user = $request->user();
        if($download->user_id !== $user->id){ abort(403); }
        if(! $request->hasValidSignature()) abort(401);

        // Resolve file path
        $path = null; $name='audio.mp3';
        if ($download->product_id && $download->product) { $path = $download->product->full_file; $name = $download->product->slug.'.mp3'; }
        if ($download->tts_audio_product_id && $download->ttsProduct) { $path = $download->ttsProduct->audio_urls[0] ?? null; $name = $download->ttsProduct->slug.'.mp3'; }
        if(!$path) abort(404);
        $abs = storage_path('app/'.$path);
        if(!is_file($abs)) abort(404);

        $cfg = config('downloads');
        $chunk = (int)($cfg['base_chunk_size'] ?? 16384);
        $normalDelay = (int)($cfg['normal_delay_ms'] ?? 0);
        $throttledDelay = (int)($cfg['throttled_delay_ms'] ?? 25);
        $hardCap = (int)($cfg['hard_cap_kbps'] ?? 0);

        // Determine mode (simple heuristic; can be replaced with cache counting active downloads)
        $throttled = false;
        $globalThreshold = (int)($cfg['global_concurrent_threshold'] ?? 0);
        if($globalThreshold > 0){
            // naive global counter using cache increment
            $key='active_downloads';
            $active = cache()->increment($key);
            cache()->put($key,$active, 60); // TTL 60s refresh
            if($active > $globalThreshold){ $throttled=true; }
        }

        $response = new StreamedResponse(function() use ($abs,$chunk,$normalDelay,$throttledDelay,$throttled,$hardCap){
            $fp = fopen($abs,'rb');
            if(!$fp){ return; }
            $start = microtime(true);
            $bytesSent = 0;
            while(!feof($fp)){
                $buffer = fread($fp,$chunk);
                if($buffer===false) break;
                echo $buffer;
                $bytesSent += strlen($buffer);
                if($hardCap>0){
                    // enforce hard cap via timing
                    $elapsed = microtime(true)-$start;
                    $targetBytes = ($hardCap*1024)*$elapsed; // convert kbps to bytes per second
                    if($bytesSent > $targetBytes){
                        usleep(20000); // 20ms backoff
                    }
                } else {
                    $delay = $throttled? $throttledDelay : $normalDelay;
                    if($delay>0) usleep($delay*1000);
                }
                @ob_flush(); flush();
            }
            fclose($fp);
        });
        $response->headers->set('Content-Type','audio/mpeg');
        $response->headers->set('Content-Disposition','attachment; filename="'.$name.'"');
        $response->headers->set('X-Download-Mode', $throttled? 'throttled':'normal');
        return $response;
    }
}
