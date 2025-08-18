import 'package:flutter/material.dart';
import '../api/tts_repository.dart';
import '../api/tts_api_service.dart';
import '../widgets/tts_language_selector.dart';
import '../api/tts_api_service.dart' show TtsProductDto, ProductDetailDto, BackgroundMusicCatalog, BackgroundMusicTrack;
import '../playback/sequence_player.dart';
import '../persistence/sequence_prefs.dart';
import '../purchase/purchase_flow.dart';

/// Screen: Meditative Minds Audio
/// Features:
/// - Language filter (dropdown) using new /api/tts/languages
/// - Optional category text filter / search
/// - List of products (<= ~30 per language) with:
///   * Checkbox to include in play queue
///   * Drag handle to reorder chosen sequence
/// - Playback configuration panel (background music volume, intervals, repeat count)
///   mirroring existing home screen options (wire hook points provided as callbacks/state fields)
class MeditativeMindsAudioScreen extends StatefulWidget {
  final TtsRepository repo;
  final PurchaseHandler? purchaseHandler;
  final SequencePrefs? prefs;
  final SequencePlayer? externalPlayer; // allow injection for tests
  const MeditativeMindsAudioScreen({super.key, required this.repo, this.purchaseHandler, this.prefs, this.externalPlayer});

  @override
  State<MeditativeMindsAudioScreen> createState() => _MeditativeMindsAudioScreenState();
}

class PlaybackConfig {
  int repeatCount;
  double bgMusicVolume; // 0..1
  Duration messageInterval; // between messages
  Duration repeatInterval; // between loops
  bool fadeIn;
  bool fadeOut;
  PlaybackConfig({
    this.repeatCount = 1,
    this.bgMusicVolume = 0.5,
    this.messageInterval = const Duration(seconds: 2),
    this.repeatInterval = const Duration(seconds: 0),
    this.fadeIn = false,
    this.fadeOut = false,
  });
  PlaybackConfig copyWith({
    int? repeatCount,
    double? bgMusicVolume,
    Duration? messageInterval,
    Duration? repeatInterval,
    bool? fadeIn,
    bool? fadeOut,
  }) => PlaybackConfig(
        repeatCount: repeatCount ?? this.repeatCount,
        bgMusicVolume: bgMusicVolume ?? this.bgMusicVolume,
        messageInterval: messageInterval ?? this.messageInterval,
        repeatInterval: repeatInterval ?? this.repeatInterval,
        fadeIn: fadeIn ?? this.fadeIn,
        fadeOut: fadeOut ?? this.fadeOut,
      );
}

class _MeditativeMindsAudioScreenState extends State<MeditativeMindsAudioScreen> {
  String _language = '';
  String _search = '';
  bool _loadingProducts = false;
  bool _restoring = true;
  List<TtsProductDto> _allProducts = [];
  final List<TtsProductDto> _selected = [];
  final ScrollController _scroll = ScrollController();
  PlaybackConfig _config = PlaybackConfig();
  final Map<int, ProductDetailDto> _detailCache = {};
  BackgroundMusicCatalog? _bgCatalog;
  BackgroundMusicTrack? _sessionBg;
  @override
  void initState() {
    super.initState();
    _player = widget.externalPlayer ?? SequencePlayer();
    _playerSub = _player.stream.listen((_) => setState(() {}));
    _restoreLast();
  }

  Future<void> _restoreLast() async {
    try {
      final restored = await widget.prefs?.restore((id) async {
        // naive: search current language cache else fetch all languages sequentially (simplified)
        for (final lang in await widget.repo.getLanguages()) {
          final prods = await widget.repo.getProductsForLanguage(lang);
          final found = prods.where((p) => p.id == id).cast<TtsProductDto?>().firstWhere((e) => e != null, orElse: () => null);
          if (found != null) return found;
        }
        return null;
      });
      if (restored != null) {
        await _loadLanguage(restored.language, restoring: true);
        setState(() {
          _language = restored.language;
          _selected.addAll(restored.products);
          _config = restored.config;
        });
      }
    } finally {
      if (mounted) setState(() => _restoring = false);
    }
  }

