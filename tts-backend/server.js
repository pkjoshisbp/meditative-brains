import express from 'express';
import mongoose from 'mongoose';
import bodyParser from 'body-parser';
import path from 'path';
import { fileURLToPath } from 'url';
import cors from 'cors';
import dotenv from 'dotenv';

// Import models
import Category from './models/Category.js';
import MotivationMessage from './models/MotivationMessage.js';
import User from './models/User.js';
import TtsProduct from './models/TtsProduct.js';

// Import utilities
import { generateSSMLFromNaturalLanguage } from './utils/ssmlParser.js';
import { generateAudioFromMessages } from './utils/audioGenerator.js';
import logger from './utils/logger.js';

// Import Azure voices list for TTS products
import voicesList from './azure-voices.json' assert { type: 'json' };

const app = express();
const PORT = 3001;

app.use(express.json());
app.use(express.static(path.join(process.cwd(), 'ui/build')));

app.get('/api/categories', async (req, res) => {
    const categories = await MotivationMessage.distinct('category');
    res.json(categories);
});

app.get('/api/messages', async (req, res) => {
    const { category, language } = req.query;
    const messages = await MotivationMessage.find({ category, language });
    res.json(messages);
});

app.post('/api/save-ssml', (req, res) => {
    const { ssml, language, category } = req.body;
    // Save SSML configuration logic here
    res.json({ success: true });
});

// =====================================
// TTS PRODUCTS API ENDPOINTS
// =====================================

// Health check endpoint
app.get('/api/health', (req, res) => {
    res.json({
        status: 'healthy',
        timestamp: new Date().toISOString(),
        version: '1.0.0',
        services: {
            database: mongoose.connection.readyState === 1 ? 'connected' : 'disconnected',
            azure_tts: process.env.AZURE_KEY ? 'configured' : 'not_configured',
            vits_tts: 'available'
        }
    });
});

// Get available TTS voices and engines
app.get('/api/tts/voices', (req, res) => {
    try {
        const { language, engine } = req.query;
        
        let filteredVoices = voicesList;
        
        // Filter by language if specified
        if (language) {
            filteredVoices = voicesList.filter(voice => 
                voice.Locale.toLowerCase().includes(language.toLowerCase()) ||
                voice.ShortName.toLowerCase().includes(language.toLowerCase())
            );
        }
        
        // Filter by engine if specified
        if (engine && engine.toLowerCase() === 'azure') {
            // Azure voices have specific properties
            filteredVoices = filteredVoices.filter(voice => voice.VoiceType);
        }
        
        res.json({
            success: true,
            voices: filteredVoices,
            total: filteredVoices.length,
            engines: ['azure', 'vits'],
            supported_languages: ['en-US', 'es-ES', 'fr-FR', 'de-DE', 'it-IT', 'pt-BR', 'ja-JP', 'ko-KR', 'zh-CN']
        });
    } catch (error) {
        logger.error('Error fetching voices:', error);
        res.status(500).json({
            success: false,
            error: 'Failed to fetch voices',
            message: error.message
        });
    }
});

// Generate TTS audio preview for a text sample
app.post('/api/tts/preview', async (req, res) => {
    try {
        const {
            text,
            voice = 'en-US-AriaNeural',
            engine = 'azure',
            language = 'en-US',
            prosodyRate = 'medium',
            prosodyPitch = 'medium',
            prosodyVolume = 'medium',
            speakerStyle = null
        } = req.body;
        
        if (!text) {
            return res.status(400).json({
                success: false,
                error: 'Text is required for preview generation'
            });
        }
        
        logger.info('Generating TTS preview:', {
            text: text.substring(0, 100) + '...',
            voice,
            engine,
            language
        });
        
        // Use the existing audio generator
        const audioResult = await generateAudioFromMessages({
            messages: [text],
            speaker: voice,
            engine: engine,
            language: language,
            prosodyRate: prosodyRate,
            prosodyPitch: prosodyPitch,
            prosodyVolume: prosodyVolume,
            speakerStyle: speakerStyle
        });
        
        if (audioResult.success && audioResult.audioPaths.length > 0) {
            const audioPath = audioResult.audioPaths[0];
            const audioUrl = audioResult.audioUrls[0];
            
            res.json({
                success: true,
                preview: {
                    text,
                    voice,
                    engine,
                    language,
                    audio_path: audioPath,
                    audio_url: audioUrl,
                    duration_estimate: Math.ceil(text.length / 15), // rough estimate: 15 chars per second
                    settings: {
                        prosodyRate,
                        prosodyPitch,
                        prosodyVolume,
                        speakerStyle
                    }
                }
            });
        } else {
            throw new Error(audioResult.error || 'Failed to generate audio');
        }
        
    } catch (error) {
        logger.error('Error generating TTS preview:', error);
        res.status(500).json({
            success: false,
            error: 'Failed to generate TTS preview',
            message: error.message
        });
    }
});

