# Node.js → Laravel Migration — Complete Reference

> This document covers every step taken to migrate the `tts-backend` Node.js/MongoDB service to
> Laravel/MySQL, replacing the HTTP Express API with a **PHP Ratchet WebSocket** server.

---

## Table of Contents
1. [Background](#1-background)
2. [What Changed](#2-what-changed)
3. [Database Schema](#3-database-schema)
4. [PHP Eloquent Models](#4-php-eloquent-models)
5. [TTS Audio Generation Service](#5-tts-audio-generation-service)
6. [Ratchet WebSocket Server](#6-ratchet-websocket-server)
7. [WebSocket Protocol Reference](#7-websocket-protocol-reference)
8. [MongoDB → MySQL Data Migration](#8-mongodb--mysql-data-migration)
9. [Supervisor / Production Setup](#9-supervisor--production-setup)
10. [Flutter Integration](#10-flutter-integration)
11. [Environment Variables](#11-environment-variables)
12. [Testing the WebSocket](#12-testing-the-websocket)

---

## 1. Background

The original architecture had two separate processes:

| Layer | Technology | Port | Storage |
|---|---|---|---|
| Admin/web UI | Laravel (PHP) | 80/443 | MySQL |
| Flutter backend API | Node.js Express | 3001 | MongoDB |

The migration goal:
- **Consolidate** all backend logic into Laravel
- **Replace** the Node.js HTTP API with a **Ratchet WebSocket** accessible at `wss://mentalfitness.store:8091`
- **Migrate** all MongoDB documents to MySQL

---

## 2. What Changed

### Files created / modified

| Path | Purpose |
|---|---|
| `database/migrations/2026_04_09_171817_create_tts_categories_messages_languages_tables.php` | Creates `tts_languages`, `tts_source_categories`, `tts_motivation_messages` |
| `database/migrations/2026_04_09_171826_create_tts_attention_guides_audiobooks_table.php` | Creates `tts_attention_guides`, `tts_audiobooks`, `tts_audiobook_chapters` |
| `app/Models/TtsLanguage.php` | Eloquent: languages table |
| `app/Models/TtsSourceCategory.php` | Eloquent: categories table |
| `app/Models/TtsMotivationMessage.php` | Eloquent: messages + audio_urls (JSON cast) |
| `app/Models/TtsAttentionGuide.php` | Eloquent: attention guide records |
| `app/Models/TtsAudiobook.php` | Eloquent: audiobooks + hasMany chapters |
| `app/Models/TtsAudiobookChapter.php` | Eloquent: chapters + belongsTo audiobook |
| `app/Services/TtsAudioGeneratorService.php` | PHP drop-in for `audioGenerator.js` — Azure + VITS |
| `app/WebSocket/TtsWebSocketServer.php` | Ratchet `MessageComponentInterface` — all message handlers |
| `app/Console/Commands/TtsWebSocketServe.php` | `php artisan tts:websocket --port=8091` |
| `app/Console/Commands/MigrateMongoToMysql.php` | `php artisan tts:migrate-mongo` |
| `supervisor-tts-websocket.conf` | Supervisor program block (copy to `/etc/supervisor/conf.d/`) |
| `flutter/lib/config/api_endpoints.dart` | Added `ttsWsBase = 'wss://mentalfitness.store:8091'` |
| `config/services.php` | Added `azure_tts` key |

---

## 3. Database Schema

### `tts_languages`
| Column | Type | Notes |
|---|---|---|
| id | bigint PK auto | |
| code | varchar(20) unique | e.g. `en-US`, `hi-IN` |
| name | varchar(100) | Human name |
| local_name | varchar(100) nullable | Name in native language |
| is_active | tinyint(1) | default 1 |
| created_at / updated_at | timestamps | |

### `tts_source_categories`
| Column | Type | Notes |
|---|---|---|
| id | bigint PK auto | |
| mongo_id | varchar(30) nullable unique | Original MongoDB `_id` |
| category | varchar(255) | Category name |
| user_id | bigint FK → users nullable | |
| created_at / updated_at | timestamps | |

### `tts_motivation_messages`
| Column | Type | Notes |
|---|---|---|
| id | bigint PK auto | |
| mongo_id | varchar(30) nullable unique | |
| source_category_id | bigint FK → tts_source_categories nullable | |
| user_id | bigint FK → users nullable | |
| messages | JSON | Array of message strings |
| ssml_messages | JSON nullable | SSML counterparts |
| ssml | JSON nullable | Per-message SSML overrides |
| engine | varchar(20) | `azure` or `vits` |
| language | varchar(20) | e.g. `en-IN` |
| speaker | varchar(100) | Azure voice name / VITS speaker |
| speaker_style | varchar(50) nullable | |
| speaker_personality | varchar(50) nullable | |
| prosody_pitch / rate / volume | varchar(20) | default `medium` |
| audio_paths | JSON nullable | Storage-relative paths |
| audio_urls | JSON nullable | Public streaming URLs |
| editable | tinyint(1) | default 1 |
| created_at / updated_at | timestamps | |

### `tts_attention_guides`
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| mongo_id | varchar(30) nullable | |
| text | text | |
| language / speaker / engine | varchar | |
| speaker_style | varchar(50) nullable | |
| category | varchar(100) nullable | |
| speed | decimal(3,2) | default 1.00 |
| audio_path / audio_url | text nullable | |
| created_at / updated_at | timestamps | |

### `tts_audiobooks`
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| mongo_id | varchar(30) nullable | |
| book_title | varchar(255) unique | |
| book_author | varchar(255) nullable | |
| language / speaker / engine | varchar | |
| speaker_style / expression_style | varchar nullable | |
| prosody_rate / pitch / volume | varchar(20) | default `medium` |
| created_at / updated_at | timestamps | |

### `tts_audiobook_chapters`
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| audiobook_id | bigint FK → tts_audiobooks | CASCADE delete |
| chapter_number | int | |
| title | varchar(255) nullable | |
| plain_content | longtext | |
| ssml_content | longtext nullable | |
| audio_path / audio_url | text nullable | |
| status | enum(pending,generating,done,error) | default `pending` |
| created_at / updated_at | timestamps | |

---

## 4. PHP Eloquent Models

All models live in `app/Models/`. Quick relationship summary:

```
TtsSourceCategory  1──∞  TtsMotivationMessage
TtsAudiobook       1──∞  TtsAudiobookChapter
```

JSON cast columns (`messages`, `ssml`, `audio_urls` etc.) are automatically encoded/decoded by Laravel.

---

## 5. TTS Audio Generation Service

**File:** `app/Services/TtsAudioGeneratorService.php`

Drop-in PHP equivalent of `tts-backend/utils/audioGenerator.js`.

### Key methods

| Method | Description |
|---|---|
| `generateForMessage(string $text, array $options)` | Generate audio for one string. Returns `{relativePath, audioUrl}`. Caches by hash — re-calling is free. |
| `generateBatch(array $messages, array $options)` | Loop over messages, returns array of results. |
| `buildSSML(array $opts)` | Builds an Azure SSML document matching Node.js `buildSSML`. |
| `convertMarkupToSSML(string $text)` | Converts `[pause:N]`, `[rate:X]…[/rate]`, `**bold**`, etc. |
| `normaliseLanguageCode(string $lang)` | `hn-IN → hi-IN`, `en → en-US`, `en_IN → en-IN`. |

### Engine: `azure`
1. Builds SSML via `buildSSML()`
2. POSTs to `https://centralindia.tts.speech.microsoft.com/cognitiveservices/v1`
3. Gets back PCM WAV → converts to AAC via FFmpeg
4. Stores under `storage/app/audio-cache/{lang}/{category}/{speaker}/{slug}-{hash}.aac`

### Engine: `vits`
1. Resolves Python speaker (`p225`, `p227`, `p230`, `p245`, `hi-female`, `hi-male`)
2. Runs `tts-backend/tts-venv/bin/python3 tts-backend/run_infer.py`
3. Saves WAV → converts to AAC via FFmpeg

### Audio URL routing
Audio files are served via the existing Laravel `/audio/stream/…` signed-URL route. The `buildPaths()` method generates the correct `route('audio.stream.path', …)` URL automatically.

---

## 6. Ratchet WebSocket Server

### Architecture

```
Flutter app  ──wss:8091──►  Ratchet IoServer
                               └─ HttpServer
                                   └─ WsServer
                                       └─ TtsWebSocketServer  (MessageComponentInterface)
                                           ├─ Auth gate (Sanctum token)
                                           ├─ Category CRUD
                                           ├─ Message CRUD
                                           ├─ Audio generation (Azure/VITS)
                                           ├─ Audiobook CRUD + chapter generation
                                           └─ Flutter log submission
```

### Start manually
```bash
php artisan tts:websocket --port=8091
```

### Source files
- `app/WebSocket/TtsWebSocketServer.php` — all message handlers
- `app/Console/Commands/TtsWebSocketServe.php` — Artisan command

---

## 7. WebSocket Protocol Reference

All messages are **JSON objects**. The client sends `{ "action": "...", ... }` and receives `{ "event": "...", ... }`.

### Connection lifecycle

```
Client connects to wss://mentalfitness.store:8091
  ← { "event": "connected", "message": "..." }

Client sends auth:
  → { "action": "auth", "token": "<sanctum-personal-access-token>" }
  ← { "event": "auth.success", "user": { "id": 1, "name": "...", "email": "..." } }
  OR
  ← { "event": "error", "message": "Invalid or expired token" }

Client sends any action:
  → { "action": "<action>", ...params }
  ← { "event": "<response-event>", "data": ... }
  OR
  ← { "event": "error", "message": "..." }
```

---

### Actions

#### `ping`
```json
→ { "action": "ping" }
← { "event": "pong", "time": "2026-04-09T17:00:00.000Z" }
```

---

#### Languages

##### `language.list`
```json
→ { "action": "language.list" }
← { "event": "language.list", "data": [ { "id": 1, "code": "en-US", "name": "English (US)", ... } ] }
```

---

#### Categories

##### `category.list`
```json
→ { "action": "category.list" }
← { "event": "category.list", "data": [ { "id": 1, "category": "Motivation", ... } ] }
```

##### `category.create`
```json
→ { "action": "category.create", "category": "Sleep Meditation" }
← { "event": "category.created", "data": { "id": 5, "category": "Sleep Meditation", ... } }
```

##### `category.update`
```json
→ { "action": "category.update", "id": 5, "category": "Deep Sleep Meditation" }
← { "event": "category.updated", "data": { ... } }
```

##### `category.delete`
```json
→ { "action": "category.delete", "id": 5 }
← { "event": "category.deleted", "id": 5 }
```

---

#### Messages

##### `message.list`
```json
→ { "action": "message.list" }
← { "event": "message.list", "data": [ { "id": 1, "messages": ["You are great.", "..."], ... } ] }
```

##### `message.listByCategory`
```json
→ { "action": "message.listByCategory", "categoryId": 3 }
← { "event": "message.listByCategory", "categoryId": 3, "data": [ ... ] }
```
> `categoryId` can be a MySQL `id` (integer) **or** legacy MongoDB `_id` string.

##### `message.create`
```json
→ {
    "action": "message.create",
    "categoryId": 3,
    "messages": ["Message one.", "Message two."],
    "ssmlMessages": [],
    "ssml": [],
    "engine": "azure",
    "language": "en-IN",
    "speaker": "en-US-AvaMultilingualNeural",
    "speakerStyle": "calm",
    "speakerPersonality": null,
    "prosodyRate": "medium",
    "prosodyPitch": "medium",
    "prosodyVolume": "medium"
  }
← { "event": "message.created", "data": { "id": 42, ... } }
```

##### `message.update`
```json
→ { "action": "message.update", "id": 42, "language": "en-GB", "speaker": "en-GB-SoniaNeural" }
← { "event": "message.updated", "data": { ... } }
```

##### `message.delete`
```json
→ { "action": "message.delete", "id": 42 }
← { "event": "message.deleted", "id": 42 }
```

---

#### Audio Generation

##### `audio.generate` — single message
```json
→ {
    "action": "audio.generate",
    "text": "You can achieve anything you set your mind to.",
    "engine": "azure",
    "language": "en-IN",
    "speaker": "en-US-AvaMultilingualNeural",
    "speakerStyle": "calm",
    "category": "motivation"
  }
← { "event": "audio.generating", "text": "You can achieve..." }
← { "event": "audio.generated", "data": { "relativePath": "audio-cache/en-IN/...", "audioUrl": "https://..." } }
```

##### `audio.generateCategory` — generate all messages in a category
```json
→ { "action": "audio.generateCategory", "categoryId": 3 }
← { "event": "audio.generateCategory.start", "total": 12 }
← { "event": "audio.generateCategory.progress", "messageId": 8, "done": 1, "errors": [] }
... (one progress event per message)
← { "event": "audio.generateCategory.complete", "generated": 12 }
```

##### `audio.attentionGuide` — generate attention guide audio
```json
→ {
    "action": "audio.attentionGuide",
    "text": "Take a deep breath and relax...",
    "language": "en-IN",
    "speaker": "en-US-AvaMultilingualNeural",
    "speed": "slow",
    "engine": "azure",
    "category": "attention-guide"
  }
← { "event": "audio.attentionGuide", "data": { "relativePath": "...", "audioUrl": "..." } }
```

##### `audio.reminder` — generate reminder audio
```json
→ {
    "action": "audio.reminder",
    "text": "Time for your evening meditation!",
    "language": "en-US",
    "speaker": "en-US-AriaNeural",
    "engine": "azure"
  }
← { "event": "audio.reminder", "data": { "relativePath": "...", "audioUrl": "..." } }
```

---

#### Audiobooks

##### `audiobook.list`
```json
→ { "action": "audiobook.list" }
← { "event": "audiobook.list", "data": [ { "id": 1, "book_title": "...", "chapters": [...] } ] }
```

##### `audiobook.get`
```json
→ { "action": "audiobook.get", "id": 1 }
← { "event": "audiobook.get", "data": { "id": 1, "chapters": [ ... full chapter objects ... ] } }
```

##### `audiobook.upsert`
```json
→ {
    "action": "audiobook.upsert",
    "book_title": "The Power of Now",
    "book_author": "Eckhart Tolle",
    "language": "en-US",
    "speaker": "en-US-GuyNeural",
    "engine": "azure",
    "speaker_style": "calm",
    "prosody_rate": "slow",
    "prosody_pitch": "medium",
    "prosody_volume": "medium",
    "chapters": [
      { "chapter_number": 1, "title": "You Are Not Your Mind", "plain_content": "..." }
    ]
  }
← { "event": "audiobook.upserted", "data": { "id": 3, "chapters": [...] } }
```

##### `audiobook.delete`
```json
→ { "action": "audiobook.delete", "id": 3 }
← { "event": "audiobook.deleted", "id": 3 }
```

##### `audiobook.generateChapter`
```json
→ { "action": "audiobook.generateChapter", "chapterId": 7 }
← { "event": "audiobook.chapter.generating", "chapterId": 7 }
← { "event": "audiobook.chapter.done", "data": { "id": 7, "audio_url": "...", "status": "done" } }
```

---

#### Logs

##### `logs.submit`
```json
→ {
    "action": "logs.submit",
    "logs": "2026-04-09 INFO AppStarted...",
    "deviceInfo": "Samsung Galaxy S21 / Android 13"
  }
← { "event": "logs.submitted", "file": "app_logs_2026-04-09T17-30-00.txt" }
```

---

#### Mental Fitness TTS Catalog
> These actions replace the four REST calls in `tts_api_service.dart` (`fetchAccessibleLanguages`, `fetchProductsByLanguage`, `fetchProductDetail`, `fetchBackgroundMusic`).

##### `tts.language.list`
Returns every TTS language for which the authenticated user has at least one accessible product.
```json
→ { "action": "tts.language.list" }
← {
    "event": "tts.language.list",
    "success": true,
    "languages": ["en-IN", "hi-IN"],
    "total": 2
  }
```

##### `tts.product.listByLanguage`
Lists products for a language. Without `preview: true` only products the user has access to are returned.
```json
→ { "action": "tts.product.listByLanguage", "language": "en-IN", "preview": false }
← {
    "event": "tts.product.listByLanguage",
    "success": true,
    "language": "en-IN",
    "products": [
      {
        "id": 1,
        "name": "supreme-confidence",
        "display_name": "Supreme Confidence",
        "category": "confidence",
        "language": "en-IN",
        "price": 299,
        "formatted_price": "₹299",
        "has_access": true,
        "access_type": "product_purchase",
        "preview_available": false,
        "sample_messages": []
      }
    ],
    "count": 1,
    "preview_mode": false
  }
```

##### `tts.product.detail`
Returns full product metadata plus signed, playable audio track URLs. Requires the user to have entitlement; returns `code: "access_denied"` otherwise.
```json
→ { "action": "tts.product.detail", "productId": 1 }
← {
    "event": "tts.product.detail",
    "success": true,
    "product": {
      "id": 1,
      "name": "supreme-confidence",
      "display_name": "Supreme Confidence",
      "category": "confidence",
      "language": "en-IN",
      "total_tracks": 30,
      "has_background_music": true,
      "background_music_track": "calm-piano",
      "background_music_url": "https://mentalfitness.store/audio/signed-stream?..."
    },
    "tracks": [
      { "index": 0, "url": "https://mentalfitness.store/audio/signed-stream?...", "title": "Supreme Confidence #1" }
    ],
    "access": { "has_access": true, "access_type": "product_purchase" }
  }
```

Access-denied response:
```json
← {
    "event": "error",
    "code": "access_denied",
    "product_id": 1,
    "category": "confidence",
    "available_for_purchase": true,
    "message": "No entitlement for this product or category"
  }
```

##### `tts.backgroundMusic.list`
Returns all background music files (original + encrypted variants) with signed playback URLs.
```json
→ { "action": "tts.backgroundMusic.list" }
← {
    "event": "tts.backgroundMusic.list",
    "success": true,
    "variants": {
      "original": [
        {
          "file": "calm-piano.mp3",
          "variant": "original",
          "path": "bg-music/original/calm-piano.mp3",
          "url": "https://mentalfitness.store/audio/signed-stream?..."
        }
      ],
      "encrypted": [ ]
    },
    "total": 1
  }
```

---

### Error response
Any action failure returns:
```json
{ "event": "error", "message": "Human-readable error description" }
```

---

## 8. MongoDB → MySQL Data Migration

### Step 1 — Export from MongoDB

On the server running MongoDB:

```bash
cd /tmp

mongoexport \
  --uri="mongodb://localhost:27017/mentalfitness" \
  --collection=categories \
  --jsonArray \
  --out=categories.json

mongoexport \
  --uri="mongodb://localhost:27017/mentalfitness" \
  --collection=motivationmessages \
  --jsonArray \
  --out=messages.json

mongoexport \
  --uri="mongodb://localhost:27017/mentalfitness" \
  --collection=languages \
  --jsonArray \
  --out=languages.json

mongoexport \
  --uri="mongodb://localhost:27017/mentalfitness" \
  --collection=audiobooks \
  --jsonArray \
  --out=audiobooks.json
```

### Step 2 — Copy to Laravel

```bash
mkdir -p /var/www/clients/client1/web71/web/storage/app/mongo-export
cp /tmp/{categories,messages,languages,audiobooks}.json \
   /var/www/clients/client1/web71/web/storage/app/mongo-export/
```

### Step 3 — Run the Artisan command

```bash
cd /var/www/clients/client1/web71/web
php artisan tts:migrate-mongo
```

The command is **idempotent** — safe to run multiple times. It uses `firstOrCreate` keyed on `mongo_id`.

### Step 4 — Migrate audio files (optional)

If you want to serve audio from Laravel's `storage/app/audio-cache` rather than the Node.js folder:

```bash
rsync -av /var/www/clients/client1/web71/web/tts-backend/audio-cache/ \
          /var/www/clients/client1/web71/web/storage/app/audio-cache/
```

---

## 9. Supervisor / Production Setup

### Copy config
```bash
sudo cp /var/www/clients/client1/web71/web/supervisor-tts-websocket.conf \
        /etc/supervisor/conf.d/tts-websocket.conf
```

### Enable
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start tts-websocket
sudo supervisorctl status
```

### Logs
```bash
tail -f /var/log/supervisor/tts-websocket.log
```

### Restart after code changes
```bash
sudo supervisorctl restart tts-websocket
```

---

## 10. Flutter Integration

### `flutter/lib/config/api_endpoints.dart`

```dart
class ApiEndpoints {
  static const nodeBase    = 'https://mentalfitness.store:3001/api';  // legacy
  static const laravelBase = 'https://mentalfitness.store/api';
  static const ttsWsBase   = 'wss://mentalfitness.store:8091';         // NEW
  // ...
}
```

### Recommended Flutter WebSocket client

Add to `pubspec.yaml`:
```yaml
dependencies:
  web_socket_channel: ^2.4.0
```

### Usage pattern

```dart
import 'package:web_socket_channel/web_socket_channel.dart';
import 'dart:convert';

class TtsWebSocketService {
  late WebSocketChannel _channel;

  void connect(String token) {
    _channel = WebSocketChannel.connect(
      Uri.parse(ApiEndpoints.ttsWsBase),
    );
    // Authenticate immediately after connecting
    _send({'action': 'auth', 'token': token});
    _channel.stream.listen(_onMessage);
  }

  void _onMessage(dynamic raw) {
    final msg = jsonDecode(raw as String) as Map<String, dynamic>;
    switch (msg['event']) {
      case 'auth.success':
        print('Authenticated: ${msg['user']}');
        break;
      case 'category.list':
        // handle categories
        break;
      case 'audio.generated':
        final audioUrl = msg['data']['audioUrl'];
        // play audio
        break;
      case 'error':
        print('WS error: ${msg['message']}');
        break;
    }
  }

  void listCategories() => _send({'action': 'category.list'});

  void generateAudio({
    required String text,
    String language = 'en-IN',
    String speaker  = 'en-US-AvaMultilingualNeural',
    String engine   = 'azure',
    String category = 'motivation',
  }) {
    _send({
      'action':   'audio.generate',
      'text':     text,
      'language': language,
      'speaker':  speaker,
      'engine':   engine,
      'category': category,
    });
  }

  void _send(Map<String, dynamic> data) {
    _channel.sink.add(jsonEncode(data));
  }

  void dispose() => _channel.sink.close();
}
```

### Replacing previous Node.js calls

| Old (Node HTTP) | New (WebSocket action) |
|---|---|
| `GET :3001/api/category` | `category.list` |
| `POST :3001/api/category` | `category.create` |
| `PUT :3001/api/category/:id` | `category.update` |
| `DELETE :3001/api/category/:id` | `category.delete` |
| `GET :3001/api/motivationMessage` | `message.list` |
| `GET :3001/api/motivationMessage?category=X` | `message.listByCategory` |
| `POST :3001/api/motivationMessage` | `message.create` |
| `PUT :3001/api/motivationMessage/:id` | `message.update` |
| `DELETE :3001/api/motivationMessage/:id` | `message.delete` |
| `POST :3001/api/motivationMessage/generate` | `audio.generateCategory` |
| `POST :3001/api/attention-guide/audio` | `audio.attentionGuide` |
| `POST :3001/api/reminder/audio` | `audio.reminder` |
| `GET :3001/api/language` | `language.list` |
| `GET :3001/api/audioBook` | `audiobook.list` |
| `POST :3001/api/audioBook` | `audiobook.upsert` |
| `DELETE :3001/api/audioBook/:id` | `audiobook.delete` |
| `POST :3001/api/logs` | `logs.submit` |

### Replacing Mental Fitness REST calls (`TtsApiService`)

| Old (Laravel REST) | New (WebSocket action) |
|---|---|
| `GET /api/tts/languages` | `tts.language.list` |
| `GET /api/tts/language/:lang/products` | `tts.product.listByLanguage` |
| `GET /api/tts/products/:id/detail` | `tts.product.detail` |
| `GET /api/tts/background-music` | `tts.backgroundMusic.list` |

---

## 11. Environment Variables

Add these to `.env` (they were already used by Node.js):

```env
# Azure TTS (same key used by Node.js .env)
AZURE_KEY=your_azure_speech_key_here
AZURE_REGION=centralindia
```

The `config/services.php` `azure_tts` block now reads these values.

---

## 12. Testing the WebSocket

### Quick test with `websocat`

```bash
# Install websocat
cargo install websocat
# OR
sudo apt-get install websocat

# Connect
websocat ws://localhost:8091

# In the session, type:
{"action":"auth","token":"YOUR_SANCTUM_TOKEN"}
{"action":"ping"}
{"action":"category.list"}
{"action":"audio.generate","text":"Hello world","language":"en-IN","speaker":"en-US-AvaMultilingualNeural","engine":"azure"}
```

### Test with Node.js (one-off script)

```js
const ws = new (require('ws'))('ws://localhost:8091');
ws.on('open', () => ws.send(JSON.stringify({ action: 'auth', token: 'TOKEN' })));
ws.on('message', d => console.log(JSON.parse(d)));
```

### Verify supervisor is running
```bash
sudo supervisorctl status tts-websocket
# expected:  tts-websocket  RUNNING  pid 12345, uptime 0:01:23
```

---

*Document generated automatically as part of Node.js → Laravel migration.*
*Last updated: April 2026*
