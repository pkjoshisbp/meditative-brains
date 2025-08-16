const OpenAI = require('openai');
const fs = require('fs').promises;
const path = require('path');
const { v4: uuidv4 } = require('uuid');
const AudioMixer = require('./audioMixer');

class PreviewGenerator {
    constructor() {
        this.openai = new OpenAI({
            apiKey: process.env.OPENAI_API_KEY
        });
        this.audioMixer = new AudioMixer();
        this.tempDir = path.join(__dirname, '../temp');
        this.supportedVoices = [
            'alloy', 'echo', 'fable', 'onyx', 'nova', 'shimmer'
        ];
        this.supportedModels = ['tts-1', 'tts-1-hd'];
    }

    /**
     * Generate TTS preview with optional background music
     */
    async generatePreview(options) {
        const {
            text,
            voice = 'alloy',
            model = 'tts-1',
            backgroundMusicUrl = null,
            previewDuration = 30,
            speed = 1.0
        } = options;

        // Validate inputs
        this.validateInputs({ text, voice, model, speed });

        try {
            // Generate TTS audio
            console.log('Generating TTS audio...');
            const ttsResult = await this.generateTTSAudio(text, voice, model, speed);
            
            // Mix with background music if provided
            if (backgroundMusicUrl) {
                console.log('Mixing with background music...');
                const mixedResult = await this.audioMixer.mixWithBackground(
                    ttsResult.filePath,
                    backgroundMusicUrl,
                    previewDuration
                );
                
                // Clean up TTS file
                await this.cleanupFile(ttsResult.filePath);
                
                return {
                    success: true,
                    audioUrl: mixedResult.publicUrl,
                    duration: mixedResult.duration,
                    fileId: mixedResult.fileId,
                    voice,
                    model,
                    hasMixedAudio: true,
                    backgroundMusic: backgroundMusicUrl,
                    generatedAt: new Date().toISOString()
                };
            } else {
                // Trim TTS audio to preview duration
                console.log('Trimming TTS audio...');
                const trimmedResult = await this.audioMixer.trimAudio(
                    ttsResult.filePath,
                    previewDuration
                );
                
                // Clean up original TTS file
                await this.cleanupFile(ttsResult.filePath);
                
                return {
                    success: true,
                    audioUrl: trimmedResult.publicUrl,
                    duration: trimmedResult.duration,
                    fileId: trimmedResult.fileId,
                    voice,
                    model,
                    hasMixedAudio: false,
                    generatedAt: new Date().toISOString()
                };
            }
        } catch (error) {
            console.error('Preview generation error:', error);
            throw new Error(`Preview generation failed: ${error.message}`);
        }
    }

    /**
     * Generate full TTS audio (no preview limitation)
     */
    async generateFullAudio(options) {
        const {
            text,
            voice = 'alloy',
            model = 'tts-1',
            speed = 1.0
        } = options;

        this.validateInputs({ text, voice, model, speed });

        try {
            console.log('Generating full TTS audio...');
            const ttsResult = await this.generateTTSAudio(text, voice, model, speed);
            
            // Move to public directory
            const outputId = uuidv4();
            const publicPath = path.join(__dirname, '../public/temp-audio', `full_${outputId}.mp3`);
            
            await fs.rename(ttsResult.filePath, publicPath);
            
            // Get audio info
            const audioInfo = await this.audioMixer.getAudioInfo(publicPath);
            
            return {
                success: true,
                audioUrl: `/temp-audio/full_${outputId}.mp3`,
                duration: audioInfo.duration,
                fileId: outputId,
                voice,
                model,
                generatedAt: new Date().toISOString()
            };
        } catch (error) {
            console.error('Full audio generation error:', error);
            throw new Error(`Full audio generation failed: ${error.message}`);
        }
    }

