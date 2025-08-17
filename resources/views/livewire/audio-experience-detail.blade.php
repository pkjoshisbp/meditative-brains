@php($p = $product)
<div>
    <div class="py-5 bg-light border-bottom mb-4">
        <div class="container">
            <h1 class="display-5 fw-bold mb-2">{{ $p->name }}</h1>
            @if($p->short_description)
                <p class="lead text-muted mb-0">{{ $p->short_description }}</p>
            @endif
        </div>
    </div>
    <div class="container pb-5">
        <div class="row g-5">
        <div class="col-lg-8">
            <div class="ratio ratio-16x9 rounded shadow-sm mb-4 bg-light overflow-hidden">
                @if($p->cover_image_url || $p->cover_image_path)
                    <img src="{{ $p->cover_image_url ?? asset($p->cover_image_path) }}" alt="{{ $p->name }}" class="w-100 h-100 object-fit-cover">
                @else
                    <div class="d-flex align-items-center justify-content-center w-100 h-100 bg-gradient" style="background: linear-gradient(45deg,#6366f1,#8b5cf6);">
                        <i class="fas fa-headphones fa-4x text-white opacity-75"></i>
                    </div>
                @endif
            </div>
            @if($p->description)
                <div class="mb-4">
                    <h2 class="h5 fw-semibold">About this experience</h2>
                    <div class="text-muted">{!! nl2br(e($p->description)) !!}</div>
                </div>
            @endif
            <div class="card shadow-sm mb-4" x-data="audioPreview()">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h2 class="h6 fw-semibold mb-0">Preview</h2>
                        <button x-on:click="toggle()" class="btn btn-sm btn-primary" x-text="playing ? 'Stop' : 'Play Preview'"></button>
                    </div>
                    <div class="progress mb-2" style="height:6px;">
                        <div class="progress-bar" role="progressbar" :style="`width:${progress}%`"></div>
                    </div>
                    <div class="small text-muted" x-text="status"></div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <div class="mb-3">
                        @if($p->sale_price && $p->sale_price < $p->price)
                            <div class="h3 fw-bold text-success mb-0">${{ number_format($p->sale_price,2) }}</div>
                            <div class="text-muted text-decoration-line-through">${{ number_format($p->price,2) }}</div>
                        @else
                            <div class="h3 fw-bold mb-0">${{ number_format($p->price,2) }}</div>
                        @endif
                    </div>
                    <button class="btn btn-primary w-100 mb-3">Purchase / Unlock</button>
                    <p class="text-muted small mb-0">Instant access after purchase. High-quality audio playback.</p>
                </div>
            </div>
            @if($p->tags)
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h2 class="h6 fw-semibold mb-3">Tags</h2>
                        <div class="d-flex flex-wrap gap-2">
                            @foreach(explode(',', $p->tags) as $tag)
                                <span class="badge bg-primary-subtle text-primary">{{ trim($tag) }}</span>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif
        </div>
        </div>
    </div>
</div>

<script>
// Frontend audio preview replicating admin sequential playback with background music support
function audioPreview(){
    const config = {
        audioUrls: @json(($p->audio_urls ?? []) ?: (($p->preview_audio_url ?? null)? [$p->preview_audio_url]: [])),
        messageRepeatCount: @json($p->message_repeat_count ?? 1),
        repeatInterval: @json($p->repeat_interval ?? 0),
        messageInterval: @json($p->message_interval ?? 0),
        silenceStart: @json($p->silence_start ?? 0),
        silenceEnd: @json($p->silence_end ?? 0),
        hasBackgroundMusic: @json($p->has_background_music ?? false),
        backgroundMusicTrack: @json($p->background_music_track ?? null),
        backgroundMusicType: @json($p->background_music_type ?? null),
        bgMusicVolume: @json($p->bg_music_volume ?? 0.3),
        previewDuration: @json($p->preview_duration ?? 30),
        enforceTimeline: true,
        previewTitle: @json($p->name)
    };
    return {
        playing:false, progress:0, status:'Idle',
        _bg:null,_current:null,_start:null,_raf:null,
        toggle(){ this.playing? this.stop(): this.play(); },
        async play(){
            if(!config.audioUrls.length){ this.status='No preview available'; return; }
            this.stop(true);
            this.playing=true; this.status='Starting preview...'; this.progress=0; this._start=Date.now();
            if(config.hasBackgroundMusic){ await this.startBackground(config); }
            if(config.silenceStart>0) await this.sleep(config.silenceStart*1000);
            const deadline = Date.now()+ (config.previewDuration*1000);
            outer: for(let i=0;i<config.audioUrls.length && this.playing;i++){
                for(let r=0;r<Math.max(1,config.messageRepeatCount||1) && this.playing;r++){
                    if(config.enforceTimeline && Date.now()>=deadline) break outer;
                    await this.playClip(config.audioUrls[i], i, config.audioUrls.length);
                    if(r < (config.messageRepeatCount-1) && config.repeatInterval>0) await this.sleep(config.repeatInterval*1000);
                }
                if(i < config.audioUrls.length-1 && config.messageInterval>0) await this.sleep(config.messageInterval*1000);
            }
            if(config.silenceEnd>0) await this.sleep(config.silenceEnd*1000);
            this.finish();
        },
        stop(internal=false){
            this.playing=false; if(this._raf) cancelAnimationFrame(this._raf);
            if(this._current){ this._current.pause(); this._current=null; }
            if(this._bg){ this._bg.pause(); this._bg=null; }
            if(!internal) this.status='Stopped';
        },
        finish(){ this.stop(true); this.progress=100; this.status='Preview finished'; },
        async startBackground(cfg){
            try{
                this.status='Loading music...';
                const track = cfg.backgroundMusicTrack || cfg.backgroundMusicType || cfg.category || 'relaxing';
                const resp = await fetch(`/bg-music/issue?track=${encodeURIComponent(track)}`, {credentials:'include'});
                if(!resp.ok) throw new Error('bg music issue');
                const data = await resp.json();
                if(!data.url) throw new Error('no music url');
                const v = Math.min(1, Math.max(0, parseFloat(cfg.bgMusicVolume ?? 0.3)));
                const bg = new Audio(data.url); bg.loop=true; bg.volume=v; await bg.play(); this._bg=bg; this.status='Music ready';
            }catch(e){ console.warn('BG music failed', e); this.status='Music unavailable'; }
        },
        playClip(url, index, total){
            return new Promise(res=>{
                const a = new Audio(url); this._current=a;
                a.onloadeddata=()=>{ a.play().catch(err=>console.warn('play blocked',err)); this.status=`Playing ${index+1}/${total}`; this.loopProgress(); };
                a.onended=()=> res(); a.onerror=()=> res();
            });
        },
        loopProgress(){
            if(!this.playing) return;
            const elapsed = (Date.now()-this._start)/1000; const pct = Math.min(100, (elapsed/(config.previewDuration||30))*100);
            this.progress=pct; this._raf=requestAnimationFrame(()=>this.loopProgress());
        },
        sleep(ms){ return new Promise(r=> setTimeout(()=>r(), ms)); }
    }
}
</script>
