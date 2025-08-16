#!/bin/bash

# TTS Backend Startup Script
echo "🚀 Starting TTS Backend..."

# Check if we're in the right directory
if [ ! -f "server.js" ]; then
    echo "❌ Error: server.js not found. Please run this script from the tts-backend directory."
    exit 1
fi

# Check if node_modules exists
if [ ! -d "node_modules" ]; then
    echo "📦 Installing dependencies..."
    npm install
fi

# Check if .env file exists
if [ ! -f ".env" ]; then
    echo "⚙️ Creating .env file..."
    cat > .env << EOL
# MongoDB Connection
MONGODB_URI=mongodb://localhost:27017/tts_backend

# Azure TTS Configuration
AZURE_KEY=your_azure_key_here
AZURE_REGION=your_azure_region_here

# Server Configuration
PORT=3001
NODE_ENV=development

# Logging
LOG_LEVEL=info
EOL
    echo "📝 .env file created. Please update with your actual Azure TTS credentials."
fi

# Check if MongoDB is running
echo "🔍 Checking MongoDB connection..."
if ! mongosh --eval "db.runCommand('ping')" > /dev/null 2>&1; then
    echo "⚠️ Warning: MongoDB doesn't seem to be running. Please start MongoDB first."
    echo "   Ubuntu/Debian: sudo systemctl start mongod"
    echo "   macOS: brew services start mongodb-community"
    echo "   Docker: docker run -d -p 27017:27017 mongo"
fi

# Create audio directories
echo "📁 Creating audio directories..."
mkdir -p audio-cache
mkdir -p temp-texts

# Start the server
echo "🌐 Starting TTS Backend Server..."
node server.js
