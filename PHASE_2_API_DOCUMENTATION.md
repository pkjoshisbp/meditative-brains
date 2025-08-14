# 🎵 Phase 2: Enhanced TTS Audio Preview API Documentation

## 🚀 New Enhanced Features

### Enhanced Preview Generation with Background Music

**POST** `/api/tts/products/preview`

**Enhanced Response:**
```json
{
  "success": true,
  "preview_audio_url": "https://meditative-brains.com:3001/temp-audio/preview_abc123.mp3",
  "duration": 30,
  "message_text": "I radiate confidence and self-assurance in everything I do",
  "expires_at": "2025-08-15T20:30:00.000000Z",
  "product": {
    "id": 1,
    "name": "Unshakeable Self Confidence",
    "category": "self_confidence",
    "has_background_music": true
  },
  "generation_info": {
    "voice_used": "default",
    "speed_used": 1.0,
    "cached": false
  }
}
```

**Enhanced Error Response:**
```json
{
  "error": "Preview service temporarily unavailable",
  "details": "TTS backend connection failed",
  "product_id": 1
}
```

---

### 🎵 New: Bulk Preview Generation

**POST** `/api/tts/products/bulk-preview`

Generate previews for multiple sample messages at once.

**Request Body:**
```json
{
  "product_id": 1,
  "voice": "en-US-AriaNeural",
  "speed": 1.0,
  "count": 3
}
```

**Response:**
```json
{
  "success": true,
  "product": {
    "id": 1,
    "name": "Unshakeable Self Confidence",
    "category": "self_confidence"
  },
  "previews": [
    {
      "index": 0,
      "message": "I radiate confidence and self-assurance in everything I do",
      "preview_url": "https://meditative-brains.com:3001/temp-audio/preview_abc123.mp3",
      "duration": 30,
      "success": true
    },
    {
      "index": 1,
      "message": "My inner strength grows more powerful with each passing day",
      "preview_url": "https://meditative-brains.com:3001/temp-audio/preview_def456.mp3",
      "duration": 30,
      "success": true
    },
    {
      "index": 2,
      "message": "I trust my intuition and make decisions with complete confidence",
      "preview_url": "https://meditative-brains.com:3001/temp-audio/preview_ghi789.mp3",
      "duration": 30,
      "success": true
    }
  ],
  "total_generated": 3,
  "expires_at": "2025-08-15T20:30:00.000000Z"
}
```

---

### 🔧 New: Audio Service Status

**GET** `/api/tts/audio-service/status`

Get real-time status of the audio generation service.

**Response:**
```json
{
  "success": true,
  "audio_service": {
    "backend_url": "https://meditative-brains.com:3001",
    "timeout": 60,
    "cache_enabled": true,
    "status": "operational"
  },
  "backend_status": "operational",
  "backend_url": "https://meditative-brains.com:3001",
  "features": {
    "preview_generation": true,
    "background_music_mixing": true,
    "caching": true,
    "bulk_preview": true,
    "voice_selection": true
  },
  "timestamp": "2025-08-15T18:45:00.000000Z"
}
```

---

### 🎤 Enhanced Voice Selection

**GET** `/api/tts/voices?language=en`

**Enhanced Response:**
```json
{
  "success": true,
  "language": "en",
  "voices": [
    {
      "id": "en-US-male-1",
      "name": "David (Male)",
      "gender": "male"
    },
    {
      "id": "en-US-female-1",
      "name": "Sarah (Female)",
      "gender": "female"
    },
    {
      "id": "en-US-male-2",
      "name": "Michael (Male)",
      "gender": "male"
    },
    {
      "id": "en-US-female-2",
      "name": "Emma (Female)",
      "gender": "female"
    }
  ],
  "source": "backend",
  "total_count": 4
}
```

---

### 📱 Enhanced Product Catalog

**GET** `/api/tts/products/catalog`

