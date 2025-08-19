import 'dart:convert';
import 'package:shared_preferences/shared_preferences.dart';
import '../api/tts_api_service.dart';
import '../screens/meditative_minds_audio_screen.dart';

/// Stores last selected language, selected product IDs, and playback config.
class SequencePrefs {
  static const _key = 'meditative_sequence_v1';

  Future<void> save({required String language, required List<TtsProductDto> products, required PlaybackConfig config}) async {
    final prefs = await SharedPreferences.getInstance();
    final map = {
      'language': language,
      'product_ids': products.map((p) => p.id).toList(),
      'config': {
        'repeat': config.repeatCount,
        'bgVol': config.bgMusicVolume,
        'msgInt': config.messageInterval.inMilliseconds,
        'repInt': config.repeatInterval.inMilliseconds,
        'fadeIn': config.fadeIn,
        'fadeOut': config.fadeOut,
      }
    };
    await prefs.setString(_key, jsonEncode(map));
  }

  Future<SequenceRestore?> restore(Future<TtsProductDto?> Function(int id) resolver) async {
    final prefs = await SharedPreferences.getInstance();
    final raw = prefs.getString(_key);
    if (raw == null) return null;
    try {
      final json = jsonDecode(raw) as Map<String, dynamic>;
      final ids = (json['product_ids'] as List).cast<int>();
      final items = <TtsProductDto>[];
      for (final id in ids) {
        final p = await resolver(id);
        if (p != null) items.add(p);
      }
      final c = json['config'] as Map<String, dynamic>;
      final cfg = PlaybackConfig(
        repeatCount: (c['repeat'] as num).toInt(),
        bgMusicVolume: (c['bgVol'] as num).toDouble(),
        messageInterval: Duration(milliseconds: c['msgInt'] as int),
        repeatInterval: Duration(milliseconds: c['repInt'] as int),
        fadeIn: c['fadeIn'] as bool,
        fadeOut: c['fadeOut'] as bool,
      );
      return SequenceRestore(language: json['language'] as String, products: items, config: cfg);
    } catch (_) {
      return null;
    }
  }
}

class SequenceRestore {
  final String language;
  final List<TtsProductDto> products;
  final PlaybackConfig config;
  SequenceRestore({required this.language, required this.products, required this.config});
}
