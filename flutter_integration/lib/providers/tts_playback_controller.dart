import 'dart:async';
import '../api/tts_api_service.dart';
import '../screens/meditative_minds_audio_screen.dart';

/// Simple playback controller stub; integrate with real audio engine.
class TtsPlaybackController {
  final _stateController = StreamController<PlaybackState>.broadcast();
  PlaybackState _state = PlaybackState.stopped();
  Stream<PlaybackState> get stream => _stateController.stream;
  PlaybackState get current => _state;

  Future<void> playSequence(List<TtsProductDto> order, PlaybackConfig config) async {
    _update(PlaybackState.playing(queue: order, index: 0));
    // Pseudocode placeholder: iterate order * repeatCount with delays
  }

  void stop() {
    _update(PlaybackState.stopped());
  }

  void _update(PlaybackState s) {
    _state = s;
    _stateController.add(s);
  }

  void dispose() { _stateController.close(); }
}

class PlaybackState {
  final bool isPlaying;
  final List<TtsProductDto> queue;
  final int index;
  PlaybackState({required this.isPlaying, required this.queue, required this.index});
  factory PlaybackState.stopped() => PlaybackState(isPlaying: false, queue: const [], index: 0);
  factory PlaybackState.playing({required List<TtsProductDto> queue, required int index}) =>
      PlaybackState(isPlaying: true, queue: queue, index: index);
}
