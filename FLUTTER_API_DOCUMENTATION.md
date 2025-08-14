# 🎵 Music Library & TTS Backend API Documentation

## Flutter Integration Guide for Meditative Brains

### 🔐 Authentication
All protected endpoints require authentication using Laravel Sanctum tokens.

**Headers Required:**
```
Authorization: Bearer {token}
Accept: application/json
Content-Type: application/json
```

---

## 🎼 Music Library APIs

### 1. Browse Music Library
**GET** `/api/music-library`

**Response:**
```json
{
  "categories": [
    {
      "name": "Sleep Aid Music",
      "products": [
        {
          "id": 1,
          "name": "Deep Sleep Delta Waves",
          "description": "Delta wave frequencies for deep sleep",
          "price": 9.99,
          "original_price": 12.99,
          "audio_type": "sleep_aid",
          "audio_features": ["binaural", "pink_noise"],
          "preview_duration": 90,
          "preview_url": "https://example.com/preview.mp3",
          "full_audio_url": null,
          "tags": ["sleep", "delta", "waves"],
          "is_featured": true,
          "has_access": false,
          "can_preview": true
        }
      ]
    }
  ],
  "user_access": {
    "music_library": {
      "has_access": false,
      "summary": {
        "has_full_access": false,
        "purchased_products": [],
        "subscription_access": false,
        "access_expires_at": null
      }
    }
  },
  "subscription_plans": [
    {
      "id": 1,
      "name": "Music Library Monthly",
      "price": 9.99,
      "billing_cycle": "monthly",
      "includes_music_library": true,
      "trial_days": 7
    }
  ]
}
```

### 2. Get User's Accessible Music
**GET** `/api/music-library/my-library` 🔒

**Response:**
```json
{
  "access_type": "individual_purchases",
  "total_accessible": 3,
  "subscription_info": null,
  "products": [
    {
      "id": 1,
      "name": "Deep Sleep Delta Waves",
      "full_audio_url": "https://example.com/full-audio.mp3",
      "category": "Sleep Aid Music",
      "downloaded": false
    }
  ]
}
```

### 3. Preview Music Track
**GET** `/api/music-library/preview/{productId}`

**Response:**
```json
{
  "product_id": 1,
  "product_name": "Deep Sleep Delta Waves",
  "preview_url": "https://example.com/preview.mp3",
  "preview_duration": 90,
  "full_access_required": true
}
```

### 4. Get Full Audio File
**GET** `/api/music-library/full-audio/{productId}` 🔒

**Response:**
```json
{
  "product_id": 1,
  "product_name": "Deep Sleep Delta Waves",
  "audio_url": "https://example.com/full-audio.mp3",
  "audio_type": "sleep_aid",
  "audio_features": ["binaural", "pink_noise"]
}
```

### 5. Check Access Permissions
**GET** `/api/music-library/check-access` 🔒

**Parameters:**
- `resource_type`: "music_library" | "music_product" | "tts_category"
- `resource_id`: ID of specific resource (required unless resource_type is music_library)

**Response:**
```json
{
  "can_access": false,
  "access_type": null,
  "expires_at": null,
  "reason": "No music library access. Consider purchasing a subscription."
}
```

---

## 🎤 TTS (Text-to-Speech) APIs

### 1. Get TTS Categories
**GET** `/api/tts/categories`

**Response:**
```json
{
  "categories": [
    {
      "name": "Self Confidence",
      "has_access": false,
      "description": "Build unshakeable self-confidence",
      "estimated_content_count": 25
    }
  ],
  "user_access_summary": {
    "accessible_categories": ["Self Confidence"],
    "total_accessible": 1,
    "total_available": 10,
    "subscription_access": true
  }
}
```

### 2. Get Messages for Category
**GET** `/api/tts/category/{category}/messages` 🔒

**Response:**
```json
{
  "success": true,
  "category": "Self Confidence",
  "access_info": {
    "can_access": true,
    "access_type": "subscription",
    "expires_at": "2025-09-14T10:30:00Z"
  },
  "messages": [
    {
      "_id": "64f123...",
      "text": "I am confident and capable",
      "category": "Self Confidence",
      "audioUrl": "https://example.com/audio.mp3",
      "createdAt": "2025-08-01T10:30:00Z"
    }
  ],
  "total_count": 15
}
```

