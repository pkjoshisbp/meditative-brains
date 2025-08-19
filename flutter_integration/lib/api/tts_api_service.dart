import 'dart:convert';
import 'package:http/http.dart' as http;

/// Lightweight service for calling new Laravel TTS endpoints from Flutter.
/// Drop this file into your Flutter project (e.g. lib/services/) and adjust baseUrl + auth token provider.
class TtsApiService {
  final String baseUrl;
  final Future<String?> Function() tokenProvider;

  TtsApiService({
    required this.baseUrl,
    required this.tokenProvider,
  });

  /// Ensures we only have a single /api prefix regardless of how baseUrl is provided.
  String _apiRoot() {
    var b = baseUrl.trim();
    if (b.endsWith('/')) b = b.substring(0, b.length - 1);
    // If caller already passed .../api, keep it; else append /api
    if (!b.toLowerCase().endsWith('/api')) b = '$b/api';
    return b;
  }

  Map<String, String> _headers(String? token) => {
        'Content-Type': 'application/json',
        if (token != null) 'Authorization': 'Bearer $token',
        'Accept': 'application/json',
      };

  Future<List<String>> fetchAccessibleLanguages() async {
    final token = await tokenProvider();
  final uri = Uri.parse('${_apiRoot()}/tts/languages');
    final resp = await http.get(uri, headers: _headers(token));
    if (resp.statusCode == 200) {
      final data = jsonDecode(resp.body) as Map<String, dynamic>;
      final langs = (data['languages'] as List).cast<String>();
      return langs;
    }
    throw Exception('Failed to load languages: ${resp.statusCode} ${resp.body}');
  }

  Future<List<TtsProductDto>> fetchProductsByLanguage(String language,
      {bool preview = false}) async {
    final token = await tokenProvider();
  final uri = Uri.parse(
    '${_apiRoot()}/tts/language/$language/products${preview ? '?preview=1' : ''}');
    final resp = await http.get(uri, headers: _headers(token));
    if (resp.statusCode == 200) {
      final data = jsonDecode(resp.body) as Map<String, dynamic>;
      final list = (data['products'] as List)
          .map((e) => TtsProductDto.fromJson(e as Map<String, dynamic>))
          .toList();
      return list;
    }
    throw Exception('Failed to load products: ${resp.statusCode} ${resp.body}');
  }

  Future<ProductDetailDto> fetchProductDetail(int productId) async {
    final token = await tokenProvider();
  final uri = Uri.parse('${_apiRoot()}/tts/products/$productId/detail');
    final resp = await http.get(uri, headers: _headers(token));
    if (resp.statusCode == 200) {
      return ProductDetailDto.fromJson(jsonDecode(resp.body));
    }
    throw Exception('Failed product detail: ${resp.statusCode} ${resp.body}');
  }

  Future<BackgroundMusicCatalog> fetchBackgroundMusic() async {
    final token = await tokenProvider();
  final uri = Uri.parse('${_apiRoot()}/tts/background-music');
    final resp = await http.get(uri, headers: _headers(token));
    if (resp.statusCode == 200) {
      return BackgroundMusicCatalog.fromJson(jsonDecode(resp.body));
    }
    throw Exception('Failed to load background music: ${resp.statusCode} ${resp.body}');
  }

  Future<GeneratedAudioResult> generateMessageAudio({
    required String messageId,
    required String category,
    String? voice,
    double? speed,
  }) async {
    final token = await tokenProvider();
  final uri = Uri.parse('${_apiRoot()}/tts/generate-audio');
    final body = jsonEncode({
      'message_id': messageId,
      'category': category,
      if (voice != null) 'voice': voice,
      if (speed != null) 'speed': speed,
    });
    final resp = await http.post(uri, headers: _headers(token), body: body);
    final json = jsonDecode(resp.body);
    if (resp.statusCode == 200 && json['success'] == true) {
      return GeneratedAudioResult.fromJson(json);
    }
    throw Exception('Audio generation failed: ${resp.statusCode} ${resp.body}');
  }
}

class TtsProductDto {
  final int id;
  final String name;
  final String displayName;
  final String category;
  final String language;
  final double price;
  final String formattedPrice;
  final bool hasAccess;
  final String accessType;
  final bool previewAvailable;
  final List<String> sampleMessages;

  TtsProductDto({
    required this.id,
    required this.name,
    required this.displayName,
    required this.category,
    required this.language,
    required this.price,
    required this.formattedPrice,
    required this.hasAccess,
    required this.accessType,
    required this.previewAvailable,
    required this.sampleMessages,
  });

