#!/bin/bash
cd /var/www/clients/client1/web71/web/tts-backend

# Run the app (systemd will handle user/group)
/usr/bin/node app.js > app.log 2>&1
