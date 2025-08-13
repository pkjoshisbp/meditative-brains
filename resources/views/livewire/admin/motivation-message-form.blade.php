<div>
    {{-- Debug info --}}
    @if($editingRecordId)
        <div class="mb-4 p-3 bg-light border rounded">
            <h6>🐛 Debug Information:</h6>
            <div class="small">
                <strong>Editing Record ID:</strong> {{ $editingRecordId }}<br>
                <strong>Messages Raw Length:</strong> {{ strlen($messages) }}<br>
                <strong>Messages Line Count:</strong> {{ substr_count($messages, "\n") + 1 }}<br>
                <strong>Current Engine:</strong> {{ $engine }}<br>
                <strong>Current Language:</strong> {{ $language }}<br>
                <strong>Current Speaker:</strong> {{ $speaker }}<br>
                <strong>Available Speakers:</strong> {{ implode(', ', $speakers) }}<br>
                <strong>Categories Count:</strong> {{ count($categories) }}<br>
                <strong>Records Count:</strong> {{ count($existingRecords) }}<br>
                <hr class="my-2">
                <strong>Messages Preview:</strong><br>
                <code style="white-space: pre-wrap; font-size: 0.8em;">{{ substr($messages, 0, 200) }}{{ strlen($messages) > 200 ? '...' : '' }}</code>
            </div>
        </div>
    @else
        <div class="mb-4 p-3 bg-info text-white rounded">
            <h6>ℹ️ New Record Mode</h6>
            <div class="small">
                <strong>Engine:</strong> {{ $engine }} | 
                <strong>Language:</strong> {{ $language }} | 
                <strong>Speaker:</strong> {{ $speaker }} | 
                <strong>Available Speakers:</strong> {{ count($speakers) }} speakers
            </div>
        </div>
    @endif

    <div class="card">
        <div class="card-header">
            <h4 class="card-title mb-0">Motivation Message Form</h4>
        </div>
        <div class="card-body">
            <form wire:submit.prevent="{{ $editingRecordId ? 'updateRecord' : 'generateAudio' }}" wire:key="form-{{ $editingRecordId ?? 'new' }}">
            {{-- Category select --}}
            <div class="row mb-3">
                <div class="col-md mb-3">
                    <label class="form-label d-block mb-2">Category</label>
                    <select class="form-select" wire:model.live="categoryId">
                        <option value="">Select</option>
                        @forelse($categories as $category)
                            @if(is_array($category) && isset($category['_id']) && isset($category['category']))
                                <option value="{{ $category['_id'] }}">{{ $category['category'] }}</option>
                            @endif
                        @empty
                            <option disabled>No categories available</option>
                        @endforelse
                    </select>
                </div>

                <div class="col-md mb-3">
                    <label class="form-label d-block mb-2">Engine</label>
                    <select class="form-select" wire:model.live="engine">
                        <option value="azure">Azure</option>
                        <option value="vits">VITS</option>
                    </select>
                </div>

                <div class="col-md mb-3">
                    <label class="form-label d-block mb-2">Language</label>
                    <select class="form-select" wire:model.live="language">
                        @foreach($languages as $lang)
                            <option value="{{ $lang }}">{{ $lang }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md mb-3">
                    <label class="form-label d-block mb-2">Speaker</label>
                    <select class="form-select" wire:model.live="speaker">
                        @foreach($speakers as $spk)
                            <option value="{{ $spk }}">
                                {{ $azureVoices->firstWhere('ShortName', $spk)['DisplayName'] ?? $spk }}
                            </option>
                        @endforeach
                    </select>
                </div>

                @if(!empty($availableStyles))
                    <div class="col-md mb-3">
                        <label class="form-label d-block mb-2">Speaking Style</label>
                        <select class="form-select" wire:model.live="speakerStyle">
                            @foreach($availableStyles as $style)
                                <option value="{{ $style }}">{{ ucfirst($style) }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                @if(!empty($availablePersonalities))
                    <div class="col-md mb-3">
                        <label class="form-label d-block mb-2">Personality</label>
                        <select class="form-select" wire:model.live="speakerPersonality">
                            @foreach($availablePersonalities as $personality)
                                <option value="{{ $personality }}">{{ ucfirst($personality) }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                {{-- Speech Speed with Custom Option --}}
                <div class="col-md mb-3">
                    <label class="form-label d-block mb-2">Speech Speed</label>
                    <select class="form-select" wire:model.live="prosodyRate" id="speedSelect">
                        <option value="">Default</option>
                        <option value="x-slow">Extra Slow</option>
                        <option value="slow">Slow</option>
                        <option value="medium">Medium</option>
                        <option value="fast">Fast</option>
                        <option value="x-fast">Extra Fast</option>
                        <option value="-15%">85% Speed</option>
                        <option value="-20%">80% Speed</option>
                        <option value="-10%">90% Speed</option>
                        <option value="10%">110% Speed</option>
                        <option value="20%">120% Speed</option>
                        <option value="30%">130% Speed</option>
                        <option value="custom">Custom</option>
                    </select>
                    <input 
                        type="text" 
                        class="form-control mt-2" 
                        placeholder="e.g., -25%, +15%, 1.2x" 
                        wire:model.live="prosodyRate"
                        style="display: none;"
                        id="customSpeedInput"
                    >
                </div>

                {{-- Pitch Control --}}
                <div class="col-md mb-3">
                    <label class="form-label d-block mb-2">Pitch</label>
                    <select class="form-select" wire:model.live="prosodyPitch" id="pitchSelect">
                        <option value="">Default</option>
                        <option value="x-low">Extra Low</option>
                        <option value="low">Low</option>
                        <option value="medium">Medium</option>
                        <option value="high">High</option>
                        <option value="x-high">Extra High</option>
                        <option value="-30%">-30%</option>
                        <option value="-25%">-25%</option>
                        <option value="-20%">-20%</option>
                        <option value="-15%">-15%</option>
                        <option value="-10%">-10%</option>
                        <option value="-5%">-5%</option>
                        <option value="+5%">+5%</option>
                        <option value="+10%">+10%</option>
                        <option value="+15%">+15%</option>
                        <option value="+20%">+20%</option>
                        <option value="+25%">+25%</option>
                        <option value="+30%">+30%</option>
                        <option value="custom">Custom</option>
                    </select>
                    <input 
                        type="text" 
                        class="form-control mt-2" 
                        placeholder="e.g., -25%, +15%, 100Hz, +2st" 
                        wire:model.live="prosodyPitch"
                        style="display: none;"
                        id="customPitchInput"
                    >
                </div>

                {{-- Volume Control --}}
                <div class="col-md mb-3">
                    <label class="form-label d-block mb-2">Volume</label>
                    <select class="form-select" wire:model.live="prosodyVolume" id="volumeSelect">
                        <option value="">Default</option>
                        <option value="silent">Silent</option>
                        <option value="x-soft">Extra Soft</option>
                        <option value="soft">Soft</option>
                        <option value="medium">Medium</option>
                        <option value="loud">Loud</option>
                        <option value="x-loud">Extra Loud</option>
                        <option value="-30%">-30%</option>
                        <option value="-25%">-25%</option>
                        <option value="-20%">-20%</option>
                        <option value="-15%">-15%</option>
                        <option value="-10%">-10%</option>
                        <option value="-5%">-5%</option>
                        <option value="+5%">+5%</option>
                        <option value="+10%">+10%</option>
                        <option value="+15%">+15%</option>
                        <option value="+20%">+20%</option>
                        <option value="+25%">+25%</option>
                        <option value="+30%">+30%</option>
                        <option value="custom">Custom</option>
                    </select>
                    <input 
                        type="text" 
                        class="form-control mt-2" 
                        placeholder="e.g., -25%, +15%, 1.5, 0.5" 
                        wire:model.live="prosodyVolume"
                        style="display: none;"
                        id="customVolumeInput"
                    >
                </div>

                {{-- Expression Style Control (from TailoredScenarios) --}}
                @if(!empty($availableExpressionStyles))
                    <div class="col-md mb-3">
                        <label class="form-label d-block mb-2">Expression Style</label>
                        <select class="form-select" wire:model.live="expressionStyle">
                            <option value="">Default</option>
                            @foreach($availableExpressionStyles as $style)
                                <option value="{{ $style }}">{{ ucfirst($style) }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
            </div>

            {{-- Messages textarea with explicit binding --}}
            <div class="mb-4">
                <label class="block text-gray-700">Messages (one per line)</label>
                <textarea 
                    wire:model.live="messages"
                    rows="10" 
                    class="form-control"
                >{{ $messages }}</textarea>
                <div class="mt-2 flex items-center gap-3">
                    <button type="button" wire:click="translateAndFill" class="btn btn-outline-secondary btn-sm">
                        Translate to selected language
                    </button>
                    <label class="ms-3 text-sm">
                        <input type="checkbox" wire:model="shouldTranslate" class="form-check-input me-1">
                        Auto-translate on Save As New
                    </label>
                </div>
                
                {{-- Individual Message Management (when editing) --}}
                @if($editingRecordId && !empty($messages))
                    <div class="mt-3 p-3 bg-light border rounded">
                        <h6 class="mb-3">
                            <i class="fas fa-list"></i> Individual Messages 
                            <small class="text-muted">({{ substr_count($messages, "\n") + 1 }} messages)</small>
                        </h6>
                        @php
                            $individualMessages = array_filter(array_map('trim', explode("\n", $messages)));
                            $categoryId = $categoryId; // Available from component
                        @endphp
                        <div class="row">
                            @foreach($individualMessages as $index => $message)
                                <div class="col-md-6 mb-2">
                                    <div class="d-flex align-items-start">
                                        <div class="flex-grow-1 me-2">
                                            <small class="text-muted">Message {{ $index + 1 }}:</small>
                                            <div class="small bg-white p-2 border rounded">
                                                {{ Str::limit($message, 100) }}
                                            </div>
                                        </div>
                                        <button type="button" 
                                                wire:click="deleteMessage('{{ $categoryId }}', '{{ $editingRecordId }}_{{ $index }}')"
                                                class="btn btn-sm btn-outline-danger"
                                                title="Delete this message"
                                                onclick="return confirm('Are you sure you want to delete this message?')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <div class="text-muted small mt-2">
                            <i class="fas fa-info-circle"></i> 
                            Note: Individual message deletion uses API endpoint: DELETE /category/{categoryId}/message/{messageId}
                        </div>
                    </div>
                @endif
            </div>

            <div class="mb-4">
                <label class="block text-gray-700 mb-2">SSML Markup Messages</label>
                
                <div class="bg-gray-50 p-4 mb-3 rounded border text-sm">
                    <h5 class="font-semibold mb-2">Available Markup Options:</h5>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div>
                            <h6 class="font-semibold">Emphasis:</h6>
                            <ul class="list-disc pl-5 text-sm">
                                <li><code>**text**</code> - Strong emphasis</li>
                                <li><code>*text*</code> - Moderate emphasis</li>
                            </ul>
                        </div>

                        <div>
                            <h6 class="font-semibold">Pauses:</h6>
                            <ul class="list-disc pl-5 text-sm">
                                <li><code>[pause:500]</code> - Break for 500ms</li>
                                <li><code>[silence:1000]</code> - Silence for 1 second</li>
                            </ul>
                        </div>

                        @if(!empty($availableStyles))
                        <div>
                            <h6 class="font-semibold">Speaking Styles:</h6>
                            <ul class="list-disc pl-5 text-sm">
                                @foreach($availableStyles as $style)
                                    <li><code>[style:{{ $style }}]text[/style]</code></li>
                                @endforeach
                            </ul>
                        </div>
                        @endif

                        @if(!empty($availablePersonalities))
                        <div>
                            <h6 class="font-semibold">Personalities:</h6>
                            <ul class="list-disc pl-5 text-sm">
                                @foreach($availablePersonalities as $personality)
                                    <li><code>[personality:{{ $personality }}]text[/personality]</code></li>
                                @endforeach
                            </ul>
                        </div>
                        @endif

                        <div>
                            <h6 class="font-semibold">Rate, Pitch & Volume:</h6>
                            <ul class="list-disc pl-5 text-sm">
                                <li><code>[rate:slow]text[/rate]</code> - Slow speaking</li>
                                <li><code>[rate:fast]text[/rate]</code> - Fast speaking</li>
                                <li><code>[rate:-20%]text[/rate]</code> - 80% speed</li>
                                <li><code>[pitch:high]text[/pitch]</code> - Higher pitch</li>
                                <li><code>[pitch:low]text[/pitch]</code> - Lower pitch</li>
                                <li><code>[volume:soft]text[/volume]</code> - Soft volume</li>
                                <li><code>[volume:loud]text[/volume]</code> - Loud volume</li>
                                <li><code>[prosody rate="-10%" pitch="+5%" volume="soft"]text[/prosody]</code> - Combined</li>
                            </ul>
                        </div>
                    </div>

                    <div class="mt-3 text-xs text-gray-600">
                        <strong>Usage:</strong> Select text in the textarea below and click the markup buttons to wrap it with SSML tags.<br>
                        <strong>Example:</strong><br>
                        <code>
                            I am [style:cheerful]very happy[/style] to see you! [pause:500]<br>
                            Let me tell you **something important**. [silence:1000]<br>
                            This is [rate:slow]*really amazing*[/rate]!
                        </code>
                    </div>
                </div>

                {{-- Interactive Markup Buttons - Moved here above the textarea --}}
                <div class="mb-3 p-3 bg-white rounded border">
                    <h6 class="font-semibold mb-2">Quick Markup Tools:</h6>
                    <div class="flex flex-wrap gap-2 mb-3">
                        {{-- Emphasis buttons --}}
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="wrapSelectedText('**', '**', 'ssmlMessages')">
                            <strong>Bold</strong>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="wrapSelectedText('*', '*', 'ssmlMessages')">
                            <em>Emphasis</em>
                        </button>
                        
                        {{-- Pause buttons --}}
                        <button type="button" class="btn btn-sm btn-outline-info" onclick="insertAtCursor('[pause:500]', 'ssmlMessages')">
                            Pause 500ms
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-info" onclick="insertAtCursor('[silence:1000]', 'ssmlMessages')">
                            Silence 1s
                        </button>
                        
                        {{-- Rate buttons --}}
                        <button type="button" class="btn btn-sm btn-outline-warning" onclick="wrapSelectedText('[rate:slow]', '[/rate]', 'ssmlMessages')">
                            Slow
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-warning" onclick="wrapSelectedText('[rate:fast]', '[/rate]', 'ssmlMessages')">
                            Fast
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-warning" onclick="wrapSelectedText('[rate:-30%]', '[/rate]', 'ssmlMessages')">
                            70% Speed
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-warning" onclick="wrapSelectedText('[rate:-20%]', '[/rate]', 'ssmlMessages')">
                            80% Speed
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-warning" onclick="wrapSelectedText('[rate:20%]', '[/rate]', 'ssmlMessages')">
                            120% Speed
                        </button>
                        
                        {{-- Pitch buttons --}}
                        <button type="button" class="btn btn-sm btn-outline-success" onclick="wrapSelectedText('[pitch:high]', '[/pitch]', 'ssmlMessages')">
                            High Pitch
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-success" onclick="wrapSelectedText('[pitch:low]', '[/pitch]', 'ssmlMessages')">
                            Low Pitch
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-success" onclick="wrapSelectedText('[pitch:+10%]', '[/pitch]', 'ssmlMessages')">
                            +10% Pitch
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-success" onclick="wrapSelectedText('[pitch:-10%]', '[/pitch]', 'ssmlMessages')">
                            -10% Pitch
                        </button>
                        
                        {{-- Combined prosody button --}}
                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="wrapSelectedText('[prosody rate=&quot;-20%&quot; pitch=&quot;+5%&quot;]', '[/prosody]', 'ssmlMessages')">
                            Custom Prosody
                        </button>
                    </div>
                    
                    {{-- Style buttons if available --}}
                    @if(!empty($availableStyles))
                    <div class="mb-3">
                        <label class="form-label text-sm">Speaking Styles:</label>
                        <div class="flex flex-wrap gap-1">
                            @foreach($availableStyles as $style)
                                <button type="button" class="btn btn-xs btn-outline-dark" onclick="wrapSelectedText('[style:{{ $style }}]', '[/style]', 'ssmlMessages')">
                                    {{ ucfirst($style) }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                    @endif
                    
                    {{-- Personality buttons if available --}}
                    @if(!empty($availablePersonalities))
                    <div class="mb-3">
                        <label class="form-label text-sm">Personalities:</label>
                        <div class="flex flex-wrap gap-1">
                            @foreach($availablePersonalities as $personality)
                                <button type="button" class="btn btn-xs btn-outline-purple" onclick="wrapSelectedText('[personality:{{ $personality }}]', '[/personality]', 'ssmlMessages')">
                                    {{ ucfirst($personality) }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                    @endif
                </div>

                <textarea 
                    id="ssmlMessages"
                    wire:model.live="ssmlMessages"
                    rows="10" 
                    class="form-control font-mono"
                    placeholder="Enter messages with markup..."
                >{{ $ssmlMessages }}</textarea>
            </div>

            {{-- Form buttons --}}
            <div class="flex gap-2">
                <button class="btn btn-primary">
                    {{ $editingRecordId ? 'Update Messages' : 'Save Messages' }}
                </button>
                @if($editingRecordId)
                    <button type="button" wire:click="saveAsNew" class="btn btn-secondary">
                        Save As New
                    </button>
                    <button type="button" wire:click="generateAudioForRecord('{{ $editingRecordId }}')" class="btn btn-success">
                        <i class="fas fa-play"></i> Generate Audio Now
                    </button>
                    <button type="button" wire:click="previewSsml" class="btn btn-info">
                        <i class="fas fa-eye"></i> Preview SSML
                    </button>
                    <button type="button" wire:click="deleteRecord('{{ $editingRecordId }}')" 
                            class="btn btn-danger"
                            onclick="return confirm('Are you sure you want to delete this entire record? This action cannot be undone.')">
                        <i class="fas fa-trash"></i> Delete Record
                    </button>
                @endif
                
                {{-- Debug buttons --}}
                <div class="ms-auto">
                    <button type="button" wire:click="fetchAdminMessages" class="btn btn-sm btn-outline-info">
                        🔄 Refresh Records
                    </button>
                    <button type="button" onclick="console.log('Debug info:', {
                        'engine': '{{ $engine }}',
                        'language': '{{ $language }}',
                        'speaker': '{{ $speaker }}',
                        'speakers_count': {{ count($speakers) }},
                        'messages_length': {{ strlen($messages) }},
                        'editing_id': '{{ $editingRecordId }}'
                    })" class="btn btn-sm btn-outline-warning">
                        🐛 Log Debug
                    </button>
                </div>
            </div>
            </form>
        </div>
    </div>

    {{-- Records display --}}
    @if (!empty($existingRecords))
        <div class="mt-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Existing Messages ({{ count($existingRecords) }})</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Category</th>
                                    <th>Engine</th>
                                    <th>Language</th>
                                    <th>Speaker</th>
                                    <th>Messages</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($existingRecords as $record)
                                    <tr>
                                        <td>
                                            @php
                                                $categoryName = 'Unknown';
                                                if (isset($record['categoryId'])) {
                                                    if (is_array($record['categoryId']) && isset($record['categoryId']['category'])) {
                                                        $categoryName = $record['categoryId']['category'];
                                                    } elseif (is_string($record['categoryId'])) {
                                                        // Find category name from categories array
                                                        $category = collect($categories)->firstWhere('_id', $record['categoryId']);
                                                        $categoryName = $category['category'] ?? 'Unknown';
                                                    }
                                                }
                                            @endphp
                                            {{ $categoryName }}
                                        </td>
                                        <td>{{ $record['engine'] ?? '-' }}</td>
                                        <td>{{ $record['language'] ?? '-' }}</td>
                                        <td>{{ $record['speaker'] ?? '-' }}</td>
                                        <td>{{ is_array($record['messages'] ?? null) ? count($record['messages']) : 0 }}</td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button wire:click="editRecord('{{ $record['_id'] }}')" class="btn btn-sm btn-primary">
                                                    <i class="fas fa-edit"></i> Edit
                                                </button>
                                                <button wire:click="generateAudioForRecord('{{ $record['_id'] }}')" class="btn btn-sm btn-secondary">
                                                    <i class="fas fa-play"></i> Generate Audio
                                                </button>
                                                <button wire:click="deleteRecord('{{ $record['_id'] }}')" 
                                                        class="btn btn-sm btn-danger" 
                                                        onclick="return confirm('Are you sure you want to delete this entire record? This action cannot be undone.')">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-muted">No records found</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- SSML Preview Modal --}}
    @if($showSsmlModal)
        <div class="modal fade show" 
             id="ssmlPreviewModal" 
             tabindex="-1" 
             style="display: block;" 
             aria-modal="true" 
             role="dialog"
             wire:keydown.escape="closeSsmlModal">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title mb-0">
                            <i class="fas fa-code"></i> SSML Preview - Record ID: {{ $editingRecordId }}
                        </h5>
                        <button type="button" 
                                class="btn-close btn-close-white" 
                                wire:click="closeSsmlModal" 
                                aria-label="Close"></button>
                    </div>
                    <div class="modal-body" style="max-height: 70vh; overflow-y: auto; background-color: #f8f9fa;">
                        @if(!empty($previewSsmlData))
                            @foreach($previewSsmlData as $data)
                                <div class="card mb-3 shadow-sm">
                                    <div class="card-header bg-info text-white">
                                        <h6 class="card-title mb-0">
                                            <i class="fas fa-comment"></i> Message {{ $data['index'] }}
                                        </h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <h6 class="text-primary border-bottom pb-2">
                                                    <i class="fas fa-edit"></i> Markup Version:
                                                </h6>
                                                <div class="bg-white p-3 rounded border shadow-sm">
                                                    <pre class="mb-0 text-wrap" style="font-size: 0.9em; color: #495057;">{{ $data['markup'] }}</pre>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <h6 class="text-success border-bottom pb-2">
                                                    <i class="fas fa-code"></i> Generated SSML:
                                                </h6>
                                                <div class="bg-white p-3 rounded border shadow-sm" style="max-height: 300px; overflow-y: auto;">
                                                    <pre class="mb-0 text-wrap" style="font-size: 0.85em; font-family: 'Courier New', monospace; color: #28a745;">{{ $data['ssml'] }}</pre>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                            
                            {{-- Summary info --}}
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> 
                                <strong>Total Messages:</strong> {{ count($previewSsmlData) }}
                                <br>
                                <small class="text-muted">
                                    <i class="fas fa-keyboard"></i> Press ESC or click Close to exit this preview
                                </small>
                            </div>
                        @else
                            <div class="text-center text-muted py-5">
                                <i class="fas fa-exclamation-triangle fa-3x mb-3"></i>
                                <h5>No SSML Data Available</h5>
                                <p>No SSML data was found for this record</p>
                            </div>
                        @endif
                    </div>
                    <div class="modal-footer bg-light">
                        <div class="me-auto text-muted small">
                            <i class="fas fa-clock"></i> Record ID: {{ $editingRecordId }}
                        </div>
                        <button type="button" class="btn btn-secondary" wire:click="closeSsmlModal">
                            <i class="fas fa-times"></i> Close
                        </button>
                    </div>
                </div>
            </div>
        </div>
        {{-- Modal backdrop --}}
        <div class="modal-backdrop fade show" wire:click="closeSsmlModal"></div>
    @endif
</div>

<script>
function wrapSelectedText(openTag, closeTag, textareaId) {
    const textarea = document.getElementById(textareaId);
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const selectedText = textarea.value.substring(start, end);
    
    if (selectedText.length === 0) {
        alert('Please select some text first');
        return;
    }
    
    const replacement = openTag + selectedText + closeTag;
    const newValue = textarea.value.substring(0, start) + replacement + textarea.value.substring(end);
    
    // Update the textarea value
    textarea.value = newValue;
    
    // Trigger Livewire update
    textarea.dispatchEvent(new Event('input', { bubbles: true }));
    
    // Set cursor position after the inserted text
    const newCursorPos = start + replacement.length;
    textarea.setSelectionRange(newCursorPos, newCursorPos);
    textarea.focus();
}

function insertAtCursor(text, textareaId) {
    const textarea = document.getElementById(textareaId);
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    
    const newValue = textarea.value.substring(0, start) + text + textarea.value.substring(end);
    
    // Update the textarea value
    textarea.value = newValue;
    
    // Trigger Livewire update
    textarea.dispatchEvent(new Event('input', { bubbles: true }));
    
    // Set cursor position after the inserted text
    const newCursorPos = start + text.length;
    textarea.setSelectionRange(newCursorPos, newCursorPos);
    textarea.focus();
}

// Add some custom CSS for better button styling
document.addEventListener('DOMContentLoaded', function() {
    const style = document.createElement('style');
    style.textContent = `
        .btn-xs {
            padding: 0.125rem 0.5rem;
            font-size: 0.75rem;
            line-height: 1.25;
        }
        .btn-outline-purple {
            color: #7c3aed;
            border-color: #7c3aed;
        }
        .btn-outline-purple:hover {
            color: #fff;
            background-color: #7c3aed;
            border-color: #7c3aed;
        }
        .modal.show {
            z-index: 1055;
        }
        .modal-backdrop.show {
            z-index: 1050;
        }
        .modal-xl {
            max-width: 95%;
        }
        @media (min-width: 1200px) {
            .modal-xl {
                max-width: 1140px;
            }
        }
    `;
    document.head.appendChild(style);
    
    // Handle custom speed input with Livewire compatibility
    function initSpeedControl() {
        const speedSelect = document.getElementById('speedSelect');
        const customSpeedInput = document.getElementById('customSpeedInput');
        
        if (speedSelect && customSpeedInput) {
            // Remove any existing listeners
            speedSelect.removeEventListener('change', handleSpeedChange);
            speedSelect.addEventListener('change', handleSpeedChange);
            
            // Initialize on page load
            if (speedSelect.value === 'custom') {
                customSpeedInput.style.display = 'block';
            } else {
                customSpeedInput.style.display = 'none';
            }
        }
    }
    
    function handleSpeedChange() {
        const customSpeedInput = document.getElementById('customSpeedInput');
        if (this.value === 'custom') {
            customSpeedInput.style.display = 'block';
            customSpeedInput.focus();
        } else {
            customSpeedInput.style.display = 'none';
        }
    }
    
    // Initialize speed control
    initSpeedControl();
    
    // Re-initialize after Livewire updates
    document.addEventListener('livewire:updated', function() {
        initSpeedControl();
    });
    
    // Handle custom pitch input with Livewire compatibility
    function initPitchControl() {
        const pitchSelect = document.getElementById('pitchSelect');
        const customPitchInput = document.getElementById('customPitchInput');
        
        if (pitchSelect && customPitchInput) {
            // Remove any existing listeners
            pitchSelect.removeEventListener('change', handlePitchChange);
            pitchSelect.addEventListener('change', handlePitchChange);
            
            // Initialize on page load
            if (pitchSelect.value === 'custom') {
                customPitchInput.style.display = 'block';
            } else {
                customPitchInput.style.display = 'none';
            }
        }
    }
    
    function handlePitchChange() {
        const customPitchInput = document.getElementById('customPitchInput');
        if (this.value === 'custom') {
            customPitchInput.style.display = 'block';
            customPitchInput.focus();
        } else {
            customPitchInput.style.display = 'none';
        }
    }
    
    // Initialize pitch control
    initPitchControl();
    
    // Handle custom volume input with Livewire compatibility
    function initVolumeControl() {
        const volumeSelect = document.getElementById('volumeSelect');
        const customVolumeInput = document.getElementById('customVolumeInput');
        
        if (volumeSelect && customVolumeInput) {
            // Remove any existing listeners
            volumeSelect.removeEventListener('change', handleVolumeChange);
            volumeSelect.addEventListener('change', handleVolumeChange);
            
            // Initialize on page load
            if (volumeSelect.value === 'custom') {
                customVolumeInput.style.display = 'block';
            } else {
                customVolumeInput.style.display = 'none';
            }
        }
    }
    
    function handleVolumeChange() {
        const customVolumeInput = document.getElementById('customVolumeInput');
        if (this.value === 'custom') {
            customVolumeInput.style.display = 'block';
            customVolumeInput.focus();
        } else {
            customVolumeInput.style.display = 'none';
        }
    }
    
    // Initialize volume control
    initVolumeControl();
    
    // Re-initialize all controls after Livewire updates
    document.addEventListener('livewire:updated', function() {
        initPitchControl();
        initVolumeControl();
    });
});
</script>
