import 'package:flutter/material.dart';

/// Meditative Music Screen (music-only products)
/// Categories: sleep, relaxation, focus, etc.
/// This is a scaffold implementing structure; hook your existing music-library endpoints.
class MeditativeMusicScreen extends StatefulWidget {
  final Future<List<MusicCategory>> Function()? loadCategories; // Provide loader
  final Future<List<MusicTrack>> Function(String category)? loadTracks; // Provide loader
  final void Function(MusicTrack track)? onPlay;
  const MeditativeMusicScreen({super.key, this.loadCategories, this.loadTracks, this.onPlay});

  @override
  State<MeditativeMusicScreen> createState() => _MeditativeMusicScreenState();
}

class _MeditativeMusicScreenState extends State<MeditativeMusicScreen> {
  List<MusicCategory> _categories = [];
  MusicCategory? _selected;
  List<MusicTrack> _tracks = [];
  bool _loadingCats = true;
  bool _loadingTracks = false;
  String _search = '';

  @override
  void initState() {
    super.initState();
    _init();
  }

  Future<void> _init() async {
    setState(() => _loadingCats = true);
    final cats = await widget.loadCategories?.call() ?? [];
    setState(() {
      _categories = cats;
      _selected = cats.isNotEmpty ? cats.first : null;
      _loadingCats = false;
    });
    if (_selected != null) _loadTracks(_selected!.id);
  }

  Future<void> _loadTracks(String categoryId) async {
    setState(() { _loadingTracks = true; _tracks = []; });
    final t = await widget.loadTracks?.call(categoryId) ?? [];
    setState(() { _tracks = t; _loadingTracks = false; });
  }

  List<MusicTrack> get _filteredTracks {
    if (_search.isEmpty) return _tracks;
    final q = _search.toLowerCase();
    return _tracks.where((t) => t.title.toLowerCase().contains(q) || t.tags.any((tag) => tag.toLowerCase().contains(q))).toList();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Meditative Music')),
      body: Column(
        children: [
          _buildCategoryBar(),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
            child: TextField(
              decoration: const InputDecoration(prefixIcon: Icon(Icons.search), hintText: 'Search tracks'),
              onChanged: (v) => setState(() => _search = v),
            ),
          ),
          Expanded(
            child: _loadingTracks
                ? const Center(child: CircularProgressIndicator())
                : _buildTrackList(),
          )
        ],
      ),
    );
  }

  Widget _buildCategoryBar() {
    if (_loadingCats) {
      return const SizedBox(height: 56, child: Center(child: CircularProgressIndicator()));
    }
    if (_categories.isEmpty) {
      return const Padding(
        padding: EdgeInsets.all(16),
        child: Text('No music categories'),
      );
    }
    return SizedBox(
      height: 60,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        padding: const EdgeInsets.symmetric(horizontal: 8),
        itemCount: _categories.length,
        separatorBuilder: (_, __) => const SizedBox(width: 8),
        itemBuilder: (ctx, i) {
          final c = _categories[i];
            final selected = c == _selected;
            return ChoiceChip(
              label: Text(c.name),
              selected: selected,
              onSelected: (_) {
                setState(() => _selected = c);
                _loadTracks(c.id);
              },
            );
        },
      ),
    );
  }

  Widget _buildTrackList() {
    final items = _filteredTracks;
    if (items.isEmpty) {
      return const Center(child: Text('No tracks'));
    }
    return ListView.separated(
      itemCount: items.length,
      separatorBuilder: (_, __) => const Divider(height: 1),
      itemBuilder: (ctx, i) {
        final t = items[i];
        return ListTile(
          leading: CircleAvatar(backgroundColor: Colors.blueGrey.shade100, child: const Icon(Icons.music_note)),
          title: Text(t.title),
          subtitle: Text(t.durationLabel + (t.tags.isNotEmpty ? ' • ' + t.tags.take(3).join(', ') : '')),
          trailing: IconButton(
            icon: const Icon(Icons.play_arrow),
            onPressed: () => widget.onPlay?.call(t),
          ),
        );
      },
    );
  }
}

class MusicCategory {
  final String id;
  final String name;
  MusicCategory({required this.id, required this.name});
}

class MusicTrack {
  final String id;
  final String title;
  final Duration duration;
  final List<String> tags;
  final String audioUrl;
  MusicTrack({required this.id, required this.title, required this.duration, required this.tags, required this.audioUrl});
  String get durationLabel => _format(duration);
  static String _format(Duration d) {
    final m = d.inMinutes;
    final s = d.inSeconds % 60;
    return '${m.toString().padLeft(2,'0')}:${s.toString().padLeft(2,'0')}';
  }
}
