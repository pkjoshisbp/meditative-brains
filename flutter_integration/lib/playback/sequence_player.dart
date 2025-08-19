import 'dart:async';
import 'package:just_audio/just_audio.dart';
import '../api/tts_api_service.dart';
import '../screens/meditative_minds_audio_screen.dart';

/// SequencePlayer orchestrates playback of a selected queue of TTS products with configurable intervals,
/// repeat loops, background music mixing (placeholder), fade in/out (simple gain ramp), and message repeats.
class SequencePlayer {
  final AudioPlayer voicePlayer = AudioPlayer();
  final AudioPlayer bgPlayer = AudioPlayer();

  final _stateCtrl = StreamController<PlayerSequenceState>.broadcast();
  PlayerSequenceState _state = PlayerSequenceState.idle();
  Stream<PlayerSequenceState> get stream => _stateCtrl.stream;
  PlayerSequenceState get state => _state;

  bool _disposed = false;

  Future<void> play({
    required List<TtsProductDto> products,
    required PlaybackConfig config,
    required Future<SequenceItem?> Function(TtsProductDto product, int loop, int index) resolveItem,
    Future<String?> Function(TtsProductDto product)? resolveBackgroundMusic,
    void Function(String messageText)? onMessage,
  }) async {
    if (products.isEmpty) return;
    _update(PlayerSequenceState.preparing(products: products));

    // Optional: preload background music for first product
  final bgUrl = await resolveBackgroundMusic?.call(products.first);
    if (bgUrl != null) {
      try { await bgPlayer.setUrl(bgUrl); bgPlayer.setVolume(config.bgMusicVolume); } catch (_) {}
    }

    // Iterate repeat loops
    for (int loop = 0; loop < config.repeatCount && !_disposed; loop++) {
      for (int i = 0; i < products.length && !_disposed; i++) {
        final p = products[i];
        _update(PlayerSequenceState.playing(products: products, index: i, loop: loop + 1));
        final item = await resolveItem(p, loop, i);
        if (item == null) continue;
        if (onMessage != null) onMessage(item.messageText);
        _update(_state.copyWith(currentMessage: item.messageText));
        final url = item.audioUrl;
        if (url == null) continue;
        try {
          await voicePlayer.setUrl(url);
          if (config.fadeIn) { await _fade(voicePlayer, from: 0, to: 1, ms: 400); }
          await voicePlayer.play();
          await voicePlayer.playerStateStream.firstWhere((s) => s.processingState == ProcessingState.completed || s.processingState == ProcessingState.idle);
          if (config.fadeOut) { await _fade(voicePlayer, from: voicePlayer.volume, to: 0, ms: 300); }
          voicePlayer.setVolume(1);
        } catch (_) {}
        if (_disposed) break;
        if (i < products.length - 1 && config.messageInterval.inMilliseconds > 0) {
          await Future.delayed(config.messageInterval);
        }
      }
      if (loop < config.repeatCount - 1 && !_disposed && config.repeatInterval.inMilliseconds > 0) {
        _update(PlayerSequenceState.interval(products: products, index: products.length - 1, loop: loop + 1));
        await Future.delayed(config.repeatInterval);
      }
    }
    if (!_disposed) {
      _update(PlayerSequenceState.completed(products: products));
    }
  }

  Future<void> stop() async {
    await voicePlayer.stop();
    await bgPlayer.stop();
    if (!_disposed) _update(PlayerSequenceState.stopped(products: state.products));
  }

  Future<void> pause() async {
    if (state.isPlaying) {
      await voicePlayer.pause();
      _update(_state.asPaused());
    }
  }

  Future<void> resume() async {
    if (!_state.isPlaying && _state.phase == PlayerPhase.playing) {
      await voicePlayer.play();
      _update(_state.asResumed());
    }
  }

  Future<void> dispose() async {
    _disposed = true;
    await stop();
    await voicePlayer.dispose();
    await bgPlayer.dispose();
    await _stateCtrl.close();
  }

  Future<void> _fade(AudioPlayer player, {required double from, required double to, required int ms}) async {
    final steps = 12;
    final stepDur = Duration(milliseconds: (ms / steps).ceil());
    for (int s = 0; s <= steps; s++) {
      final t = s / steps;
      final v = from + (to - from) * t;
      player.setVolume(v.clamp(0, 1));
      await Future.delayed(stepDur);
    }
  }

  void _update(PlayerSequenceState s) { _state = s; _stateCtrl.add(s); }
}

class PlayerSequenceState {
  final List<TtsProductDto> products;
  final int index; // current product index
  final bool isPlaying;
  final bool isCompleted;
  final int loop; // current loop number (1-based)
  final PlayerPhase phase;
  final String? currentMessage;
  PlayerSequenceState({required this.products, required this.index, required this.isPlaying, required this.isCompleted, required this.loop, required this.phase, this.currentMessage});
  factory PlayerSequenceState.idle() => PlayerSequenceState(products: const [], index: 0, isPlaying: false, isCompleted: false, loop: 0, phase: PlayerPhase.idle, currentMessage: null);
  factory PlayerSequenceState.preparing({required List<TtsProductDto> products}) => PlayerSequenceState(products: products, index: 0, isPlaying: false, isCompleted: false, loop: 1, phase: PlayerPhase.preparing, currentMessage: null);
  factory PlayerSequenceState.playing({required List<TtsProductDto> products, required int index, required int loop}) => PlayerSequenceState(products: products, index: index, isPlaying: true, isCompleted: false, loop: loop, phase: PlayerPhase.playing, currentMessage: null);
  factory PlayerSequenceState.interval({required List<TtsProductDto> products, required int index, required int loop}) => PlayerSequenceState(products: products, index: index, isPlaying: false, isCompleted: false, loop: loop, phase: PlayerPhase.interval, currentMessage: null);
  factory PlayerSequenceState.completed({required List<TtsProductDto> products}) => PlayerSequenceState(products: products, index: products.isEmpty?0:products.length-1, isPlaying: false, isCompleted: true, loop: 0, phase: PlayerPhase.completed, currentMessage: null);
  factory PlayerSequenceState.stopped({required List<TtsProductDto> products}) => PlayerSequenceState(products: products, index: 0, isPlaying: false, isCompleted: false, loop: 0, phase: PlayerPhase.stopped, currentMessage: null);
  PlayerSequenceState copyWith({String? currentMessage}) => PlayerSequenceState(products: products, index: index, isPlaying: isPlaying, isCompleted: isCompleted, loop: loop, phase: phase, currentMessage: currentMessage ?? this.currentMessage);
  PlayerSequenceState asPaused() => PlayerSequenceState(products: products, index: index, isPlaying: false, isCompleted: isCompleted, loop: loop, phase: phase, currentMessage: currentMessage);
  PlayerSequenceState asResumed() => PlayerSequenceState(products: products, index: index, isPlaying: true, isCompleted: isCompleted, loop: loop, phase: phase, currentMessage: currentMessage);
}

enum PlayerPhase { idle, preparing, playing, interval, completed, stopped }

class SequenceItem {
  final String messageText;
  final String? audioUrl;
  SequenceItem({required this.messageText, required this.audioUrl});
}
