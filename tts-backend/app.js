import dotenv from 'dotenv';
dotenv.config();
import express from 'express';
import mongoose from 'mongoose';
import fs from 'fs';
import https from 'https';
import bodyParser from 'body-parser';
import categoryRouter from './routes/category.js';
import userRouter from './routes/user.js';
import motivationMessageRouter from './routes/motivationMessage.js';
import authRouter from './routes/auth.js'; 
import audioRouter from './routes/audio.js';
import languageRouter from './routes/language.js';
import attentionGuideRouter from './routes/attentionGuide.js';
import logsRouter from './routes/logs.js';
import ttsProductsRouter from './routes/ttsProducts.js';
import path from 'path';
import cors from 'cors';
import logger from './utils/logger.js';

// Import TTS-related modules
import TtsProduct from './models/TtsProduct.js';
import { generateAudioForMessage } from './utils/audioGenerator.js';
import voicesList from './azure-voices.json' assert { type: 'json' };

// Define HTTPS options with your SSL certificate and key
const options = {
    cert: fs.readFileSync('/var/www/meditative-brains.com/ssl/meditative-brains.com-le.crt'),
    key: fs.readFileSync('/var/www/meditative-brains.com/ssl/meditative-brains.com-le.key')
};

const app = express();

// Enable CORS for Laravel integration
app.use(cors({
    origin: process.env.CORS_ORIGIN || '*',
    credentials: true
}));

app.use(bodyParser.json({ limit: '10mb' }));
app.use(express.json({ limit: '10mb' }));

// Proper request logging middleware
app.use((req, res, next) => {
    logger.info(`[REQUEST] ${req.method} ${req.originalUrl}`);
    next();
});

// Error handling middleware
app.use((err, req, res, next) => {
    logger.error(`[ERROR] Unhandled error:`, err);
    res.status(500).json({ 
        success: false, 
        error: 'Internal server error', 
        message: err.message 
    });
});

// Mount routers
app.use("/api/motivationMessage", motivationMessageRouter);
app.use("/api", motivationMessageRouter);
app.use("/api/category", categoryRouter);
app.use("/api/user", userRouter);
app.use("/api/auth", authRouter);
app.use("/api/audio", audioRouter);
app.use("/api/language", languageRouter);
app.use("/api/attention-guide", attentionGuideRouter);
app.use("/api/logs", logsRouter); // Add this line for logs router
app.use("/api/tts-products", ttsProductsRouter); // Add TTS products router

// Add static file serving for audio files
const audioCachePath = path.join(process.cwd(), 'audio-cache');
app.use('/audio-cache', express.static(audioCachePath));

// Add static file serving for flutter logs
const flutterLogsPath = path.join(process.cwd(), 'flutter_logs');
app.use('/flutter-logs', express.static(flutterLogsPath));

// =====================================
// TTS API ENDPOINTS FOR LARAVEL INTEGRATION
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

// TTS Product Management Endpoints
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

// Connect to MongoDB
mongoose.connect('mongodb://pawan:pragati123..@127.0.0.1:27017/motivation')
    .then(() => {
        // Create directories if they don't exist
        if (!fs.existsSync(audioCachePath)) {
            fs.mkdirSync(audioCachePath, { recursive: true });
            logger.info(`Created audio cache directory: ${audioCachePath}`);
        }
        
        // Create the HTTPS server and start listening on port 3001
        https.createServer(options, app).listen(3001, () => {
            logger.info('Server running on port 3001 with HTTPS');
            logger.info(`Audio cache directory: ${audioCachePath}`);
        });
    })
    .catch(err => logger.error('MongoDB connection error:', err));

// Add a 404 handler for API routes
app.use('/api/*', (req, res) => {
    logger.error(`API endpoint not found: ${req.originalUrl}`);
    res.status(404).json({ success: false, error: 'Endpoint not found' });
});

