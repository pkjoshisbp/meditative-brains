# Phase 2: TTS Backend Enhancement Instructions

## 🎯 Node.js TTS Backend Updates (meditative-brains.com:3001)

The Laravel backend is ready to handle preview generation. Now we need to enhance the TTS backend to support:

1. **Background Music Mixing**
2. **Preview Duration Control**
3. **Temporary File Management**
4. **Audio Quality Optimization**

## 🛠️ Required TTS Backend Enhancements

### 1. Install Required Dependencies

```bash
npm install ffmpeg-static fluent-ffmpeg node-cleanup uuid
```

### 2. Create Audio Mixing Service

Create `services/audioMixer.js`:

```javascript
const ffmpeg = require('fluent-ffmpeg');
const ffmpegPath = require('ffmpeg-static');
const path = require('path');
const fs = require('fs').promises;
const { v4: uuidv4 } = require('uuid');

ffmpeg.setFfmpegPath(ffmpegPath);

class AudioMixer {
    constructor() {
        this.tempDir = path.join(__dirname, '../temp');
        this.ensureTempDir();
    }

    async ensureTempDir() {
        try {
            await fs.mkdir(this.tempDir, { recursive: true });
        } catch (error) {
            console.error('Failed to create temp directory:', error);
        }
    }

    /**
     * Mix TTS audio with background music
     */
    async mixWithBackground(ttsAudioPath, backgroundMusicUrl, previewDuration = 30) {
        const outputId = uuidv4();
        const outputPath = path.join(this.tempDir, `preview_${outputId}.mp3`);

        return new Promise((resolve, reject) => {
            const command = ffmpeg();

            // Add TTS audio as first input
            command.input(ttsAudioPath);

            // Add background music as second input
            if (backgroundMusicUrl) {
                command.input(backgroundMusicUrl);

                // Mix audio: TTS at full volume, background at 20% volume
                command.complexFilter([
                    '[0:a]volume=1.0[tts]',
                    '[1:a]volume=0.2[bg]',
                    '[tts][bg]amix=inputs=2:duration=shortest[mixed]'
                ], 'mixed');
            }

            command
                .duration(previewDuration) // Limit to preview duration
                .audioCodec('mp3')
                .audioBitrate('128k')
                .audioFrequency(44100)
                .output(outputPath)
                .on('end', () => {
                    resolve({
                        outputPath,
                        duration: previewDuration,
                        fileId: outputId
                    });
                })
                .on('error', (error) => {
                    console.error('Audio mixing error:', error);
                    reject(error);
                });

            command.run();
        });
    }

    /**
     * Trim audio to specified duration
     */
    async trimAudio(inputPath, duration) {
        const outputId = uuidv4();
        const outputPath = path.join(this.tempDir, `trimmed_${outputId}.mp3`);

        return new Promise((resolve, reject) => {
            ffmpeg(inputPath)
                .duration(duration)
                .output(outputPath)
                .on('end', () => {
                    resolve(outputPath);
                })
                .on('error', reject);
        });
    }

    /**
     * Clean up temporary files older than 2 hours
     */
    async cleanupTempFiles() {
        try {
            const files = await fs.readdir(this.tempDir);
            const now = Date.now();
            const twoHours = 2 * 60 * 60 * 1000;

            for (const file of files) {
                if (file.startsWith('preview_') || file.startsWith('trimmed_')) {
                    const filePath = path.join(this.tempDir, file);
                    const stats = await fs.stat(filePath);
                    
                    if (now - stats.mtime.getTime() > twoHours) {
                        await fs.unlink(filePath);
                        console.log(`Cleaned up temporary file: ${file}`);
                    }
                }
            }
        } catch (error) {
            console.error('Cleanup error:', error);
        }
    }
}

module.exports = AudioMixer;
```

### 3. Create Preview Generator Service

Create `services/previewGenerator.js`:

```javascript
const AudioMixer = require('./audioMixer');
const TtsService = require('./ttsService'); // Your existing TTS service
const path = require('path');

class PreviewGenerator {
    constructor() {
        this.audioMixer = new AudioMixer();
        this.ttsService = new TtsService();
    }

    /**
     * Generate preview with background music
     */
    async generatePreview({
        text,
        voice = 'default',
        speed = 1.0,
        language = 'en',
        previewDuration = 30,
        backgroundMusicUrl = null
    }) {
        try {
            // Step 1: Generate TTS audio
            console.log('Generating TTS audio...');
            const ttsResult = await this.ttsService.generateAudio({
                text,
                voice,
                speed,
                language
            });

            let finalAudioPath = ttsResult.audioPath;
            let actualDuration = previewDuration;

            // Step 2: Mix with background music if provided
            if (backgroundMusicUrl) {
                console.log('Mixing with background music...');
                const mixResult = await this.audioMixer.mixWithBackground(
                    ttsResult.audioPath,
                    backgroundMusicUrl,
                    previewDuration
                );
                finalAudioPath = mixResult.outputPath;
                actualDuration = mixResult.duration;
            } else {
                // Just trim the TTS audio to preview duration
                finalAudioPath = await this.audioMixer.trimAudio(
                    ttsResult.audioPath,
                    previewDuration
                );
            }

            // Step 3: Generate accessible URL
            const previewUrl = await this.createTemporaryUrl(finalAudioPath);

            return {
                success: true,
                preview_url: previewUrl,
                duration: actualDuration,
                expires_in: 7200 // 2 hours
            };

        } catch (error) {
            console.error('Preview generation failed:', error);
            throw new Error('Failed to generate preview: ' + error.message);
        }
    }

    /**
     * Create temporary accessible URL for the audio file
     */
    async createTemporaryUrl(audioPath) {
        const fileName = path.basename(audioPath);
        // This should return a URL that serves the file temporarily
        // Implementation depends on your server setup
        return `${process.env.BASE_URL}/temp-audio/${fileName}`;
    }
}

module.exports = PreviewGenerator;
```

