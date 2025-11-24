import 'dart:convert';

class Customer {
  final int id;
  final int? companyId;
  final int? departmentId;
  final int? employeeId;
  final int? categoryId;
  final int? programId;

  final String? departmentName;
  final String? employeeName;
  final String? categoryName;
  final String? programName;

  final String name;
  final String phone;
  final String? email;

  final List<Map<String, dynamic>> addressDetail; // ⬅ alamat detail array
  final String? alamat; // ⬅ full alamat dari backend

  final String? gmapsLink;
  final int programPoint;
  final int rewardPoint;

  /// status umum dari backend (pending/active/non-active, dll)
  final String status;

  /// status pengajuan (pending/approved/rejected)
  final String? statusPengajuan;

 

  final String? imageUrl;

  final DateTime? createdAt;
  final DateTime? updatedAt;

  const Customer({
    required this.id,
    this.companyId,
    this.departmentId,
    this.employeeId,
    this.categoryId,
    this.programId,
    this.departmentName,
    this.employeeName,
    this.categoryName,
    this.programName,
    required this.name,
    required this.phone,
    this.email,
    this.addressDetail = const [],
    this.alamat,
    this.gmapsLink,
    required this.programPoint,
    required this.rewardPoint,
    required this.status,
    this.statusPengajuan,
    this.imageUrl,
    this.createdAt,
    this.updatedAt,
  });

  // ---------- Utils ----------
  static int _toInt(dynamic v, {int def = 0}) {
    if (v is int) return v;
    return int.tryParse(v?.toString() ?? '') ?? def;
  }

  static int? _toIntOrNull(dynamic v) {
    if (v == null) return null;
    if (v is int) return v;
    return int.tryParse(v.toString());
  }

  static String _toString(dynamic v, {String def = ''}) {
    return (v ?? def).toString();
  }

  static String? _optString(dynamic v) {
    if (v == null) return null;
    final s = v.toString().trim();
    return s.isEmpty ? null : s;
  }

  static DateTime? _parseDate(dynamic v) {
    if (v == null) return null;
    try {
      return DateTime.tryParse(v.toString());
    } catch (_) {
      return null;
    }
  }

  static List<Map<String, dynamic>> _toAddressList(dynamic v) {
    if (v is List) {
      return v
          .whereType<Map>()
          .map((e) => Map<String, dynamic>.from(e))
          .toList();
    }
    if (v is String && v.trim().isNotEmpty) {
      try {
        final decoded = jsonDecode(v);
        if (decoded is List) {
          return decoded
              .whereType<Map>()
              .map((e) => Map<String, dynamic>.from(e))
              .toList();
        }
      } catch (_) {}
    }
    return <Map<String, dynamic>>[];
  }

  static List<String> _toImages(dynamic v) {
    if (v == null) return [];
    if (v is List) return v.map((e) => e.toString()).toList();

    if (v is String && v.trim().isNotEmpty) {
      try {
        final decoded = jsonDecode(v);
        if (decoded is List) return decoded.map((e) => e.toString()).toList();
      } catch (_) {
        return [v];
      }
    }
    return [];
  }

  // ---------- Factory ----------
  factory Customer.fromJson(Map<String, dynamic> j) {
    // satu kali ambil status dari backend
    final String statusVal = _toString(j['status']);

    return Customer(
      id: _toInt(j['id']),
      companyId: _toIntOrNull(j['company_id']),
      departmentId: _toIntOrNull(j['department_id']),
      employeeId: _toIntOrNull(j['employee_id']),
      categoryId: _toIntOrNull(j['customer_categories_id'] ?? j['category_id']),
      programId: _toIntOrNull(j['customer_program_id'] ?? j['program_id']),
      departmentName: _optString(j['department_name'] ?? j['department']),
      employeeName: _optString(j['employee_name'] ?? j['employee']),
      categoryName: _optString(j['category_name']),
      programName: _optString(j['program_name'] ?? j['customer_program_name']),
      name: _toString(j['name'], def: '-'),
      phone: _toString(j['phone']),
      email: _optString(j['email']),
      addressDetail: _toAddressList(j['alamat_detail'] ?? j['address']),
      alamat: _optString(j['alamat'] ?? j['full_address']),
      gmapsLink: _optString(j['gmaps_link'] ?? j['maps']),
      programPoint: _toInt(j['program_point'] ?? j['jumlah_program']),
      rewardPoint: _toInt(j['reward_point']),
      
      // isi status utama
      status: statusVal,

      // status pengajuan dari kolom status_pengajuan
      statusPengajuan: _optString(j['status_pengajuan']),

      imageUrl: j['image']?.toString(),
      createdAt: _parseDate(j['created_at']),
      updatedAt: _parseDate(j['updated_at']),
    );
  }

  // ---------- Getter Display ----------
  String get alamatDisplay {
    if (addressDetail.isNotEmpty) {
      final a = addressDetail.first;
      final detail = a['detail_alamat'] ?? '';
      final prov = a['provinsi']?['name'] ?? '';
      final kab = a['kota_kab']?['name'] ?? '';
      final kec = a['kecamatan']?['name'] ?? '';
      final kel = a['kelurahan']?['name'] ?? '';
      final kode = a['kode_pos']?.toString() ?? '';

      return [detail, kel, kec, kab, prov, kode]
          .where(
            (x) =>
                x != null &&
                x.toString().trim().isNotEmpty &&
                x.toString() != '-',
          )
          .join(', ');
    }

    if ((alamat ?? '').trim().isNotEmpty) return alamat!;
    return '-';
  }
}
