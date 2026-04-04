# Quick Reference Guide - Meditative Brains

**For:** Cross-platform development (Windows Desktop → MacBook)  
**Last Updated:** November 10, 2025

---

## ✅ LATEST: Offline Mode Implementation (Nov 10, 2025)

**Status:** Complete - True offline mode now working!

### Your Question:
> "Can I assume that if I have played 5 different categories of messages - and then I go offline and close the app, restart app and then play - will it still play the cached music which I played while online?"

### Answer: ✅ YES! Absolutely!

**What Works:**
1. Play any category online → Automatically cached
2. Close app completely
3. Turn on airplane mode
4. Restart app
5. Play same category → Works offline!
6. Play all 5 categories → All work offline!

**Audio Formats:**
- ✅ Both `.mp3` and `.aac` supported
- ✅ Automatically detected from file path/URL

**What Changed:**
- Message metadata now persisted to SharedPreferences
- Offline-first architecture with automatic API fallback
- Shows orange notification: "📱 Offline Mode: Playing X cached messages"

**Files Modified:**
- `lib/services/audio_cache_service.dart` - AAC/MP3 auto-detection
- `lib/models/message.dart` - Added toJson() serialization
- `lib/screens/home_screen.dart` - Offline fallback logic
- `lib/services/offline_message_service.dart` - NEW persistence service

**Documentation:**
- `flutter/OFFLINE_MODE_EXPLANATION.md` - User guide
- `flutter/OFFLINE_MODE_IMPLEMENTATION.md` - Technical details

---

## TTS Messages - Wrong Speaker Being Saved (Nov 7, 2025)

**Issue**: When saving new TTS messages with a selected speaker (e.g., "Ada Multilingual"), the system was saving a different speaker instead (e.g., "Ava Multilingual" / AriaNeural).

**Root Cause**: The backend `/tts-backend/routes/motivationMessage.js` had hardcoded logic that was overriding the speaker selection for certain languages:
```javascript
// OLD BAD CODE:
selectedSpeaker = ['en-IN', 'en-US', 'hi-IN', 'hn-IN'].includes(formattedLanguage) 
  ? 'en-US-AvaMultilingualNeural'  // <-- FORCED speaker for these languages!
  : (speaker || 'en-US-AriaNeural');
```

**Solution Applied**:

1. **Backend Fix** (`/tts-backend/routes/motivationMessage.js`):
   - Removed hardcoded speaker override logic
   - Now respects the speaker value sent from frontend
   - Added detailed logging to track speaker selection
   
   ```javascript
   // NEW CORRECT CODE:
   if (engine === 'vits') {
     selectedSpeaker = speaker || 'p225';
   } else {
     selectedSpeaker = speaker || 'en-US-AriaNeural'; // Only fallback if no speaker sent
   }
   ```

2. **Frontend Defaults** (`app/Livewire/Admin/MotivationMessageForm.php`):
   - Changed default language from `en-US` to `en-IN`
   - Changed default speaker from `en-US-AriaNeural` (Aria) to `en-GB-AdaMultilingualNeural` (Ada)
   - Updated initialization to prefer Ada Multilingual when available

3. **Enhanced Logging**:
   - Frontend logs speaker value being sent in payload
   - Backend logs speaker received and final speaker selected
   - Logs include speakerStyle and speakerPersonality for debugging

**Testing**:
```bash
# After restart, check logs when saving:
tail -f /var/www/clients/client1/web63/web/storage/logs/laravel.log | grep speaker
tail -f /var/www/clients/client1/web63/web/tts-backend/logs/app.log | grep speaker
```

**Files Modified**:
1. `/var/www/clients/client1/web63/web/tts-backend/routes/motivationMessage.js` (lines 38-47, 65-74)
2. `/var/www/clients/client1/web63/web/app/Livewire/Admin/MotivationMessageForm.php` (lines 16-17, 189-213, 419-427)

**Backend Restart Required**: Yes (kill -HUP 884 or restart app.js)

---

## Background Music Not Playing

**Issue**: Background music files placed in `tts-backend/bg-music/` folder were not playing in the Flutter app.

**Root Causes**:
1. **Incorrect Filename Slug**: Flutter app uses a specific slug generation algorithm that replaces ` - ` (space-dash-space) with `---` (three dashes)
2. **Wrong Directory**: Background music for Flutter app must be in `/public/bg-music/` (web-accessible), not `/tts-backend/bg-music/` (server-side mixing only)
3. **Missing MP3 Format**: Flutter app tries MP3 first, then AAC, then M4A

