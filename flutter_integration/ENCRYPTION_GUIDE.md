# Audio Encryption Integration Guide

## Overview

Background music files are encrypted using AES-256-CBC encryption on the Laravel backend and need to be decrypted in Flutter for playback.

## How It Works

### Backend (Laravel)
- Files are encrypted using `AudioSecurityService`
- Algorithm: AES-256-CBC
- Key: SHA256 hash of Laravel's `APP_KEY`
- Format: `[16-byte IV][encrypted content]`
- Stored in: `storage/app/bg-music/encrypted/`

### Flutter Integration

1. **Get Encryption Key**:
```dart
final encryptionKey = await repository.getEncryptionKey();
```

2. **Initialize Decryption Service**:
```dart
final decryptionService = AudioDecryptionService();
decryptionService.setEncryptionKey(encryptionKey.encryptionKey);
```

3. **Download and Decrypt Audio**:
```dart
// Fetch encrypted file data
final response = await http.get(Uri.parse(track.url));
final encryptedData = response.bodyBytes;

// Decrypt
final decryptedAudio = decryptionService.decryptAudio(encryptedData);

// Use with audio player (just_audio, audioplayers, etc.)
```

## Required Dependencies

Add to your `pubspec.yaml`:

```yaml
dependencies:
  crypto: ^3.0.3
  pointycastle: ^3.7.3
  http: ^1.1.0
```

## Usage Example

```dart
import 'package:flutter/services.dart';
import '../encryption/audio_decryption_service.dart';

class BackgroundMusicPlayer {
  final AudioDecryptionService _decryption = AudioDecryptionService();
  bool _initialized = false;

  Future<void> _ensureInitialized() async {
    if (_initialized) return;
    final keyDto = await repository.getEncryptionKey();
    _decryption.setEncryptionKey(keyDto.encryptionKey);
    _initialized = true;
  }

  Future<Uint8List> loadDecryptedAudio(String trackUrl) async {
    await _ensureInitialized();
    
    // Download encrypted file
    final response = await http.get(Uri.parse(trackUrl));
    if (response.statusCode != 200) {
      throw Exception('Failed to download: ${response.statusCode}');
    }
    
    // Decrypt and return
    return _decryption.decryptAudio(response.bodyBytes);
  }
}
```

## Security Notes

- The encryption key is transmitted over HTTPS and requires authentication
- Keys are cached in memory only (not persisted to disk)
- Each encrypted file has a unique IV for security
- Decrypted audio should be kept in memory only (not cached to disk)

## Testing

Test the encryption key endpoint:
```bash
curl -H "Authorization: Bearer YOUR_TOKEN" \
     -H "Accept: application/json" \
     "https://your-domain/api/tts/encryption-key"
```

Expected response:
```json
{
  "success": true,
  "encryption_key": "base64-encoded-32-byte-key",
  "algorithm": "AES-256-CBC", 
  "iv_length": 16,
  "format": "First 16 bytes are IV, remainder is encrypted content"
}
```
