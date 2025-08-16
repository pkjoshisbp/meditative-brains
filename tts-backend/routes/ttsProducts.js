import express from 'express';
import { generateAudioForMessage, generateAudioPaths } from '../utils/audioGenerator.js';
import logger from '../utils/logger.js';
import cors from 'cors';

const router = express.Router();

// Enable CORS for Laravel integration
router.use(cors({
    origin: ['https://meditative-brains.com', 'http://localhost:8000', 'http://127.0.0.1:8000'],
    credentials: true
}));

/**
 * Generate preview audio for TTS products
 * POST /api/tts-products/preview
 */
router.post('/preview', async (req, res) => {
    try {
        const {
            text,
            voice = 'en-US-AriaNeural',
            language = 'en-US',
            engine = 'azure',
            category = 'preview',
            speakerStyle,
            speakerPersonality,
            ssml,
            speed,
            noise,
            noiseW
        } = req.body;

        if (!text) {
            return res.status(400).json({
                success: false,
                error: 'Text is required for preview generation'
            });
        }

        // Validate text length for preview (limit to prevent abuse)
        if (text.length > 500) {
            return res.status(400).json({
                success: false,
                error: 'Preview text cannot exceed 500 characters'
            });
        }

        const options = {
            engine,
            language,
            speaker: voice,
            category,
            speakerStyle,
            speakerPersonality,
            ssml,
            speed,
            noise,
            noiseW
        };

        logger.info('Generating TTS preview', { text: text.substring(0, 100), options });

        const result = await generateAudioForMessage(text, options);

        res.json({
            success: true,
            data: {
                audioUrl: result.audioUrl,
                relativePath: result.relativePath,
                engine,
                voice,
                language,
                category
            }
        });

    } catch (error) {
        logger.error('TTS preview generation failed:', error);
        res.status(500).json({
            success: false,
            error: 'Failed to generate preview audio',
            message: error.message
        });
    }
});

/**
 * Generate full audio for TTS products
 * POST /api/tts-products/generate
 */
router.post('/generate', async (req, res) => {
    try {
        const {
            text,
            voice = 'en-US-AriaNeural',
            language = 'en-US',
            engine = 'azure',
            category = 'tts-product',
            speakerStyle,
            speakerPersonality,
            ssml,
            speed,
            noise,
            noiseW,
            productId
        } = req.body;

        if (!text) {
            return res.status(400).json({
                success: false,
                error: 'Text is required for audio generation'
            });
        }

        // Use productId as category if provided
        const audioCategory = productId ? `product-${productId}` : category;

        const options = {
            engine,
            language,
            speaker: voice,
            category: audioCategory,
            speakerStyle,
            speakerPersonality,
            ssml,
            speed,
            noise,
            noiseW
        };

        logger.info('Generating TTS product audio', { 
            textLength: text.length, 
            productId, 
            category: audioCategory,
            options 
        });

        const result = await generateAudioForMessage(text, options);

        res.json({
            success: true,
            data: {
                audioUrl: result.audioUrl,
                relativePath: result.relativePath,
                engine,
                voice,
                language,
                category: audioCategory,
                productId
            }
        });

    } catch (error) {
        logger.error('TTS product audio generation failed:', error);
        res.status(500).json({
            success: false,
            error: 'Failed to generate product audio',
            message: error.message
        });
    }
});

/**
 * Generate multiple previews for bulk processing
 * POST /api/tts-products/bulk-preview
 */
router.post('/bulk-preview', async (req, res) => {
    try {
        const { items } = req.body;

        if (!Array.isArray(items) || items.length === 0) {
            return res.status(400).json({
                success: false,
                error: 'Items array is required for bulk preview'
            });
        }

        // Limit bulk operations to prevent server overload
        if (items.length > 10) {
            return res.status(400).json({
                success: false,
                error: 'Bulk preview limited to 10 items maximum'
            });
        }

        const results = [];
        const errors = [];

        for (const [index, item] of items.entries()) {
            try {
                const {
                    text,
                    voice = 'en-US-AriaNeural',
                    language = 'en-US',
                    engine = 'azure',
                    category = 'bulk-preview',
                    id
                } = item;

                if (!text) {
                    errors.push({
                        index,
                        id,
                        error: 'Text is required'
                    });
                    continue;
                }

                if (text.length > 200) {
                    errors.push({
                        index,
                        id,
                        error: 'Preview text cannot exceed 200 characters'
                    });
                    continue;
                }

                const options = {
                    engine,
                    language,
                    speaker: voice,
                    category: `${category}-${index}`
                };

                const result = await generateAudioForMessage(text, options);

                results.push({
                    index,
                    id,
                    success: true,
                    audioUrl: result.audioUrl,
                    relativePath: result.relativePath,
                    engine,
                    voice,
                    language
                });

            } catch (error) {
                logger.error(`Bulk preview item ${index} failed:`, error);
                errors.push({
                    index,
                    id: item.id,
                    error: error.message
                });
            }
        }

        res.json({
            success: true,
            data: {
                results,
                errors,
                total: items.length,
                successful: results.length,
                failed: errors.length
            }
        });

    } catch (error) {
        logger.error('Bulk preview generation failed:', error);
        res.status(500).json({
            success: false,
            error: 'Failed to process bulk preview',
            message: error.message
        });
    }
});

