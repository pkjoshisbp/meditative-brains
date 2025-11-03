# Quick Reference Guide - Meditative Brains

**For:** Cross-platform development (Windows Desktop → MacBook)  
**Last Updated:** November 1, 2025

---

## 🚨 Recent Fixes

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