// Generate multiple TTS audio files (for TTS products with multiple messages)
app.post('/api/tts/generate-batch', async (req, res) => {
    try {
        const {
            messages,
            voice = 'en-US-AriaNeural',
            engine = 'azure',
            language = 'en-US',
            prosodyRate = 'medium',
            prosodyPitch = 'medium',
            prosodyVolume = 'medium',
            speakerStyle = null,
            product_id = null
        } = req.body;
        
        if (!messages || !Array.isArray(messages) || messages.length === 0) {
            return res.status(400).json({
                success: false,
                error: 'Messages array is required for batch generation'
            });
        }
        
        logger.info('Generating TTS batch:', {
            messageCount: messages.length,
            voice,
            engine,
            language,
            product_id
        });
        
        // Generate audio for all messages
        const audioResult = await generateAudioFromMessages({
            messages,
            speaker: voice,
            engine: engine,
            language: language,
            prosodyRate: prosodyRate,
            prosodyPitch: prosodyPitch,
            prosodyVolume: prosodyVolume,
            speakerStyle: speakerStyle
        });
        
        if (audioResult.success) {
            res.json({
                success: true,
                batch: {
                    product_id,
                    message_count: messages.length,
                    voice,
                    engine,
                    language,
                    audio_paths: audioResult.audioPaths,
                    audio_urls: audioResult.audioUrls,
                    total_duration_estimate: Math.ceil(messages.join(' ').length / 15),
                    settings: {
                        prosodyRate,
                        prosodyPitch,
                        prosodyVolume,
                        speakerStyle
                    },
                    generated_at: new Date().toISOString()
                }
            });
        } else {
            throw new Error(audioResult.error || 'Failed to generate audio batch');
        }
        
    } catch (error) {
        logger.error('Error generating TTS batch:', error);
        res.status(500).json({
            success: false,
            error: 'Failed to generate TTS batch',
            message: error.message
        });
    }
});

// Get TTS engine capabilities and status
app.get('/api/tts/engines', (req, res) => {
    try {
        const engines = {
            azure: {
                name: 'Microsoft Azure Cognitive Services',
                status: process.env.AZURE_KEY ? 'available' : 'not_configured',
                features: ['SSML support', 'Voice styles', 'Prosody control', 'High quality'],
                languages: ['en-US', 'es-ES', 'fr-FR', 'de-DE', 'it-IT', 'pt-BR', 'ja-JP', 'ko-KR', 'zh-CN'],
                voice_count: voicesList.filter(v => v.VoiceType).length,
                output_format: 'AAC'
            },
            vits: {
                name: 'VITS (Variational Inference TTS)',
                status: 'available',
                features: ['Natural speech', 'Fast generation', 'Multiple speakers'],
                languages: ['en-US', 'es-ES', 'fr-FR', 'de-DE'],
                voice_count: 8,
                output_format: 'AAC'
            }
        };
        
        res.json({
            success: true,
            engines,
            default_engine: 'azure',
            total_voices: voicesList.length
        });
    } catch (error) {
        logger.error('Error fetching engine info:', error);
        res.status(500).json({
            success: false,
            error: 'Failed to fetch engine information',
            message: error.message
        });
    }
});

