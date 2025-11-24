// lib/utils/downloader_web.dart
// ignore_for_file: avoid_web_libraries_in_flutter
import 'dart:html' as html;

/// Implementasi khusus Web:
/// 1) <a download> (paling andal saat masih dalam user-gesture)
/// 2) XHR -> Blob (butuh CORS)
/// 3) Buka tab baru sebagai jalan terakhir
Future<void> downloadFile(String url, {required String fileName}) async {
  if (url.isEmpty) return;

  // 1) Anchor <a download>
  final okAnchor = _anchorDownload(url, fileName);
  if (okAnchor) return;

  // 2) Blob fallback (CORS dependent)
  final okBlob = await _blobDownload(url, fileName);
  if (okBlob) return;

  // 3) Last resort
  _openNew(url);
}

bool _anchorDownload(String url, String fileName) {
  try {
    final a = html.AnchorElement(href: url)
      ..download = fileName
      ..target = '_blank'
      ..style.display = 'none';
    html.document.body?.append(a);
    a.click();
    a.remove();
    return true;
  } catch (_) {
    return false;
  }
}

Future<bool> _blobDownload(String url, String fileName) async {
  try {
    final req = await html.HttpRequest.request(
      url,
      method: 'GET',
      requestHeaders: {
        'Accept': 'application/pdf,application/octet-stream,*/*',
      },
      responseType: 'blob',
      withCredentials: false,
    );

    final blob = req.response as html.Blob;
    final blobUrl = html.Url.createObjectUrl(blob);

    final a = html.AnchorElement(href: blobUrl)
      ..download = fileName
      ..target = '_blank'
      ..style.display = 'none';
    html.document.body?.append(a);
    a.click();
    a.remove();

    html.Url.revokeObjectUrl(blobUrl);
    return true;
  } catch (_) {
    return false;
  }
}

void _openNew(String url) {
  try {
    html.window.open(url, '_blank');
  } catch (_) {
    // noop
  }
}
