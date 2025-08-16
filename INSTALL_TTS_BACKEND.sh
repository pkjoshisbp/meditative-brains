#!/bin/bash

echo "🎵 Installing TTS Backend with Audio Mixing..."

# Create necessary directories
echo "📁 Creating directories..."
mkdir -p /var/www/clients/client1/web63/web/tts-backend
mkdir -p /var/www/clients/client1/web63/web/tts-backend/public/temp-audio
mkdir -p /var/www/clients/client1/web63/web/tts-backend/background-music
mkdir -p /var/www/clients/client1/web63/web/tts-backend/temp
mkdir -p /var/www/clients/client1/web63/web/tts-backend/services

# Copy files from TTS_BACKEND_FILES to the actual backend directory
echo "📋 Copying TTS backend files..."
cp -r /var/www/clients/client1/web63/web/TTS_BACKEND_FILES/* /var/www/clients/client1/web63/web/tts-backend/

# Navigate to backend directory
cd /var/www/clients/client1/web63/web/tts-backend

# Initialize npm if package.json doesn't exist
if [ ! -f "package.json" ]; then
    echo "📦 Initializing npm..."
    npm init -y
fi

# Install production dependencies
echo "📦 Installing dependencies..."
npm install express@^4.18.2
npm install cors@^2.8.5
npm install dotenv@^16.3.1
npm install openai@^4.20.1
npm install fluent-ffmpeg@^2.1.2
npm install ffmpeg-static@^5.2.0
npm install uuid@^9.0.1

# Install development dependencies
echo "🔧 Installing dev dependencies..."
npm install --save-dev nodemon@^3.0.1

# Set up environment file
echo "🔐 Setting up environment..."
if [ ! -f ".env" ]; then
    cp .env.example .env
    echo "⚠️  Please edit .env file and add your OpenAI API key"
fi

# Set proper permissions
echo "🔒 Setting permissions..."
chmod +x app.js
chmod -R 755 public/
chmod -R 755 temp/
chmod -R 755 background-music/

# Create systemd service file
echo "🚀 Creating systemd service..."
cat > /etc/systemd/system/tts-backend.service << EOF
[Unit]
Description=TTS Audio Backend Service
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/clients/client1/web63/web/tts-backend
ExecStart=/usr/bin/node app.js
Restart=always
RestartSec=10
Environment=NODE_ENV=production

[Install]
WantedBy=multi-user.target
EOF

# Reload systemd and enable service
echo "🔄 Configuring service..."
systemctl daemon-reload
systemctl enable tts-backend.service

# Test npm installation
echo "🧪 Testing installation..."
if npm list --depth=0 | grep -q "express"; then
    echo "✅ Dependencies installed successfully"
else
    echo "❌ Dependency installation failed"
    exit 1
fi

# Create sample background music directory structure
echo "🎶 Setting up background music..."
cat > background-music/README.md << EOF
# Background Music Directory

Place your background music files here in the following formats:
- MP3 (.mp3)
- WAV (.wav)
- M4A (.m4a)
- FLAC (.flac)

Example files:
- ambient-calm.mp3
- nature-sounds.wav
- meditation-bells.mp3
- ocean-waves.wav

These files will be available for mixing with TTS audio.
EOF

# Create a simple test script
echo "📝 Creating test script..."
cat > test-backend.js << EOF
const axios = require('axios');

async function testBackend() {
    try {
        console.log('Testing TTS Backend...');
        
        // Test health endpoint
        const health = await axios.get('http://localhost:3001/health');
        console.log('✅ Health check:', health.data);
        
        // Test voices endpoint
        const voices = await axios.get('http://localhost:3001/api/voices');
        console.log('✅ Voices available:', voices.data.voices.length);
        
        console.log('🎉 Backend is working properly!');
    } catch (error) {
        console.error('❌ Backend test failed:', error.message);
    }
}

testBackend();
EOF

echo "🎉 TTS Backend installation complete!"
echo ""
echo "📋 Next steps:"
echo "1. Edit .env file and add your OpenAI API key"
echo "2. Start the service: systemctl start tts-backend"
echo "3. Check status: systemctl status tts-backend"
echo "4. Test the backend: node test-backend.js"
echo "5. Add background music files to background-music/ directory"
echo ""
echo "🌐 Backend will be available at: http://localhost:3001"
echo "📖 API Documentation: http://localhost:3001/api/voices"