### 3. Generate Audio for Message
**POST** `/api/tts/generate-audio` 🔒

**Request Body:**
```json
{
  "message_id": "64f123...",
  "category": "Self Confidence",
  "voice": "en-US-AriaNeural",
  "speed": 1.0
}
```

**Response:**
```json
{
  "success": true,
  "message_id": "64f123...",
  "audio_url": "https://example.com/generated-audio.mp3",
  "duration": 5.2,
  "voice_used": "en-US-AriaNeural"
}
```

### 4. Search Messages
**POST** `/api/tts/search` 🔒

**Request Body:**
```json
{
  "query": "confidence building",
  "categories": ["Self Confidence", "Positive Attitude"],
  "limit": 20
}
```

**Response:**
```json
{
  "success": true,
  "query": "confidence building",
  "searched_categories": ["Self Confidence"],
  "results": [
    {
      "_id": "64f123...",
      "text": "I am building my confidence daily",
      "category": "Self Confidence",
      "relevance_score": 0.95
    }
  ],
  "total_count": 8
}
```

### 5. Get Available Voices
**GET** `/api/tts/voices`

**Response:**
```json
{
  "voices": [
    {
      "name": "en-US-AriaNeural",
      "language": "English (US)",
      "gender": "Female",
      "provider": "Azure"
    },
    {
      "name": "hi-IN-SwaraNeural",
      "language": "Hindi (India)",
      "gender": "Female",
      "provider": "Azure"
    }
  ]
}
```

### 6. Get User TTS Statistics
**GET** `/api/tts/user-stats` 🔒

**Response:**
```json
{
  "user_id": 1,
  "accessible_categories": ["Self Confidence", "Positive Attitude"],
  "total_accessible": 2,
  "total_available": 10,
  "access_percentage": 20.0,
  "subscription_info": {
    "plan_type": "tts-affirmations-complete",
    "status": "active",
    "ends_at": "2025-09-14T10:30:00Z"
  },
  "has_full_access": false
}
```

### 7. Get Category Pricing
**GET** `/api/tts/category-pricing`

**Response:**
```json
{
  "individual_category_pricing": [
    {
      "category": "Self Confidence",
      "price": 4.99,
      "description": "Build unshakeable self-confidence",
      "estimated_messages": 25
    }
  ],
  "bundle_savings": "Save up to 60% with subscription plans",
  "recommended_plan": "TTS Affirmations Complete - $14.99/month"
}
```

---

## 💳 Payment APIs

### 1. Get Subscription Plans
**GET** `/api/payment/plans`

**Response:**
```json
{
  "plans": [
    {
      "id": 1,
      "name": "Music Library Monthly",
      "slug": "music-library-monthly",
      "description": "Access to complete music library",
      "price": 9.99,
      "billing_cycle": "monthly",
      "features": [
        "Complete Music Library Access",
        "High-Quality Downloads",
        "Unlimited Streaming"
      ],
      "includes_music_library": true,
      "includes_all_tts_categories": false,
      "trial_days": 7,
      "is_featured": false
    }
  ]
}
```

### 2. Create Product Purchase
**POST** `/api/payment/create-product-payment` 🔒

**Request Body:**
```json
{
  "product_id": 1
}
```

**Response:**
```json
{
  "success": true,
  "paypal_order_id": "8XY12345...",
  "approval_url": "https://www.sandbox.paypal.com/checkoutnow?token=8XY12345...",
  "product": {
    "id": 1,
    "name": "Deep Sleep Delta Waves",
    "price": 9.99
  }
}
```

### 3. Create Subscription Purchase
**POST** `/api/payment/create-subscription-payment` 🔒

**Request Body:**
```json
{
  "plan_id": 1
}
```

**Response:**
```json
{
  "success": true,
  "paypal_order_id": "8XY12345...",
  "approval_url": "https://www.sandbox.paypal.com/checkoutnow?token=8XY12345...",
  "plan": {
    "id": 1,
    "name": "Music Library Monthly",
    "price": 9.99,
    "billing_cycle": "monthly"
  }
}
```