**Enhanced Response (shows background music info):**
```json
{
  "success": true,
  "categories": [
    {
      "category": {
        "name": "self_confidence",
        "display_name": "Self Confidence",
        "description": "Boost your self-confidence with powerful motivational messages",
        "icon_url": "https://example.com/icons/confidence.png"
      },
      "products": [
        {
          "id": 1,
          "name": "Unshakeable Self Confidence",
          "description": "Build rock-solid confidence that cannot be shaken by external circumstances",
          "category": "self_confidence",
          "language": "en",
          "price": 4.99,
          "formatted_price": "$4.99",
          "preview_duration": 30,
          "cover_image_url": "https://meditative-brains.com/images/covers/confidence-cover.jpg",
          "total_messages_count": 167,
          "sample_messages": [
            "I radiate confidence and self-assurance in everything I do",
            "My inner strength grows more powerful with each passing day",
            "I trust my intuition and make decisions with complete confidence",
            "My self-worth is unshakeable and comes from deep within",
            "I speak my truth with clarity, courage, and conviction"
          ],
          "has_access": false,
          "access_type": "none",
          "can_preview": true,
          "preview_available": true,
          "background_music_url": "https://meditative-brains.com:3001/background-music/confidence-empowerment.mp3"
        }
      ],
      "user_has_category_access": false
    }
  ],
  "user_access_summary": {
    "subscription_access": false,
    "purchased_products": 0,
    "accessible_categories": [],
    "trial_access": false
  }
}
```

---

## 🎯 Phase 2 Key Improvements

### ✅ Performance Enhancements
- **Preview Caching**: Identical preview requests are cached for 30 minutes
- **Bulk Generation**: Generate multiple previews in one request
- **Error Handling**: Graceful fallbacks when TTS backend is unavailable
- **Connection Pooling**: Optimized HTTP connections to TTS backend

### 🎵 Audio Quality Features
- **Background Music Mixing**: TTS voice + background music at optimal levels
- **Duration Control**: Precise preview length control (10-60 seconds)
- **Voice Selection**: Multiple voice options with gender preferences
- **Audio Optimization**: High-quality audio generation and compression

### 🔒 Enhanced Security
- **Temporary URLs**: Preview audio files expire after 2 hours
- **Access Validation**: All preview requests validate user authentication
- **Rate Limiting**: Prevents abuse of preview generation
- **Content Protection**: Original sample messages only, no full content access

### 📊 Monitoring & Analytics
- **Service Status**: Real-time backend connectivity monitoring
- **Generation Stats**: Track preview generation success rates
- **Error Logging**: Comprehensive error tracking and debugging
- **Performance Metrics**: Response time and cache hit rate tracking

---

## 🧪 Testing Your Implementation

### Test Preview Generation:
```bash
curl -X POST https://meditative-brains.com/api/tts/products/preview \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "product_id": 1,
    "voice": "en-US-AriaNeural",
    "speed": 1.0
  }'
```

### Test Bulk Preview:
```bash
curl -X POST https://meditative-brains.com/api/tts/products/bulk-preview \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "product_id": 1,
    "count": 3,
    "voice": "en-US-AriaNeural"
  }'
```

### Test Service Status:
```bash
curl https://meditative-brains.com/api/tts/audio-service/status \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

## 🎵 Flutter Integration Example

```dart
class TtsPreviewService {
  // Generate single preview
  Future<PreviewAudio> generatePreview({
    required int productId,
    String? messageText,
    String? voice,
    double? speed,
  }) async {
    final response = await http.post(
      Uri.parse('$baseUrl/api/tts/products/preview'),
      headers: headers,
      body: json.encode({
        'product_id': productId,
        if (messageText != null) 'message_text': messageText,
        if (voice != null) 'voice': voice,
        if (speed != null) 'speed': speed,
      }),
    );
    
    if (response.statusCode == 200) {
      return PreviewAudio.fromJson(json.decode(response.body));
    }
    throw Exception('Failed to generate preview');
  }
  
  // Generate multiple previews
  Future<BulkPreviewResult> generateBulkPreview({
    required int productId,
    String? voice,
    double? speed,
    int count = 3,
  }) async {
    final response = await http.post(
      Uri.parse('$baseUrl/api/tts/products/bulk-preview'),
      headers: headers,
      body: json.encode({
        'product_id': productId,
        'count': count,
        if (voice != null) 'voice': voice,
        if (speed != null) 'speed': speed,
      }),
    );
    
    if (response.statusCode == 200) {
      return BulkPreviewResult.fromJson(json.decode(response.body));
    }
    throw Exception('Failed to generate bulk preview');
  }
}
```

**Phase 2 is ready for production! 🚀**
