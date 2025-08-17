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
function audioPreview(){
    return {
        playing:false, progress:0, status:'', controller:null, clips: @json(array_slice($p->sample_messages ?? [],0,3)),
        toggle(){ if(this.playing){ this.stop(); } else { this.play(); } },
        play(){ if(!this.clips.length){ this.status='No samples available'; return;} this.playing=true; this.status='Loading...'; this.progress=0; this.playSequential(0); },
        stop(){ this.playing=false; if(this.controller){ this.controller.abort(); } this.status='Stopped'; },
        async playSequential(i){ if(!this.playing) return; if(i>=this.clips.length){ this.status='Preview finished'; this.playing=false; this.progress=100; return;} const msg=this.clips[i]; try{ const url= await this.fetchClipUrl(msg); await this.playAudio(url, i); this.playSequential(i+1);}catch(e){ this.status='Error: '+e.message; this.playing=false;} },
        async fetchClipUrl(message){ const res = await fetch('/audio/preview-url',{method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content}, body: JSON.stringify({ message })}); if(!res.ok) throw new Error('preview failed'); const data= await res.json(); return data.url; },
        playAudio(url, index){ return new Promise((resolve,reject)=>{ const audio=new Audio(url); audio.volume=1.0; audio.onloadedmetadata=()=>{ audio.play(); this.status='Playing sample '+(index+1)+' of '+this.clips.length;}; audio.ontimeupdate=()=>{ const base = (index/this.clips.length)*100; const seg = (audio.currentTime / audio.duration)*(100/this.clips.length); this.progress = Math.min(100, base+seg); }; audio.onended=()=> resolve(); audio.onerror=()=> reject(new Error('audio error')); }); }
    };
}
</script>
