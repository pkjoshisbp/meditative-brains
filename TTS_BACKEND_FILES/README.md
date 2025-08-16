# TTS Audio Backend

A Node.js backend service for generating TTS (Text-to-Speech) audio with background music mixing capabilities.

## Features

- 🎵 Text-to-Speech generation using OpenAI's API
- 🎶 Background music mixing with FFmpeg
- ⚡ Preview generation with customizable duration
- 🔊 Multiple voice options (alloy, echo, fable, onyx, nova, shimmer)
- 📦 Bulk preview generation
- 🧹 Automatic temporary file cleanup
- 🔄 Audio format conversion and optimization

## Installation

1. Install dependencies:
```bash
npm install
```

2. Set up environment variables:
```bash
cp .env.example .env
# Edit .env with your OpenAI API key
```

3. Create required directories:
```bash
mkdir -p public/temp-audio
mkdir -p background-music
mkdir -p temp
```

4. Start the server:
```bash
npm start
```

For development with auto-reload:
```bash
npm run dev
```

## API Endpoints

### Health Check
- `GET /health` - Server health status

### Service Status
- `GET /api/status` - Detailed service status including OpenAI connection

### Voice Information
- `GET /api/voices` - Get available voices and models

### Audio Generation
- `POST /api/generate-preview` - Generate TTS preview with optional background music
- `POST /api/generate-full` - Generate full TTS audio
- `POST /api/generate-bulk-previews` - Generate multiple previews in batch

### Utility
- `POST /api/cleanup` - Clean up temporary files
- `POST /api/audio-info` - Get audio file information

## Request Examples

### Generate Preview
```json
{
  "text": "Hello, this is a test message for TTS generation.",
  "voice": "alloy",
  "model": "tts-1",
  "backgroundMusicUrl": "https://example.com/music.mp3",
  "previewDuration": 30,
  "speed": 1.0
}
```

### Bulk Preview Generation
```json
{
  "requests": [
    {
      "text": "First message",
      "voice": "alloy",
      "backgroundMusicUrl": "https://example.com/music1.mp3"
    },
    {
      "text": "Second message",
      "voice": "nova",
      "backgroundMusicUrl": "https://example.com/music2.mp3"
    }
  ]
}
```

## Background Music

Place background music files in the `background-music/` directory. Supported formats:
- MP3
- WAV
- M4A
- FLAC

Background music will be:
- Mixed at 20% volume
- Faded in/out automatically
- Trimmed to match preview duration

## Environment Variables

- `OPENAI_API_KEY` - Your OpenAI API key (required)
- `PORT` - Server port (default: 3001)
- `NODE_ENV` - Environment (development/production)

## File Structure

```
TTS_BACKEND_FILES/
├── app.js                    # Main server file
├── package.json              # Dependencies
├── .env.example             # Environment template
├── services/
│   ├── audioMixer.js        # Audio mixing service
│   └── previewGenerator.js  # TTS generation service
├── public/
│   └── temp-audio/          # Temporary audio files
├── background-music/        # Background music files
└── temp/                    # TTS generation temp files
```

## Dependencies

- **express** - Web framework
- **openai** - OpenAI API client
- **fluent-ffmpeg** - Audio processing
- **ffmpeg-static** - FFmpeg binary
- **uuid** - Unique ID generation
- **cors** - Cross-origin requests
- **dotenv** - Environment variables

## Audio Processing

The service uses FFmpeg for:
- Audio format conversion
- Background music mixing
- Audio trimming and duration control
- Volume adjustment and fading
- Quality optimization

## Cleanup

Temporary files are automatically cleaned up:
- Every hour via scheduled task
- Files older than 2 hours are removed
- Manual cleanup via `/api/cleanup` endpoint

## Error Handling

The service includes comprehensive error handling for:
- Invalid input validation
- OpenAI API errors
- Audio processing failures
- File system operations
- Network issues

## Production Deployment

1. Set `NODE_ENV=production` in .env
2. Ensure OpenAI API key is set
3. Configure reverse proxy (nginx/apache)
4. Set up process manager (PM2)
5. Configure firewall for port 3001

## License

MIT License - see LICENSE file for details.
