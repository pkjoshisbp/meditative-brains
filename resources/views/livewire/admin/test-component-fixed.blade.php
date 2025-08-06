<div class="card">
    <div class="card-body">
        <p>{{ $message }}</p>
        <button wire:click="$set('message', 'Button clicked!')" class="btn btn-primary">
            Click me
        </button>
    </div>
</div>
