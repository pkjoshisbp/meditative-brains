#!/bin/bash
# Systemd service startup script for TTS Backend

# Set working directory
cd /var/www/clients/client1/web63/web/tts-backend

# Activate Python virtual environment
source tts-venv/bin/activate

# Set environment variables for TTS library
export MPLCONFIGDIR=/var/www/clients/client1/web63/web/tts-backend/tmp/matplotlib
export XDG_CACHE_HOME=/var/www/clients/client1/web63/web/tts-backend/tmp/cache
export HOME=/var/www/clients/client1/web63/web/tts-backend
export COQUI_TTS_CACHE_DIR=/var/www/clients/client1/web63/web/tts-backend/home/mywebmotivation/.local/share/tts

# Create required directories
mkdir -p /var/www/clients/client1/web63/web/tts-backend/tmp/matplotlib
mkdir -p /var/www/clients/client1/web63/web/tts-backend/tmp/cache

# Start the Node.js application
exec /usr/bin/node app.js
