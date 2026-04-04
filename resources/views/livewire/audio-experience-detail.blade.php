@php $p = $product; @endphp
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
    @include('partials.app_only_notice')
        <div class="row g-5">
        <div class="col-lg-8">
            <div class="ratio ratio-16x9 rounded shadow-sm mb-4 bg-light overflow-hidden">
@php
                    $dc = strtolower($p->category ?? $p->ttsCategory?->name ?? '');
                    if (str_contains($dc, 'confidence') || str_contains($dc, 'hypnosis')) {
                        $cat_img = 'confidence.jpg';
                    } elseif (str_contains($dc, 'relax') || str_contains($dc, 'bliss') || str_contains($dc, 'sleep')) {
                        $cat_img = 'relaxation.jpg';
                    } elseif (str_contains($dc, 'motivat') || str_contains($dc, 'inspir') || str_contains($dc, 'quot')) {
                        $cat_img = 'motivation.jpg';
                    } elseif (str_contains($dc, 'happin') || str_contains($dc, 'positive') || str_contains($dc, 'attitude')) {
                        $cat_img = 'happiness.jpg';
                    } elseif (str_contains($dc, 'goal') || str_contains($dc, 'achiev') || str_contains($dc, 'time') || str_contains($dc, 'manage')) {
                        $cat_img = 'goals.jpg';
                    } elseif (str_contains($dc, 'resilien') || str_contains($dc, 'failure')) {
                        $cat_img = 'resilience.jpg';
                    } elseif (str_contains($dc, 'smok') || str_contains($dc, 'quit')) {
                        $cat_img = 'quit-smoking.jpg';
                    } elseif (str_contains($dc, 'meditat')) {
                        $cat_img = 'meditation.jpg';
                    } else {
                        $cat_img = 'wellness.jpg';
                    }
                    $cat_img_src = $p->cover_image_url ?? ($p->cover_image_path ? asset($p->cover_image_path) : asset('images/categories/' . $cat_img));
                @endphp
                <img src="{{ $cat_img_src }}" alt="{{ $p->name }}" class="w-100 h-100 object-fit-cover">
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
            @php
                $isIndia = session('user_currency') === 'INR';
                $gateway = session('payment_gateway', 'paypal');
                $isBook = in_array($p->product_type ?? 'audio', ['ebook_pdf', 'ebook_bundle']);
                $versions = $p->versions ?? collect();
                $selectedVersion = null;
            @endphp

            {{-- Multi-version selector --}}
            @if($versions->count() > 0)
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <h2 class="h6 fw-semibold mb-3"><i class="fas fa-language me-2 text-primary"></i>Available Versions</h2>
                    <div class="d-grid gap-2" id="version-selector">
                        @foreach($versions->where('is_active', true)->sortBy('sort_order') as $v)
                        <button type="button" class="btn btn-outline-secondary btn-sm text-start version-btn"
                            data-version-id="{{ $v->id }}"
                            data-version-label="{{ $v->version_label }}"
                            data-price-usd="{{ $v->price ?? $p->price }}"
                            data-price-inr="{{ $v->inr_price ?? ($v->price ?? $p->price) * 100 }}"
                            onclick="selectVersion(this)">
                            <span class="fw-semibold">{{ $v->version_label }}</span>
                            @if($v->language) <span class="badge bg-secondary ms-1">{{ $v->language }}</span> @endif
                            @if($v->accent) <span class="badge bg-info ms-1">{{ $v->accent }}</span> @endif
                            <span class="float-end">
                                @if($isIndia)&#8377;{{ number_format($v->inr_price ?? ($v->price ?? $p->price) * 100, 0) }}
                                @else ${{ number_format($v->price ?? $p->price, 2) }}@endif
                            </span>
                        </button>
                        @endforeach
                    </div>
                    <p class="text-muted small mt-2 mb-0">Each version counts as one product slot in your subscription.</p>
                </div>
            </div>
            @endif

            {{-- Book product type: PDF / Bundle / Audio-only options --}}
            @if($isBook)
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <h2 class="h6 fw-semibold mb-3"><i class="fas fa-book-open me-2 text-primary"></i>Purchase Options</h2>
                    <div class="d-grid gap-2">
                        @if($p->pdf_price)
                        <div class="border rounded p-3 purchase-option" data-type="pdf"
                             data-price-usd="{{ $p->pdf_price }}"
                             data-price-inr="{{ $p->pdf_price_inr ?: $p->pdf_price * 100 }}"
                             onclick="selectPurchaseOption(this)" style="cursor:pointer;">
                            <div class="d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-file-pdf text-danger me-2"></i><strong>PDF eBook</strong></span>
                                <span class="text-primary fw-bold">
                                    @if($isIndia)&#8377;{{ number_format($p->pdf_price_inr ?: $p->pdf_price * 100, 0) }}
                                    @else ${{ number_format($p->pdf_price, 2) }}@endif
                                </span>
                            </div>
                            <div class="small text-muted mt-1">Digital PDF download</div>
                        </div>
                        @endif
                        @if($p->bundle_price)
                        <div class="border rounded p-3 purchase-option" data-type="bundle"
                             data-price-usd="{{ $p->bundle_price }}"
                             data-price-inr="{{ $p->bundle_price_inr ?: $p->bundle_price * 100 }}"
                             onclick="selectPurchaseOption(this)" style="cursor:pointer;">
                            <div class="d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-layer-group text-success me-2"></i><strong>PDF + Audio Bundle</strong></span>
                                <span class="text-primary fw-bold">
                                    @if($isIndia)&#8377;{{ number_format($p->bundle_price_inr ?: $p->bundle_price * 100, 0) }}
                                    @else ${{ number_format($p->bundle_price, 2) }}@endif
                                </span>
                            </div>
                            <div class="small text-muted mt-1">PDF eBook + Full Audio via App</div>
                        </div>
                        @endif
                        @if($p->audio_only_price)
                        <div class="border rounded p-3 purchase-option" data-type="audio_only"
                             data-price-usd="{{ $p->audio_only_price }}"
                             data-price-inr="{{ $p->inr_price ?: $p->audio_only_price * 100 }}"
                             onclick="selectPurchaseOption(this)" style="cursor:pointer;">
                            <div class="d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-headphones text-primary me-2"></i><strong>Audio Only</strong></span>
                                <span class="text-primary fw-bold">
                                    @if($isIndia)&#8377;{{ number_format($p->inr_price ?: $p->audio_only_price * 100, 0) }}
                                    @else ${{ number_format($p->audio_only_price, 2) }}@endif
                                </span>
                            </div>
                            <div class="small text-muted mt-1"><i class="fas fa-mobile-alt me-1"></i>Available via App only</div>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
            @endif

            {{-- Standard purchase card --}}
            <div class="card shadow-sm mb-4" id="purchase-card">
                <div class="card-body">
                    @php
                        $hasDiscount = $p->sale_price && $p->sale_price < $p->price;
                    @endphp
                    <div class="mb-3" id="price-display">
                        @if($isIndia)
                            @if($hasDiscount && $p->inr_sale_price)
                                <div class="h3 fw-bold text-success mb-0">&#8377;{{ number_format($p->inr_sale_price, 0) }}</div>
                                <div class="text-muted text-decoration-line-through">&#8377;{{ number_format($p->inr_price ?: $p->price * 100, 0) }}</div>
                            @else
                                <div class="h3 fw-bold mb-0">&#8377;{{ number_format($p->inr_price ?: $p->price * 100, 0) }}</div>
                            @endif
                            <div class="small text-muted mt-1">Prices in Indian Rupees</div>
                        @else
                            @if($hasDiscount)
                                <div class="h3 fw-bold text-success mb-0">${{ number_format($p->sale_price, 2) }}</div>
                                <div class="text-muted text-decoration-line-through">${{ number_format($p->price, 2) }}</div>
                            @else
                                <div class="h3 fw-bold mb-0">${{ number_format($p->price, 2) }}</div>
                            @endif
                        @endif
                    </div>

                    @auth
                        @if($isIndia)
                            <button class="btn btn-success w-100 mb-3"
                                id="pay-btn"
                                data-product-id="{{ $p->id }}"
                                data-amount="{{ $p->inr_price ?: $p->price * 100 }}"
                                data-name="{{ $p->name }}"
                                onclick="initiateRazorpay(this)">
                                <i class="fas fa-lock me-2"></i>Pay with Razorpay &#8377;{{ number_format($p->inr_price ?: $p->price * 100, 0) }}
                            </button>
                        @else
                            <a href="{{ route('payment.paypal.create', ['product_id' => $p->id]) }}"
                               class="btn btn-primary w-100 mb-3" id="pay-btn">
                                <i class="fab fa-paypal me-2"></i>Pay with PayPal ${{ number_format($p->price, 2) }}
                            </a>
                        @endif
                    @else
                        <a href="{{ route('login') }}" class="btn btn-primary w-100 mb-3">
                            <i class="fas fa-lock me-2"></i>Login to Purchase
                        </a>
                    @endauth
                    <p class="text-muted small mb-0"><i class="fas fa-check-circle text-success me-1"></i>Instant access after purchase.</p>
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

