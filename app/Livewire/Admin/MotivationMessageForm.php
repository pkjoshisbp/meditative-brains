<?php
namespace App\Livewire\Admin;

use Livewire\Component;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Process\Process;

class MotivationMessageForm extends Component
{
    public $categoryId = '';
    public $messages = '';
    public $engine = 'azure';
    public $language = 'en-US';
    public $speaker = 'en-US-AriaNeural';
    public $speakerStyle = '';

    // Prosody controls
    public $prosodyRate = 'medium';
    public $prosodyPitch = 'medium';
    public $prosodyVolume = 'medium';

    // Test properties for Livewire connectivity
    public $testCounter = 0;
    public $testInput = '';

    public $categories = [];
    public $languages = [];
    public $speakers = [];
    public $availableStyles = [];

    public $existingRecords = []; // to list current saved records
    public $editingRecordId=null;

    public $ssmlMessages = '';  // New field for SSML markup version

    public $savingAsNew = false; // Renamed from saveAsNew
    public $shouldTranslate = false; // For auto-translation

    public $azureVoices = [];

    // Add these properties
    public $speakerPersonality = '';
    public $availablePersonalities = [];
    
    // Add these properties for expression style (from TailoredScenarios)
    public $expressionStyle = '';
    public $availableExpressionStyles = [];

    private $markupReplacements = [];

    // Preview SSML data
    public $previewSsmlData = [];
    public $showSsmlModal = false;

    protected $casts = [
    'editingRecordId' => 'string',
];