  @override
  void dispose() {
    _playerSub?.cancel();
    _player.dispose();
    super.dispose();
  }
  late final SequencePlayer _player;
  StreamSubscription? _playerSub;
  final _prefs = SequencePrefs();

  Future<void> _loadLanguage(String lang, {bool restoring = false}) async {
    setState(() {
      _language = lang;
      _loadingProducts = true;
      _allProducts = [];
      if (!restoring) _selected.clear();
    });
    final list = await widget.repo.getProductsForLanguage(lang, forceRefresh: true);
    setState(() {
      _allProducts = list;
      _loadingProducts = false;
    });
  }

  List<TtsProductDto> get _filteredProducts {
    if (_search.isEmpty) return _allProducts;
    final q = _search.toLowerCase();
    return _allProducts.where((p) =>
        p.displayName.toLowerCase().contains(q) || p.category.toLowerCase().contains(q)).toList();
  }

  void _toggleSelection(TtsProductDto p) {
    setState(() {
      if (_selected.contains(p)) {
        _selected.remove(p);
      } else {
        _selected.add(p);
      }
    });
  }

  void _onReorder(int oldIndex, int newIndex) {
    setState(() {
      if (newIndex > oldIndex) newIndex -= 1;
      final item = _selected.removeAt(oldIndex);
      _selected.insert(newIndex, item);
    });
  }

  void _startPlayback() async {
    if (_selected.isEmpty) return;
    await widget.prefs?.save(language: _language, products: _selected, config: _config);
    _player.play(
      products: List.of(_selected),
      config: _config,
      resolveItem: (p, loop, index) async {
        final detail = await _ensureDetail(p);
        if (detail == null || detail.tracks.isEmpty) return null;
        final track = detail.tracks[index % detail.tracks.length];
        // For now message text = product display name (no transcript per track)
        return SequenceItem(messageText: detail.displayName, audioUrl: track.url);
      },
      resolveBackgroundMusic: (p) async {
        if (_sessionBg != null) return _sessionBg!.url;
        final detail = await _ensureDetail(p);
        // If product has a default music track that appears in catalog, try to map it.
        return detail?.backgroundMusicTrack;
      },
      onMessage: (_) {},
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Meditative Minds Audio'),
        actions: [
          if (_player.state.isPlaying)
            IconButton(icon: const Icon(Icons.pause), onPressed: () => _player.pause())
          else if (_player.state.phase == PlayerPhase.playing && !_player.state.isCompleted)
            IconButton(icon: const Icon(Icons.play_arrow), onPressed: () => _player.resume()),
          IconButton(icon: const Icon(Icons.stop), onPressed: _player.state.phase != PlayerPhase.idle ? () => _player.stop() : null)
        ],
      ),
      body: Column(
        children: [
          if (_restoring) const LinearProgressIndicator(minHeight: 2),
          Padding(
            padding: const EdgeInsets.all(8.0),
            child: Row(
              children: [
                Expanded(
                  child: TtsLanguageSelector(
                    repo: widget.repo,
                    initial: _language.isEmpty ? null : _language,
                    onSelected: _loadLanguage,
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: TextField(
                    decoration: const InputDecoration(
                      labelText: 'Search / Category filter',
                      prefixIcon: Icon(Icons.search),
                    ),
                    onChanged: (v) => setState(() => _search = v),
                  ),
                ),
              ],
            ),
          ),
          _buildConfigPanel(),
          _buildBackgroundMusicSelector(),
          _buildPlaybackStatusBar(),
          Expanded(
            child: _loadingProducts
                ? const Center(child: CircularProgressIndicator())
                : Row(
                    children: [
                      Expanded(child: _buildProductList()),
                      VerticalDivider(width: 1, color: Colors.grey.shade400),
                      Expanded(child: _buildSelectedList()),
                    ],
                  ),
          ),
          SafeArea(
            child: Padding(
              padding: const EdgeInsets.all(12.0),
              child: Row(
                children: [
                  Text('Selected: ${_selected.length}'),
                  const Spacer(),
                  ElevatedButton.icon(
                    onPressed: _selected.isEmpty ? null : _startPlayback,
                    icon: const Icon(Icons.play_arrow),
                    label: const Text('Play Sequence'),
                  )
                ],
              ),
            ),
          )
        ],
      ),
    );
  }

