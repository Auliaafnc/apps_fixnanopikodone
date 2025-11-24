// lib/utils/downloader_stub.dart
//
// Fallback implementation untuk platform yang tidak cocok dengan IO atau Web.
// Biasanya hanya akan membuka URL menggunakan aplikasi atau browser default.
//
// Catatan:
// - Tidak menyimpan file secara lokal.
// - File akan terbuka langsung di browser atau aplikasi bawaan sesuai jenis file.
//
import 'package:url_launcher/url_launcher.dart';

Future<void> downloadFile(String? url, {String? fileName}) async {
  if (url == null || url.isEmpty) return;

  final uri = Uri.tryParse(url);
  if (uri == null) return;

  await launchUrl(uri, mode: LaunchMode.externalApplication);
}