// Validate TTS configuration
app.post('/api/tts/validate', (req, res) => {
    try {
        const {
            text,
            voice,
            engine,
            language,
            prosodyRate,
            prosodyPitch,
            prosodyVolume
        } = req.body;
        
        const validation = {
            text: {
                valid: text && text.length > 0 && text.length <= 5000,
                issues: []
            },
            voice: {
                valid: true,
                issues: []
            },
            engine: {
                valid: ['azure', 'vits'].includes(engine),
                issues: []
            },
            settings: {
                valid: true,
                issues: []
            }
        };
        
        // Validate text
        if (!text) {
            validation.text.issues.push('Text is required');
        } else if (text.length > 5000) {
            validation.text.issues.push('Text exceeds maximum length of 5000 characters');
        }
        
        // Validate voice
        if (engine === 'azure') {
            const voiceExists = voicesList.some(v => v.ShortName === voice);
            if (!voiceExists) {
                validation.voice.valid = false;
                validation.voice.issues.push('Voice not found in Azure voices list');
            }
        }
        
        // Validate engine
        if (!validation.engine.valid) {
            validation.engine.issues.push('Engine must be either "azure" or "vits"');
        }
        
        // Validate prosody settings
        const validRates = ['x-slow', 'slow', 'medium', 'fast', 'x-fast'];
        const validPitches = ['x-low', 'low', 'medium', 'high', 'x-high'];
        const validVolumes = ['silent', 'x-soft', 'soft', 'medium', 'loud', 'x-loud'];
        
        if (prosodyRate && !validRates.includes(prosodyRate)) {
            validation.settings.valid = false;
            validation.settings.issues.push(`Invalid prosody rate. Must be one of: ${validRates.join(', ')}`);
        }
        
        if (prosodyPitch && !validPitches.includes(prosodyPitch)) {
            validation.settings.valid = false;
            validation.settings.issues.push(`Invalid prosody pitch. Must be one of: ${validPitches.join(', ')}`);
        }
        
        if (prosodyVolume && !validVolumes.includes(prosodyVolume)) {
            validation.settings.valid = false;
            validation.settings.issues.push(`Invalid prosody volume. Must be one of: ${validVolumes.join(', ')}`);
        }
        
        const isValid = Object.values(validation).every(v => v.valid);
        
        res.json({
            success: true,
            valid: isValid,
            validation
        });
        
    } catch (error) {
        logger.error('Error validating TTS configuration:', error);
        res.status(500).json({
            success: false,
            error: 'Failed to validate TTS configuration',
            message: error.message
        });
    }
});

// =====================================
// TTS PRODUCT MANAGEMENT ENDPOINTS
// =====================================

// Create or update TTS product
app.post('/api/tts/products', async (req, res) => {
    try {
        const {
            laravel_product_id,
            title,
            description,
            category,
            messages,
            language = 'en-US',
            voice = 'en-US-AriaNeural',
            engine = 'azure',
            prosodyRate = 'medium',
            prosodyPitch = 'medium',
            prosodyVolume = 'medium',
            speakerStyle = null,
            backgroundMusicCategory = null,
            backgroundMusicFile = null
        } = req.body;
        
        if (!laravel_product_id || !title || !messages || messages.length === 0) {
            return res.status(400).json({
                success: false,
                error: 'laravel_product_id, title, and messages are required'
            });
        }
        
        // Check if product already exists
        let product = await TtsProduct.findOne({ laravel_product_id });
        
        const productData = {
            laravel_product_id,
            title,
            description,
            category,
            messages,
            language,
            voice,
            engine,
            prosodyRate,
            prosodyPitch,
            prosodyVolume,
            speakerStyle,
            backgroundMusicCategory,
            backgroundMusicFile,
            status: 'draft',
            audioGenerated: false
        };
        
        if (product) {
            // Update existing product
            Object.assign(product, productData);
            await product.save();
            logger.info('Updated TTS product:', { laravel_product_id, title });
        } else {
            // Create new product
            product = new TtsProduct(productData);
            await product.save();
            logger.info('Created TTS product:', { laravel_product_id, title });
        }
        
        res.json({
            success: true,
            product: product
        });
        
    } catch (error) {
        logger.error('Error creating/updating TTS product:', error);
        res.status(500).json({
            success: false,
            error: 'Failed to create/update TTS product',
            message: error.message
        });
    }
});

// Get TTS product by Laravel product ID
app.get('/api/tts/products/:laravel_product_id', async (req, res) => {
    try {
        const { laravel_product_id } = req.params;
        
        const product = await TtsProduct.findOne({ 
            laravel_product_id: parseInt(laravel_product_id) 
        });
        
        if (!product) {
            return res.status(404).json({
                success: false,
                error: 'TTS product not found'
            });
        }
        
        res.json({
            success: true,
            product: product
        });
        
    } catch (error) {
        logger.error('Error fetching TTS product:', error);
        res.status(500).json({
            success: false,
            error: 'Failed to fetch TTS product',
            message: error.message
        });
    }
});