  factory TtsProductDto.fromJson(Map<String, dynamic> json) => TtsProductDto(
        id: json['id'] as int,
        name: json['name'] as String,
        displayName: json['display_name'] as String? ?? json['name'] as String,
        category: json['category'] as String,
        language: json['language'] as String,
        price: (json['price'] as num).toDouble(),
        formattedPrice: json['formatted_price'] as String? ?? '',
        hasAccess: json['has_access'] as bool? ?? false,
        accessType: json['access_type'] as String? ?? 'none',
        previewAvailable: json['preview_available'] as bool? ?? false,
        sampleMessages: (json['sample_messages'] as List?)
                ?.map((e) => e.toString())
                .toList() ??
            const [],
      );
}

class ProductDetailDto {
  final int id;
  final String name;
  final String displayName;
  final String category;
  final String language;
  final bool hasBackgroundMusic;
  final String? backgroundMusicTrack;
  final List<ProductTrackDto> tracks;

  ProductDetailDto({
    required this.id,
    required this.name,
    required this.displayName,
    required this.category,
    required this.language,
    required this.hasBackgroundMusic,
    required this.backgroundMusicTrack,
    required this.tracks,
  });

  factory ProductDetailDto.fromJson(Map<String,dynamic> json) {
    final product = json['product'] as Map<String,dynamic>;
    final tracks = (json['tracks'] as List? ?? [])
        .map((e) => ProductTrackDto.fromJson(e as Map<String,dynamic>))
        .toList();
    return ProductDetailDto(
      id: product['id'] as int,
      name: product['name'] as String,
      displayName: product['display_name'] as String? ?? product['name'] as String,
      category: product['category'] as String,
      language: product['language'] as String,
      hasBackgroundMusic: product['has_background_music'] as bool? ?? false,
      backgroundMusicTrack: product['background_music_track'] as String?,
      tracks: tracks,
    );
  }
}

class ProductTrackDto {
  final int index;
  final String url;
  final String title;
  ProductTrackDto({required this.index, required this.url, required this.title});
  factory ProductTrackDto.fromJson(Map<String,dynamic> json) => ProductTrackDto(
    index: json['index'] as int,
    url: json['url'] as String,
    title: json['title'] as String? ?? 'Track ${json['index']}'
  );
}

class BackgroundMusicCatalog {
  final Map<String,List<BackgroundMusicTrack>> variants; // variant -> list
  BackgroundMusicCatalog(this.variants);
  factory BackgroundMusicCatalog.fromJson(Map<String,dynamic> json) {
    final v = <String,List<BackgroundMusicTrack>>{};
    final raw = json['variants'] as Map<String,dynamic>? ?? {};
    raw.forEach((variant, list){
      v[variant] = (list as List).map((e)=>BackgroundMusicTrack.fromJson(e as Map<String,dynamic>)).toList();
    });
    return BackgroundMusicCatalog(v);
  }
  List<BackgroundMusicTrack> allTracks() => variants.values.expand((e)=>e).toList();
}

class BackgroundMusicTrack {
  final String file; // display name (without .enc)
  final String? encryptedFile; // actual encrypted filename with .enc
  final String variant;
  final String path; // storage relative
  final String url; // streaming endpoint
  
  BackgroundMusicTrack({
    required this.file, 
    this.encryptedFile,
    required this.variant, 
    required this.path, 
    required this.url
  });
  
  factory BackgroundMusicTrack.fromJson(Map<String,dynamic> json) => BackgroundMusicTrack(
    file: json['file'] as String,
    encryptedFile: json['encrypted_file'] as String?,
    variant: json['variant'] as String,
    path: json['path'] as String,
    url: json['url'] as String,
  );
  
  @override
  String toString() => file;
}

class GeneratedAudioResult {
  final bool success;
  final String? audioUrl;
  final double? duration;
  final String? voiceUsed;

  GeneratedAudioResult(
      {required this.success, this.audioUrl, this.duration, this.voiceUsed});

  factory GeneratedAudioResult.fromJson(Map<String, dynamic> json) =>
      GeneratedAudioResult(
        success: json['success'] == true,
        audioUrl: json['audio_url'] as String?,
        duration: (json['duration'] as num?)?.toDouble(),
        voiceUsed: json['voice_used'] as String?,
      );
}
