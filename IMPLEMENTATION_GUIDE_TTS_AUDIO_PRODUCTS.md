# 🎯 Implementation Guide: TTS Audio Products with Preview & Purchase Integration

## 📋 Overview
This guide outlines how to implement motivational message audio products with TTS preview functionality, background music integration, and secure access control for Flutter app integration.

---

## 🏗️ Architecture Overview

### Current Setup Analysis:
- ✅ **Laravel Backend**: Handles authentication, payments, access control
- ✅ **TTS Backend**: Running on `meditative-brains.com:3001` (Node.js)
- ✅ **Flutter App**: Currently direct access to TTS backend
- ✅ **PayPal Integration**: Complete payment processing system
- ✅ **Access Control**: Category-based permissions system

### Target Architecture:
```
Flutter App → Laravel API → TTS Backend (with access control)
           ↓
    PayPal Payment System → Access Grant → Protected Content
```

---

## 🎵 Phase 1: TTS Audio Product System

### 1.1 Database Schema Extensions

**Create new migration for TTS products:**
```bash
php artisan make:migration create_tts_audio_products_table
```

**Migration Structure:**
```sql
CREATE TABLE tts_audio_products (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    category VARCHAR(100) NOT NULL,
    language VARCHAR(10) NOT NULL,
    price DECIMAL(8,2) NOT NULL,
    preview_duration INT DEFAULT 30, -- seconds
    background_music_url VARCHAR(500),
    cover_image_url VARCHAR(500),
    sample_messages JSON, -- 3-5 sample messages for preview
    total_messages_count INT DEFAULT 0,
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX idx_category (category),
    INDEX idx_language (language),
    INDEX idx_active (is_active)
);
```

**Product Categories Table:**
```sql
CREATE TABLE tts_categories (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    display_name VARCHAR(255) NOT NULL,
    description TEXT,
    icon_url VARCHAR(500),
    sort_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);
```

### 1.2 Model Creation

**TtsAudioProduct Model:**
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TtsAudioProduct extends Model
{
    protected $fillable = [
        'name', 'description', 'category', 'language', 'price',
        'preview_duration', 'background_music_url', 'cover_image_url',
        'sample_messages', 'total_messages_count', 'is_active'
    ];

    protected $casts = [
        'sample_messages' => 'array',
        'price' => 'decimal:2',
        'is_active' => 'boolean'
    ];

    public function purchases()
    {
        return $this->hasMany(TtsProductPurchase::class);
    }

    public function isPurchasedByUser($userId)
    {
        return $this->purchases()
            ->where('user_id', $userId)
            ->where('status', 'completed')
            ->exists();
    }
}
```

**TtsProductPurchase Model:**
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TtsProductPurchase extends Model
{
    protected $fillable = [
        'user_id', 'tts_audio_product_id', 'order_id',
        'amount', 'currency', 'status', 'paypal_order_id',
        'purchased_at'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'purchased_at' => 'datetime'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function product()
    {
        return $this->belongsTo(TtsAudioProduct::class, 'tts_audio_product_id');
    }
}
```

---

## 🎼 Phase 2: Preview System with Background Music

### 2.1 Enhanced TTS Backend Integration

**Update TtsBackendController for preview functionality:**

