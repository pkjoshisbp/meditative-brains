<?php
namespace App\Livewire\Admin;

use Livewire\Component;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Process\Process;

class MotivationMessageFormWorking extends Component
{
    public $categoryId = '';
    public $messages = '';
    public $engine = 'azure';
    public $language = 'en-US';
    public $speaker = 'en-US-AriaNeural';
    public $speakerStyle = '';

    public $categories = [];
    public $languages = [];
    public $speakers = [];
    public $availableStyles = [];

    public $existingRecords = []; // to list current saved records
    public $editingRecordId=null;

    public $ssmlMessages = '';  // New field for SSML markup version

    public $savingAsNew = false; // Renamed from saveAsNew

    public $azureVoices = [];

    // Add these properties
    public $speakerPersonality = '';
    public $availablePersonalities = [];

    private $markupReplacements = [];

    // Preview SSML data
    public $previewSsmlData = [];
    public $showSsmlModal = false;

    protected $casts = [
    'editingRecordId' => 'string',
];


    public function mount()
    {
        // Load categories with logging
        \Log::info('Fetching categories...');
        $response = Http::get('https://meditative-brains.com:3001/api/category');
        
        if ($response->successful()) {
            $this->categories = $response->json();
            \Log::info('Categories fetched successfully', [
                'count' => count($this->categories),
                'data' => $this->categories
            ]);
        } else {
            \Log::error('Failed to fetch categories', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            $this->categories = [];
        }

        // Load Azure voices from JSON
        $this->azureVoices = collect(json_decode(file_get_contents(config_path('azure-voices.json')), true));
        $this->languages = $this->azureVoices->pluck('Locale')->unique()->values()->all();
        $this->language = $this->languages[0] ?? '';
        $this->updateSpeakersFromJson();

        // Fetch all system-level messages (not user-submitted)
        $this->fetchAdminMessages();
    }

    private function fetchMessagesForCategory($categoryId)
    {
        $response = Http::get("https://meditative-brains.com:3001/api/motivationMessage/category/{$categoryId}");
        $this->existingRecords = $response->successful() ? $response->json() : [];
    }

    public function fetchAdminMessages()
    {
        $response = Http::get("https://meditative-brains.com:3001/api/motivationMessage/admin-only");
        $this->existingRecords = $response->successful() ? $response->json() : [];
    }



    public function updatedLanguage()
    {
        $this->updateSpeakersFromJson();
    }

    // Add this method to update styles when speaker changes
    public function updatedSpeaker()
    {
        $this->updateSpeakerStylesFromJson();
    }

    public function updatedEngine()
    {
        $this->languages = array_keys(config("tts.$this->engine"));
        $this->language = $this->languages[0] ?? '';
        $this->updateSpeakers();
    }

    private function updateSpeakers()
    {
        $config = config("tts.$this->engine.$this->language", []);
        $this->speakers = array_keys($config);

        // Only reset speaker if the current one is not in the list
        if (!in_array($this->speaker, $this->speakers)) {
            $this->speaker = $this->speakers[0] ?? '';
        }
        $this->updateSpeakerStyles();
    }

    private function updateSpeakerStyles()
    {
        // Always use the current speaker value
        $config = config("tts.$this->engine.$this->language.$this->speaker", []);
        $this->availableStyles = $config['styles'] ?? [];
        
        // Only reset if current style is not available
        if (!in_array($this->speakerStyle, $this->availableStyles)) {
            $this->speakerStyle = $config['default_style'] ?? ($this->availableStyles[0] ?? '');
        }

        // Voice personalities support (if present in config)
        $this->availablePersonalities = $config['personalities'] ?? [];
        
        // Only reset if current personality is not available
        if (!in_array($this->speakerPersonality, $this->availablePersonalities)) {
            $this->speakerPersonality = $this->availablePersonalities[0] ?? '';
        }
    }

    private function updateSpeakersFromJson()
    {
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

        if (!in_array($this->speaker, $this->speakers)) {
            $this->speaker = $this->speakers[0] ?? '';
        }
        $this->updateSpeakerStylesFromJson();
    }

    private function updateSpeakerStylesFromJson()
    {
        $voice = $this->azureVoices->firstWhere('ShortName', $this->speaker);
        
        // Debug logging
        \Log::info('Voice data:', [
            'name' => $voice['DisplayName'],
            'shortName' => $voice['ShortName'],
            'styleList' => $voice['StyleList'] ?? [],
            'voiceTag' => $voice['VoiceTag'] ?? []
        ]);

        // Get speaking styles correctly - StyleList is root level
        $this->availableStyles = $voice['StyleList'] ?? [];
        
        // Only reset speakerStyle if current value is not in the available list
        if (!in_array($this->speakerStyle, $this->availableStyles)) {
            $this->speakerStyle = $this->availableStyles[0] ?? '';
        }

        // Get personalities correctly - VoiceTag.VoicePersonalities is nested
        $this->availablePersonalities = [];
        if (isset($voice['VoiceTag']['VoicePersonalities'])) {
            $this->availablePersonalities = $voice['VoiceTag']['VoicePersonalities'];
            \Log::info('Found personalities:', $this->availablePersonalities);
        }
        
        // Only reset speakerPersonality if current value is not in the available list
        if (!in_array($this->speakerPersonality, $this->availablePersonalities)) {
            $this->speakerPersonality = $this->availablePersonalities[0] ?? '';
        }
        
        // Get expression styles from TailoredScenarios
        $this->availableExpressionStyles = [];
        if (isset($voice['VoiceTag']['TailoredScenarios'])) {
            $this->availableExpressionStyles = $voice['VoiceTag']['TailoredScenarios'];
            \Log::info('Found expression styles:', $this->availableExpressionStyles);
        }
        
        // Only reset expressionStyle if current value is not in the available list
        if (!in_array($this->expressionStyle, $this->availableExpressionStyles)) {
            $this->expressionStyle = $this->availableExpressionStyles[0] ?? '';
        }
    }

    // Add this property for prosody rate (speech speed)
    public $prosodyRate = '';
    
    // Add this property for pitch control
    public $prosodyPitch = '';
    
    // Add this property for volume control
    public $prosodyVolume = '';
    
    // Add this property for expression style (from TailoredScenarios)
    public $expressionStyle = '';
    
    // Available expression styles from TailoredScenarios
    public $availableExpressionStyles = [];

    public function convertToSSML($simpleMarkup)
    {
        // Use the speaker's default locale for <speak>, but always wrap text in <lang xml:lang="...">
        $ssml = '<?xml version="1.0"?>'
            . '<speak version="1.0" xmlns="http://www.w3.org/2001/10/synthesis"'
            . ' xmlns:mstts="https://www.w3.org/2001/mstts"'
            . ' xmlns:emo="http://www.w3.org/2009/10/emotionml"'
            . ' xml:lang="' . $this->getVoiceLocale($this->speaker) . '">'
            . '<voice name="' . $this->speaker . '">';

        // Check if we need to wrap with mstts:express-as
        $hasExistingExpressAs = strpos($simpleMarkup, '<mstts:express-as') !== false;
        
        // Build combined mstts:express-as attributes if needed
        $expressAsAttributes = [];
        
        if ($this->speakerPersonality && !$hasExistingExpressAs) {
            $expressAsAttributes[] = 'role="' . $this->speakerPersonality . '"';
        }
        
        // Prioritize speakerStyle over expressionStyle, both use the style attribute
        if ($this->speakerStyle && !$hasExistingExpressAs) {
            $expressAsAttributes[] = 'style="' . $this->speakerStyle . '"';
        } elseif ($this->expressionStyle && !$this->speakerStyle && !$hasExistingExpressAs) {
            $expressAsAttributes[] = 'style="' . $this->expressionStyle . '"';
        }
        
        // Add combined mstts:express-as tag if we have attributes
        if (!empty($expressAsAttributes)) {
            $ssml .= '<mstts:express-as ' . implode(' ', $expressAsAttributes) . '>';
        }

        // Always wrap the text in <lang xml:lang="...">
        $ssml .= '<lang xml:lang="' . $this->language . '">';
        
        // Process the text with proper prosody handling
        $processedText = $this->processTextWithProsody($simpleMarkup);
        $ssml .= $processedText;
        
        $ssml .= '</lang>';

        // Close mstts:express-as tag if we added one
        if (!empty($expressAsAttributes)) {
            $ssml .= '</mstts:express-as>';
        }
        
        $ssml .= '</voice></speak>';

        return $ssml;
    }

    private function processTextWithProsody($text)
    {
        // Step 1: Remove all personality tags since we're not using them anymore
        $text = preg_replace('/\[personality:[^\]]+\]/', '', $text);
        $text = preg_replace('/\[\/personality\]/', '', $text);
        
        // Step 2: Handle missing closing tags for remaining markup (rate, pitch, style)
        $text = $this->fixMissingClosingTags($text);
        
        // Step 3: Apply emphasis and pauses first (simple replacements)
        $simpleReplacements = [
            '/\*\*(.*?)\*\*/' => '<emphasis level="strong">$1</emphasis>',
            '/\*(.*?)\*/' => '<emphasis level="moderate">$1</emphasis>',
            '/\[pause:(\d+)\]/' => '<break time="$1ms"/>',
            '/\[silence:(\d+)\]/' => '<mstts:silence type="Sentenceboundary" value="$1ms"/>',
        ];
        
        $processedText = preg_replace(array_keys($simpleReplacements), array_values($simpleReplacements), $text);
        
        // Step 4: Process remaining markup tags (rate, pitch, style only)
        $processedText = $this->processMarkupTags($processedText);
        
        // Step 5: Apply default prosody if needed
        return $this->wrapWithDefaultProsody($processedText);
    }

    public function fixMissingClosingTags($text)
    {
        // Handle cases like [rate:slow][pitch:high]content[/rate] (missing /pitch)
        // Handle rate, pitch, volume, and style tags
        
        // Pattern to match: [tag1:value1][tag2:value2]...content...[/tag1] (missing /tag2)
        $pattern = '/(\[(?:style|rate|pitch|volume):[^\]]+\])(\[(?:style|rate|pitch|volume):[^\]]+\])(.*?)\[\/([^\]]+)\]/';
        
        while (preg_match($pattern, $text, $matches)) {
            $firstTag = $matches[1];  // [rate:slow]
            $secondTag = $matches[2]; // [pitch:high] 
            $content = $matches[3];   // content
            $closingTag = $matches[4]; // rate
            
            // Extract tag names
            preg_match('/\[([^:]+):[^\]]+\]/', $firstTag, $firstTagMatch);
            preg_match('/\[([^:]+):[^\]]+\]/', $secondTag, $secondTagMatch);
            
            $firstTagName = $firstTagMatch[1];
            $secondTagName = $secondTagMatch[1];
            
            if ($closingTag === $firstTagName) {
                // Need to add closing tag for second tag
                $replacement = $firstTag . $secondTag . $content . '[/' . $secondTagName . '][/' . $firstTagName . ']';
                $text = str_replace($matches[0], $replacement, $text);
            }
        }
        
        return $text;
    }

    private function processMarkupTags($text)
    {
        // Process markup tags and merge global prosody settings
        $markupReplacements = [
            // Rate/Speed
            '/\[rate:(slow|fast|x-slow|x-fast|-?\d+%?)\](.*?)\[\/rate\]/s' => '<prosody rate="$1">$2</prosody>',
            
            // Pitch
            '/\[pitch:(low|high|x-low|x-high|medium|\+?\-?\d+(?:%|Hz|st)?)\](.*?)\[\/pitch\]/s' => '<prosody pitch="$1">$2</prosody>',
            
            // Volume
            '/\[volume:(silent|x-soft|soft|medium|loud|x-loud|\+?\-?\d+(?:%)?)\](.*?)\[\/volume\]/s' => '<prosody volume="$1">$2</prosody>',
            
            // Style (keeping this for Azure voices that support it)
            '/\[style:(.*?)\](.*?)\[\/style\]/s' => '<mstts:express-as style="$1">$2</mstts:express-as>',
            
            // Combined prosody
            '/\[prosody([^\]]*)\](.*?)\[\/prosody\]/s' => '<prosody$1>$2</prosody>',
        ];
        
        foreach ($markupReplacements as $pattern => $replacement) {
            $text = preg_replace($pattern, $replacement, $text);
        }
        
        // Now enhance existing prosody tags with global settings
        $text = $this->enhanceExistingProsodyTags($text);
        
        return $text;
    }

    private function enhanceExistingProsodyTags($text)
    {
        // Check if we're going to apply global prosody wrapper
        $hasGlobalProsody = ($this->prosodyRate && $this->prosodyRate !== 'default' && $this->prosodyRate !== '') ||
                           ($this->prosodyPitch && $this->prosodyPitch !== 'default' && $this->prosodyPitch !== '') ||
                           ($this->prosodyVolume && $this->prosodyVolume !== 'default' && $this->prosodyVolume !== '');
        
        // If we have global prosody, don't modify individual prosody tags - let the global wrapper handle it
        if ($hasGlobalProsody) {
            return $text;
        }
        
        // Find all existing prosody tags and enhance them with global settings (only if no global prosody)
        $pattern = '/<prosody([^>]*)>(.*?)<\/prosody>/s';
        
        return preg_replace_callback($pattern, function($matches) {
            $existingAttributes = $matches[1];
            $content = $matches[2];
            
            // Parse existing attributes
            $attributes = [];
            if (preg_match('/rate="([^"]*)"/', $existingAttributes, $rateMatch)) {
                $attributes['rate'] = $rateMatch[1];
            }
            if (preg_match('/pitch="([^"]*)"/', $existingAttributes, $pitchMatch)) {
                $attributes['pitch'] = $pitchMatch[1];
            }
            if (preg_match('/volume="([^"]*)"/', $existingAttributes, $volumeMatch)) {
                $attributes['volume'] = $volumeMatch[1];
            }
            
            // Add global settings if not already present
            if (!isset($attributes['rate']) && $this->prosodyRate && $this->prosodyRate !== 'default' && $this->prosodyRate !== '') {
                $attributes['rate'] = $this->prosodyRate;
            }
            if (!isset($attributes['pitch']) && $this->prosodyPitch && $this->prosodyPitch !== 'default' && $this->prosodyPitch !== '') {
                $attributes['pitch'] = $this->prosodyPitch;
            }
            if (!isset($attributes['volume']) && $this->prosodyVolume && $this->prosodyVolume !== 'default' && $this->prosodyVolume !== '') {
                $attributes['volume'] = $this->prosodyVolume;
            }
            
            // Build the enhanced prosody tag
            $attributeString = '';
            foreach ($attributes as $key => $value) {
                $attributeString .= ' ' . $key . '="' . $value . '"';
            }
            
            return '<prosody' . $attributeString . '>' . $content . '</prosody>';
        }, $text);
    }

    private function handleProsodySegmentation($text)
    {
        // This method is now simplified since we handle markup differently
        return $text;
    }

    private function wrapWithDefaultProsody($text)
    {
        if (empty(trim($text))) {
            return $text;
        }
        
        // Build prosody attributes
        $attributes = [];
        
        if ($this->prosodyRate && $this->prosodyRate !== 'default' && $this->prosodyRate !== '') {
            $attributes[] = 'rate="' . $this->prosodyRate . '"';
        }
        
        if ($this->prosodyPitch && $this->prosodyPitch !== 'default' && $this->prosodyPitch !== '') {
            $attributes[] = 'pitch="' . $this->prosodyPitch . '"';
        }
        
        if ($this->prosodyVolume && $this->prosodyVolume !== 'default' && $this->prosodyVolume !== '') {
            $attributes[] = 'volume="' . $this->prosodyVolume . '"';
        }
        
        // Apply global prosody wrapper if we have attributes
        // This should wrap the ENTIRE text, not just parts without prosody tags
        if (!empty($attributes)) {
            return '<prosody ' . implode(' ', $attributes) . '>' . $text . '</prosody>';
        }
        
        return $text;
    }

    // Helper to get the default locale for a given speaker
    private function getVoiceLocale($shortName)
    {
        $voice = $this->azureVoices->firstWhere('ShortName', $shortName);
        return $voice['Locale'] ?? $this->language;
    }

    public function save()
    {
        $messagesArray = array_filter(explode("\n", $this->messages));
        $ssmlArray = array_filter(explode("\n", $this->ssmlMessages));

        $payload = [
            'categoryId' => $this->categoryId,
            'messages' => $messagesArray,
            'ssmlMessages' => $ssmlArray,
            'ssml' => array_map([$this, 'convertToSSML'], $ssmlArray),
            'engine' => $this->engine,
            'language' => $this->language, // accent
            'speaker' => $this->speaker,   // voice name
            'speakerStyle' => $this->speakerStyle ?: $this->expressionStyle, // Use speakerStyle first, fallback to expressionStyle
            'speakerPersonality' => $this->speakerPersonality,
            'prosodyRate' => $this->prosodyRate,
            'prosodyPitch' => $this->prosodyPitch,
            'prosodyVolume' => $this->prosodyVolume,
            'expressionStyle' => $this->expressionStyle, // Keep this for backward compatibility
        ];

        // Only add userId for non-admin users
        if (auth()->check() && method_exists(auth()->user(), 'is_admin') && !auth()->user()->is_admin) {
            $payload['userId'] = auth()->id();
        }
        // If you use a different admin check, adjust the above condition accordingly.

        // If savingAsNew is true, always create a new record
        if ($this->savingAsNew || !$this->editingRecordId) {
            $response = Http::post('https://meditative-brains.com:3001/api/motivationMessage', $payload);
        } else {
            $response = Http::put("https://meditative-brains.com:3001/api/motivationMessage/{$this->editingRecordId}", $payload);
        }

        if ($response->successful()) {
            session()->flash('success', ($this->savingAsNew || !$this->editingRecordId) ? 'Saved as new successfully.' : 'Updated successfully.');
            $this->resetForm();
            $this->fetchAdminMessages();
        } else {
            session()->flash('error', 'Failed to save. ' . $response->body());
            \Log::error('API Error:', ['response' => $response->json()]);
        }
        $this->savingAsNew = false; // Reset after save
    }

    // Add this property to control translation
    public $shouldTranslate = false;

    // Add this method to translate messages using Azure Translator API
    public function translateMessages()
    {
        \Log::info('[MotivationMessageForm] translateMessages called', [
            'language' => $this->language,
            'messages_length' => strlen($this->messages),
        ]);

        // Only translate if language is not English
        if ($this->language === 'en-US') {
            \Log::info('[MotivationMessageForm] Skipping translation: language is en-US');
            session()->flash('info', 'Selected language is English. No translation needed.');
            return;
        }

        $messagesArray = array_filter(explode("\n", $this->messages));
        if (empty($messagesArray)) {
            \Log::warning('[MotivationMessageForm] No messages to translate');
            session()->flash('error', 'No messages to translate.');
            return;
        }

        // Azure Translator API endpoint and key (set these in your .env and config)
        $endpoint = config('services.azure_translator.endpoint');
        $key = config('services.azure_translator.key');
        $region = config('services.azure_translator.region');
        $toLang = substr($this->language, 0, 2); // e.g., 'hi' for 'hi-IN'

        \Log::info('[MotivationMessageForm] Sending translation requests', [
            'endpoint' => $endpoint,
            'region' => $region,
            'toLang' => $toLang,
            'messages_count' => count($messagesArray),
        ]);

        $translated = [];
        foreach ($messagesArray as $msg) {
            try {
                $response = Http::withHeaders([
                    'Ocp-Apim-Subscription-Key' => $key,
                    'Ocp-Apim-Subscription-Region' => $region,
                    'Content-Type' => 'application/json',
                ])->post($endpoint . '/translate?api-version=3.0&to=' . $toLang, [
                    ['Text' => $msg]
                ]);
                \Log::info('[MotivationMessageForm] Azure response', [
                    'message' => $msg,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                if ($response->successful()) {
                    $result = $response->json();
                    $translated[] = $result[0]['translations'][0]['text'] ?? $msg;
                } else {
                    $translated[] = $msg; // fallback to original
                }
            } catch (\Exception $e) {
                \Log::error('[MotivationMessageForm] Exception during translation', [
                    'message' => $msg,
                    'error' => $e->getMessage(),
                ]);
                $translated[] = $msg;
            }
        }
        $this->messages = implode("\n", $translated);
        \Log::info('[MotivationMessageForm] Translation complete', [
            'translated_length' => strlen($this->messages),
        ]);
        session()->flash('success', 'Messages translated to ' . $this->language);
    }

    // Translate and fill messages (for UI button)
    public function translateAndFill()
    {
        \Log::info('[MotivationMessageForm] translateAndFill called');
        $this->translateMessages();
        // Optionally, also fill SSML messages with the translated text
        $this->ssmlMessages = $this->messages;
        \Log::info('[MotivationMessageForm] ssmlMessages updated after translation');
    }

    // Update saveAsNew to optionally translate before saving
    public function saveAsNew()
    {
        if ($this->shouldTranslate && $this->language !== 'en-US') {
            $this->translateMessages();
            $this->ssmlMessages = $this->messages;
        }
        $this->savingAsNew = true;
        $this->save();
    }

    private function resetForm()
    {
        $this->reset([
            'editingRecordId', 
            'messages', 
            'ssmlMessages',
            'categoryId',
            'speakerPersonality',
            'availablePersonalities',
            'prosodyRate',
            'prosodyPitch',
            'prosodyVolume',
            'expressionStyle',
            'availableExpressionStyles'
        ]);
        $this->engine = 'azure';
        $this->language = 'en-US';
        $this->speaker = 'en-US-AriaNeural';
        $this->speakerStyle = '';
        $this->updatedLanguage();
    }

    public function updateRecord($recordId, $engine, $language, $speaker, $messages)
    {
        $messagesForApi = explode("\n", $messages);
        
        $response = Http::put("https://meditative-brains.com:3001/api/motivationMessage/{$recordId}", [
            'engine' => $engine,
            'language' => $language,
            'speaker' => $speaker,
            'messages' => $messagesForApi,
        ]);

        if ($response->successful()) {
            session()->flash('success', 'Record updated.');
            $this->fetchMessagesForCategory($this->categoryId);
        } else {
            session()->flash('error', 'Update failed.');
        }
    }
    public function editCategory($recordId)
    {
        try {
            $record = collect($this->existingRecords)->firstWhere('_id', $recordId);
            
            if (!$record) {
                throw new \Exception("Record not found");
            }

            $this->editingRecordId = $recordId;
            $this->categoryId = is_array($record['categoryId']) ? $record['categoryId']['_id'] : $record['categoryId'];
            $this->engine = $record['engine'] ?? 'azure';
            $this->language = $record['language'] ?? 'en-US';
            $this->speaker = $record['speaker'] ?? 'en-US-AriaNeural';
            
            // First update the language/speaker to populate available options
            $this->updatedLanguage();
            
            // Then restore speaker style and personality if available
            $this->speakerStyle = $record['speakerStyle'] ?? '';
            $this->speakerPersonality = $record['speakerPersonality'] ?? '';
            
            // Restore prosody settings if available
            $this->prosodyRate = $record['prosodyRate'] ?? '';
            $this->prosodyPitch = $record['prosodyPitch'] ?? '';
            $this->prosodyVolume = $record['prosodyVolume'] ?? '';
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
                $this->messages = implode("\n", array_filter($record['messages']));
                $this->ssmlMessages = $record['ssmlMessages'] ? implode("\n", $record['ssmlMessages']) : $this->messages;
            }
            
            // Use dispatch instead of emit for Livewire 3
            $this->dispatch('message-updated');
            
        } catch (\Exception $e) {
            \Log::error('Edit category failed: ' . $e->getMessage());
            session()->flash('error', 'Failed to load record');
        }
    }

    public function cancelEdit()
    {
        $this->reset(['editingRecordId', 'messages', 'categoryId', 'engine', 'language', 'speaker']);
    }

    public function testMethod($param = 'test')
    {
        \Log::info('Test method called with: ' . $param);
        
        // Test the new prosody handling
        if ($param === 'prosody') {
            $this->prosodyRate = '-10%';
            $testText = 'I can visualize myself as a [rate:-20%]**non-smoker**, **confident**, **healthier**[/rate] and charming person full of joy.';
            $ssml = $this->convertToSSML($testText);
            \Log::info('Generated SSML:', ['ssml' => $ssml]);
            session()->flash('success', 'Prosody test completed. Check logs for SSML output.');
            return;
        }
        
        session()->flash('success', 'Test method called successfully');
    }

    public function generateAudio()
    {
        if (!$this->editingRecordId) {
            session()->flash('error', 'Please select a category first');
            return;
        }

        try {
            $response = Http::post('https://meditative-brains.com:3001/api/generate-category-audio', [
                'categoryId' => $this->categoryId,
                'language' => $this->language,
                'speaker' => $this->speaker,
                'engine' => $this->engine
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
            session()->flash('error', 'Error: ' . $e->getMessage());
            \Log::error('Audio generation failed:', ['error' => $e->getMessage()]);
        }
    }

    public function generateAudioForRecord($recordId)
    {
        try {
            \Log::info('Triggering audio generation for record', ['recordId' => $recordId]);
            $response = Http::get("https://meditative-brains.com:3001/api/generate-category-audio/{$recordId}");

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
            \Log::error('Audio generation exception', ['error' => $e->getMessage()]);
            session()->flash('error', 'Error: ' . $e->getMessage());
        }
    }

    // Initialize markup replacements in boot()
    public function boot()
    {
        $this->markupReplacements = [
            // Emphasis
            '/\*\*(.*?)\*\*/' => '<emphasis level="strong">$1</emphasis>',
            '/\*(.*?)\*/' => '<emphasis level="moderate">$1</emphasis>',
            
            // Pauses
            '/\[pause:(\d+)\]/' => '<break time="$1ms"/>',
            '/\[silence:(\d+)\]/' => '<mstts:silence type="Sentenceboundary" value="$1ms"/>',
            
            // Speaking styles and personalities
            '/\[style:(.*?)\](.*?)\[\/style\]/' => '<mstts:express-as style="$1">$2</mstts:express-as>',
            '/\[personality:(.*?)\](.*?)\[\/personality\]/' => '<mstts:express-as role="$1">$2</mstts:express-as>',
            
            // Note: Prosody (rate, pitch, volume) is now handled separately in processTextWithProsody()
            // to avoid nesting issues with the global prosodyRate setting.
            // 
            // Supported prosody markup formats:
            // [rate:-20%]text[/rate]
            // [pitch:+10%]text[/pitch] 
            // [prosody rate="-20%" pitch="+10%"]text[/prosody]
        ];
    }

    public function previewSsml()
    {
        try {
            if (!$this->editingRecordId) {
                session()->flash('error', 'No record selected for preview');
                return;
            }

            // Get the current record from existing records
            $record = collect($this->existingRecords)->firstWhere('_id', $this->editingRecordId);
            
            if (!$record) {
                session()->flash('error', 'Record not found');
                return;
            }

            $this->previewSsmlData = [];

            // Get SSML messages from the record
            $ssmlMessages = $record['ssml'] ?? [];
            $ssmlMarkupMessages = $record['ssmlMessages'] ?? [];

            if (!empty($ssmlMessages) && is_array($ssmlMessages)) {
                foreach ($ssmlMessages as $index => $ssml) {
                    $markup = 'N/A';
                    
                    // Handle markup messages safely
                    if (is_array($ssmlMarkupMessages) && isset($ssmlMarkupMessages[$index])) {
                        $markup = $ssmlMarkupMessages[$index];
                    } elseif (is_array($record['messages']) && isset($record['messages'][$index])) {
                        // Fallback to regular messages if ssmlMessages not available
                        $markup = $record['messages'][$index];
                    }

                    $this->previewSsmlData[] = [
                        'index' => $index + 1,
                        'markup' => $markup,
                        'ssml' => $ssml
                    ];
                }
            } else {
                // If no SSML, try to show regular messages as fallback
                $messages = $record['messages'] ?? [];
                if (is_array($messages) && !empty($messages)) {
                    foreach ($messages as $index => $message) {
                        $this->previewSsmlData[] = [
                            'index' => $index + 1,
                            'markup' => $message,
                            'ssml' => 'SSML not generated yet for this message'
                        ];
                    }
                } else {
                    session()->flash('error', 'No SSML or message data found for this record');
                    return;
                }
            }

            $this->showSsmlModal = true;

        } catch (\Exception $e) {
            \Log::error('Preview SSML failed: ' . $e->getMessage(), [
                'recordId' => $this->editingRecordId,
                'error' => $e->getTraceAsString()
            ]);
            session()->flash('error', 'Failed to preview SSML: ' . $e->getMessage());
        }
    }

    public function closeSsmlModal()
    {
        $this->showSsmlModal = false;
        $this->previewSsmlData = [];
    }

    // Debug method to test SSML conversion - made public for testing
    public function testSsmlConversion()
    {
        $testInput = '[personality:Caring][rate:slow]Welcome to this **powerful journey** of transformation.[/personality]';
        
        \Log::info('Testing SSML conversion:', [
            'input' => $testInput,
        ]);
        
        $step1 = $this->fixMissingClosingTags($testInput);
        $step2 = preg_replace(['/\*\*(.*?)\*\*/', '/\*(.*?)\*/'], ['<emphasis level="strong">$1</emphasis>', '<emphasis level="moderate">$1</emphasis>'], $step1);
        $step3 = $this->processMarkupTags($step2);
        
        \Log::info('SSML conversion steps:', [
            'step1_fixed_tags' => $step1,
            'step2_emphasis' => $step2,
            'step3_markup' => $step3,
        ]);
        
        $fullSsml = $this->convertToSSML($testInput);
        \Log::info('Final SSML:', ['ssml' => $fullSsml]);
        
        session()->flash('success', 'SSML test completed. Check logs for details.');
    }

    public function render()
    {
        return view('livewire.admin.motivation-message-form-working')->layout('components.layouts.admin', [
            'title' => 'Manage Messages',
            'content_header_title' => 'Motivation Messages',
            'content_header_description' => 'Working Version from OneDollar'
        ]);
    }
}