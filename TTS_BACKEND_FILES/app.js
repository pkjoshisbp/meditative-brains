const express = require('express');
const cors = require('cors');
const path = require('path');
const fs = require('fs').promises;
require('dotenv').config();

const PreviewGenerator = require('./services/previewGenerator');
const AudioMixer = require('./services/audioMixer');

const app = express();
const port = process.env.PORT || 3001;

// Initialize services
const previewGenerator = new PreviewGenerator();
const audioMixer = new AudioMixer();

// Middleware
app.use(cors({
    origin: ['https://meditative-brains.com', 'http://localhost:3000', 'http://localhost:8000'],
    credentials: true
}));
app.use(express.json({ limit: '10mb' }));
app.use(express.urlencoded({ extended: true, limit: '10mb' }));

// Serve static files for temporary audio
app.use('/temp-audio', express.static(path.join(__dirname, 'public/temp-audio')));

// Health check endpoint
app.get('/health', (req, res) => {
    res.json({
        status: 'healthy',
        timestamp: new Date().toISOString(),
        service: 'TTS Backend',
        version: '1.0.0'
    });
});

// Service status endpoint
app.get('/api/status', async (req, res) => {
    try {
        const status = await previewGenerator.getStatus();
        res.json(status);
    } catch (error) {
        res.status(500).json({
            status: 'error',
            error: error.message,
            timestamp: new Date().toISOString()
        });
    }
});

// Get available voices
app.get('/api/voices', (req, res) => {
    try {
        const voices = previewGenerator.getAvailableVoices();
        res.json({
            success: true,
            ...voices
        });
    } catch (error) {
        res.status(500).json({
            success: false,
            error: error.message
        });
    }
});

// Generate TTS preview
app.post('/api/generate-preview', async (req, res) => {
    try {
        const {
            text,
            voice = 'alloy',
            model = 'tts-1',
            backgroundMusicUrl,
            previewDuration = 30,
            speed = 1.0
        } = req.body;

        console.log('Preview request:', {
            textLength: text?.length,
            voice,
            model,
            hasBackgroundMusic: !!backgroundMusicUrl,
            previewDuration
        });

        const result = await previewGenerator.generatePreview({
            text,
            voice,
            model,
            backgroundMusicUrl,
            previewDuration,
            speed
        });

        res.json(result);
    } catch (error) {
        console.error('Preview generation error:', error);
        res.status(400).json({
            success: false,
            error: error.message
        });
    }
});

// Generate full TTS audio
app.post('/api/generate-full', async (req, res) => {
    try {
        const {
            text,
            voice = 'alloy',
            model = 'tts-1',
            speed = 1.0
        } = req.body;

        console.log('Full audio request:', {
            textLength: text?.length,
            voice,
            model
        });

        const result = await previewGenerator.generateFullAudio({
            text,
            voice,
            model,
            speed
        });

        res.json(result);
    } catch (error) {
        console.error('Full audio generation error:', error);
        res.status(400).json({
            success: false,
            error: error.message
        });
    }
});

// Generate bulk previews
app.post('/api/generate-bulk-previews', async (req, res) => {
    try {
        const { requests } = req.body;

        if (!Array.isArray(requests) || requests.length === 0) {
            return res.status(400).json({
                success: false,
                error: 'Requests must be a non-empty array'
            });
        }

        if (requests.length > 10) {
            return res.status(400).json({
                success: false,
                error: 'Maximum 10 requests allowed per bulk operation'
            });
        }

        console.log(`Bulk preview request: ${requests.length} items`);

        const results = await previewGenerator.generateBulkPreviews(requests);
        res.json(results);
    } catch (error) {
        console.error('Bulk preview error:', error);
        res.status(400).json({
            success: false,
            error: error.message
        });
    }
});

// Mix audio with background music
app.post('/api/mix-audio', async (req, res) => {
    try {
        const {
            audioUrl,
            backgroundMusicUrl,
            duration = 30
        } = req.body;

        if (!audioUrl || !backgroundMusicUrl) {
            return res.status(400).json({
                success: false,
                error: 'Both audioUrl and backgroundMusicUrl are required'
            });
        }

        // For now, this is a simplified implementation
        // In a full implementation, you'd download the audio file first
        res.status(501).json({
            success: false,
            error: 'Audio mixing from URLs not yet implemented'
        });
    } catch (error) {
        console.error('Audio mixing error:', error);
        res.status(400).json({
            success: false,
            error: error.message
        });
    }
});

// Clean up temporary files
app.post('/api/cleanup', async (req, res) => {
    try {
        const { maxAgeHours = 2 } = req.body;
        await audioMixer.cleanupTempFiles(maxAgeHours);
        
        res.json({
            success: true,
            message: 'Cleanup completed',
            timestamp: new Date().toISOString()
        });
    } catch (error) {
        console.error('Cleanup error:', error);
        res.status(500).json({
            success: false,
            error: error.message
        });
    }
});

// Get audio file info
app.post('/api/audio-info', async (req, res) => {
    try {
        const { filePath } = req.body;
        
        if (!filePath) {
            return res.status(400).json({
                success: false,
                error: 'filePath is required'
            });
        }

        const info = await audioMixer.getAudioInfo(filePath);
        res.json({
            success: true,
            audioInfo: info
        });
    } catch (error) {
        console.error('Audio info error:', error);
        res.status(400).json({
            success: false,
            error: error.message
        });
    }
});

// Error handling middleware
app.use((error, req, res, next) => {
    console.error('Unhandled error:', error);
    res.status(500).json({
        success: false,
        error: 'Internal server error',
        timestamp: new Date().toISOString()
    });
});

// 404 handler
app.use('*', (req, res) => {
    res.status(404).json({
        success: false,
        error: 'Endpoint not found',
        path: req.originalUrl
    });
});

// Cleanup routine - run every hour
setInterval(async () => {
    try {
        console.log('Running scheduled cleanup...');
        await audioMixer.cleanupTempFiles(2);
    } catch (error) {
        console.error('Scheduled cleanup error:', error);
    }
}, 60 * 60 * 1000); // 1 hour

// Start server
app.listen(port, () => {
    console.log(`🎵 TTS Backend Server running on port ${port}`);
    console.log(`📁 Temp audio directory: ${path.join(__dirname, 'public/temp-audio')}`);
    console.log(`🎵 Background music directory: ${path.join(__dirname, 'background-music')}`);
    console.log(`🔊 OpenAI API Key: ${process.env.OPENAI_API_KEY ? 'Set' : 'Not Set'}`);
    
    // Initial cleanup
    audioMixer.cleanupTempFiles(2).catch(console.error);
});

module.exports = app;