### 4. Add Preview Endpoint to TTS Backend

Add to your main `app.js` or `server.js`:

```javascript
const express = require('express');
const PreviewGenerator = require('./services/previewGenerator');
const AudioMixer = require('./services/audioMixer');

const app = express();
const previewGenerator = new PreviewGenerator();
const audioMixer = new AudioMixer();

// Add preview generation endpoint
app.post('/api/generate-preview', async (req, res) => {
    try {
        const {
            text,
            voice,
            speed,
            language,
            preview_duration: previewDuration,
            background_music_url: backgroundMusicUrl
        } = req.body;

        // Validate input
        if (!text || text.length === 0) {
            return res.status(400).json({
                error: 'Text is required for preview generation'
            });
        }

        if (text.length > 500) {
            return res.status(400).json({
                error: 'Text too long for preview (max 500 characters)'
            });
        }

        console.log(`Generating preview for: "${text.substring(0, 50)}..."`);

        const result = await previewGenerator.generatePreview({
            text,
            voice: voice || 'default',
            speed: speed || 1.0,
            language: language || 'en',
            previewDuration: previewDuration || 30,
            backgroundMusicUrl
        });

        res.json(result);

    } catch (error) {
        console.error('Preview generation error:', error);
        res.status(500).json({
            error: 'Failed to generate preview',
            details: error.message
        });
    }
});

// Serve temporary audio files
app.use('/temp-audio', express.static(path.join(__dirname, 'temp'), {
    maxAge: '2h', // Cache for 2 hours
    setHeaders: (res, path) => {
        // Set CORS headers for audio files
        res.setHeader('Access-Control-Allow-Origin', '*');
        res.setHeader('Access-Control-Allow-Methods', 'GET');
        res.setHeader('Content-Type', 'audio/mpeg');
    }
}));

// Cleanup job - run every hour
setInterval(() => {
    audioMixer.cleanupTempFiles();
}, 60 * 60 * 1000); // 1 hour

// Cleanup on app termination
process.on('SIGINT', async () => {
    console.log('Cleaning up temporary files...');
    await audioMixer.cleanupTempFiles();
    process.exit(0);
});

process.on('SIGTERM', async () => {
    console.log('Cleaning up temporary files...');
    await audioMixer.cleanupTempFiles();
    process.exit(0);
});
```

### 5. Environment Variables

Add to your `.env` file in TTS backend:

```bash
BASE_URL=https://meditative-brains.com:3001
TEMP_CLEANUP_INTERVAL=3600000
MAX_PREVIEW_DURATION=60
DEFAULT_PREVIEW_DURATION=30
BACKGROUND_MUSIC_VOLUME=0.2
TTS_AUDIO_VOLUME=1.0
```

### 6. Background Music Storage

Create a `background-music` directory and add sample background music files:

```bash
mkdir background-music
# Add your background music files here
# background-music/confidence-bg.mp3
# background-music/success-bg.mp3
# background-music/health-bg.mp3
# etc.
```

## 🚀 Deployment Instructions

1. **Update TTS Backend:**
   ```bash
   cd /path/to/tts-backend
   npm install ffmpeg-static fluent-ffmpeg node-cleanup uuid
   # Add the new services and endpoints
   pm2 restart tts-backend
   ```

2. **Test Preview Generation:**
   ```bash
   curl -X POST https://meditative-brains.com:3001/api/generate-preview \
     -H "Content-Type: application/json" \
     -d '{
       "text": "I am confident and capable",
       "voice": "default",
       "speed": 1.0,
       "language": "en",
       "preview_duration": 30,
       "background_music_url": "https://meditative-brains.com:3001/background-music/confidence-bg.mp3"
     }'
   ```

3. **Monitor Logs:**
   ```bash
   pm2 logs tts-backend
   ```

## ✅ Success Criteria

- [ ] Preview generation completes in < 10 seconds
- [ ] Background music mixing works correctly
- [ ] Temporary files are cleaned up automatically
- [ ] Audio quality is maintained during mixing
- [ ] CORS headers allow access from Laravel backend

## 🔧 Troubleshooting

**FFmpeg not found:**
```bash
npm install ffmpeg-static
# Or install system-wide: apt-get install ffmpeg
```

**Permission errors:**
```bash
chmod +x node_modules/ffmpeg-static/ffmpeg
mkdir -p temp && chmod 755 temp
```

**Memory issues:**
```bash
# Increase Node.js memory limit
node --max-old-space-size=4096 app.js
```

This completes the TTS backend enhancement for Phase 2!