/**
 * Get available voices for TTS
 * GET /api/tts-products/voices
 */
router.get('/voices', async (req, res) => {
    try {
        const { engine = 'azure', language } = req.query;

        // Load voices based on engine
        let voices = [];

        if (engine === 'azure') {
            // Load Azure voices from voices file
            try {
                const { default: azureVoices } = await import('../azure-voices.json', { assert: { type: 'json' } });
                voices = azureVoices;

                // Filter by language if specified
                if (language) {
                    voices = voices.filter(voice => 
                        voice.Locale?.toLowerCase() === language.toLowerCase() ||
                        voice.ShortName?.startsWith(language)
                    );
                }
            } catch (error) {
                logger.error('Failed to load Azure voices:', error);
                voices = [
                    { ShortName: 'en-US-AriaNeural', DisplayName: 'Aria (Female)', Locale: 'en-US' },
                    { ShortName: 'en-US-DavisNeural', DisplayName: 'Davis (Male)', Locale: 'en-US' },
                    { ShortName: 'en-US-JennyNeural', DisplayName: 'Jenny (Female)', Locale: 'en-US' }
                ];
            }
        } else if (engine === 'vits') {
            // VITS voices
            voices = [
                { ShortName: 'p225', DisplayName: 'VCTK p225 (Female)', Locale: 'en-US' },
                { ShortName: 'p227', DisplayName: 'VCTK p227 (Male)', Locale: 'en-US' },
                { ShortName: 'p230', DisplayName: 'VCTK p230 (Female)', Locale: 'en-US' },
                { ShortName: 'p245', DisplayName: 'VCTK p245 (Male)', Locale: 'en-US' },
                { ShortName: 'hi-female', DisplayName: 'Hindi Female', Locale: 'hi-IN' },
                { ShortName: 'hi-male', DisplayName: 'Hindi Male', Locale: 'hi-IN' }
            ];

            if (language) {
                voices = voices.filter(voice => 
                    voice.Locale?.toLowerCase() === language.toLowerCase()
                );
            }
        }

        res.json({
            success: true,
            data: {
                engine,
                language,
                voices: voices.slice(0, 50) // Limit response size
            }
        });

    } catch (error) {
        logger.error('Failed to get voices:', error);
        res.status(500).json({
            success: false,
            error: 'Failed to retrieve voices',
            message: error.message
        });
    }
});

/**
 * Health check endpoint for TTS service
 * GET /api/tts-products/health
 */
router.get('/health', async (req, res) => {
    try {
        const health = {
            status: 'healthy',
            timestamp: new Date().toISOString(),
            services: {
                azure: {
                    available: !!(process.env.AZURE_KEY && process.env.AZURE_REGION),
                    region: process.env.AZURE_REGION || 'not-configured'
                },
                vits: {
                    available: true, // VITS is always available if installed
                    models: ['p225', 'p227', 'p230', 'p245', 'hi-female', 'hi-male']
                }
            },
            cache: {
                directory: 'audio-cache',
                writable: true // We assume it's writable since the app is running
            }
        };

        res.json({
            success: true,
            data: health
        });

    } catch (error) {
        logger.error('Health check failed:', error);
        res.status(500).json({
            success: false,
            error: 'Health check failed',
            message: error.message
        });
    }
});

/**
 * Get audio path without generating (for path validation)
 * POST /api/tts-products/path
 */
router.post('/path', async (req, res) => {
    try {
        const {
            text,
            voice = 'en-US-AriaNeural',
            language = 'en-US',
            engine = 'azure',
            category = 'preview'
        } = req.body;

        if (!text) {
            return res.status(400).json({
                success: false,
                error: 'Text is required to generate path'
            });
        }

        const options = {
            engine,
            language,
            speaker: voice,
            category
        };

        const paths = generateAudioPaths(text, options);

        res.json({
            success: true,
            data: {
                relativePath: paths.relativePath,
                audioUrl: paths.audioUrl,
                engine,
                voice,
                language,
                category
            }
        });

    } catch (error) {
        logger.error('Path generation failed:', error);
        res.status(500).json({
            success: false,
            error: 'Failed to generate audio path',
            message: error.message
        });
    }
});

export default router;