  Future<ProductDetailDto?> _ensureDetail(TtsProductDto p) async {
    if (_detailCache.containsKey(p.id)) return _detailCache[p.id];
    try {
      final detail = await widget.repo.getProductDetail(p.id);
      _detailCache[p.id] = detail;
      return detail;
    } catch (_) {
      return null;
    }
  }

  Future<void> _loadBgMusic() async {
    try {
      final cat = await widget.repo.getBackgroundMusic();
      if (mounted) setState(()=> _bgCatalog = cat);
    } catch (_) {}
  }

  Widget _buildProductList() {
    final items = _filteredProducts;
    if (items.isEmpty) {
      return const Center(child: Text('No products in this language'));
    }
    return ListView.builder(
      controller: _scroll,
      itemCount: items.length,
      itemBuilder: (ctx, i) {
        final p = items[i];
        final selected = _selected.contains(p);
        return ListTile(
          dense: true,
          leading: Checkbox(
            value: selected,
            onChanged: p.hasAccess ? (_) => _toggleSelection(p) : null,
          ),
          title: Text(p.displayName),
          subtitle: Text('${p.category}${p.hasAccess ? '' : ' • Locked'}'),
          trailing: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              if (p.previewAvailable)
                IconButton(
                  icon: const Icon(Icons.play_circle_outline),
                  tooltip: 'Preview Sample',
                  onPressed: () => _previewSample(p),
                ),
              if (!p.hasAccess)
                IconButton(
                  icon: const Icon(Icons.shopping_cart_outlined),
                  tooltip: 'Purchase',
                  onPressed: () => _purchaseProduct(p),
                ),
            ],
          ),
          onTap: p.hasAccess ? () => _toggleSelection(p) : () => _purchaseProduct(p),
        );
      },
    );
  }

  Widget _buildSelectedList() {
    if (_selected.isEmpty) {
      return const Center(child: Text('Selected queue empty'));
    }
    return ReorderableListView.builder(
      itemCount: _selected.length,
      onReorder: _onReorder,
      buildDefaultDragHandles: true,
      itemBuilder: (ctx, i) {
        final p = _selected[i];
        return ListTile(
          key: ValueKey(p.id),
          title: Text(p.displayName),
          subtitle: Text(p.category),
          trailing: IconButton(
            icon: const Icon(Icons.clear),
            onPressed: () => setState(() => _selected.removeAt(i)),
          ),
        );
      },
    );
  }

  Widget _buildConfigPanel() {
    return ExpansionTile(
      maintainState: true,
      initiallyExpanded: true,
      title: const Text('Playback Settings'),
      children: [
        Padding(
          padding: const EdgeInsets.all(8.0),
          child: Wrap(
            runSpacing: 12,
            spacing: 24,
            children: [
              _numberField(label: 'Repeat Count', value: _config.repeatCount, min: 1, max: 99,
                  onChanged: (v) => setState(() => _config = _config.copyWith(repeatCount: v))),
              _sliderField(label: 'BG Music Volume', value: _config.bgMusicVolume,
                  onChanged: (v) => setState(() => _config = _config.copyWith(bgMusicVolume: v))),
              _durationField(label: 'Message Interval (s)', seconds: _config.messageInterval.inSeconds,
                  onChanged: (s) => setState(() => _config = _config.copyWith(messageInterval: Duration(seconds: s)))),
              _durationField(label: 'Repeat Interval (s)', seconds: _config.repeatInterval.inSeconds,
                  onChanged: (s) => setState(() => _config = _config.copyWith(repeatInterval: Duration(seconds: s)))),
              FilterChip(
                label: const Text('Fade In'),
                selected: _config.fadeIn,
                onSelected: (v) => setState(() => _config = _config.copyWith(fadeIn: v)),
              ),
              FilterChip(
                label: const Text('Fade Out'),
                selected: _config.fadeOut,
                onSelected: (v) => setState(() => _config = _config.copyWith(fadeOut: v)),
              ),
            ],
          ),
        ),
      ],
    );
  }

  Widget _buildPlaybackStatusBar() {
    final s = _player.state;
    if (s.phase == PlayerPhase.idle || s.phase == PlayerPhase.stopped) return const SizedBox.shrink();
    String text;
    switch (s.phase) {
      case PlayerPhase.preparing:
        text = 'Preparing sequence...';
        break;
      case PlayerPhase.playing:
        text = 'Playing ${s.index + 1}/${s.products.length} (Loop ${s.loop})';
        break;
      case PlayerPhase.interval:
        text = 'Interval before next loop...';
        break;
      case PlayerPhase.completed:
        text = 'Sequence complete';
        break;
      case PlayerPhase.stopped:
        text = 'Stopped';
        break;
      case PlayerPhase.idle:
        text = '';
        break;
    }
    return Container(
      width: double.infinity,
      color: Colors.black12,
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
      child: Row(
        children: [
          Expanded(
              child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(text, style: const TextStyle(fontSize: 12)),
              if (s.currentMessage != null)
                Text(
                  s.currentMessage!,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(fontSize: 11, fontStyle: FontStyle.italic),
                )
            ],
          )),
          if (s.isPlaying)
            IconButton(
              icon: const Icon(Icons.stop_circle_outlined, size: 20),
              onPressed: () => _player.stop(),
            )
        ],
      ),
    );
  }

  Widget _buildBackgroundMusicSelector() {
    if (_bgCatalog == null) {
      return Align(
        alignment: Alignment.centerLeft,
        child: TextButton.icon(
          icon: const Icon(Icons.library_music),
          label: const Text('Load Background Music'),
          onPressed: _loadBgMusic,
        ),
      );
    }
    final tracks = _bgCatalog!.allTracks();
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
      child: Row(
        children: [
          const Text('Session BG:'),
            const SizedBox(width: 12),
            Expanded(
              child: DropdownButton<BackgroundMusicTrack?>(
                isExpanded: true,
                value: _sessionBg,
                hint: const Text('Default (per product)'),
                items: [
                  const DropdownMenuItem<BackgroundMusicTrack?>(value: null, child: Text('Default (per product)')),
                  ...tracks.map((t) => DropdownMenuItem(value: t, child: Text(t.file)))
                ],
                onChanged: (val) => setState(()=> _sessionBg = val),
              ),
            ),
            IconButton(
              tooltip: 'Refresh list',
              icon: const Icon(Icons.refresh),
              onPressed: () async { await _loadBgMusic(); },
            )
        ],
      ),
    );
  }

  Future<void> _previewSample(TtsProductDto p) async {
    if (p.sampleMessages.isEmpty) return;
    final msg = p.sampleMessages.first;
    // Placeholder preview logic: In production call backend preview endpoint, set URL.
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text('Preview: ${msg.substring(0, msg.length > 60 ? 60 : msg.length)}...')),
    );
  }

  Future<void> _purchaseProduct(TtsProductDto p) async {
    final handler = widget.purchaseHandler;
    if (handler == null) return;
    final ok = await handler.purchaseProduct(p);
    if (ok && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Purchased ${p.displayName}')));
      // Refresh language products to reflect access
      await _loadLanguage(_language, restoring: true);
    }
  }

  Widget _numberField({required String label, required int value, required int min, required int max, required ValueChanged<int> onChanged}) {
    return SizedBox(
      width: 140,
      child: TextFormField(
        initialValue: value.toString(),
        decoration: InputDecoration(labelText: label),
        keyboardType: TextInputType.number,
        onChanged: (v) {
          final n = int.tryParse(v) ?? value;
          if (n >= min && n <= max) onChanged(n);
        },
      ),
    );
  }

  Widget _sliderField({required String label, required double value, required ValueChanged<double> onChanged}) {
    return SizedBox(
      width: 200,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(label),
          Slider(
            value: value,
            min: 0,
            max: 1,
            divisions: 20,
            label: value.toStringAsFixed(2),
            onChanged: onChanged,
          ),
        ],
      ),
    );
  }

  Widget _durationField({required String label, required int seconds, required ValueChanged<int> onChanged}) {
    return SizedBox(
      width: 200,
      child: TextFormField(
        initialValue: seconds.toString(),
        decoration: InputDecoration(labelText: label),
        keyboardType: TextInputType.number,
        onChanged: (v) {
          final s = int.tryParse(v) ?? seconds;
          if (s >= 0 && s <= 3600) onChanged(s);
        },
      ),
    );
  }
}
