import 'package:flutter/material.dart';
import '../api/tts_repository.dart';

class TtsProductList extends StatefulWidget {
  final TtsRepository repo;
  final String language;
  final bool previewMode; // if true show inaccessible too (coming from backend flag)
  final void Function(TtsProductDto product)? onPreview;
  final void Function(TtsProductDto product)? onPurchase;
  const TtsProductList({super.key, required this.repo, required this.language, this.previewMode = false, this.onPreview, this.onPurchase});

  @override
  State<TtsProductList> createState() => _TtsProductListState();
}

class _TtsProductListState extends State<TtsProductList> {
  late Future<List<TtsProductDto>> _future;

  @override
  void initState() {
    super.initState();
    _future = widget.repo.getProductsForLanguage(widget.language, preview: widget.previewMode);
  }

  @override
  void didUpdateWidget(covariant TtsProductList oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.language != widget.language) {
      setState(() {
        _future = widget.repo.getProductsForLanguage(widget.language, preview: widget.previewMode, forceRefresh: true);
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<List<TtsProductDto>>(
      future: _future,
      builder: (ctx, snap) {
        if (snap.connectionState == ConnectionState.waiting) {
          return const Center(child: CircularProgressIndicator());
        }
        if (snap.hasError) {
          return Text('Error: ${snap.error}');
        }
        final list = snap.data ?? [];
        if (list.isEmpty) {
          return const Text('No products available');
        }
        return ListView.separated(
          itemCount: list.length,
          separatorBuilder: (_, __) => const Divider(height: 1),
          itemBuilder: (ctx, i) {
            final p = list[i];
            return ListTile(
              title: Text(p.displayName),
              subtitle: Text('${p.category} • ${p.formattedPrice}${p.hasAccess ? ' • Owned' : ''}'),
              trailing: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  if (p.previewAvailable)
                    IconButton(
                        tooltip: 'Preview',
                        icon: const Icon(Icons.play_arrow),
                        onPressed: widget.onPreview == null
                            ? null
                            : () => widget.onPreview!(p)),
                  if (!p.hasAccess)
                    ElevatedButton(
                        onPressed: widget.onPurchase == null
                            ? null
                            : () => widget.onPurchase!(p),
                        child: const Text('Buy'))
                ],
              ),
            );
          },
        );
      },
    );
  }
}
