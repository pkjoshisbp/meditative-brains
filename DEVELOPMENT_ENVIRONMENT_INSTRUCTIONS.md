# Meditative Brains - Development Environment Instructions

**Last Updated:** November 1, 2025  
**Project:** Meditative Brains TTS Application  
**Repository:** pkjoshisbp/meditative-brains

---

## 📋 Table of Contents
1. [System Overview](#system-overview)
2. [Server Architecture](#server-architecture)
3. [Environment Access](#environment-access)
4. [Technology Stack](#technology-stack)
5. [Directory Structure](#directory-structure)
6. [Laravel Backend Setup](#laravel-backend-setup)
7. [TTS Backend (Node.js) Setup](#tts-backend-nodejs-setup)
8. [Flutter Mobile App](#flutter-mobile-app)
9. [Database Configuration](#database-configuration)
10. [Common Commands](#common-commands)
11. [Troubleshooting](#troubleshooting)
12. [Deployment Workflow](#deployment-workflow)

---

## 🖥️ System Overview

**Production Environment:**
- **Domain:** meditative-brains.com
- **Server Location:** Linux production server
- **Web Root:** `/var/www/clients/client1/web63/web`
- **PHP Version:** 8.1.33
- **Laravel Version:** 10.x
- **Node.js Version:** 18.x (for TTS backend)

**Development Environments:**
- Windows Desktop (previous development)
- MacBook (current development)

---

## 🏗️ Server Architecture

### 1. Laravel Backend (Port 443/80)
- **URL:** https://meditative-brains.com
- **Purpose:** Main web application, API endpoints, admin panel
- **Framework:** Laravel 10.x with Livewire
- **Auth:** Laravel Sanctum for API authentication

### 2. TTS Backend (Port 3001)
- **URL:** https://meditative-brains.com:3001
- **Purpose:** Text-to-Speech processing, audio generation, SSML support
- **Framework:** Node.js/Express
- **Process Manager:** PM2

### 3. Database
- **Type:** MySQL
- **Configuration:** See `config/database.php`

---

## 🔐 Environment Access

### SSH Access
```bash
ssh username@meditative-brains.com
cd /var/www/clients/client1/web63/web
```

### File Permissions
```bash
# Web root permissions
chown -R www-data:www-data /var/www/clients/client1/web63/web
chmod -R 755 /var/www/clients/client1/web63/web

# Laravel storage permissions
chmod -R 775 storage bootstrap/cache
```

---

## 🛠️ Technology Stack

### Backend (Laravel)
- **Framework:** Laravel 10.x
- **PHP:** 8.1.33
- **Auth:** Laravel Sanctum
- **Frontend:** Livewire + Bootstrap
- **Admin Panel:** Laravel AdminLTE
- **Queue:** Laravel Queue for background jobs

### TTS Backend (Node.js)
- **Runtime:** Node.js 18.x
- **Framework:** Express.js
- **Audio Processing:** FFmpeg, fluent-ffmpeg
- **TTS Engines:** 
  - Azure TTS (primary)
  - Coqui VITS (alternative)
  - SSML support for advanced speech control

### Mobile App (Flutter)
- **Framework:** Flutter 3.x
- **Language:** Dart
- **State Management:** Provider/Riverpod
- **Audio Player:** just_audio package
- **Platforms:** Android, iOS

### Database
- **Primary:** MySQL 8.x
- **Caching:** Redis (optional)

---

## 📁 Directory Structure

```
/var/www/clients/client1/web63/web/
├── app/                          # Laravel application code
│   ├── Console/                  # Artisan commands
│   ├── Events/                   # Event classes
│   ├── Exceptions/               # Exception handling
│   ├── Http/
│   │   ├── Controllers/          # HTTP controllers
│   │   ├── Middleware/           # HTTP middleware
│   │   └── Requests/             # Form requests
│   ├── Livewire/                 # Livewire components
│   ├── Mail/                     # Email classes
│   ├── Models/                   # Eloquent models
│   ├── Providers/                # Service providers
│   └── Services/                 # Business logic services
├── bootstrap/                    # Laravel bootstrap files
├── config/                       # Configuration files
│   ├── app.php                   # Main app config
│   ├── database.php              # Database config
│   ├── azure-voices.json         # Azure TTS voices
│   └── ...
├── database/
│   ├── migrations/               # Database migrations
│   ├── seeders/                  # Database seeders
│   └── factories/                # Model factories
├── flutter/                      # Flutter mobile app
│   ├── lib/                      # Dart source code
│   ├── android/                  # Android specific files
│   ├── ios/                      # iOS specific files
│   └── assets/                   # App assets
├── public/                       # Web accessible files
│   ├── index.php                 # Entry point
│   ├── audio/                    # Generated audio files
│   └── bg-music/                 # Background music files
├── resources/
│   ├── views/                    # Blade templates
│   ├── js/                       # JavaScript files
│   └── css/                      # CSS files
├── routes/
│   ├── web.php                   # Web routes
│   ├── api.php                   # API routes
│   └── admin.php                 # Admin routes
├── storage/
│   ├── app/                      # Application storage
│   ├── logs/                     # Log files
│   └── framework/                # Framework cache
├── tts-backend/                  # Node.js TTS backend
│   ├── services/                 # TTS services
│   ├── temp/                     # Temporary audio files
│   └── background-music/         # Background music
├── tests/                        # PHPUnit tests
└── vendor/                       # Composer dependencies
```

---

## 🚀 Laravel Backend Setup

### Initial Setup
```bash
# Navigate to project directory
cd /var/www/clients/client1/web63/web

# Install PHP dependencies
composer install

# Copy environment file (if not exists)
cp .env.example .env

# Generate application key
php artisan key:generate

# Run migrations
php artisan migrate

# Seed database (if needed)
php artisan db:seed

# Create symbolic link for storage
php artisan storage:link

# Clear caches
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Optimize for production
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Environment Variables (.env)
```env
APP_NAME="Meditative Brains"
APP_ENV=production
APP_KEY=base64:...
APP_DEBUG=false
APP_URL=https://meditative-brains.com

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database_name
DB_USERNAME=your_database_user
DB_PASSWORD=your_database_password

# TTS Backend
TTS_BACKEND_URL=https://meditative-brains.com:3001

# Azure TTS (if used)
AZURE_SPEECH_KEY=your_azure_key
AZURE_SPEECH_REGION=your_region

# Mail Configuration
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=null
MAIL_PASSWORD=null

# Queue Configuration
QUEUE_CONNECTION=database
```

### File Permissions
```bash
# Set proper ownership
sudo chown -R www-data:www-data /var/www/clients/client1/web63/web

# Set directory permissions
sudo find /var/www/clients/client1/web63/web -type d -exec chmod 755 {} \;
sudo find /var/www/clients/client1/web63/web -type f -exec chmod 644 {} \;

# Set storage and cache permissions
sudo chmod -R 775 /var/www/clients/client1/web63/web/storage
sudo chmod -R 775 /var/www/clients/client1/web63/web/bootstrap/cache
```

---

## 🎙️ TTS Backend (Node.js) Setup

### Location
```bash
cd /var/www/clients/client1/web63/web/tts-backend
```

### Installation
```bash
# Install Node.js dependencies
npm install

# Install additional packages for audio processing
npm install ffmpeg-static fluent-ffmpeg node-cleanup uuid

# Install PM2 globally (if not installed)
npm install -g pm2
```

### Environment Variables (TTS Backend .env)
```env
PORT=3001
BASE_URL=https://meditative-brains.com:3001
TEMP_CLEANUP_INTERVAL=3600000
MAX_PREVIEW_DURATION=60
DEFAULT_PREVIEW_DURATION=30
BACKGROUND_MUSIC_VOLUME=0.2
TTS_AUDIO_VOLUME=1.0

# Azure TTS
AZURE_SPEECH_KEY=your_azure_key
AZURE_SPEECH_REGION=your_region

# Coqui VITS (if used)
VITS_API_URL=http://localhost:5000
```

### Running TTS Backend

#### Development Mode
```bash
npm run dev
# or
node app.js
```

#### Production Mode (with PM2)
```bash
# Start the application
pm2 start app.js --name tts-backend

# Save PM2 configuration
pm2 save

# Setup PM2 to start on boot
pm2 startup

# View logs
pm2 logs tts-backend

# Restart application
pm2 restart tts-backend

# Stop application
pm2 stop tts-backend

# Monitor all processes
pm2 monit
```

### Testing TTS Backend
```bash
# Test health endpoint
curl https://meditative-brains.com:3001/health

# Test preview generation
curl -X POST https://meditative-brains.com:3001/api/generate-preview \
  -H "Content-Type: application/json" \
  -d '{
    "text": "I am confident and capable",
    "voice": "default",
    "speed": 1.0,
    "language": "en",
    "preview_duration": 30
  }'
```

---

## 📱 Flutter Mobile App

### Location
```bash
cd /var/www/clients/client1/web63/web/flutter
```

### Setup
```bash
# Get Flutter packages
flutter pub get

# Run code generation (if using freezed/json_serializable)
flutter pub run build_runner build --delete-conflicting-outputs

# Check for issues
flutter doctor
```

### Build Commands

#### Android
```bash
# Debug APK
flutter build apk --debug

# Release APK
flutter build apk --release

# App Bundle for Play Store
flutter build appbundle --release
```

#### iOS
```bash
# Debug build
flutter build ios --debug

# Release build
flutter build ios --release
```

### Running the App
```bash
# Run on connected device
flutter run

# Run on specific device
flutter devices
flutter run -d device_id

# Hot reload: Press 'r' in terminal
# Hot restart: Press 'R' in terminal
```

---

## 🗄️ Database Configuration

### Access MySQL
```bash
# Login to MySQL
mysql -u your_username -p

# Select database
USE your_database_name;

# Show tables
SHOW TABLES;
```

### Common Migrations
```bash
# Run all pending migrations
php artisan migrate

# Rollback last migration
php artisan migrate:rollback

# Rollback all migrations and re-run
php artisan migrate:fresh

# Run with seeding
php artisan migrate:fresh --seed
```

### Database Backup
```bash
# Backup database
mysqldump -u username -p database_name > backup_$(date +%Y%m%d).sql

# Restore database
mysql -u username -p database_name < backup_20251101.sql
```

---

## ⚡ Common Commands

### Laravel Artisan
```bash
# Clear all caches
php artisan optimize:clear

# Create controller
php artisan make:controller ControllerName

# Create model with migration
php artisan make:model ModelName -m

# Create Livewire component
php artisan make:livewire ComponentName

# Run queue worker
php artisan queue:work

# List all routes
php artisan route:list

# Tinker (Laravel REPL)
php artisan tinker
```

### Composer
```bash
# Install dependencies
composer install

# Update dependencies
composer update

# Require new package
composer require vendor/package

# Dump autoload
composer dump-autoload
```

### Git
```bash
# Check status
git status

# Pull latest changes
git pull origin main

# Push changes
git add .
git commit -m "Description of changes"
git push origin main

# Create new branch
git checkout -b feature-name

# View logs
git log --oneline
```

---

## 🐛 Troubleshooting

### Laravel Issues

#### "Undefined variable $slot" Error
**Problem:** Layout file using component syntax ({{ $slot }}) with traditional Blade inheritance (@extends)

**Solution:**
```php
// In resources/views/layouts/app.blade.php
// Change: {{ $slot }}
// To: @yield('content')
```

#### Permission Denied Errors
```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

#### 500 Internal Server Error
```bash
# Check error logs
tail -f storage/logs/laravel.log

# Clear caches
php artisan config:clear
php artisan cache:clear
php artisan view:clear
```

#### Database Connection Issues
```bash
# Verify database credentials in .env
# Test connection
php artisan tinker
>>> DB::connection()->getPdo();
```

### TTS Backend Issues

#### Port Already in Use
```bash
# Find process using port 3001
lsof -i :3001

# Kill process
kill -9 <PID>

# Or restart PM2
pm2 restart tts-backend
```

#### FFmpeg Not Found
```bash
# Install FFmpeg
sudo apt-get update
sudo apt-get install ffmpeg

# Or use npm package
npm install ffmpeg-static
```

#### Memory Issues
```bash
# Increase Node.js memory
node --max-old-space-size=4096 app.js

# Or in PM2
pm2 start app.js --name tts-backend --max-memory-restart 1G
```

### Flutter Issues

#### Flutter Doctor Issues
```bash
flutter doctor --verbose
```

#### Build Failures
```bash
# Clean build
flutter clean
flutter pub get
flutter build apk
```

---

## 🚢 Deployment Workflow

### 1. Pull Latest Changes
```bash
cd /var/www/clients/client1/web63/web
git pull origin main
```

### 2. Update Dependencies
```bash
# PHP dependencies
composer install --optimize-autoloader --no-dev

# Node.js dependencies (if TTS backend changed)
cd tts-backend
npm install --production
cd ..
```

### 3. Run Migrations
```bash
php artisan migrate --force
```

### 4. Clear & Cache
```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 5. Restart Services
```bash
# Restart TTS backend
pm2 restart tts-backend

# Restart PHP-FPM (if needed)
sudo systemctl restart php8.1-fpm

# Restart web server (if needed)
sudo systemctl restart nginx
# or
sudo systemctl restart apache2
```

### 6. Verify Deployment
```bash
# Check Laravel
curl https://meditative-brains.com

# Check TTS backend
curl https://meditative-brains.com:3001/health

# Check logs
tail -f storage/logs/laravel.log
pm2 logs tts-backend
```

---

## 📚 Important Documentation Files

- `README.md` - General project overview
- `PHASE_2_TTS_BACKEND_INSTRUCTIONS.md` - TTS backend enhancement guide
- `IMPLEMENTATION_GUIDE_TTS_AUDIO_PRODUCTS.md` - TTS products implementation
- `AUDIO_PREVIEW_SYSTEM.md` - Audio preview system documentation
- `FLUTTER_API_DOCUMENTATION.md` - Flutter API integration guide
- `flutter/README.md` - Flutter app specific documentation
- `flutter/EQUALIZER_GUIDE.md` - Audio equalizer implementation
- `flutter/SCHEDULING_FIX_IMPLEMENTED.md` - Scheduling feature documentation

---

## 🔗 Useful Links

- **Laravel Documentation:** https://laravel.com/docs/10.x
- **Livewire Documentation:** https://livewire.laravel.com
- **Flutter Documentation:** https://flutter.dev/docs
- **Node.js Documentation:** https://nodejs.org/docs
- **PM2 Documentation:** https://pm2.keymetrics.io

---

## 📞 Support

For issues or questions:
1. Check the logs: `storage/logs/laravel.log` and `pm2 logs tts-backend`
2. Review relevant documentation files
3. Test endpoints individually to isolate issues
4. Verify environment variables in `.env` files

---

**Note:** This document should be updated as the project evolves. Keep it in sync with actual deployment procedures and configurations.
