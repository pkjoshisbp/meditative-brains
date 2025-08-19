# Flutter Integration Snippets for New Laravel TTS Endpoints

This folder contains drop‑in code you can copy into your Flutter project to consume the new backend endpoints you just added.

## Added Laravel Endpoints Recap
- `GET /api/tts/languages` -> List languages with at least one accessible product (auth)
- `GET /api/tts/language/{language}/products` -> List products for a language (auth). Add `?preview=1` to also include inaccessible items for upsell.
- `POST /api/tts/generate-audio` -> Generate audio for a motivation message (existing) with access control.

Each product now returns `display_name` (language + name) so you can show it directly.

## Files Here
| File | Purpose |
|------|---------|
| `lib/api/tts_api_service.dart` | Low level HTTP client for endpoints. |
| `lib/api/tts_repository.dart` | Adds simple caching + sorting for UI. |
| `lib/widgets/tts_language_selector.dart` | Dropdown that shows only accessible languages. |
| `lib/widgets/tts_product_list.dart` | List view rendering products with preview & purchase hooks. |
| `lib/screens/meditative_minds_audio_screen.dart` | Full sequence builder UI with selection, reorder, settings, playback status. |
| `lib/screens/meditative_music_screen.dart` | Music-only categories & tracks UI scaffold. |
| `lib/screens/navigation_entrypoints.dart` | Routes + launcher card to link new screens. |
| `lib/playback/sequence_player.dart` | just_audio based sequence player (voice + bg channels). |
| `lib/persistence/sequence_prefs.dart` | SharedPreferences persistence for last session. |
| `lib/providers/tts_playback_controller.dart` | Simple playback state abstraction (legacy alternative). |
| `lib/purchase/purchase_flow.dart` | Purchase handler interface + mock implementation. |
| `lib/state/providers.dart` | Riverpod providers for API, repo, and player. |

## Usage Example
```dart
final ttsService = TtsApiService(
  baseUrl: 'https://YOUR_DOMAIN',
  tokenProvider: () async => authStore.token, // implement
);
final repo = TtsRepository(ttsService);

class TtsScreen extends StatefulWidget { /* ... */ }
class _TtsScreenState extends State<TtsScreen> {
  String _language = 'en';
  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        TtsLanguageSelector(
          repo: repo,
          initial: _language,
          onSelected: (lang) => setState(() => _language = lang),
        ),
        Expanded(
          child: TtsProductList(
            repo: repo,
            language: _language,
            onPreview: (p) => _previewProduct(p),
            onPurchase: (p) => _startPurchaseFlow(p),
          ),
        ),
      ],
    );
  }

  Future<void> _previewProduct(TtsProductDto p) async {
    // Use existing preview endpoint if you have one or build a small dialog showing sample messages
  }

  void _startPurchaseFlow(TtsProductDto p) {
    // Launch your purchase screen/payment; after completion refresh:
    repo.getProductsForLanguage(p.language, forceRefresh: true);
  }
}
```

## Audio Generation (Existing Endpoint)
```dart
final result = await ttsService.generateMessageAudio(
  messageId: 'abc123',
  category: 'Motivation',
  voice: 'en-US-AriaNeural',
  speed: 1.0,
);
print(result.audioUrl);
```

## Notes
- Cache TTL kept short (10 minutes). Adjust for your UX.
- For offline support, persist product metadata in local storage (e.g. Hive) – not included here.
- Add error handling / retry wrappers (e.g. exponential backoff) in production.
- If you later expose a public unauthenticated language list, adapt the service accordingly.
- Sequence builder persists last session automatically (language, product IDs, settings) if you pass a `SequencePrefs` instance.
- Replace mock preview & purchase stubs with real backend calls.

## Next Ideas
- Add Riverpod/Bloc providers wrapping `TtsRepository`.
  * Riverpod version added (`state/providers.dart`).
- Implement preview playback using just AudioPlayer with the preview URLs returned from backend. 
- Add purchase success listener to log conversion events client-side (optional redundancy).
- Background music: expand `SequencePlayer` to load per-product `background_music_url` when available.
- Pause / resume & current message display implemented.
- Background override selector (UI placeholder ready; implement product bg list & custom file picker if needed).
- Add skip/next controls & progress indicators per message.
