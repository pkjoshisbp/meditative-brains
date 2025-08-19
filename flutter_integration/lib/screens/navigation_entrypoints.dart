import 'package:flutter/material.dart';
import '../api/tts_api_service.dart';
import '../api/tts_repository.dart';
import 'meditative_minds_audio_screen.dart';
import 'meditative_music_screen.dart';

/// Central place to register new routes and a simple launcher list.
class MeditativeRoutes {
  static const mindsAudio = '/meditative-minds-audio';
  static const music = '/meditative-music';

  static Map<String, WidgetBuilder> builders({required TtsRepository repo, required MeditativeMusicLoader musicLoader}) => {
        mindsAudio: (_) => MeditativeMindsAudioScreen(repo: repo),
        music: (_) => MeditativeMusicScreen(
              loadCategories: musicLoader.loadCategories,
              loadTracks: musicLoader.loadTracks,
              onPlay: musicLoader.onPlay,
            ),
      };
}

/// Launcher list widget you can temporarily mount in existing home screen.
class MeditativeFeatureLauncher extends StatelessWidget {
  final TtsRepository repo;
  final MeditativeMusicLoader musicLoader;
  const MeditativeFeatureLauncher({super.key, required this.repo, required this.musicLoader});

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.all(16),
      child: Padding(
        padding: const EdgeInsets.all(12.0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text('New Experiences', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
            const SizedBox(height: 12),
            ListTile(
              leading: const Icon(Icons.psychology_alt),
              title: const Text('Meditative Minds Audio'),
              subtitle: const Text('Build custom affirmation sequences'),
              onTap: () => Navigator.of(context).pushNamed(MeditativeRoutes.mindsAudio),
            ),
            ListTile(
              leading: const Icon(Icons.music_note),
              title: const Text('Meditative Music'),
              subtitle: const Text('Explore music-only categories'),
              onTap: () => Navigator.of(context).pushNamed(MeditativeRoutes.music),
            ),
          ],
        ),
      ),
    );
  }
}

/// Adapter class you implement to bridge existing music endpoints.
class MeditativeMusicLoader {
  final Future<List<MusicCategory>> Function() loadCategories;
  final Future<List<MusicTrack>> Function(String categoryId) loadTracks;
  final void Function(MusicTrack track)? onPlay;
  MeditativeMusicLoader({required this.loadCategories, required this.loadTracks, this.onPlay});
}