// Generate audio for TTS product
app.post('/api/tts/products/:laravel_product_id/generate', async (req, res) => {
    try {
        const { laravel_product_id } = req.params;
        
        const product = await TtsProduct.findOne({ 
            laravel_product_id: parseInt(laravel_product_id) 
        });
        
        if (!product) {
            return res.status(404).json({
                success: false,
                error: 'TTS product not found'
            });
        }
        
        // Update status to generating
        product.status = 'generating';
        await product.save();
        
        logger.info('Generating audio for TTS product:', { 
            laravel_product_id, 
            title: product.title,
            messageCount: product.messages.length 
        });
        
        // Generate audio for all messages
        const audioResult = await generateAudioFromMessages({
            messages: product.messages,
            speaker: product.voice,
            engine: product.engine,
            language: product.language,
            prosodyRate: product.prosodyRate,
            prosodyPitch: product.prosodyPitch,
            prosodyVolume: product.prosodyVolume,
            speakerStyle: product.speakerStyle
        });
        
        if (audioResult.success) {
            // Update product with generated audio
            product.audioPaths = audioResult.audioPaths;
            product.audioUrls = audioResult.audioUrls;
            product.previewPath = audioResult.audioPaths[0]; // First message as preview
            product.previewUrl = audioResult.audioUrls[0];
            product.totalDuration = Math.ceil(product.messages.join(' ').length / 15);
            product.audioGenerated = true;
            product.lastGenerated = new Date();
            product.status = 'ready';
            product.errorMessage = null;
            
            await product.save();
            
            res.json({
                success: true,
                product: product,
                audio: {
                    paths: audioResult.audioPaths,
                    urls: audioResult.audioUrls,
                    preview_url: audioResult.audioUrls[0],
                    total_files: audioResult.audioPaths.length
                }
            });
        } else {
            // Update product with error status
            product.status = 'error';
            product.errorMessage = audioResult.error || 'Failed to generate audio';
            await product.save();
            
            throw new Error(audioResult.error || 'Failed to generate audio');
        }
        
    } catch (error) {
        logger.error('Error generating audio for TTS product:', error);
        
        // Update product status to error
        try {
            const product = await TtsProduct.findOne({ 
                laravel_product_id: parseInt(req.params.laravel_product_id) 
            });
            if (product) {
                product.status = 'error';
                product.errorMessage = error.message;
                await product.save();
            }
        } catch (updateError) {
            logger.error('Error updating product status:', updateError);
        }
        
        res.status(500).json({
            success: false,
            error: 'Failed to generate audio for TTS product',
            message: error.message
        });
    }
});

// Get all TTS products (with pagination)
app.get('/api/tts/products', async (req, res) => {
    try {
        const { 
            page = 1, 
            limit = 20, 
            category = null, 
            status = null,
            search = null 
        } = req.query;
        
        const query = {};
        
        if (category) {
            query.category = category;
        }
        
        if (status) {
            query.status = status;
        }
        
        if (search) {
            query.$or = [
                { title: { $regex: search, $options: 'i' } },
                { description: { $regex: search, $options: 'i' } }
            ];
        }
        
        const options = {
            page: parseInt(page),
            limit: parseInt(limit),
            sort: { createdAt: -1 }
        };
        
        const products = await TtsProduct.find(query)
            .sort(options.sort)
            .limit(options.limit * 1)
            .skip((options.page - 1) * options.limit);
            
        const total = await TtsProduct.countDocuments(query);
        
        res.json({
            success: true,
            products: products,
            pagination: {
                current_page: options.page,
                per_page: options.limit,
                total: total,
                total_pages: Math.ceil(total / options.limit)
            }
        });
        
    } catch (error) {
        logger.error('Error fetching TTS products:', error);
        res.status(500).json({
            success: false,
            error: 'Failed to fetch TTS products',
            message: error.message
        });
    }
});

// Delete TTS product
app.delete('/api/tts/products/:laravel_product_id', async (req, res) => {
    try {
        const { laravel_product_id } = req.params;
        
        const product = await TtsProduct.findOneAndDelete({ 
            laravel_product_id: parseInt(laravel_product_id) 
        });
        
        if (!product) {
            return res.status(404).json({
                success: false,
                error: 'TTS product not found'
            });
        }
        
        // TODO: Clean up generated audio files
        // This could be done in a background job
        
        logger.info('Deleted TTS product:', { laravel_product_id, title: product.title });
        
        res.json({
            success: true,
            message: 'TTS product deleted successfully'
        });
        
    } catch (error) {
        logger.error('Error deleting TTS product:', error);
        res.status(500).json({
            success: false,
            error: 'Failed to delete TTS product',
            message: error.message
        });
    }
});

app.listen(PORT, () => console.log(`🌐 Server running on http://localhost:${PORT}`));