```php
/**
 * Get TTS audio products catalog with preview capability
 */
public function getTtsProductsCatalog(Request $request)
{
    $user = Auth::user();
    
    if (!$user) {
        return response()->json(['error' => 'Authentication required'], 401);
    }

    $products = TtsAudioProduct::where('is_active', true)
        ->orderBy('category')
        ->orderBy('name')
        ->get();

    $catalog = [];
    foreach ($products as $product) {
        $hasAccess = $product->isPurchasedByUser($user->id);
        
        $catalog[] = [
            'id' => $product->id,
            'name' => $product->name,
            'description' => $product->description,
            'category' => $product->category,
            'language' => $product->language,
            'price' => $product->price,
            'preview_duration' => $product->preview_duration,
            'cover_image_url' => $product->cover_image_url,
            'total_messages_count' => $product->total_messages_count,
            'sample_messages' => $product->sample_messages,
            'has_access' => $hasAccess,
            'can_preview' => true, // Always allow preview
            'preview_available' => !empty($product->sample_messages)
        ];
    }

    return response()->json([
        'success' => true,
        'products' => $catalog,
        'user_purchases' => $user->ttsProductPurchases()
            ->where('status', 'completed')
            ->pluck('tts_audio_product_id')
            ->toArray()
    ]);
}

/**
 * Generate preview audio with background music
 */
public function generatePreviewAudio(Request $request)
{
    $request->validate([
        'product_id' => 'required|exists:tts_audio_products,id',
        'message_text' => 'required|string|max:500',
        'voice' => 'sometimes|string',
        'speed' => 'sometimes|numeric|min:0.5|max:2.0'
    ]);

    $product = TtsAudioProduct::findOrFail($request->product_id);
    
    try {
        // Generate TTS audio
        $ttsResponse = Http::timeout(60)->post($this->ttsBackendUrl . '/api/generate-preview', [
            'text' => $request->message_text,
            'voice' => $request->voice ?? 'default',
            'speed' => $request->speed ?? 1.0,
            'language' => $product->language,
            'preview_duration' => $product->preview_duration,
            'background_music_url' => $product->background_music_url
        ]);

        if ($ttsResponse->successful()) {
            $audioData = $ttsResponse->json();
            
            return response()->json([
                'success' => true,
                'preview_audio_url' => $audioData['preview_url'],
                'duration' => $audioData['duration'],
                'expires_at' => now()->addHours(2)->toISOString() // Temporary URL
            ]);
        }

        return response()->json([
            'error' => 'Failed to generate preview audio'
        ], 500);

    } catch (\Exception $e) {
        Log::error('Preview Generation Error', [
            'error' => $e->getMessage(),
            'product_id' => $request->product_id
        ]);

        return response()->json([
            'error' => 'Preview service temporarily unavailable'
        ], 503);
    }
}
```

### 2.2 TTS Backend Enhancements (Node.js)

**Required TTS Backend Updates (meditative-brains.com:3001):**

Create new endpoint: `/api/generate-preview`

```javascript
// Add to your Node.js TTS backend
app.post('/api/generate-preview', async (req, res) => {
    try {
        const { text, voice, speed, language, preview_duration, background_music_url } = req.body;
        
        // Generate TTS audio
        const ttsAudio = await generateTTSAudio({
            text,
            voice: voice || 'default',
            speed: speed || 1.0,
            language: language || 'en'
        });
        
        // If background music URL provided, mix it
        let finalAudio = ttsAudio;
        if (background_music_url) {
            finalAudio = await mixAudioWithBackground(ttsAudio, background_music_url, preview_duration);
        }
        
        // Trim to preview duration
        finalAudio = await trimAudio(finalAudio, preview_duration);
        
        // Save temporary file
        const previewUrl = await saveTemporaryAudio(finalAudio, 'preview_');
        
        res.json({
            success: true,
            preview_url: previewUrl,
            duration: preview_duration,
            expires_in: 7200 // 2 hours
        });
        
    } catch (error) {
        console.error('Preview generation error:', error);
        res.status(500).json({ error: 'Failed to generate preview' });
    }
});

// Helper function for audio mixing
async function mixAudioWithBackground(ttsAudio, backgroundMusicUrl, duration) {
    // Use ffmpeg or similar to mix TTS with background music
    // Lower background music volume, overlay TTS
    // Return mixed audio buffer
}
```

---

## 💳 Phase 3: Payment Integration for TTS Products

### 3.1 Enhanced PaymentController

**Add TTS product payment methods:**

