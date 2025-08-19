import 'package:collection/collection.dart';
import 'tts_api_service.dart';

/// Repository adds simple in-memory caching & combines data for UI.
class TtsRepository {
  final TtsApiService api;
  final Map<String, List<TtsProductDto>> _productsCache = {};
  final Map<int, ProductDetailDto> _productDetailCache = {};
  BackgroundMusicCatalog? _bgCatalog;
  EncryptionKeyDto? _encryptionKeyCache;
  List<String>? _languagesCache;
  DateTime? _languagesFetchedAt;

  TtsRepository(this.api);

  Future<List<String>> getLanguages({bool forceRefresh = false}) async {
    if (!forceRefresh &&
        _languagesCache != null &&
        _languagesFetchedAt != null &&
        DateTime.now().difference(_languagesFetchedAt!) <
            const Duration(minutes: 10)) {
      return _languagesCache!;
    }
    final langs = await api.fetchAccessibleLanguages();
    _languagesCache = langs;
    _languagesFetchedAt = DateTime.now();
    return langs;
  }

  Future<ProductDetailDto> getProductDetail(int id, {bool forceRefresh = false}) async {
    if (!forceRefresh && _productDetailCache.containsKey(id)) {
      return _productDetailCache[id]!;
    }
    final detail = await api.fetchProductDetail(id);
    _productDetailCache[id] = detail;
    return detail;
  }

  Future<BackgroundMusicCatalog> getBackgroundMusic({bool forceRefresh = false}) async {
    if (!forceRefresh && _bgCatalog != null) return _bgCatalog!;
    final cat = await api.fetchBackgroundMusic();
    _bgCatalog = cat;
    return cat;
  }

  Future<EncryptionKeyDto> getEncryptionKey({bool forceRefresh = false}) async {
    if (!forceRefresh && _encryptionKeyCache != null) return _encryptionKeyCache!;
    final key = await api.fetchEncryptionKey();
    _encryptionKeyCache = key;
    return key;
  }

  Future<List<TtsProductDto>> getProductsForLanguage(String language,
      {bool preview = false, bool forceRefresh = false}) async {
    if (!forceRefresh && _productsCache.containsKey(language)) {
      return _productsCache[language]!;
    }
    final list = await api.fetchProductsByLanguage(language, preview: preview);
    // Sort by access first then name
    final sorted = list.sorted((a, b) {
      if (a.hasAccess != b.hasAccess) {
        return a.hasAccess ? -1 : 1;
      }
      return a.displayName.compareTo(b.displayName);
    });
    _productsCache[language] = sorted;
    return sorted;
  }

  void clear() {
    _languagesCache = null;
    _productsCache.clear();
  _productDetailCache.clear();
  _bgCatalog = null;
  }
}