**Slug Generation Formula** (from `lib/services/background_music_service.dart`):
```dart
final slug = categoryName
    .toLowerCase()
    .replaceAll('&', 'and')
    .replaceAll(RegExp(r'\s+'), '-');
```

**Example**:
- Category Name: `"Quit Smoking - Respiratory Healing & Oxygenation"`
- Step 1 (lowercase): `"quit smoking - respiratory healing & oxygenation"`
- Step 2 (replace &): `"quit smoking - respiratory healing and oxygenation"`
- Step 3 (replace spaces): `"quit-smoking---respiratory-healing-and-oxygenation"`
- **Critical**: ` - ` becomes `---` (space→dash, original dash stays, space→dash)

**Flutter App Download Logic**:
The app tries to download in this order:
1. `https://meditative-brains.com/bg-music/{slug}.mp3` (preferred)
2. `https://meditative-brains.com/bg-music/{slug}.aac`
3. `https://meditative-brains.com/bg-music/{slug}.m4a`

**Solution**:
1. **Calculate correct filename**: Use the slug formula above
2. **Convert to MP3**: Flutter prefers MP3 format
3. **Place in correct directory**: `/var/www/clients/client1/web63/web/public/bg-music/`
4. **Verify accessibility**: Test with curl

**Complete Commands**:
```bash
# 1. Navigate to public bg-music directory
cd /var/www/clients/client1/web63/web/public/bg-music/

# 2. If you have an AAC file, rename it with correct slug
mv "quit-smoking-respiratory-healing-and-oxygenation.aac" "quit-smoking---respiratory-healing-and-oxygenation.aac"

# 3. Convert to MP3 (Flutter's preferred format)
ffmpeg -i quit-smoking---respiratory-healing-and-oxygenation.aac \
       -c:a libmp3lame -b:a 192k \
       quit-smoking---respiratory-healing-and-oxygenation.mp3 -y

# 4. Verify both formats are accessible
curl -I "https://meditative-brains.com/bg-music/quit-smoking---respiratory-healing-and-oxygenation.mp3"
curl -I "https://meditative-brains.com/bg-music/quit-smoking---respiratory-healing-and-oxygenation.aac"

# Both should return: HTTP/2 200
```

**Directory Structure**:
- `/tts-backend/bg-music/` - Server-side audio mixing (NOT used by Flutter app)
- `/public/bg-music/` - Web-accessible files for Flutter app (✅ CORRECT location)
- `/storage/app/bg-music/original/` - Backup/original files

**File Formats**: 
- MP3 (192kbps) - Preferred by Flutter app
- AAC - Fallback option
- M4A - Second fallback

**Troubleshooting**:
```bash
# Check what the app is requesting in Flutter logs
# Look for lines like: "🎵 Attempting download: https://meditative-brains.com/bg-music/{slug}.mp3"

# Verify file permissions
ls -lh /var/www/clients/client1/web63/web/public/bg-music/ | grep quit

# Test accessibility
curl -I "https://meditative-brains.com/bg-music/{your-slug-here}.mp3"
```

---

### TTS Messages Not Saving (Fixed: Nov 2, 2025)

**Issue:** When clicking "Save Messages" for a new TTS category, messages were not being saved to the database.

**Root Cause:** The `generateAudio()` method was trying to generate audio immediately without first saving the messages to the database. It was calling the audio generation API with only a categoryId, but no messages existed for that category yet.

**Solution Applied:**
Modified `app/Livewire/Admin/MotivationMessageForm.php` - `generateAudio()` method to:
1. **First**: Save the messages to the database via POST to `/api/motivationMessage`
2. **Second**: After successful save, trigger audio generation using the returned record ID
3. **Third**: Refresh the records list and clear the form

**Files Modified:**
- `app/Livewire/Admin/MotivationMessageForm.php` (line 419-530)

**How It Works Now:**
1. User fills in category, messages, and settings
2. Clicks "Save Messages" button
3. System saves record to MongoDB
4. System triggers audio generation for the saved record
5. Success message shows: "Record saved! Audio generation started: X files queued"
6. Form clears and records list refreshes

---

### Login Error - "Undefined variable $slot" (Fixed: Nov 1, 2025)

**Issue:** `ErrorException: Undefined variable $slot` on login page

