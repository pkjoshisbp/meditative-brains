import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../api/tts_api_service.dart';
import '../api/tts_repository.dart';
import '../playback/sequence_player.dart';

final baseUrlProvider = Provider<String>((ref) => 'https://your-domain');
final authTokenFutureProvider = FutureProvider<String?>((ref) async => null); // inject real auth token

final ttsApiServiceProvider = Provider<TtsApiService>((ref) {
  return TtsApiService(
    baseUrl: ref.watch(baseUrlProvider),
    tokenProvider: () async => await ref.watch(authTokenFutureProvider.future),
  );
});

final ttsRepositoryProvider = Provider<TtsRepository>((ref) => TtsRepository(ref.watch(ttsApiServiceProvider)));

final sequencePlayerProvider = Provider<SequencePlayer>((ref) {
  final p = SequencePlayer();
  ref.onDispose(() => p.dispose());
  return p;
});

final playerStateStreamProvider = StreamProvider((ref) => ref.watch(sequencePlayerProvider).stream);
