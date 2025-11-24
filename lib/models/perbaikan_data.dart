// lib/models/perbaikan_data.dart
import '../services/api_service.dart';

class PerbaikanData {
  final int id;
  final String? departmentName;
  final String? employeeName;
  final String? customerName;
  final String? customerCategoryName;
  final String? pilihanData;
  final String? dataBaru;

  // === alamat sama gaya Customer ===
  final List<Map<String, dynamic>> addressDetail; // standar: list of map
  final String? alamat;                            // fallback full address

  final String? imageUrl;
  final DateTime? createdAt;
  final DateTime? updatedAt;

  PerbaikanData({
    required this.id,
    this.departmentName,
    this.employeeName,
    this.customerName,
    this.customerCategoryName,
    this.pilihanData,
    this.dataBaru,
    this.addressDetail = const [],
    this.alamat,
    this.imageUrl,
    this.createdAt,
    this.updatedAt,
  });

  factory PerbaikanData.fromJson(Map<String, dynamic> json) {
    int _toInt(dynamic v) => v is int ? v : (int.tryParse('${v ?? 0}') ?? 0);
    DateTime? _toDate(dynamic v) {
      if (v == null) return null;
      try { return DateTime.parse(v.toString()); } catch (_) { return null; }
    }

    // normalisasi list alamat (sudah disiapkan di ApiService.fetchPerbaikanData)
    final addrList = (json['alamat_detail'] is List)
        ? (json['alamat_detail'] as List)
            .whereType<Map>()
            .map((e) => Map<String, dynamic>.from(e))
            .toList()
        : ApiService.formatAddressList(json); // fallback kalau belum disiapkan

    final imageAbs = ApiService.absoluteUrl(
      (json['image_url'] ?? json['image'] ?? json['foto'] ?? json['gambar'] ?? '').toString(),
    );

    return PerbaikanData(
      id: _toInt(json['id'] ?? json['perbaikan_id'] ?? json['fix_id']),
      departmentName: (json['department']?['name'] ??
              json['department_name'] ?? json['dept_name'] ?? json['department'])
          ?.toString(),
      employeeName: (json['employee']?['name'] ??
              json['employee_name'] ?? json['karyawan'] ?? json['karyawan_name'])
          ?.toString(),
      customerName: (json['customer']?['name'] ??
              json['customer_name'] ?? json['nama_customer'])
          ?.toString(),
      customerCategoryName: (json['customer_category']?['name'] ??
              json['customer_category_name'] ?? json['kategori_customer'])
          ?.toString(),
      pilihanData: (json['pilihan_data'] ?? json['field'] ?? json['pilihan'])?.toString(),
      dataBaru: (json['data_baru'] ?? json['new_value'] ?? json['value'])?.toString(),
      addressDetail: addrList,
      alamat: (json['alamat'] ?? json['full_address'])?.toString(),
      imageUrl: imageAbs.isEmpty ? null : imageAbs,
      createdAt: _toDate(json['created_at']),
      updatedAt: _toDate(json['updated_at']),
    );
  }

  // === Getter display sama seperti Customer ===
  String get alamatDisplay {
    if (addressDetail.isNotEmpty) {
      final a = addressDetail.first;
      final detail = a['detail_alamat'] ?? '';
      final prov = a['provinsi']?['name'] ?? a['provinsi_name'] ?? '';
      final kab  = a['kota_kab']?['name'] ?? a['kota_kab_name'] ?? '';
      final kec  = a['kecamatan']?['name'] ?? a['kecamatan_name'] ?? '';
      final kel  = a['kelurahan']?['name'] ?? a['kelurahan_name'] ?? '';
      final kode = a['kode_pos']?.toString() ?? '';
      return [detail, kel, kec, kab, prov, kode]
          .where((x) => x != null && x.toString().trim().isNotEmpty && x.toString() != '-')
          .join(', ');
    }
    if ((alamat ?? '').trim().isNotEmpty) return alamat!;
    return '-';
  }
}
