// lib/utils/downloader_io.dart
import 'dart:io';
import 'dart:typed_data';
import 'package:http/http.dart' as http;
import 'package:path_provider/path_provider.dart';
import 'package:open_file/open_file.dart';

Future<void> downloadFile(String url, {required String fileName}) async {
  if (url.isEmpty) {
    print("⚠️ URL kosong, tidak bisa download.");
    return;
  }

  try {
    print("⬇️ Mulai download: $url");
    final uri = Uri.parse(url);
    final response = await http.get(uri);

    print("📡 Status code: ${response.statusCode}");

    if (response.statusCode == 200) {
      Uint8List bytes = response.bodyBytes;

      Directory? saveDir;

      // Coba simpan di Downloads
      try {
        String? home = Platform.environment['HOME'] ?? Platform.environment['USERPROFILE'];
        if (home != null) {
          saveDir = Directory("$home/Downloads");
          if (!saveDir.existsSync()) {
            await saveDir.create(recursive: true); // buat kalau belum ada
          }
        }
      } catch (_) {
        saveDir = null;
      }

      // Kalau gagal, fallback ke temporary
      saveDir ??= await getTemporaryDirectory();

      final filePath = "${saveDir.path}/$fileName";
      final file = File(filePath);
      await file.writeAsBytes(bytes);

      print("✅ File tersimpan di: $filePath");

      // Buka file dengan aplikasi default
      final result = await OpenFile.open(file.path);
      print("📂 OpenFile result: ${result.type}");
    } else {
      throw Exception('Gagal download file (status ${response.statusCode})');
    }
  } catch (e) {
    print("❌ Error saat download: $e");
  }
}