    /**
     * Generate TTS audio using OpenAI
     */
    async generateTTSAudio(text, voice, model, speed) {
        try {
            const audioId = uuidv4();
            const tempFilePath = path.join(this.tempDir, `tts_${audioId}.mp3`);
            
            // Ensure temp directory exists
            await fs.mkdir(this.tempDir, { recursive: true });
            
            const mp3 = await this.openai.audio.speech.create({
                model,
                voice,
                input: text,
                speed
            });
            
            const buffer = Buffer.from(await mp3.arrayBuffer());
            await fs.writeFile(tempFilePath, buffer);
            
            // Validate generated audio
            const isValid = await this.audioMixer.validateAudioFile(tempFilePath);
            if (!isValid) {
                throw new Error('Generated audio file is invalid');
            }
            
            return {
                filePath: tempFilePath,
                fileId: audioId
            };
        } catch (error) {
            console.error('TTS generation error:', error);
            throw new Error(`TTS generation failed: ${error.message}`);
        }
    }

    /**
     * Generate bulk previews
     */
    async generateBulkPreviews(requests) {
        const results = [];
        const errors = [];
        
        console.log(`Processing ${requests.length} preview requests...`);
        
        for (let i = 0; i < requests.length; i++) {
            const request = requests[i];
            try {
                console.log(`Processing request ${i + 1}/${requests.length}`);
                const result = await this.generatePreview(request);
                results.push({
                    index: i,
                    ...result
                });
            } catch (error) {
                console.error(`Bulk preview error for request ${i}:`, error);
                errors.push({
                    index: i,
                    error: error.message,
                    request: request
                });
            }
        }
        
        return {
            success: true,
            completed: results.length,
            failed: errors.length,
            total: requests.length,
            results,
            errors
        };
    }

    /**
     * Validate inputs
     */
    validateInputs({ text, voice, model, speed }) {
        if (!text || typeof text !== 'string' || text.trim().length === 0) {
            throw new Error('Text is required and must be a non-empty string');
        }
        
        if (text.length > 4096) {
            throw new Error('Text exceeds maximum length of 4096 characters');
        }
        
        if (!this.supportedVoices.includes(voice)) {
            throw new Error(`Voice must be one of: ${this.supportedVoices.join(', ')}`);
        }
        
        if (!this.supportedModels.includes(model)) {
            throw new Error(`Model must be one of: ${this.supportedModels.join(', ')}`);
        }
        
        if (typeof speed !== 'number' || speed < 0.25 || speed > 4.0) {
            throw new Error('Speed must be a number between 0.25 and 4.0');
        }
    }

    /**
     * Get available voices
     */
    getAvailableVoices() {
        return {
            voices: this.supportedVoices.map(voice => ({
                id: voice,
                name: voice.charAt(0).toUpperCase() + voice.slice(1),
                description: this.getVoiceDescription(voice)
            })),
            models: this.supportedModels.map(model => ({
                id: model,
                name: model.toUpperCase(),
                description: this.getModelDescription(model)
            }))
        };
    }

    /**
     * Get voice description
     */
    getVoiceDescription(voice) {
        const descriptions = {
            alloy: 'Balanced and versatile voice',
            echo: 'Clear and articulate voice',
            fable: 'Warm and engaging voice',
            onyx: 'Deep and authoritative voice',
            nova: 'Bright and energetic voice',
            shimmer: 'Soft and pleasant voice'
        };
        return descriptions[voice] || 'High-quality voice';
    }

    /**
     * Get model description
     */
    getModelDescription(model) {
        const descriptions = {
            'tts-1': 'Standard quality, faster generation',
            'tts-1-hd': 'High definition, higher quality'
        };
        return descriptions[model] || 'Text-to-speech model';
    }

    /**
     * Clean up temporary file
     */
    async cleanupFile(filePath) {
        try {
            await fs.unlink(filePath);
            console.log(`Cleaned up temporary file: ${path.basename(filePath)}`);
        } catch (error) {
            console.error(`Failed to cleanup file ${filePath}:`, error);
        }
    }

    /**
     * Get service status
     */
    async getStatus() {
        try {
            // Test OpenAI connection
            const testResponse = await this.openai.models.list();
            
            return {
                status: 'healthy',
                openaiConnected: true,
                availableVoices: this.supportedVoices.length,
                availableModels: this.supportedModels.length,
                timestamp: new Date().toISOString()
            };
        } catch (error) {
            return {
                status: 'unhealthy',
                openaiConnected: false,
                error: error.message,
                timestamp: new Date().toISOString()
            };
        }
    }
}

module.exports = PreviewGenerator;