```php
/**
 * Create payment for TTS audio product
 */
public function createTtsProductPayment(Request $request)
{
    $user = Auth::user();
    
    if (!$user) {
        return response()->json(['error' => 'Authentication required'], 401);
    }

    $request->validate([
        'product_id' => 'required|exists:tts_audio_products,id'
    ]);

    $product = TtsAudioProduct::findOrFail($request->product_id);

    // Check if user already owns this product
    if ($product->isPurchasedByUser($user->id)) {
        return response()->json([
            'error' => 'You already have access to this product',
            'has_access' => true
        ], 409);
    }

    try {
        // Create PayPal order
        $paypalOrder = $this->paypalService->createProductOrder([
            'name' => $product->name,
            'description' => "TTS Audio: {$product->description}",
            'amount' => $product->price,
            'currency' => 'USD',
            'custom_id' => "tts_product_{$product->id}_{$user->id}"
        ]);

        // Store order in database
        $order = Order::create([
            'user_id' => $user->id,
            'paypal_order_id' => $paypalOrder['id'],
            'amount' => $product->price,
            'currency' => 'USD',
            'status' => 'pending',
            'order_type' => 'tts_product',
            'product_details' => [
                'product_id' => $product->id,
                'product_type' => 'tts_audio',
                'category' => $product->category,
                'language' => $product->language
            ]
        ]);

        return response()->json([
            'success' => true,
            'order_id' => $order->id,
            'paypal_order_id' => $paypalOrder['id'],
            'approval_url' => $paypalOrder['links']['approve'] ?? null,
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'price' => $product->price
            ]
        ]);

    } catch (\Exception $e) {
        Log::error('TTS Product Payment Creation Failed', [
            'user_id' => $user->id,
            'product_id' => $product->id,
            'error' => $e->getMessage()
        ]);

        return response()->json([
            'error' => 'Failed to create payment order'
        ], 500);
    }
}

/**
 * Handle successful TTS product purchase
 */
public function handleTtsProductSuccess(Request $request)
{
    $request->validate([
        'order_id' => 'required|string',
        'paypal_order_id' => 'required|string'
    ]);

    $order = Order::where('paypal_order_id', $request->paypal_order_id)
        ->where('order_type', 'tts_product')
        ->firstOrFail();

    try {
        // Capture PayPal payment
        $captureResult = $this->paypalService->captureOrder($request->paypal_order_id);

        if ($captureResult['status'] === 'COMPLETED') {
            DB::transaction(function () use ($order, $captureResult) {
                // Update order status
                $order->update([
                    'status' => 'completed',
                    'paypal_capture_id' => $captureResult['capture_id'] ?? null,
                    'completed_at' => now()
                ]);

                // Create purchase record
                TtsProductPurchase::create([
                    'user_id' => $order->user_id,
                    'tts_audio_product_id' => $order->product_details['product_id'],
                    'order_id' => $order->id,
                    'amount' => $order->amount,
                    'currency' => $order->currency,
                    'status' => 'completed',
                    'paypal_order_id' => $order->paypal_order_id,
                    'purchased_at' => now()
                ]);

                // Grant access through existing access control system
                $product = TtsAudioProduct::find($order->product_details['product_id']);
                $this->accessControlService->grantAccess(
                    $order->user_id,
                    'tts_category',
                    $product->category,
                    null, // No expiration for purchased products
                    'tts_product_purchase'
                );
            });

            return response()->json([
                'success' => true,
                'message' => 'Purchase completed successfully',
                'access_granted' => true,
                'product_category' => $order->product_details['category']
            ]);
        }

        return response()->json([
            'error' => 'Payment capture failed'
        ], 400);

    } catch (\Exception $e) {
        Log::error('TTS Product Purchase Completion Failed', [
            'order_id' => $order->id,
            'error' => $e->getMessage()
        ]);

        return response()->json([
            'error' => 'Failed to complete purchase'
        ], 500);
    }
}
```

---

## 🛡️ Phase 4: Access Control Integration

### 4.1 Enhanced Access Control Service

