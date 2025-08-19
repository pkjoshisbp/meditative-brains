import 'package:flutter/material.dart';
import '../api/tts_repository.dart';

class TtsLanguageSelector extends StatefulWidget {
  final TtsRepository repo;
  final void Function(String language) onSelected;
  final String? initial;
  const TtsLanguageSelector({super.key, required this.repo, required this.onSelected, this.initial});

  @override
  State<TtsLanguageSelector> createState() => _TtsLanguageSelectorState();
}

class _TtsLanguageSelectorState extends State<TtsLanguageSelector> {
  late Future<List<String>> _future;
  String? _current;

  @override
  void initState() {
    super.initState();
    _future = widget.repo.getLanguages();
    _current = widget.initial;
  }

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<List<String>>(
      future: _future,
      builder: (ctx, snap) {
        if (snap.connectionState == ConnectionState.waiting) {
          return const SizedBox(height: 48, child: Center(child: CircularProgressIndicator()));
        }
        if (snap.hasError) {
          return Text('Error: ${snap.error}');
        }
        final langs = snap.data ?? [];
        if (langs.isEmpty) {
          return const Text('No accessible languages');
        }
        _current ??= langs.first;
        return DropdownButton<String>(
          value: _current,
            items: langs
                .map((l) => DropdownMenuItem(value: l, child: Text(l)))
                .toList(),
            onChanged: (val) {
              if (val == null) return;
              setState(() => _current = val);
              widget.onSelected(val);
            });
      },
    );
  }
}
