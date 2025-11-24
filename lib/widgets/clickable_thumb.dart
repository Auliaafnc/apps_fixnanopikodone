import 'package:flutter/material.dart';

class ClickableThumb extends StatelessWidget {
  final String? url;
  final double size;
  final String heroTag;
  final bool showBorder;

  const ClickableThumb({
    super.key,
    required this.url,
    required this.heroTag,
    this.size = 48,
    this.showBorder = true,
  });

  @override
  Widget build(BuildContext context) {
    final hasUrl = (url != null && url!.trim().isNotEmpty);

    final deco = BoxDecoration(
      shape: BoxShape.circle,
      border: showBorder ? Border.all(color: Colors.white24, width: 1) : null,
    );

    if (!hasUrl) {
      return Container(
        width: size,
        height: size,
        decoration: deco.copyWith(color: const Color(0x11000000)),
        child: const Icon(Icons.image_not_supported, size: 20, color: Colors.white70),
      );
    }

    final thumb = Container(
      width: size,
      height: size,
      decoration: deco,
      child: ClipOval(
        child: Image.network(
          url!,
          fit: BoxFit.cover,
          width: size,
          height: size,
          loadingBuilder: (c, child, loading) => loading == null
              ? child
              : Center(
                  child: SizedBox(
                    width: size * 0.35,
                    height: size * 0.35,
                    child: const CircularProgressIndicator(strokeWidth: 2),
                  ),
                ),
          errorBuilder: (c, e, s) =>
              const Center(child: Icon(Icons.broken_image, size: 20, color: Colors.white70)),
        ),
      ),
    );

    return InkWell(
      customBorder: const CircleBorder(),
      onTap: () {
        Navigator.of(context).push(
          MaterialPageRoute(
            builder: (_) => FullscreenImageView(url: url!, heroTag: heroTag),
          ),
        );
      },
      child: Hero(tag: heroTag, child: thumb),
    );
  }
}

class FullscreenImageView extends StatelessWidget {
  final String url;
  final String heroTag;

  const FullscreenImageView({super.key, required this.url, required this.heroTag});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.black,
      appBar: AppBar(
        backgroundColor: Colors.transparent,
        foregroundColor: Colors.white,
        elevation: 0,
        actions: [
          IconButton(
            icon: const Icon(Icons.close),
            onPressed: () => Navigator.of(context).maybePop(),
          ),
        ],
      ),
      body: Center(
        child: Hero(
          tag: heroTag,
          child: InteractiveViewer(
            minScale: 0.5,
            maxScale: 4,
            child: Image.network(
              url,
              fit: BoxFit.contain,
              loadingBuilder: (c, child, loading) =>
                  loading == null ? child : const Center(child: CircularProgressIndicator()),
              errorBuilder: (c, e, s) =>
                  const Icon(Icons.broken_image, color: Colors.white54, size: 64),
            ),
          ),
        ),
      ),
    );
  }
}