**Update AccessControlService for TTS products:**

```php
/**
 * Check if user can access TTS category (includes purchased products)
 */
public function canUserAccessTtsCategory($user, $category)
{
    // Check subscription access
    $subscriptionAccess = $this->canUserAccess($user, 'tts_category', $category);
    
    if ($subscriptionAccess['can_access']) {
        return $subscriptionAccess;
    }
    
    // Check individual product purchases
    $hasPurchasedProduct = TtsProductPurchase::whereHas('product', function($query) use ($category) {
        $query->where('category', $category);
    })
    ->where('user_id', $user->id)
    ->where('status', 'completed')
    ->exists();
    
    if ($hasPurchasedProduct) {
        return [
            'can_access' => true,
            'access_type' => 'product_purchase',
            'reason' => 'Purchased product in this category'
        ];
    }
    
    return [
        'can_access' => false,
        'access_type' => 'none',
        'reason' => 'No subscription or product purchase found'
    ];
}
```

### 4.2 Updated Routes

**Add to api.php:**

```php
// TTS Audio Products
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/tts-products/catalog', [TtsBackendController::class, 'getTtsProductsCatalog']);
    Route::post('/tts-products/preview', [TtsBackendController::class, 'generatePreviewAudio']);
    Route::get('/tts-products/user-purchases', [TtsBackendController::class, 'getUserTtsProducts']);
    
    // Payment endpoints for TTS products
    Route::post('/payment/create-tts-product-payment', [PaymentController::class, 'createTtsProductPayment']);
    Route::post('/payment/handle-tts-product-success', [PaymentController::class, 'handleTtsProductSuccess']);
});
```

---

## 📱 Phase 5: Flutter App Integration Strategy

### 5.1 Recommended Architecture

**Option A: Full Laravel Proxy (Recommended)**
```
Flutter App → Laravel API (with auth) → TTS Backend
```

**Benefits:**
- ✅ Centralized access control
- ✅ Consistent authentication
- ✅ Payment integration
- ✅ Analytics tracking
- ✅ Rate limiting
- ✅ Error handling

**Option B: Hybrid Approach**
```
Flutter App → Laravel API (for auth/payments)
           → TTS Backend (with token validation)
```

### 5.2 Flutter Implementation Steps

**Step 1: Update Flutter to use Laravel API**

```dart
class TtsApiService {
  static const String baseUrl = 'https://meditative-brains.com/api';
  
  // Get TTS products catalog
  Future<List<TtsProduct>> getTtsProductsCatalog() async {
    final response = await http.get(
      Uri.parse('$baseUrl/tts-products/catalog'),
      headers: {
        'Authorization': 'Bearer ${AuthService.token}',
        'Accept': 'application/json',
      },
    );
    
    if (response.statusCode == 200) {
      final data = json.decode(response.body);
      return (data['products'] as List)
          .map((product) => TtsProduct.fromJson(product))
          .toList();
    }
    throw Exception('Failed to load TTS products');
  }
  
  // Generate preview audio
  Future<PreviewAudio> generatePreview({
    required int productId,
    required String messageText,
    String? voice,
    double? speed,
  }) async {
    final response = await http.post(
      Uri.parse('$baseUrl/tts-products/preview'),
      headers: {
        'Authorization': 'Bearer ${AuthService.token}',
        'Content-Type': 'application/json',
      },
      body: json.encode({
        'product_id': productId,
        'message_text': messageText,
        'voice': voice,
        'speed': speed,
      }),
    );
    
    if (response.statusCode == 200) {
      return PreviewAudio.fromJson(json.decode(response.body));
    }
    throw Exception('Failed to generate preview');
  }
  
  // Purchase TTS product
  Future<PaymentOrder> purchaseTtsProduct(int productId) async {
    final response = await http.post(
      Uri.parse('$baseUrl/payment/create-tts-product-payment'),
      headers: {
        'Authorization': 'Bearer ${AuthService.token}',
        'Content-Type': 'application/json',
      },
      body: json.encode({'product_id': productId}),
    );
    
    if (response.statusCode == 200) {
      return PaymentOrder.fromJson(json.decode(response.body));
    }
    throw Exception('Failed to create payment order');
  }
}
```