### 4. Handle Payment Success
**POST** `/api/payment/handle-success` 🔒

**Request Body:**
```json
{
  "paypal_order_id": "8XY12345...",
  "type": "product",
  "product_id": 1
}
```

**Response:**
```json
{
  "success": true,
  "message": "Purchase completed successfully",
  "access_granted": true,
  "order": {
    "id": 123,
    "order_number": "ORD-ABC123",
    "status": "completed"
  }
}
```

### 5. Get Purchase History
**GET** `/api/payment/history` 🔒

**Response:**
```json
{
  "orders": [
    {
      "id": 123,
      "order_number": "ORD-ABC123",
      "total_amount": 9.99,
      "status": "completed",
      "payment_method": "paypal",
      "completed_at": "2025-08-14T10:30:00Z",
      "items": [
        {
          "product_id": 1,
          "product_name": "Deep Sleep Delta Waves",
          "quantity": 1,
          "price": 9.99
        }
      ]
    }
  ],
  "subscriptions": [
    {
      "id": 456,
      "plan_type": "music-library-monthly",
      "price": 9.99,
      "status": "active",
      "starts_at": "2025-08-14T10:30:00Z",
      "ends_at": "2025-09-14T10:30:00Z",
      "is_active": true
    }
  ]
}
```

### 6. Cancel Subscription
**POST** `/api/payment/cancel-subscription` 🔒

**Response:**
```json
{
  "success": true,
  "message": "Subscription cancelled successfully"
}
```

---

## 🔄 Error Handling

### Common Error Responses:

**401 Unauthorized:**
```json
{
  "error": "Authentication required"
}
```

**403 Access Denied:**
```json
{
  "error": "Access denied",
  "reason": "This music track requires individual purchase or subscription.",
  "can_purchase": true
}
```

**404 Not Found:**
```json
{
  "error": "Resource not found"
}
```

**500 Server Error:**
```json
{
  "error": "Internal server error"
}
```

**503 Service Unavailable:**
```json
{
  "error": "TTS service temporarily unavailable"
}
```

---

## 📱 Flutter Implementation Examples

### Authentication Setup:
```dart
class ApiService {
  static const String baseUrl = 'https://meditative-brains.com';
  
  static Map<String, String> get headers => {
    'Authorization': 'Bearer ${UserPreferences.getToken()}',
    'Accept': 'application/json',
    'Content-Type': 'application/json',
  };
}
```

### Music Library Screen:
```dart
class MusicLibraryScreen extends StatefulWidget {
  @override
  _MusicLibraryScreenState createState() => _MusicLibraryScreenState();
}

class _MusicLibraryScreenState extends State<MusicLibraryScreen> {
  Future<void> loadMusicLibrary() async {
    final response = await http.get(
      Uri.parse('${ApiService.baseUrl}/api/music-library'),
      headers: ApiService.headers,
    );
    
    if (response.statusCode == 200) {
      final data = json.decode(response.body);
      // Handle music library data
    }
  }
}
```

### Purchase Flow:
```dart
Future<void> purchaseProduct(int productId) async {
  // 1. Create payment order
  final response = await http.post(
    Uri.parse('${ApiService.baseUrl}/api/payment/create-product-payment'),
    headers: ApiService.headers,
    body: json.encode({'product_id': productId}),
  );
  
  if (response.statusCode == 200) {
    final data = json.decode(response.body);
    
    // 2. Open PayPal approval URL
    await launchUrl(Uri.parse(data['approval_url']));
    
    // 3. Handle success callback
    // (Implement deep link or webhook handling)
  }
}
```

---

## 🔐 Security Features

- ✅ **Access Control**: Granular permissions per user
- ✅ **Token Authentication**: Laravel Sanctum integration
- ✅ **Purchase Verification**: PayPal order validation
- ✅ **Subscription Management**: Automatic access granting/revoking
- ✅ **Trial Support**: Built-in trial period handling
- ✅ **Content Protection**: Secure audio file serving

**Base URL:** `https://meditative-brains.com`  
**TTS Backend:** `https://meditative-brains.com:3001`  
**Version:** 2.0