    public function mount()
    {
        try {
            \Log::info('Mount method started - with existing records');
            
            // Load categories - try API first, fallback to test data
            try {
                $response = Http::timeout(5)->get('https://meditative-brains.com:3001/api/category');
                if ($response->successful()) {
                    $this->categories = $response->json();
                    \Log::info('Real categories loaded', ['count' => count($this->categories)]);
                } else {
                    throw new \Exception('API failed');
                }
            } catch (\Exception $e) {
                \Log::warning('Using test categories due to API error', ['error' => $e->getMessage()]);
                $this->categories = [
                    ['_id' => 'test1', 'category' => 'Test Category 1'],
                    ['_id' => 'test2', 'category' => 'Test Category 2']
                ];
            }
            
            // Load existing records - try API first, fallback to test data
            try {
                $response = Http::timeout(5)->get('https://meditative-brains.com:3001/api/motivationMessage/admin-only');
                if ($response->successful()) {
                    $this->existingRecords = $response->json();
                    \Log::info('Real existing records loaded', ['count' => count($this->existingRecords)]);
                } else {
                    throw new \Exception('API failed');
                }
            } catch (\Exception $e) {
                \Log::warning('Using test records due to API error', ['error' => $e->getMessage()]);
                $this->existingRecords = [
                    [
                        '_id' => 'test-record-1',
                        'categoryId' => 'test1',
                        'engine' => 'azure',
                        'language' => 'en-US',
                        'speaker' => 'en-US-AriaNeural',
                        'messages' => ['Test message 1', 'Test message 2']
                    ],
                    [
                        '_id' => 'test-record-2',
                        'categoryId' => 'test2',
                        'engine' => 'azure',
                        'language' => 'en-US',
                        'speaker' => 'en-US-JennyNeural',
                        'messages' => ['Another test message']
                    ]
                ];
            }
            
            // Load Azure voices and initialize properly
            $this->loadAzureVoices();
            $this->initializeLanguagesAndSpeakers();
            
            \Log::info('Mount method completed successfully');
            
        } catch (\Exception $e) {
            \Log::error('Mount method failed completely', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Set absolute fallback defaults
            $this->categories = [['_id' => 'fallback', 'category' => 'Fallback Category']];
            $this->languages = ['en-US'];
            $this->language = 'en-US';
            $this->speakers = ['default'];
            $this->speaker = 'default';
            $this->existingRecords = [
                [
                    '_id' => 'fallback-record',
                    'categoryId' => 'fallback',
                    'engine' => 'azure',
                    'language' => 'en-US',
                    'speaker' => 'default',
                    'messages' => ['Fallback test message']
                ]
            ];
        }
    }

    private function loadAzureVoices()
    {
        try {
            $voicesPath = config_path('azure-voices.json');
            if (file_exists($voicesPath)) {
                $voicesJson = file_get_contents($voicesPath);
                $this->azureVoices = collect(json_decode($voicesJson, true));
                \Log::info('Azure voices loaded successfully', ['count' => $this->azureVoices->count()]);
            } else {
                \Log::error('azure-voices.json file not found');
                $this->azureVoices = collect([]);
            }
        } catch (\Exception $e) {
            \Log::error('Failed to load azure voices', ['error' => $e->getMessage()]);
            $this->azureVoices = collect([]);
        }
    }

    private function initializeLanguagesAndSpeakers()
    {
        try {
            if ($this->azureVoices->isNotEmpty()) {
                // Get unique languages from the voices
                $this->languages = $this->azureVoices->pluck('Locale')->unique()->sort()->values()->all();
                $this->language = $this->languages[0] ?? 'en-US';
                
                // Update speakers for the default language
                $this->updateSpeakersFromJson();
                
                \Log::info('Languages and speakers initialized', [
                    'languages_count' => count($this->languages),
                    'speakers_count' => count($this->speakers),
                    'default_language' => $this->language,
                    'default_speaker' => $this->speaker
                ]);
            } else {
                // Fallback if no voices loaded
                $this->languages = ['en-US'];
                $this->language = 'en-US';
                $this->speakers = ['en-US-AriaNeural'];
                $this->speaker = 'en-US-AriaNeural';
                \Log::warning('Using fallback language/speaker settings');
            }
        } catch (\Exception $e) {
            \Log::error('Failed to initialize languages and speakers', ['error' => $e->getMessage()]);
            // Use fallback
            $this->languages = ['en-US'];
            $this->language = 'en-US';
            $this->speakers = ['en-US-AriaNeural'];
            $this->speaker = 'en-US-AriaNeural';
        }
    }

    private function fetchMessagesForCategory($categoryId)
    {
        $response = Http::get("https://meditative-brains.com:3001/api/motivationMessage/category/{$categoryId}");
        $this->existingRecords = $response->successful() ? $response->json() : [];
    }

    public function fetchAdminMessages()
    {
        \Log::info('DEBUG: Fetching admin messages from API');
        
        try {
            $response = Http::timeout(30)->get("https://meditative-brains.com:3001/api/motivationMessage/admin-only");
            
            \Log::info('DEBUG: fetchAdminMessages response', [
                'status' => $response->status(),
                'successful' => $response->successful(),
                'response_size' => strlen($response->body()),
                'response_preview' => substr($response->body(), 0, 500)
            ]);
            
            if ($response->successful()) {
                $data = $response->json();
                $this->existingRecords = is_array($data) ? $data : [];
                
                \Log::info('DEBUG: Records loaded', [
                    'total_records' => count($this->existingRecords),
                    'first_record_preview' => !empty($this->existingRecords) ? [
                        'id' => $this->existingRecords[0]['_id'] ?? 'no_id',
                        'messages_count' => is_array($this->existingRecords[0]['messages'] ?? null) ? count($this->existingRecords[0]['messages']) : 'not_array',
                        'engine' => $this->existingRecords[0]['engine'] ?? 'no_engine'
                    ] : 'no_records'
                ]);
            } else {
                \Log::warning('DEBUG: API call failed', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                $this->existingRecords = [];
            }
        } catch (\Exception $e) {
            \Log::error('DEBUG: fetchAdminMessages exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->existingRecords = [];
        }
    }



    public function updatedLanguage()
    {
        \Log::info('DEBUG: updatedLanguage triggered', [
            'new_language' => $this->language,
            'engine' => $this->engine
        ]);
        
        // Only update speakers if using Azure engine
        // For VITS, speakers don't change based on language
        if ($this->engine === 'azure') {
            $this->updateSpeakersFromJson();
        } else {
            \Log::info('DEBUG: Skipping speaker update for non-Azure engine', [
                'engine' => $this->engine
            ]);
        }
        
        // Force Livewire to re-render the component
        $this->dispatch('speakers-updated');
    }

    // Add this method to update styles when speaker changes
    public function updatedSpeaker()
    {
        $this->updateSpeakerStylesFromJson();
    }

    public function updatedEngine()
    {
        \Log::info('DEBUG: Engine changed', [
            'new_engine' => $this->engine,
            'current_language' => $this->language
        ]);
        
        if ($this->engine === 'vits') {
            $this->language = 'en-US';
            $this->speaker = 'p225';
            // Proper VITS speaker IDs - not the labels that were showing before
            $this->speakers = ['p225', 'p226', 'p227', 'p228', 'p229', 'p230', 'p231', 'p232', 'p233', 'p234', 'p236', 'p237', 'p238', 'p239', 'p240', 'p241', 'p243', 'p244', 'p245', 'p246', 'p247', 'p248', 'p249', 'p250', 'p251', 'p252', 'p253', 'p254', 'p255', 'p256', 'p257', 'p258', 'p259', 'p260', 'p261', 'p262', 'p263', 'p264', 'p265', 'p266', 'p267', 'p268', 'p269', 'p270', 'p271', 'p272', 'p273', 'p274', 'p275', 'p276', 'p277', 'p278', 'p279', 'p280', 'p281', 'p282', 'p283', 'p284', 'p285', 'p286', 'p287', 'p288', 'p292', 'p293', 'p294', 'p295', 'p297', 'p298', 'p299', 'p300', 'p301', 'p302', 'p303', 'p304', 'p305', 'p306', 'p307', 'p308', 'p310', 'p311', 'p312', 'p313', 'p314', 'p316', 'p317', 'p318', 'p323', 'p326', 'p329', 'p330', 'p333', 'p334', 'p335', 'p336', 'p339', 'p340', 'p341', 'p343', 'p345', 'p347', 'p351', 'p360', 'p361', 'p362', 'p363', 'p364', 'p374', 'p376'];
            
            \Log::info('DEBUG: VITS engine selected', [
                'language_set_to' => $this->language,
                'speaker_set_to' => $this->speaker,
                'available_speakers_count' => count($this->speakers),
                'first_10_speakers' => array_slice($this->speakers, 0, 10)
            ]);
        } else {
            \Log::info('DEBUG: Azure engine selected, updating speakers from JSON');
            $this->updateSpeakersFromJson();
        }
    }

    public function updatedCategoryId()
    {
        if ($this->categoryId) {
            $this->fetchMessagesForCategory($this->categoryId);
        }
    }

    private function updateSpeakersFromJson()
    {
        if ($this->engine === 'azure') {
            // Get speakers with direct locale match
            $primarySpeakers = $this->azureVoices->where('Locale', $this->language)->values();
            
            // Get multilingual speakers that support this locale in their SecondaryLocaleList
            $multilingualSpeakers = $this->azureVoices->filter(function($voice) {
                return isset($voice['SecondaryLocaleList']) && 
                       in_array($this->language, $voice['SecondaryLocaleList']);
            });

            // Merge both collections and get unique speakers
            $allSpeakers = $primarySpeakers->concat($multilingualSpeakers);
            $this->speakers = $allSpeakers->pluck('ShortName')->unique()->values()->all();

            \Log::info('DEBUG: updateSpeakersFromJson - Azure', [
                'language' => $this->language,
                'primary_speakers_count' => $primarySpeakers->count(),
                'multilingual_speakers_count' => $multilingualSpeakers->count(),
                'total_speakers_count' => count($this->speakers),
                'first_5_speakers' => array_slice($this->speakers, 0, 5),
                'primary_speaker_names' => $primarySpeakers->pluck('ShortName')->toArray(),
                'multilingual_speaker_names' => $multilingualSpeakers->pluck('ShortName')->toArray(),
                'sample_voice_data' => $this->azureVoices->take(2)->map(function($voice) {
                    return [
                        'ShortName' => $voice['ShortName'] ?? 'N/A',
                        'Locale' => $voice['Locale'] ?? 'N/A',
                        'SecondaryLocaleList' => $voice['SecondaryLocaleList'] ?? null
                    ];
                })->toArray()
            ]);

            if (!in_array($this->speaker, $this->speakers)) {
                $this->speaker = $this->speakers[0] ?? '';
            }
        } elseif ($this->engine === 'vits') {
            // VITS speakers are fixed regardless of language
            $this->speakers = ['p225', 'p226', 'p227', 'p228', 'p229', 'p230', 'p231', 'p232', 'p233', 'p234', 'p236', 'p237', 'p238', 'p239', 'p240', 'p241', 'p243', 'p244', 'p245', 'p246', 'p247', 'p248', 'p249', 'p250', 'p251', 'p252', 'p253', 'p254', 'p255', 'p256', 'p257', 'p258', 'p259', 'p260', 'p261', 'p262', 'p263', 'p264', 'p265', 'p266', 'p267', 'p268', 'p269', 'p270', 'p271', 'p272', 'p273', 'p274', 'p275', 'p276', 'p277', 'p278', 'p279', 'p280', 'p281', 'p282', 'p283', 'p284', 'p285', 'p286', 'p287', 'p288', 'p292', 'p293', 'p294', 'p295', 'p297', 'p298', 'p299', 'p300', 'p301', 'p302', 'p303', 'p304', 'p305', 'p306', 'p307', 'p308', 'p310', 'p311', 'p312', 'p313', 'p314', 'p316', 'p317', 'p318', 'p323', 'p326', 'p329', 'p330', 'p333', 'p334', 'p335', 'p336', 'p339', 'p340', 'p341', 'p343', 'p345', 'p347', 'p351', 'p360', 'p361', 'p362', 'p363', 'p364', 'p374', 'p376'];
            
            if (!in_array($this->speaker, $this->speakers)) {
                $this->speaker = $this->speakers[0] ?? 'p225';
            }
            
            \Log::info('DEBUG: updateSpeakersFromJson - VITS', [
                'language' => $this->language,
                'speakers_count' => count($this->speakers),
                'current_speaker' => $this->speaker,
                'first_10_speakers' => array_slice($this->speakers, 0, 10)
            ]);
        } else {
            \Log::warning('DEBUG: Unknown engine in updateSpeakersFromJson', [
                'engine' => $this->engine
            ]);
        }
        
        $this->updateSpeakerStylesFromJson();
        
        // Force Livewire to update the component
        $this->dispatch('speakers-list-updated', ['speakers' => $this->speakers]);
    }

    private function updateSpeakerStylesFromJson()
    {
        if ($this->engine === 'azure') {
            $voice = $this->azureVoices->firstWhere('ShortName', $this->speaker);
            
            \Log::info('DEBUG: updateSpeakerStylesFromJson', [
                'speaker' => $this->speaker,
                'voice_found' => $voice ? true : false,
                'voice_display_name' => $voice['DisplayName'] ?? 'N/A',
                'total_speakers_available' => count($this->speakers)
            ]);
            
            $this->availableStyles = $voice['StyleList'] ?? [];
            $this->speakerStyle = '';
            
            // Update personalities based on speaker
            $this->availablePersonalities = $voice['RolePlayList'] ?? [];
            $this->speakerPersonality = '';
            
            // Get personalities correctly - VoiceTag.VoicePersonalities is nested
            if (isset($voice['VoiceTag']['VoicePersonalities'])) {
                $this->availablePersonalities = $voice['VoiceTag']['VoicePersonalities'];
            }
            
            // Only reset speakerPersonality if current value is not in the available list
            if (!in_array($this->speakerPersonality, $this->availablePersonalities)) {
                $this->speakerPersonality = $this->availablePersonalities[0] ?? '';
            }
            
            // Get expression styles from TailoredScenarios
            $this->availableExpressionStyles = [];
            if (isset($voice['VoiceTag']['TailoredScenarios'])) {
                $this->availableExpressionStyles = $voice['VoiceTag']['TailoredScenarios'];
            }
            
            // Only reset expressionStyle if current value is not in the available list
            if (!in_array($this->expressionStyle, $this->availableExpressionStyles)) {
                $this->expressionStyle = $this->availableExpressionStyles[0] ?? '';
            }
        }
    }

    public function generateAudio()
    {
        \Log::info('DEBUG: generateAudio called (new record)', [
            'categoryId' => $this->categoryId,
            'messages_raw' => $this->messages,
            'messages_length' => strlen($this->messages),
            'engine' => $this->engine,
            'language' => $this->language,
            'speaker' => $this->speaker
        ]);
        
        // For new records, use the category-based audio generation API
        if (!$this->categoryId) {
            session()->flash('error', 'Please select a category first');
            return;
        }

        try {
            \Log::info('DEBUG: Using category-based audio generation API', [
                'url' => 'https://meditative-brains.com:3001/api/generate-category-audio',
                'payload' => [
                    'categoryId' => $this->categoryId,
                    'language' => $this->language,
                    'speaker' => $this->speaker,
                    'engine' => $this->engine
                ]
            ]);

            $response = Http::post('https://meditative-brains.com:3001/api/generate-category-audio', [
                'categoryId' => $this->categoryId,
                'language' => $this->language,
                'speaker' => $this->speaker,
                'engine' => $this->engine
            ]);

            \Log::info('DEBUG: generateAudio API Response', [
                'status_code' => $response->status(),
                'successful' => $response->successful(),
                'response_body' => $response->body(),
                'response_headers' => $response->headers()
            ]);

            if ($response->successful()) {
                $result = $response->json();
                $message = isset($result['filesGenerated']) 
                    ? "Audio generation started: {$result['filesGenerated']} files queued" 
                    : 'Audio generation started';
                session()->flash('success', $message);
            } else {
                session()->flash('error', 'Audio generation failed: ' . $response->body());
                \Log::error('Audio generation API failed:', ['response' => $response->json()]);
            }
        } catch (\Exception $e) {
            \Log::error('DEBUG: generateAudio exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            session()->flash('error', 'Error: ' . $e->getMessage());
        }
    }

    public function generateAudioForRecord($recordId)
    {
        \Log::info('DEBUG: generateAudioForRecord called', [
            'recordId' => $recordId,
            'method' => 'generateAudioForRecord',
            'timestamp' => now()
        ]);
        
        try {
            \Log::info('DEBUG: Using record-based audio generation API (like working component)', [
                'url' => "https://meditative-brains.com:3001/api/generate-category-audio/{$recordId}",
                'method' => 'GET',
                'recordId' => $recordId
            ]);

            // Use the correct API endpoint from working component
            $response = Http::get("https://meditative-brains.com:3001/api/generate-category-audio/{$recordId}");

            \Log::info('DEBUG: generateAudioForRecord API Response', [
                'status_code' => $response->status(),
                'successful' => $response->successful(),
                'response_body' => $response->body(),
                'response_headers' => $response->headers()
            ]);

            if ($response->successful()) {
                $result = $response->json();
                \Log::info('Audio generation triggered successfully', ['response' => $result]);
                $message = isset($result['filesGenerated'])
                    ? "Audio generation started: {$result['filesGenerated']} files queued"
                    : 'Audio generation started';
                session()->flash('success', $message);
            } else {
                \Log::error('Audio generation API failed:', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                session()->flash('error', 'Audio generation failed: ' . $response->body());
            }
        } catch (\Exception $e) {
            \Log::error('DEBUG: generateAudioForRecord exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            session()->flash('error', 'Error generating audio: ' . $e->getMessage());
        }
    }

    public function editRecord($recordId)
    {
        try {
            \Log::info('EditRecord called', ['recordId' => $recordId]);
            
            $record = collect($this->existingRecords)->firstWhere('_id', $recordId);
            
            if (!$record) {
                throw new \Exception("Record not found");
            }

            \Log::info('Record found', ['record' => $record]);

            $this->editingRecordId = $recordId;
            $this->categoryId = is_array($record['categoryId']) ? $record['categoryId']['_id'] : $record['categoryId'];
            $this->engine = $record['engine'] ?? 'azure';
            $this->language = $record['language'] ?? 'en-US';
            $this->speaker = $record['speaker'] ?? 'en-US-AriaNeural';
            
            \Log::info('Basic fields set', [
                'editingRecordId' => $this->editingRecordId,
                'categoryId' => $this->categoryId,
                'engine' => $this->engine,
                'language' => $this->language,
                'speaker' => $this->speaker
            ]);
            
            // First update the language/speaker to populate available options
            $this->updatedLanguage();
            $this->updatedSpeaker();
            
            // IMPORTANT: If engine changed to VITS but speaker is still Azure, fix it
            if ($this->engine === 'vits' && strpos($this->speaker, 'Neural') !== false) {
                \Log::info('DEBUG: Fixing VITS speaker - was Azure speaker', [
                    'old_speaker' => $this->speaker,
                    'engine' => $this->engine
                ]);
                $this->speaker = 'p225'; // Default VITS speaker
                $this->updatedEngine(); // This will set proper VITS speakers
            }
            
            // Then restore speaker style and personality if available
            $this->speakerStyle = $record['speakerStyle'] ?? '';
            $this->speakerPersonality = $record['speakerPersonality'] ?? '';
            
            // Restore prosody settings if available
            $this->prosodyRate = $record['prosodyRate'] ?? '';
            $this->prosodyPitch = $record['prosodyPitch'] ?? '';
            $this->prosodyVolume = $record['prosodyVolume'] ?? '';
            
            // Expression style handling
            $this->expressionStyle = $record['expressionStyle'] ?? '';
            
            // If expressionStyle is empty but speakerStyle has a value that could be an expression style,
            // check if it belongs to TailoredScenarios and move it to expressionStyle
            if (empty($this->expressionStyle) && !empty($this->speakerStyle)) {
                if (in_array($this->speakerStyle, $this->availableExpressionStyles ?? [])) {
                    $this->expressionStyle = $this->speakerStyle;
                    $this->speakerStyle = '';
                }
            }
            
            // Set messages directly
            if (is_array($record['messages'])) {
                \Log::info('DEBUG: Processing messages array', [
                    'messages_array' => $record['messages'],
                    'messages_count' => count($record['messages']),
                    'first_message' => $record['messages'][0] ?? 'No first message',
                    'array_filtered' => array_filter($record['messages'])
                ]);
                
                // Check if each element is a separate message or if it's one big string
                if (count($record['messages']) == 1 && strpos($record['messages'][0], "\n") !== false) {
                    // It's one big string with newlines - split it
                    $messagesArray = array_filter(array_map('trim', explode("\n", $record['messages'][0])));
                    $this->messages = implode("\n", $messagesArray);
                    \Log::info('DEBUG: Split single string into multiple messages', [
                        'original_single_string' => $record['messages'][0],
                        'split_count' => count($messagesArray),
                        'first_split_message' => $messagesArray[0] ?? 'none'
                    ]);
                } else {
                    // It's already properly formatted as separate array elements
                    $this->messages = implode("\n", array_filter($record['messages']));
                }
                
                $this->ssmlMessages = $record['ssmlMessages'] ? implode("\n", $record['ssmlMessages']) : $this->messages;
                
                \Log::info('DEBUG: Messages after processing', [
                    'this_messages' => $this->messages,
                    'messages_length' => strlen($this->messages),
                    'line_count' => substr_count($this->messages, "\n") + 1,
                    'ssmlMessages_length' => strlen($this->ssmlMessages)
                ]);
            } else {
                \Log::warning('DEBUG: Messages is not an array', [
                    'messages_type' => gettype($record['messages']),
                    'messages_value' => $record['messages']
                ]);
                $this->messages = is_string($record['messages']) ? $record['messages'] : '';
                $this->ssmlMessages = $record['ssmlMessages'] ?? $this->messages;
            }
            
            \Log::info('All fields set', [
                'speakerStyle' => $this->speakerStyle,
                'speakerPersonality' => $this->speakerPersonality,
                'prosodyRate' => $this->prosodyRate,
                'prosodyPitch' => $this->prosodyPitch,
                'prosodyVolume' => $this->prosodyVolume,
                'expressionStyle' => $this->expressionStyle,
                'messages_length' => strlen($this->messages)
            ]);
            
            // Use dispatch instead of emit for Livewire 3
            $this->dispatch('message-updated');
            
        } catch (\Exception $e) {
            \Log::error('Edit category failed: ' . $e->getMessage());
            session()->flash('error', 'Failed to load record');
        }
    }

    public function updateRecord()
    {
        // Manual validation to avoid array_merge issues
        if (empty($this->categoryId)) {
            session()->flash('error', 'Category is required');
            return;
        }
        
        if (empty($this->messages) || strlen($this->messages) < 10) {
            session()->flash('error', 'Messages field is required and must be at least 10 characters');
            return;
        }

        try {
            // Convert messages to array like the working component
            $messagesArray = array_filter(explode("\n", $this->messages));
            $ssmlArray = !empty($this->ssmlMessages) ? array_filter(explode("\n", $this->ssmlMessages)) : $messagesArray;

            $payload = [
                'categoryId' => $this->categoryId,
                'messages' => $messagesArray,  // Send as array
                'ssmlMessages' => $ssmlArray,  // Send as array
                'engine' => $this->engine,
                'language' => $this->language,
                'speaker' => $this->speaker,
                'speakerStyle' => $this->speakerStyle,
                'speakerPersonality' => $this->speakerPersonality,
                'prosodyRate' => $this->prosodyRate,
                'prosodyPitch' => $this->prosodyPitch,
                'prosodyVolume' => $this->prosodyVolume,
            ];

            $response = Http::timeout(120)->put("https://meditative-brains.com:3001/api/motivationMessage/{$this->editingRecordId}", $payload);

            if ($response->successful()) {
                session()->flash('success', 'Record updated successfully!');
                $this->cancelEdit();
                $this->fetchAdminMessages();
            } else {
                session()->flash('error', 'Failed to update record: ' . $response->body());
            }
        } catch (\Exception $e) {
            session()->flash('error', 'Error: ' . $e->getMessage());
        }
    }

    public function cancelEdit()
    {
        $this->reset(['editingRecordId', 'messages', 'ssmlMessages', 'categoryId', 'engine', 'language', 'speaker', 'speakerStyle', 'speakerPersonality', 'prosodyRate', 'prosodyPitch', 'prosodyVolume', 'expressionStyle']);
        $this->language = $this->languages[0] ?? '';
        $this->prosodyRate = 'medium';
        $this->prosodyPitch = 'medium';
        $this->prosodyVolume = 'medium';
        $this->updateSpeakersFromJson();
    }

    public function deleteRecord($recordId)
    {
        try {
            \Log::info('DEBUG: deleteRecord called', [
                'recordId' => $recordId,
                'method' => 'DELETE',
                'timestamp' => now()
            ]);

            $response = Http::delete("https://meditative-brains.com:3001/api/motivationMessage/{$recordId}");
            
            \Log::info('DEBUG: deleteRecord API Response', [
                'status_code' => $response->status(),
                'successful' => $response->successful(),
                'response_body' => $response->body(),
                'url' => "https://meditative-brains.com:3001/api/motivationMessage/{$recordId}"
            ]);
            
            if ($response->successful()) {
                session()->flash('success', 'Record deleted successfully!');
                $this->fetchAdminMessages();
                
                // If we were editing this record, cancel the edit
                if ($this->editingRecordId === $recordId) {
                    $this->cancelEdit();
                }
            } else {
                session()->flash('error', 'Failed to delete record: ' . $response->body());
            }
        } catch (\Exception $e) {
            \Log::error('DEBUG: deleteRecord exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            session()->flash('error', 'Error deleting record: ' . $e->getMessage());
        }
    }

    public function deleteMessage($categoryId, $messageId)
    {
        try {
            \Log::info('DEBUG: deleteMessage called', [
                'categoryId' => $categoryId,
                'messageId' => $messageId,
                'method' => 'DELETE',
                'timestamp' => now()
            ]);

            $url = "https://meditative-brains.com:3001/api/category/{$categoryId}/message/{$messageId}";
            
            \Log::info('DEBUG: deleteMessage API call', [
                'url' => $url,
                'method' => 'DELETE'
            ]);

            $response = Http::delete($url);
            
            \Log::info('DEBUG: deleteMessage API Response', [
                'status_code' => $response->status(),
                'successful' => $response->successful(),
                'response_body' => $response->body(),
                'response_headers' => $response->headers()
            ]);
            
            if ($response->successful()) {
                session()->flash('success', 'Message deleted successfully!');
                $this->fetchAdminMessages();
                
                // If we were editing the record that contained this message, refresh the edit data
                if ($this->editingRecordId) {
                    $this->editRecord($this->editingRecordId);
                }
            } else {
                session()->flash('error', 'Failed to delete message: ' . $response->body());
            }
        } catch (\Exception $e) {
            \Log::error('DEBUG: deleteMessage exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            session()->flash('error', 'Error deleting message: ' . $e->getMessage());
        }
    }

    public function generateSsml()
    {
        if (empty($this->messages)) {
            session()->flash('error', 'Please enter messages first');
            return;
        }

        $this->ssmlMessages = $this->convertToSsml($this->messages);
        session()->flash('success', 'SSML generated successfully!');
    }

    private function convertToSsml($text)
    {
        // Basic SSML conversion
        $ssml = "<speak>\n";
        
        $lines = explode("\n", $text);
        foreach ($lines as $line) {
            $line = trim($line);
            if (!empty($line)) {
                $ssml .= "  <p>$line</p>\n";
            }
        }
        
        $ssml .= "</speak>";
        
        return $ssml;
    }

    public function previewSsml()
    {
        if (empty($this->ssmlMessages)) {
            session()->flash('error', 'No SSML content to preview');
            return;
        }

        // Parse SSML for preview
        $this->previewSsmlData = $this->parseSsmlForPreview($this->ssmlMessages);
        $this->showSsmlModal = true;
    }

    private function parseSsmlForPreview($ssml)
    {
        // Simple SSML parsing for preview
        return [
            'raw' => $ssml,
            'formatted' => htmlentities($ssml)
        ];
    }

    // SSML Markup Methods
    public function applyBold()
    {
        $this->ssmlMessages .= '**text**';
    }

    public function applyEmphasis()
    {
        $this->ssmlMessages .= '*text*';
    }

    public function applyPause500()
    {
        $this->ssmlMessages .= '[pause:500]';
    }

    public function applySilence1s()
    {
        $this->ssmlMessages .= '[silence:1000]';
    }

    public function applySlowRate()
    {
        $this->ssmlMessages .= '[rate:slow]text[/rate]';
    }

    public function applyFastRate()
    {
        $this->ssmlMessages .= '[rate:fast]text[/rate]';
    }

    public function apply70Speed()
    {
        $this->ssmlMessages .= '[rate:-30%]text[/rate]';
    }

    public function apply80Speed()
    {
        $this->ssmlMessages .= '[rate:-20%]text[/rate]';
    }

    public function apply120Speed()
    {
        $this->ssmlMessages .= '[rate:+20%]text[/rate]';
    }

    public function applyHighPitch()
    {
        $this->ssmlMessages .= '[pitch:high]text[/pitch]';
    }

    public function applyLowPitch()
    {
        $this->ssmlMessages .= '[pitch:low]text[/pitch]';
    }

    public function applyPlus10Pitch()
    {
        $this->ssmlMessages .= '[pitch:+10%]text[/pitch]';
    }

    public function applyMinus10Pitch()
    {
        $this->ssmlMessages .= '[pitch:-10%]text[/pitch]';
    }

    public function applyCustomProsody()
    {
        $rate = $this->prosodyRate !== 'medium' ? $this->prosodyRate : '';
        $pitch = $this->prosodyPitch !== 'medium' ? $this->prosodyPitch : '';
        $volume = $this->prosodyVolume !== 'medium' ? $this->prosodyVolume : '';
        
        $attributes = [];
        if ($rate) $attributes[] = "rate=\"{$rate}\"";
        if ($pitch) $attributes[] = "pitch=\"{$pitch}\"";
        if ($volume) $attributes[] = "volume=\"{$volume}\"";
        
        $attributeString = implode(' ', $attributes);
        $this->ssmlMessages .= "[prosody {$attributeString}]text[/prosody]";
    }

    public function applyWellRounded()
    {
        $this->speakerPersonality = 'Well-Rounded';
        $this->ssmlMessages .= '[personality:Well-Rounded]text[/personality]';
    }

    public function applyAnimated()
    {
        $this->speakerPersonality = 'Animated';
        $this->ssmlMessages .= '[personality:Animated]text[/personality]';
    }

    public function applyBright()
    {
        $this->speakerPersonality = 'Bright';
        $this->ssmlMessages .= '[personality:Bright]text[/personality]';
    }

    // Save As New method
    public function saveAsNew()
    {
        // Manual validation to avoid array_merge issues
        if (empty($this->categoryId)) {
            session()->flash('error', 'Category is required');
            return;
        }
        
        if (empty($this->messages) || strlen($this->messages) < 10) {
            session()->flash('error', 'Messages field is required and must be at least 10 characters');
            return;
        }

        try {
            // Convert messages to array like the working component
            $messagesArray = array_filter(explode("\n", $this->messages));
            $ssmlArray = !empty($this->ssmlMessages) ? array_filter(explode("\n", $this->ssmlMessages)) : $messagesArray;

            $payload = [
                'categoryId' => $this->categoryId,
                'messages' => $messagesArray,  // Send as array
                'ssmlMessages' => $ssmlArray,  // Send as array
                'engine' => $this->engine,
                'language' => $this->language,
                'speaker' => $this->speaker,
                'speakerStyle' => $this->speakerStyle,
                'speakerPersonality' => $this->speakerPersonality,
                'prosodyRate' => $this->prosodyRate,
                'prosodyPitch' => $this->prosodyPitch,
                'prosodyVolume' => $this->prosodyVolume,
            ];

            // Translate if needed
            if (isset($this->shouldTranslate) && $this->shouldTranslate) {
                $this->translateAndFill();
            }

            $response = Http::timeout(120)->post('https://meditative-brains.com:3001/api/motivationMessage', $payload);

            if ($response->successful()) {
                session()->flash('success', 'New record saved successfully!');
                $this->cancelEdit();
                $this->fetchAdminMessages();
            } else {
                session()->flash('error', 'Failed to save new record: ' . $response->body());
            }
        } catch (\Exception $e) {
            session()->flash('error', 'Error saving new record: ' . $e->getMessage());
        }
    }

    // Translate and fill method
    public function translateAndFill()
    {
        if (empty($this->messages) || $this->language === 'en-US') {
            return;
        }

        try {
            $lines = explode("\n", $this->messages);
            $translatedLines = [];
            
            foreach ($lines as $line) {
                $line = trim($line);
                if (!empty($line)) {
                    // Simple translation simulation - in real app you'd use a translation service
                    $translatedLines[] = $line; // For now, just keep original
                }
            }
            
            $this->messages = implode("\n", $translatedLines);
            session()->flash('success', 'Messages translated to ' . $this->language);
        } catch (\Exception $e) {
            session()->flash('error', 'Translation failed: ' . $e->getMessage());
        }
    }

    // SSML modal methods
    public function closeSsmlModal()
    {
        $this->showSsmlModal = false;
        $this->previewSsmlData = [];
    }

    // Convert markup to SSML helper
    private function convertMarkupToSsml($text)
    {
        // Convert custom markup to SSML
        $ssml = $text;
        
        // Handle emphasis
        $ssml = preg_replace('/\*\*(.*?)\*\*/', '<emphasis level="strong">$1</emphasis>', $ssml);
        $ssml = preg_replace('/\*(.*?)\*/', '<emphasis level="moderate">$1</emphasis>', $ssml);
        
        // Handle pauses
        $ssml = preg_replace('/\[pause:(\d+)\]/', '<break time="${1}ms"/>', $ssml);
        $ssml = preg_replace('/\[silence:(\d+)\]/', '<break time="${1}ms"/>', $ssml);
        
        // Handle styles
        $ssml = preg_replace('/\[style:(.*?)\](.*?)\[\/style\]/', '<mstts:express-as style="$1">$2</mstts:express-as>', $ssml);
        
        // Handle personalities
        $ssml = preg_replace('/\[personality:(.*?)\](.*?)\[\/personality\]/', '<mstts:express-as personality="$1">$2</mstts:express-as>', $ssml);
        
        // Handle rate, pitch, volume
        $ssml = preg_replace('/\[rate:(.*?)\](.*?)\[\/rate\]/', '<prosody rate="$1">$2</prosody>', $ssml);
        $ssml = preg_replace('/\[pitch:(.*?)\](.*?)\[\/pitch\]/', '<prosody pitch="$1">$2</prosody>', $ssml);
        $ssml = preg_replace('/\[volume:(.*?)\](.*?)\[\/volume\]/', '<prosody volume="$1">$2</prosody>', $ssml);
        
        // Handle complex prosody
        $ssml = preg_replace('/\[prosody (.*?)\](.*?)\[\/prosody\]/', '<prosody $1>$2</prosody>', $ssml);
        
        // Wrap in speak tag
        return '<speak version="1.0" xmlns="http://www.w3.org/2001/10/synthesis" xmlns:mstts="https://www.w3.org/2001/mstts" xml:lang="' . $this->language . '">' . $ssml . '</speak>';
    }

    // Test methods for Livewire connectivity
    public function incrementTest()
    {
        \Log::info('incrementTest method called');
        $this->testCounter++;
        session()->flash('success', 'Test counter incremented to: ' . $this->testCounter);
    }

    public function resetTest()
    {
        \Log::info('resetTest method called');
        $this->testCounter = 0;
        $this->testInput = '';
        session()->flash('success', 'Test values reset successfully!');
    }

    public function render()
    {
        return view('livewire.admin.motivation-message-form')->layout('components.layouts.admin', [
            'title' => 'Manage Messages',
            'content_header_title' => 'Motivation Messages',
            'content_header_description' => 'Create and manage TTS motivation messages'
        ]);
    }
}
