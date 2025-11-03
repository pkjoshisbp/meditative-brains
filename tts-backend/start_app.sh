#!/bin/bash
# /var/www/clients/client1/web63/web/tts-backend/start_app.sh

# Navigate to the app directory
cd /var/www/clients/client1/web63/web/tts-backend

# Activate the Python virtual environment for TTS
source tts-venv/bin/activate

# Set environment variables for TTS library to use writable directories
export MPLCONFIGDIR=/var/www/clients/client1/web63/web/tts-backend/tmp/matplotlib
export XDG_CACHE_HOME=/var/www/clients/client1/web63/web/tts-backend/tmp/cache
export HOME=/var/www/clients/client1/web63/web/tts-backend
export COQUI_TTS_CACHE_DIR=/var/www/clients/client1/web63/web/tts-backend/home/mywebmotivation/.local/share/tts

# Create the directories if they don't exist
mkdir -p /var/www/clients/client1/web63/web/tts-backend/tmp/matplotlib
mkdir -p /var/www/clients/client1/web63/web/tts-backend/tmp/cache

# Run the app (systemd will handle user/group)
/usr/bin/node app.js > app.log 2>&1