<script>
// Version selector
function selectVersion(btn) {
    document.querySelectorAll('.version-btn').forEach(b => b.classList.remove('btn-primary', 'active'));
    btn.classList.remove('btn-outline-secondary');
    btn.classList.add('btn-primary', 'active');
    const priceUsd = btn.dataset.priceUsd;
    const priceInr = btn.dataset.priceInr;
    const payBtn = document.getElementById('pay-btn');
    if (payBtn) {
        payBtn.dataset.amount = priceInr;
        payBtn.dataset.versionId = btn.dataset.versionId;
    }
}

// Book purchase option selector
function selectPurchaseOption(el) {
    document.querySelectorAll('.purchase-option').forEach(o => {
        o.classList.remove('border-primary', 'bg-primary-subtle');
    });
    el.classList.add('border-primary', 'bg-primary-subtle');
    const payBtn = document.getElementById('pay-btn');
    if (payBtn) {
        payBtn.dataset.purchaseType = el.dataset.type;
        payBtn.dataset.amount = el.dataset.priceInr;
    }
}

// Razorpay payment initiator
function initiateRazorpay(btn) {
    const productId = btn.dataset.productId;
    const amount = parseFloat(btn.dataset.amount) * 100; // paise
    const name = btn.dataset.name || 'Audio Product';
    const versionId = btn.dataset.versionId || null;
    const purchaseType = btn.dataset.purchaseType || 'audio';

    fetch('{{ route("payment.razorpay.create") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({ product_id: productId, version_id: versionId, purchase_type: purchaseType })
    })
    .then(r => r.json())
    .then(data => {
        if (!data.order_id) { alert('Could not create order. Please try again.'); return; }
        const options = {
            key: data.key,
            amount: data.amount,
            currency: 'INR',
            name: 'Mental Fitness Store',
            description: name,
            order_id: data.order_id,
            handler: function(response) {
                fetch('{{ route("payment.razorpay.verify") }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                    body: JSON.stringify({ razorpay_order_id: response.razorpay_order_id, razorpay_payment_id: response.razorpay_payment_id, razorpay_signature: response.razorpay_signature, product_id: productId, version_id: versionId, purchase_type: purchaseType })
                }).then(r => r.json()).then(d => {
                    if (d.success) { window.location.href = d.redirect || '/'; }
                    else { alert(d.message || 'Payment verification failed.'); }
                });
            },
            theme: { color: '#6d28d9' }
        };
        const rzp = new Razorpay(options);
        rzp.open();
    })
    .catch(() => alert('Payment service unavailable. Please try again.'));
}
</script>
@if(session('user_currency') === 'INR')
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
@endif