**Root Cause:** Layout file (`app.blade.php`) was using component syntax `{{ $slot }}` while auth views (`login.blade.php`, `register.blade.php`) were using traditional Blade inheritance with `@extends` and `@section`.

**Solution Applied:**
Changed `/resources/views/layouts/app.blade.php`:
```php
# Before:
{{ $slot }}

# After:
@yield('content')
```

**File Location:** `resources/views/layouts/app.blade.php` (line 76)

---

## 📍 Quick Access Paths

### Server Locations
```bash
# Web Root
/var/www/clients/client1/web63/web

# TTS Backend
/var/www/clients/client1/web63/web/tts-backend

# Flutter App
/var/www/clients/client1/web63/web/flutter

# Laravel Logs
/var/www/clients/client1/web63/web/storage/logs/laravel.log
```

### URLs
- **Main App:** https://meditative-brains.com
- **TTS Backend:** https://meditative-brains.com:3001
- **Admin Panel:** https://meditative-brains.com/admin

---

## ⚡ Essential Commands

### Laravel (Run from web root)
```bash
# Clear all caches
php artisan optimize:clear

# View logs
tail -f storage/logs/laravel.log

# Run migrations
php artisan migrate

# Run queue worker
php artisan queue:work
```

### TTS Backend
```bash
# Navigate to TTS backend
cd tts-backend

# View PM2 status
pm2 status

# View logs
pm2 logs tts-backend

# Restart service
pm2 restart tts-backend
```

### Git Workflow
```bash
# Pull latest changes
git pull origin main

# Check status
git status

# Commit and push
git add .
git commit -m "Description"
git push origin main
```

---

## 🔍 Common Troubleshooting

### Laravel Issues

**500 Error:**
```bash
tail -f storage/logs/laravel.log
php artisan optimize:clear
```

**Permission Errors:**
```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

**Database Connection Failed:**
```bash
# Check .env file database credentials
# Test connection:
php artisan tinker
>>> DB::connection()->getPdo();
```

### TTS Backend Issues

**Service Not Running:**
```bash
pm2 status
pm2 restart tts-backend
pm2 logs tts-backend --lines 50
```

**Port 3001 Already in Use:**
```bash
lsof -i :3001
kill -9 <PID>
pm2 restart tts-backend
```

---

## 📚 Documentation Files

**Start Here:**
- `DEVELOPMENT_ENVIRONMENT_INSTRUCTIONS.md` - Complete environment setup guide (NEW)
- `README.md` - General project overview

**Backend:**
- `PHASE_2_TTS_BACKEND_INSTRUCTIONS.md` - TTS backend enhancements
- `IMPLEMENTATION_GUIDE_TTS_AUDIO_PRODUCTS.md` - TTS products guide
- `AUDIO_PREVIEW_SYSTEM.md` - Audio preview system

**Flutter:**
- `flutter/README.md` - Flutter app documentation
- `FLUTTER_API_DOCUMENTATION.md` - API integration guide
- `flutter/EQUALIZER_GUIDE.md` - Equalizer implementation

---

## 🛠️ Environment Differences

### Windows Desktop vs MacBook

Both environments access the same:
- **Production Server:** meditative-brains.com
- **Code Repository:** GitHub (pkjoshisbp/meditative-brains)
- **Database:** Production MySQL database

**Development Setup:**
- SSH access to production server
- Git for version control
- Code editing locally, deploy to server

**Important:** 
- Always pull latest changes before starting work: `git pull origin main`
- Test changes on production carefully
- Check logs after deployments

---

## 🔐 Security Notes

- Never commit `.env` files
- Keep SSH keys secure
- Use different credentials for development/production
- Regularly update dependencies

---

## 📞 Need Help?

1. **Check Logs First:**
   - Laravel: `storage/logs/laravel.log`
   - TTS Backend: `pm2 logs tts-backend`
   - Server: `/var/log/nginx/error.log` or `/var/log/apache2/error.log`

2. **Review Documentation:**
   - `DEVELOPMENT_ENVIRONMENT_INSTRUCTIONS.md` for detailed setup
   - Specific feature documentation in respective `.md` files

3. **Common Issues:**
   - Most errors are permission or cache related
   - Run `php artisan optimize:clear` for Laravel issues
   - Restart services with `pm2 restart tts-backend` for Node.js issues

---

**Remember:** When switching between development environments (Windows ↔ MacBook), always:
1. `git pull origin main` to get latest changes
2. Check for environment-specific configurations
3. Verify file permissions on the server
4. Test critical functionality after pulling changes
