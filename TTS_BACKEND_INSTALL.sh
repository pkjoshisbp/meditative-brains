#!/bin/bash

# TTS Backend Enhancement Installation Script
# Run this on your TTS backend server (meditative-brains.com:3001)

echo "🎵 Installing TTS Backend Enhancements..."

# Navigate to TTS backend directory
# cd /path/to/your/tts-backend

# Install required dependencies
echo "📦 Installing dependencies..."
npm install ffmpeg-static fluent-ffmpeg node-cleanup uuid express-rate-limit multer

# Create directories
echo "📁 Creating directories..."
mkdir -p services
mkdir -p temp
mkdir -p background-music
mkdir -p public/temp-audio

# Set permissions
chmod 755 temp
chmod 755 background-music
chmod 755 public/temp-audio

echo "✅ Dependencies installed successfully!"
echo ""
echo "Next steps:"
echo "1. Copy the service files to your TTS backend"
echo "2. Update your main app.js file"
echo "3. Add background music files"
echo "4. Restart your TTS backend service"
echo ""
echo "Files to create:"
echo "- services/audioMixer.js"
echo "- services/previewGenerator.js"
echo "- services/ttsService.js (enhance existing)"
echo "- Update main app.js"
