// lib/utils/downloader.dart
//
// Facade untuk fungsi download lintas platform.
// - Android/iOS/Desktop → downloader_io.dart
// - Web → downloader_web.dart
// - Fallback → downloader_stub.dart
//
// Cara pakai di UI cukup:
//   await downloadFile(url, fileName: "SalesOrder_123.pdf");
//
// Implementasi spesifik platform ada di:
//   - lib/utils/downloader_io.dart
//   - lib/utils/downloader_web.dart
//   - lib/utils/downloader_stub.dart

import 'downloader_stub.dart'
  if (dart.library.io) 'downloader_io.dart'
  if (dart.library.html) 'downloader_web.dart' as impl;

/// API tunggal untuk semua platform.
/// Memanggil implementasi yang sesuai platform di belakang layar.
Future<void> downloadFile(String url, {required String fileName}) {
  return impl.downloadFile(url, fileName: fileName);
}