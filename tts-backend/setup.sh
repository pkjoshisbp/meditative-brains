#!/bin/bash
# Setup script for TTS backend

echo "🚀 Setting up TTS Backend Environment..."

# Create required directories
echo "📁 Creating required directories..."
mkdir -p /var/www/clients/client1/web63/web/tts-backend/tmp/matplotlib
mkdir -p /var/www/clients/client1/web63/web/tts-backend/tmp/cache
mkdir -p /var/www/clients/client1/web63/web/tts-backend/audio-cache
mkdir -p /var/www/clients/client1/web63/web/tts-backend/temp-texts
mkdir -p /var/www/clients/client1/web63/web/tts-backend/logs
mkdir -p /var/www/clients/client1/web63/web/tts-backend/home/mywebmotivation/.local/share/tts

# Set proper permissions
echo "🔐 Setting permissions..."
chown -R web63:client1 /var/www/clients/client1/web63/web/tts-backend/
chmod +x /var/www/clients/client1/web63/web/tts-backend/start_app.sh

# Install Node.js dependencies if package.json exists
if [ -f "package.json" ]; then
    echo "📦 Installing Node.js dependencies..."
    npm install
fi

# Check if SSL certificates exist
if [ -f "/var/www/meditative-brains.com/ssl/meditative-brains.com-le.crt" ]; then
    echo "✅ SSL Certificate found: meditative-brains.com-le.crt"
else
    echo "❌ SSL Certificate not found at: /var/www/meditative-brains.com/ssl/meditative-brains.com-le.crt"
fi

if [ -f "/var/www/meditative-brains.com/ssl/meditative-brains.com-le.key" ]; then
    echo "✅ SSL Key found: meditative-brains.com-le.key"
else
    echo "❌ SSL Key not found at: /var/www/meditative-brains.com/ssl/meditative-brains.com-le.key"
fi

# Test MongoDB connection
echo "🗄️  Testing MongoDB connection..."
mongo --eval "db.adminCommand('ping')" mongodb://pawan:pragati123..@127.0.0.1:27017/motivation || echo "❌ MongoDB connection failed"

echo "✅ Setup complete!"
echo ""
echo "🎯 Next steps:"
echo "1. Run: cd /var/www/clients/client1/web63/web/tts-backend"
echo "2. Test the app: node app.js"
echo "3. Or start with: ./start_app.sh"
echo ""
echo "🌐 The app will run on: https://meditative-brains.com:3000"
