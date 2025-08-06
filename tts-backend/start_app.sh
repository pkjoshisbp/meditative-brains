#!/bin/bash
# /var/www/clients/client1/web51/web/start_app.sh

# Navigate to the app directory
cd /var/www/clients/client1/web51/web

# Run the app with the correct user and group
sudo -u web51 -g client1 /usr/bin/node app.js > app.log 2>&1