**Step 2: Update TTS Backend Access**

For accessing full content after purchase:

```dart
class SecureTtsService {
  // Access purchased content through Laravel
  Future<List<Message>> getCategoryMessages(String category) async {
    final response = await http.get(
      Uri.parse('$baseUrl/tts/category/$category/messages'),
      headers: {
        'Authorization': 'Bearer ${AuthService.token}',
        'Accept': 'application/json',
      },
    );
    
    if (response.statusCode == 200) {
      final data = json.decode(response.body);
      return (data['messages'] as List)
          .map((msg) => Message.fromJson(msg))
          .toList();
    } else if (response.statusCode == 403) {
      throw AccessDeniedException('Purchase required to access this category');
    }
    throw Exception('Failed to load messages');
  }
}
```

---

## 🚀 Phase 6: Implementation Timeline

### Week 1: Backend Foundation
- [ ] Create TTS product database schema
- [ ] Implement TtsAudioProduct and TtsProductPurchase models
- [ ] Update PaymentController for TTS products
- [ ] Test payment flow

### Week 2: Preview System
- [ ] Implement preview generation API
- [ ] Update TTS backend with preview endpoint
- [ ] Add background music mixing capability
- [ ] Test preview functionality

### Week 3: Access Control Integration
- [ ] Update AccessControlService for product purchases
- [ ] Implement secure content access endpoints
- [ ] Add purchase verification
- [ ] Test access control flow

### Week 4: Flutter Integration
- [ ] Update Flutter app to use Laravel API
- [ ] Implement preview player UI
- [ ] Add purchase flow UI
- [ ] Test end-to-end functionality

### Week 5: Testing & Polish
- [ ] Load testing for preview generation
- [ ] Security audit for access control
- [ ] UI/UX refinements
- [ ] Performance optimization

---

## 🎯 Success Metrics

### Technical Metrics:
- [ ] Preview generation < 5 seconds
- [ ] 99.9% uptime for payment processing
- [ ] Secure access control with 0 false positives
- [ ] Background music mixing quality > 95% user satisfaction

### Business Metrics:
- [ ] Conversion rate from preview to purchase > 15%
- [ ] Average revenue per user increase > 25%
- [ ] Customer satisfaction score > 4.5/5
- [ ] Support ticket reduction > 30%

---

## 🔒 Security Considerations

### Access Control:
- ✅ **Token Validation**: All requests authenticated through Laravel Sanctum
- ✅ **Purchase Verification**: Database-backed access control
- ✅ **Temporary URLs**: Preview audio expires after 2 hours
- ✅ **Rate Limiting**: Prevent abuse of preview generation

### Payment Security:
- ✅ **PayPal Integration**: Secure payment processing
- ✅ **Order Validation**: Server-side verification of all purchases
- ✅ **Audit Trail**: Complete purchase history tracking
- ✅ **Refund Support**: Built-in refund handling

### Content Protection:
- ✅ **Proxy Architecture**: No direct TTS backend access from Flutter
- ✅ **Content Encryption**: Optional audio encryption for premium content
- ✅ **Download Prevention**: Streaming-only for protected content
- ✅ **Device Limiting**: Optional device count restrictions

---

## 📞 Next Steps

1. **Review this implementation plan** with your team
2. **Choose Flutter integration approach** (Option A recommended)
3. **Set up development timeline** based on available resources
4. **Begin with Phase 1** (Database foundation)
5. **Test each phase** before proceeding to the next

**Need immediate assistance with implementation?**
- I can help implement any specific phase
- Provide code samples for complex integrations
- Debug issues during development
- Optimize performance bottlenecks

Would you like me to start implementing any specific phase of this plan?
