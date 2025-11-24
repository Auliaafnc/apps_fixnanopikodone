// lib/services/api_service.dart
import 'dart:convert';

import 'package:collection/collection.dart';
import 'package:flutter/foundation.dart' show kIsWeb, debugPrint;
import 'package:http/http.dart' as http;
import 'package:image_picker/image_picker.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../models/customer.dart';
import '../models/employee_profile.dart';
import '../models/garansi_row.dart';
import '../models/order_row.dart';
import '../models/perbaikan_data.dart';
import '../models/return_row.dart';

/// Item sederhana utk dropdown
class OptionItem {
  final int id;
  final String name;
  final int? categoryId;
  final int? employeeId;
  final int? departmentId;
  final String? phone;
  final String? address;
  final String? programName;
  final int? programId;

  OptionItem({
    required this.id,
    required this.name,
    this.categoryId,
    this.employeeId,
    this.departmentId,
    this.phone,
    this.address,
    this.programName,
    this.programId,
  });

  factory OptionItem.fromJson(Map<String, dynamic> json) {
    // --- id ---
    int idVal = 0;
    final idCandidates = [
      json['id'],
      json['customer_id'],
      json['category_id'],
      json['program_id'],
      json['value'],
      json['code'], // untuk wilayah (laravolt)
    ];
    for (final c in idCandidates) {
      if (c is int) {
        idVal = c;
        break;
      }
      final parsed = int.tryParse('${c ?? ''}');
      if (parsed != null) {
        idVal = parsed;
        break;
      }
    }

    // --- name ---
    final nameCandidates = [
      json['name'],
      json['nama'],
      json['title'],
      json['label'],
      json['text'],
      json['customer_name'],
      '${json['name'] ?? ''} ${json['phone'] ?? ''}',
    ];
    String nameVal = '-';
    for (final c in nameCandidates) {
      final s = (c ?? '').toString();
      if (s.trim().isNotEmpty) {
        nameVal = s;
        break;
      }
    }

    // --- address -> string manusiawi ---
    String addressText = '-';
    if (json['address'] is List && (json['address'] as List).isNotEmpty) {
      final addr = json['address'][0];
      if (addr is Map) {
        final detail = addr['detail_alamat']?.toString() ?? '';
        final kel = addr['kelurahan']?['name']?.toString() ??
            addr['kelurahan_name']?.toString() ??
            '';
        final kec = addr['kecamatan']?['name']?.toString() ??
            addr['kecamatan_name']?.toString() ??
            '';
        final kota = addr['kota_kab']?['name']?.toString() ??
            addr['kota_kab_name']?.toString() ??
            '';
        final prov = addr['provinsi']?['name']?.toString() ??
            addr['provinsi_name']?.toString() ??
            '';
        final kodePos = addr['kode_pos']?.toString() ?? '';
        final parts = [detail, kel, kec, kota, prov, kodePos]
            .where((e) => e.trim().isNotEmpty && e.toLowerCase() != 'null')
            .toList();
        addressText = parts.isEmpty ? '-' : parts.join(', ');
      }
    } else if (json['alamat_detail'] != null) {
      addressText = json['alamat_detail'].toString();
    } else if (json['address'] is String) {
      addressText = json['address'];
    }

    return OptionItem(
      id: idVal,
      name: nameVal,
      categoryId: ApiService._extractCategoryId(json),
      employeeId:
          json['employee_id'] != null ? int.tryParse('${json['employee_id']}') : null,
      departmentId:
          json['department_id'] != null ? int.tryParse('${json['department_id']}') : null,
      phone: json['phone']?.toString(),
      address: addressText,
      programName: json['customer_program']?['name'],
      programId: json['customer_program']?['id'],
    );
  }

  @override
  String toString() => 'OptionItem(id: $id, name: $name)';
}



/// Input alamat sesuai repeater di CustomerResource
/// >>> DITAMBAH field *name agar dashboard bisa render lengkap
class AddressInput {
  // KODE (wajib)
  final String provinsiCode;
  final String kotaKabCode;
  final String kecamatanCode;
  final String kelurahanCode;

  // OPSIONAL
  final String? kodePos;
  final String detailAlamat;

  // >>> NEW: NAMA wilayah (akan ikut dikirim)
  final String? provinsiName;
  final String? kotaKabName;
  final String? kecamatanName;
  final String? kelurahanName;

  AddressInput({
    required this.provinsiCode,
    required this.kotaKabCode,
    required this.kecamatanCode,
    required this.kelurahanCode,
    required this.detailAlamat,
    this.kodePos,
    this.provinsiName,
    this.kotaKabName,
    this.kecamatanName,
    this.kelurahanName,
  });

  Map<String, dynamic> toMap() => {
        'provinsi_code': provinsiCode,
        'kota_kab_code': kotaKabCode,
        'kecamatan_code': kecamatanCode,
        'kelurahan_code': kelurahanCode,
        if (kodePos != null) 'kode_pos': kodePos,
        'detail_alamat': detailAlamat,
        if (provinsiName != null) 'provinsi_name': provinsiName,
        if (kotaKabName != null) 'kota_kab_name': kotaKabName,
        if (kecamatanName != null) 'kecamatan_name': kecamatanName,
        if (kelurahanName != null) 'kelurahan_name': kelurahanName,
      };
}

/// Hasil perhitungan total
class OrderTotals {
  final int total; // jumlah semua subtotal
  final int totalAfterDiscount; // setelah diskon (jika aktif)
  const OrderTotals({required this.total, required this.totalAfterDiscount});
}

class ApiService {
  static const String baseUrl = 'http://localhost/api';
  static const String perbaikanBasePath = 'perbaikandatas';

  // ---------- Helpers umum ----------
  static int? _extractCategoryId(Map<String, dynamic> json) {
    if (json['customer_category'] is Map) {
      return int.tryParse('${json['customer_category']['id']}');
    }
    return int.tryParse(
      '${json['customer_categories_id'] ?? json['customer_category_id'] ?? ''}',
    );
  }

  /// Normalisasi alamat menjadi string manusiawi untuk semua bentuk payload
  /// Normalisasi alamat menjadi string manusiawi untuk semua bentuk payload
// ...
static String formatAddress(dynamic json) {
    // Bentuk object dengan key "alamat_detail"
    if (json is Map &&
        json['alamat_detail'] is List &&
        (json['alamat_detail'] as List).isNotEmpty) {
      final addr = json['alamat_detail'][0];
      if (addr is Map) {
        final detail = addr['detail_alamat']?.toString() ?? '';
        final kel = addr['kelurahan']?['name']?.toString() ??
            addr['kelurahan_name']?.toString() ??
            '';
        final kec = addr['kecamatan']?['name']?.toString() ??
            addr['kecamatan_name']?.toString() ??
            '';
        final kota = addr['kota_kab']?['name']?.toString() ??
            addr['kota_kab_name']?.toString() ??
            '';
        final prov = addr['provinsi']?['name']?.toString() ??
            addr['provinsi_name']?.toString() ??
            '';
        final kodePos = addr['kode_pos']?.toString() ?? '';
        final parts = [detail, kel, kec, kota, prov, kodePos]
            .where((e) => e.trim().isNotEmpty && e.toLowerCase() != 'null')
            .toList();
        return parts.isEmpty ? '-' : parts.join(', ');
      }
    }

    // Fallback langsung "address" array standar
    if (json is List && json.isNotEmpty) {
      final addr = json[0];
      if (addr is Map) {
        final detail = addr['detail_alamat']?.toString() ?? '';
        final kel = addr['kelurahan']?['name']?.toString() ??
            addr['kelurahan_name']?.toString() ??
            '';
        final kec = addr['kecamatan']?['name']?.toString() ??
            addr['kecamatan_name']?.toString() ??
            '';
        final kota = addr['kota_kab']?['name']?.toString() ??
            addr['kota_kab_name']?.toString() ??
            '';
        final prov = addr['provinsi']?['name']?.toString() ??
            addr['provinsi_name']?.toString() ??
            '';
        final kodePos = addr['kode_pos']?.toString() ?? '';
        final parts = [detail, kel, kec, kota, prov, kodePos]
            .where((e) => e.trim().isNotEmpty && e.toLowerCase() != 'null')
            .toList();
        return parts.isEmpty ? '-' : parts.join(', ');
      }
    }

    // String langsung
    if (json is String && json.trim().isNotEmpty) {
      return json;
    }

    return '-';
  }

  // >>> Tambah di ApiService (dekat formatAddress)
  static List<Map<String, dynamic>> formatAddressList(dynamic json) {
    Map<String, dynamic>? _first(dynamic v) {
      if (v is Map<String, dynamic>) return v;
      if (v is Map) return Map<String, dynamic>.from(v);
      return null;
    }

    Map<String, dynamic>? addr;

    // 1) Bentuk { "alamat_detail": [ {...} ] }
    if (json is Map && json['alamat_detail'] is List && (json['alamat_detail'] as List).isNotEmpty) {
      addr = _first((json['alamat_detail'] as List).first);
    }

    // 2) Bentuk { "address": [ {...} ] }
    if (addr == null && json is Map && json['address'] is List && (json['address'] as List).isNotEmpty) {
      addr = _first((json['address'] as List).first);
    }

    // 3) Bentuk langsung List
    if (addr == null && json is List && json.isNotEmpty) {
      addr = _first(json.first);
    }

    if (addr == null) return <Map<String, dynamic>>[];

    final detail = (addr['detail_alamat'] ?? '').toString();
    final kodePos = (addr['kode_pos'] ?? '').toString();

    // dukung 2 bentuk: flat *_name, atau nested object
    String _name(dynamic m, String key) {
      if (m is Map && m[key] is Map && m[key]['name'] != null) {
        return m[key]['name'].toString();
      }
      final flat = m['${key}_name'];
      if (flat != null) return flat.toString();
      return '';
    }

    String _code(dynamic m, String key) {
      if (m is Map && m[key] is Map && m[key]['code'] != null) {
        return m[key]['code'].toString();
      }
      final flat = m['${key}_code'];
      if (flat != null) return flat.toString();
      return '';
    }

    final map = <String, dynamic>{
      'detail_alamat': detail,
      'kode_pos'     : kodePos,
      'provinsi'     : {'code': _code(addr, 'provinsi'),  'name': _name(addr, 'provinsi')},
      'kota_kab'     : {'code': _code(addr, 'kota_kab'),  'name': _name(addr, 'kota_kab')},
      'kecamatan'    : {'code': _code(addr, 'kecamatan'), 'name': _name(addr, 'kecamatan')},
      'kelurahan'    : {'code': _code(addr, 'kelurahan'), 'name': _name(addr, 'kelurahan')},
    };

    return [map];
  }



  // ====================== PARSER KHUSUS CUSTOMER ======================
  static OptionItem _parseCustomer(Map<String, dynamic> json) {
    final id = int.tryParse('${json['id'] ?? json['customer_id']}') ?? 0;

    // Ambil nama
    final nameCandidates = [
      json['name'],
      json['nama'],
      json['customer_name'],
      '${json['name'] ?? ''} ${json['phone'] ?? ''}',
    ];
    String nameVal = '-';
    for (final c in nameCandidates) {
      final s = (c ?? '').toString();
      if (s.trim().isNotEmpty) {
        nameVal = s;
        break;
      }
    }

    return OptionItem(
      id: id,
      name: nameVal,
      phone: json['phone']?.toString(),
      categoryId: ApiService._extractCategoryId(json),
      address: ApiService.formatAddress(json),
    );
  }

  static Future<Map<String, String>> _authorizedHeaders({bool jsonContent = false}) async {
    final prefs = await SharedPreferences.getInstance();
    final token = prefs.getString('token');
    return {
      'Accept': 'application/json',
      if (jsonContent) 'Content-Type': 'application/json',
      if (token != null && token.isNotEmpty) 'Authorization': 'Bearer $token',
    };
  }

  static dynamic _safeDecode(String body) {
    try {
      return jsonDecode(body);
    } catch (_) {
      return body;
    }
  }

  static Uri _buildUri(String path, {Map<String, String>? query}) {
    final normalizedBase = baseUrl.replaceAll(RegExp(r'/+$'), '');
    final normalizedPath = path.replaceAll(RegExp(r'^/+'), '');
    final raw = '$normalizedBase/$normalizedPath';
    return (query == null || query.isEmpty)
        ? Uri.parse(raw)
        : Uri.parse(raw).replace(queryParameters: {...query});
  }

  static List<Map<String, dynamic>> _extractList(dynamic decoded) {
    if (decoded is List) {
      return decoded
          .whereType<Map>()
          .map((e) => Map<String, dynamic>.from(e))
          .toList();
    }
    if (decoded is Map) {
      final d = decoded['data'];

      if (d is List) {
        return d
            .whereType<Map>()
            .map((e) => Map<String, dynamic>.from(e))
            .toList();
      }

      if (d is Map && d['data'] is List) {
        return (d['data'] as List)
            .whereType<Map>()
            .map((e) => Map<String, dynamic>.from(e))
            .toList();
      }

      final items = decoded['items'];
      if (items is List) {
        return items
            .whereType<Map>()
            .map((e) => Map<String, dynamic>.from(e))
            .toList();
      }

      final cust = decoded['customers'];
      if (cust is List) {
        return cust
            .whereType<Map>()
            .map((e) => Map<String, dynamic>.from(e))
            .toList();
      }
    }
    return <Map<String, dynamic>>[];
  }

  // ---------- AUTH ----------
  static Future<void> _saveUserFromLoginPayload(dynamic body) async {
    try {
      if (body is! Map) return;
      final map = Map<String, dynamic>.from(body);
      final u = (map['user'] is Map)
          ? Map<String, dynamic>.from(map['user'])
          : (map['data'] is Map)
              ? Map<String, dynamic>.from(map['data'])
              : null;
      if (u == null) return;

      final prefs = await SharedPreferences.getInstance();

      if (u['email'] != null) {
        await prefs.setString('user_email', u['email'].toString());
      }
      if (u['name'] != null) {
        await prefs.setString('user_name', u['name'].toString());
      }

      // <<< TAMBAHKAN >>>
      final empId = u['employee_id'] ?? u['employee']?['id'];
      if (empId != null) {
        await prefs.setInt('employee_id', int.parse(empId.toString()));
      }

      debugPrint('LOGIN: saved user_email=${u['email']}, employee_id=$empId');
    } catch (e) {
      debugPrint('LOGIN: _saveUserFromLoginPayload error: $e');
    }
  }

  static Map<String, dynamic>? _decodeJwt(String token) {
    try {
      final parts = token.split('.');
      if (parts.length != 3) return null;
      String normalized = parts[1];
      while (normalized.length % 4 != 0) {
        normalized += '=';
      }
      final payload = utf8.decode(base64Url.decode(normalized));
      final map = jsonDecode(payload);
      if (map is Map<String, dynamic>) return map;
    } catch (_) {}
    return null;
  }

  static Future<bool> login(String email, String password) async {
    try {
      final url = _buildUri('auth/login');
      final res = await http.post(
        url,
        headers: await _authorizedHeaders(jsonContent: true),
        body: jsonEncode({'email': email, 'password': password}),
      );

      final body = _safeDecode(res.body);

      if ((res.statusCode == 200 || res.statusCode == 201) &&
          body is Map &&
          body['token'] != null) {
        final prefs = await SharedPreferences.getInstance();

        // simpan token
        await prefs.setString('token', body['token'].toString());

        // >>> PENTING: SELALU simpan email yang dipakai login
        await prefs.setString('user_email', email.trim());

        // simpan nama & employee_id kalau ada di payload
        await _saveUserFromLoginPayload(body);

        // kalau JWT-nya memang berisi email, boleh override
        final jwt = _decodeJwt(body['token'].toString());
        final jwtEmail = jwt?['email'];
        if (jwtEmail is String && jwtEmail.isNotEmpty) {
          await prefs.setString('user_email', jwtEmail.trim());
        }

        debugPrint(
            'LOGIN OK: user_email=${prefs.getString('user_email')}, token saved');
        return true;
      }

      debugPrint('LOGIN FAILED: ${res.statusCode} $body');
      return false;
    } catch (e) {
      debugPrint('LOGIN ERROR: $e');
      return false;
    }
  }

  static Future<void> logout() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove('token');
    await prefs.remove('user_email');
    await prefs.remove('user_name');
    await prefs.remove('employee_id');
  }

  static Future<Map<String, dynamic>?> fetchAuthMe() async {
    final prefs = await SharedPreferences.getInstance();
    final cachedEmail = prefs.getString('user_email');
    final cachedName = prefs.getString('user_name');
    if (cachedEmail != null && cachedEmail.isNotEmpty) {
      return {'email': cachedEmail, if (cachedName != null) 'name': cachedName};
    }

    final headers = await _authorizedHeaders();
    for (final path in [
      'auth/me',
      'me',
      'user',
      'auth/user',
      'users/me',
      'profile',
    ]) {
      try {
        final uri = _buildUri(path);
        final res = await http.get(uri, headers: headers);
        if (res.statusCode != 200) continue;
        final decoded = _safeDecode(res.body);
        final data = (decoded is Map && decoded['data'] is Map)
            ? Map<String, dynamic>.from(decoded['data'])
            : (decoded is Map ? Map<String, dynamic>.from(decoded) : null);
        if (data != null && (data['email'] ?? data['user']?['email']) != null) {
          final email = (data['email'] ?? data['user']?['email']).toString();
          await prefs.setString('user_email', email);
          if (data['name'] != null) {
            await prefs.setString('user_name', data['name'].toString());
          }

          // 👉 SIMPAN employee_id DI SINI JUGA
          final empId = data['employee_id'] ?? data['employee']?['id'];
          if (empId != null) {
            await prefs.setInt('employee_id', int.parse(empId.toString()));
          }

          return data;
        }
      } catch (_) {}
    }

    final token = prefs.getString('token');
    if (token != null && token.isNotEmpty) {
      final jwt = _decodeJwt(token);
      final email = jwt?['email'] ?? jwt?['sub'];
      if (email is String && email.isNotEmpty) {
        await prefs.setString('user_email', email);
        return {'email': email};
      }
    }

    return null;
  }

  /// Ambil profil employee untuk user yang login (dengan filter email).
  static Future<EmployeeProfile?> fetchMyEmployeeProfile() async {
    // 1. Ambil email dari auth/me / cache
    final me = await fetchAuthMe();
    final email = (me?['email'] ??
            me?['user']?['email'] ??
            me?['data']?['email'])
        ?.toString()
        .trim()
        .toLowerCase();

    if (email == null || email.isEmpty) {
      debugPrint('PROFILE: email kosong, tidak bisa cari employee');
      return null;
    }

    debugPrint('PROFILE: cari employee dengan email = $email');

    // 2. Panggil /employees TANPA filter (ambil banyak, lalu filter di Flutter)
    final headers = await _authorizedHeaders();
    final uri = _buildUri('employees', query: {
      'per_page': '1000',
    });

    final res = await http.get(uri, headers: headers);
    debugPrint('PROFILE: GET $uri => ${res.statusCode}');

    if (res.statusCode != 200) {
      debugPrint('PROFILE: gagal load employees: ${res.body}');
      return null;
    }

    final decoded = _safeDecode(res.body);
    final list = _extractList(decoded);
    if (list.isEmpty) {
      debugPrint('PROFILE: list employees kosong');
      return null;
    }

    // 3. Cari employee dengan email yang sama
    final raw = list.firstWhereOrNull((m) {
      final map = Map<String, dynamic>.from(m);
      final e = (map['email'] ?? '').toString().trim().toLowerCase();
      return e == email;
    });

    if (raw == null) {
      debugPrint('PROFILE: tidak ada employee dengan email $email');
      return null;
    }

    final map = Map<String, dynamic>.from(raw);
    map['photo'] = _absoluteUrl((map['photo'] ?? map['image'] ?? '').toString());

    debugPrint('PROFILE: ketemu employee ${map['name']} (${map['email']})');

    return EmployeeProfile.fromJson(map);
  }

  /// Ambil URL gambar banner dari backend (maks 4)
  static Future<List<String>> fetchBannerImages() async {
    final headers = await _authorizedHeaders();
    for (final path in ['banners', 'banner']) {
      try {
        final uri = _buildUri(path, query: {'per_page': '50'});
        final res = await http.get(uri, headers: headers);
        if (res.statusCode != 200) continue;

        final decoded = _safeDecode(res.body);
        final list = _extractList(decoded);

        final urls = <String>[];
        for (final raw in list) {
          if (raw is Map) {
            for (final key in ['image_1', 'image_2', 'image_3', 'image_4']) {
              final v = (raw[key] ?? '').toString().trim();
              if (v.isEmpty || v.toLowerCase() == 'null') continue;
              urls.add(_absoluteUrl(v));
            }
          }
        }
        final uniq = urls.where((e) => e.isNotEmpty).toSet().toList();
        if (uniq.isNotEmpty) return uniq.take(4).toList();
      } catch (_) {}
    }
    return <String>[];
  }

  // ---------- DROPDOWN ----------
  Future<List<OptionItem>> _fetchOptionsTryPaths(
    List<String> paths, {
    Map<String, String>? query,
    bool filterActive = true,
  }) async {
    final headers = await _authorizedHeaders();
    for (final p in paths) {
      final uri = _buildUri(p, query: {'per_page': '1000', ...(query ?? const {})});
      try {
        final res = await http.get(uri, headers: headers);
        if (res.statusCode != 200) continue;

        final decoded = _safeDecode(res.body);
        var list = _extractList(decoded);
        if (list.isEmpty) continue;

        if (filterActive) {
          list = list.where((m) {
            final status = (m['status'] ?? '').toString().toLowerCase().trim();
            final pengajuan = (m['status_pengajuan'] ?? '').toString().toLowerCase().trim();
            final okStatus = status.isEmpty ||
                status == 'active' ||
                status == 'aktif' ||
                status == '1' ||
                status == 'true';
            final okApproved = pengajuan.isEmpty ||
                pengajuan == 'disetujui' ||
                pengajuan == 'approved' ||
                pengajuan == '1' ||
                pengajuan == 'true';
            return okStatus && okApproved;
          }).toList();
        }

        final options = list
            .map(OptionItem.fromJson)
            .where((o) => o.id != 0 && o.name.isNotEmpty)
            .toList();
        if (options.isNotEmpty) return options;
      } catch (_) {}
    }
    return <OptionItem>[];
  }

  // Departments
  static Future<List<OptionItem>> fetchDepartments() =>
      ApiService()._fetchOptionsTryPaths(['departments']);

  // ======================= DROPDOWN =======================

  // employees
  static Future<List<OptionItem>> fetchEmployees({required int departmentId}) async {
    return ApiService()._fetchOptionsTryPaths(
      ['orders'],
      query: {
        'type': 'employees',
        'department_id': '$departmentId',
      },
      filterActive: false,
    );
  }

  // categories (by employee optional)
  static Future<List<OptionItem>> fetchCustomerCategories({int? employeeId}) {
    final q = <String, String>{'type': 'customer-categories'};
    if (employeeId != null) q['employee_id'] = '$employeeId';
    return ApiService()._fetchOptionsTryPaths(
      ['orders'],
      query: q,
      filterActive: true,
    );
  }

  // programs (all)
  static Future<List<OptionItem>> fetchCustomerPrograms({int? employeeId, int? categoryId}) {
    return ApiService()._fetchOptionsTryPaths(
      ['orders'],
      query: {'type': 'customer-programs'},
      filterActive: true,
    );
  }

  // all categories (fallback)
  static Future<List<OptionItem>> fetchCustomerCategoriesAll() {
    return ApiService()._fetchOptionsTryPaths(
      ['customer-categories'],
      filterActive: true,
    );
  }

  // customers by dept+emp
  static Future<List<OptionItem>> fetchCustomersByDeptEmp({
    required int departmentId,
    required int employeeId,
  }) async {
    final headers = await _authorizedHeaders();
    final uri = _buildUri('orders', query: {
      'type': 'customers',
      'department_id': '$departmentId',
      'employee_id': '$employeeId',
      'per_page': '1000',
    });

    final res = await http.get(uri, headers: headers);
    if (res.statusCode != 200) return [];

    final decoded = _safeDecode(res.body);
    final list = _extractList(decoded);
    return list.map<OptionItem>((m) => _parseCustomer(m)).toList();
  }

  static Future<List<OptionItem>> fetchCustomerProgramsByCategory(int categoryId) async {
    return ApiService()._fetchOptionsTryPaths(
      ['customer-programs'],
      query: {'customer_category_id': '$categoryId'},
      filterActive: true,
    );
  }

  static Future<List<OptionItem>> fetchCustomersByCategory(int categoryId) async {
    final headers = await _authorizedHeaders();
    final uri = _buildUri('customers', query: {'per_page': '1000'});
    final res = await http.get(uri, headers: headers);
    if (res.statusCode != 200) {
      // ignore: avoid_print
      print("DEBUG fetchCustomersByCategory failed: ${res.statusCode} ${res.body}");
      return [];
    }

    final decoded = _safeDecode(res.body);
    final list = _extractList(decoded);

    final customers = list.map<OptionItem>((m) => _parseCustomer(m)).toList();

    return customers.where((c) => c.categoryId == categoryId).toList();
  }

  static Future<List<OptionItem>> fetchCustomersFiltered({
    required int departmentId,
    required int employeeId,
    required int categoryId,
  }) async {
    final headers = await _authorizedHeaders();
    final uri = _buildUri('orders', query: {
      'type': 'customers',
      'department_id': '$departmentId',
      'employee_id': '$employeeId',
      'customer_categories_id': '$categoryId',
      'per_page': '1000',
    });

    final res = await http.get(uri, headers: headers);
    if (res.statusCode != 200) return [];

    final decoded = _safeDecode(res.body);
    final list = _extractList(decoded);
    return list.map<OptionItem>((m) => _parseCustomer(m)).toList();
  }

  // ---- Detail customer (model) ----
  static Future<Customer> fetchCustomerDetail(int id) async {
    final headers = await _authorizedHeaders();
    final uri = _buildUri('customers/$id');
    final res = await http.get(uri, headers: headers);
    if (res.statusCode != 200) {
      throw Exception('Failed to load customer detail: ${res.statusCode}');
    }
    final decoded = _safeDecode(res.body);
    Map<String, dynamic>? data;

    if (decoded is Map) {
      if (decoded['data'] is Map) {
        data = Map<String, dynamic>.from(decoded['data']);
      } else {
        data = Map<String, dynamic>.from(decoded);
      }
    }
    if (data == null) throw Exception('Customer detail not found');

    // FIX: jangan return dulu; mapping image ke absolute dulu
    final map = Map<String, dynamic>.from(data);
    map['image'] = _absoluteUrl((map['image'] ?? map['image_url'] ?? '').toString());
    return Customer.fromJson(map);
  }

  // ---- Detail customer RAW map (untuk formatAddress) ----
  static Future<Map<String, dynamic>> fetchCustomerDetailRaw(int id) async {
    final headers = await _authorizedHeaders();
    final uri = _buildUri('customers/$id');
    final res = await http.get(uri, headers: headers);
    if (res.statusCode != 200) {
      throw Exception('Failed to load customer detail: ${res.statusCode}');
    }
    final decoded = _safeDecode(res.body);
    if (decoded is Map && decoded['data'] is Map) {
      return Map<String, dynamic>.from(decoded['data']);
    }
    if (decoded is Map) {
      return Map<String, dynamic>.from(decoded);
    }
    return <String, dynamic>{};
  }

  static Future<Map<String, dynamic>> fetchCustomerDetailMap(int id) =>
      fetchCustomerDetailRaw(id);

  // ---- Produk dependensi ----
  static Future<List<OptionItem>> fetchCategoriesByBrand(int brandId) {
    return ApiService()._fetchOptionsTryPaths(
      ['orders'],
      query: {'type': 'categories-by-brand', 'brand_id': '$brandId'},
      filterActive: true,
    );
  }

  static Future<List<OptionItem>> fetchProductsByBrandCategory(int brandId, int categoryId) {
    return ApiService()._fetchOptionsTryPaths(
      ['orders'],
      query: {
        'type': 'products-by-brand-category',
        'brand_id': '$brandId',
        'category_id': '$categoryId',
      },
      filterActive: true,
    );
  }

  static Future<List<OptionItem>> fetchColorsByProductFiltered(int productId) {
    return ApiService()._fetchOptionsTryPaths(
      ['orders'],
      query: {'type': 'colors-by-product', 'product_id': '$productId'},
      filterActive: false,
    );
  }

  /// Ambil semua customer aktif + approved
  static Future<List<OptionItem>> fetchCustomersDropdown() async {
    final headers = await _authorizedHeaders();
    final uri = _buildUri('customers', query: {'per_page': '1000'});
    final res = await http.get(uri, headers: headers);
    if (res.statusCode != 200) return [];

    final decoded = _safeDecode(res.body);
    final list = _extractList(decoded);

    final customers = list
        .where((m) {
          final status = (m['status'] ?? '').toString().toLowerCase();
          final pengajuan = (m['status_pengajuan'] ?? '').toString().toLowerCase();
          return (status == 'active' ||
                  status == 'aktif' ||
                  status == '1' ||
                  status == 'true') &&
              (pengajuan == 'disetujui' ||
                  pengajuan == 'approved' ||
                  pengajuan == '1' ||
                  pengajuan == 'true');
        })
        .map<OptionItem>((m) => _parseCustomer(m))
        .toList();

    return customers;
  }

  // Categories / Brands / Products
  static Future<List<OptionItem>> fetchProductCategories() =>
      ApiService()._fetchOptionsTryPaths(['categories'], filterActive: true);
  static Future<List<OptionItem>> fetchBrands() =>
      ApiService()._fetchOptionsTryPaths(['brands'], filterActive: true);
  static Future<List<OptionItem>> fetchProducts() =>
      ApiService()._fetchOptionsTryPaths(['products'], filterActive: true);

  // === Wilayah (Indonesia) ===
  static Future<List<OptionItem>> fetchProvinces() =>
      ApiService()._fetchOptionsTryPaths(
        ['customers'],
        query: {'type': 'provinces'},
        filterActive: false,
      );
  static Future<List<OptionItem>> fetchCities(String provinceCode) =>
      ApiService()._fetchOptionsTryPaths(
        ['customers'],
        query: {'type': 'cities', 'province_code': provinceCode},
        filterActive: false,
      );
  static Future<List<OptionItem>> fetchDistricts(String cityCode) =>
      ApiService()._fetchOptionsTryPaths(
        ['customers'],
        query: {'type': 'districts', 'city_code': cityCode},
        filterActive: false,
      );
  static Future<List<OptionItem>> fetchVillages(String districtCode) =>
      ApiService()._fetchOptionsTryPaths(
        ['customers'],
        query: {'type': 'villages', 'district_code': districtCode},
        filterActive: false,
      );
  static Future<String?> fetchPostalCodeByVillage(String villageCode) async {
    final headers = await _authorizedHeaders();
    final uri = _buildUri('customers', query: {
      'type': 'postal_code',
      'village_code': villageCode,
    });
    try {
      final res = await http.get(uri, headers: headers);
      if (res.statusCode == 200) {
        final decoded = _safeDecode(res.body);
        if (decoded is Map && decoded['postal_code'] != null) {
          return decoded['postal_code'].toString();
        }
      }
    } catch (_) {}
    return null;
  }

  // Colors untuk 1 produk (fallback ke berbagai bentuk)
  static Future<List<OptionItem>> fetchColorsByProduct(int productId) async {
    final headers = await _authorizedHeaders();

    final tries = <Uri>[
      _buildUri('products/$productId', query: {'include': 'colors'}),
      _buildUri('products/$productId'),
    ];

    for (final uri in tries) {
      try {
        final res = await http.get(uri, headers: headers);
        if (res.statusCode != 200) continue;

        final decoded = _safeDecode(res.body);
        final data = (decoded is Map && decoded['data'] is Map)
            ? Map<String, dynamic>.from(decoded['data'])
            : (decoded is Map ? Map<String, dynamic>.from(decoded) : <String, dynamic>{});

        final dynamic raw = data['colors'] ??
            data['warna'] ??
            (data['attributes'] is Map ? (data['attributes'] as Map)['colors'] : null) ??
            data['color_options'];

        List<OptionItem> out = [];

        if (raw is List) {
          if (raw.isNotEmpty && raw.first is! Map) {
            final list = raw.map((e) => e.toString()).where((s) => s.trim().isNotEmpty).toList();
            out = [
              for (int i = 0; i < list.length; i++) OptionItem(id: i + 1, name: list[i]),
            ];
          } else {
            out = raw
                .whereType<Map>()
                .map((m) {
                  final name = (m['name'] ?? m['nama'] ?? m['label'] ?? '').toString();
                  final id = int.tryParse('${m['id'] ?? 0}') ?? 0;
                  return id > 0 ? OptionItem(id: id, name: name) : OptionItem(id: name.hashCode, name: name);
                })
                .where((o) => o.name.trim().isNotEmpty)
                .toList();
          }
        } else if (raw is String && raw.trim().isNotEmpty) {
          final parts = raw.split(',').map((e) => e.trim()).where((e) => e.isNotEmpty).toList();
          out = [
            for (int i = 0; i < parts.length; i++) OptionItem(id: i + 1, name: parts[i]),
          ];
        }

        // ignore: avoid_print
        print('DEBUG colors for product $productId => $out');

        if (out.isNotEmpty) return out;
      } catch (e) {
        // ignore: avoid_print
        print('DEBUG fetchColorsByProduct error: $e');
      }
    }

    return <OptionItem>[];
  }

  // Harga produk
  static Future<int> fetchProductPrice(int productId) async {
    final headers = await _authorizedHeaders();
    final tries = <Uri>[
      _buildUri('products/$productId', query: {'include': 'prices'}),
      _buildUri('products/$productId'),
    ];

    for (final uri in tries) {
      try {
        final res = await http.get(uri, headers: headers);
        if (res.statusCode != 200) continue;

        final decoded = _safeDecode(res.body);
        final data = (decoded is Map && decoded['data'] is Map)
            ? Map<String, dynamic>.from(decoded['data'])
            : (decoded is Map ? Map<String, dynamic>.from(decoded) : <String, dynamic>{});

        final candidates = [
          data['price'],
          data['harga'],
          (data['prices'] is Map)
              ? ((data['prices'] as Map)['sale'] ?? (data['prices'] as Map)['base'])
              : null,
          (data['attributes'] is Map) ? (data['attributes'] as Map)['price'] : null,
        ];

        for (final c in candidates) {
          if (c == null) continue;
          if (c is int) return c;
          if (c is double) return c.round();
          final parsed = int.tryParse(c.toString().replaceAll(RegExp(r'[^\d\-]'), ''));
          if (parsed != null) return parsed;
        }
      } catch (_) {}
    }
    return 0;
  }

  // ---------- CUSTOMERS ----------
  static Future<bool> createCustomer({
    required int companyId,
    required int departmentId,
    required int employeeId,
    required String name,
    required String phone,
    String? email,
    required int customerCategoryId,
    int? customerProgramId,
    String? gmapsLink,
    required AddressInput address,
    List<XFile>? photos,
  }) async {
    final url = _buildUri('customers');
    final headers = await _authorizedHeaders();

    final request = http.MultipartRequest('POST', url);
    request.headers.addAll(headers);

    request.fields['company_id'] = companyId.toString();
    request.fields['department_id'] = departmentId.toString();
    request.fields['employee_id'] = employeeId.toString();
    request.fields['name'] = name;
    request.fields['phone'] = phone;
    if (email != null && email.isNotEmpty) request.fields['email'] = email;
    request.fields['customer_categories_id'] = customerCategoryId.toString();
    if (customerProgramId != null) {
      request.fields['customer_program_id'] = customerProgramId.toString();
    }
    if (gmapsLink != null && gmapsLink.isNotEmpty) {
      request.fields['gmaps_link'] = gmapsLink;
    }

    // ===== Address (kode Wajib) =====
    request.fields['address[0][provinsi_code]'] = address.provinsiCode;
    request.fields['address[0][kota_kab_code]'] = address.kotaKabCode;
    request.fields['address[0][kecamatan_code]'] = address.kecamatanCode;
    request.fields['address[0][kelurahan_code]'] = address.kelurahanCode;
    if (address.kodePos != null) {
      request.fields['address[0][kode_pos]'] = address.kodePos!;
    }
    request.fields['address[0][detail_alamat]'] = address.detailAlamat;

    // ===== Flat "name" fallback (sering dipakai dashboard) =====
    if (address.provinsiName != null) {
      request.fields['address[0][provinsi_name]'] = address.provinsiName!;
    }
    if (address.kotaKabName != null) {
      request.fields['address[0][kota_kab_name]'] = address.kotaKabName!;
    }
    if (address.kecamatanName != null) {
      request.fields['address[0][kecamatan_name]'] = address.kecamatanName!;
    }
    if (address.kelurahanName != null) {
      request.fields['address[0][kelurahan_name]'] = address.kelurahanName!;
    }

    // ===== Nested object (mirip relasi laravolt) =====
    request.fields['address[0][provinsi][code]'] = address.provinsiCode;
    if (address.provinsiName != null) {
      request.fields['address[0][provinsi][name]'] = address.provinsiName!;
    }

    request.fields['address[0][kota_kab][code]'] = address.kotaKabCode;
    if (address.kotaKabName != null) {
      request.fields['address[0][kota_kab][name]'] = address.kotaKabName!;
    }

    request.fields['address[0][kecamatan][code]'] = address.kecamatanCode;
    if (address.kecamatanName != null) {
      request.fields['address[0][kecamatan][name]'] = address.kecamatanName!;
    }

    request.fields['address[0][kelurahan][code]'] = address.kelurahanCode;
    if (address.kelurahanName != null) {
      request.fields['address[0][kelurahan][name]'] = address.kelurahanName!;
    }

    // ===== Upload foto (single) =====
    if (photos != null && photos.isNotEmpty) {
      final file = photos.first;
      if (kIsWeb) {
        final bytes = await file.readAsBytes();
        request.files.add(http.MultipartFile.fromBytes(
          'image',
          bytes,
          filename: file.name,
        ));
      } else {
        request.files.add(await http.MultipartFile.fromPath('image', file.path));
      }
    }

    final streamedResponse = await request.send();
    final res = await http.Response.fromStream(streamedResponse);

    // ignore: avoid_print
    print('DEBUG createCustomer => ${res.statusCode} ${res.body}');

    return res.statusCode == 200 || res.statusCode == 201;
  }

  static Future<List<Customer>> fetchCustomers({int page = 1, int perPage = 20, String? q}) async {
    final headers = await _authorizedHeaders();
    final params = {
      'page': '$page',
      'per_page': '$perPage',
      if (q != null && q.isNotEmpty) 'filter[search]': q,
    };
    final uri = _buildUri('customers', query: params);
    final res = await http.get(uri, headers: headers);
    if (res.statusCode != 200) {
      throw Exception('GET /customers ${res.statusCode}: ${res.body}');
    }
    final items = _extractList(_safeDecode(res.body));

    return items.map((raw) {
      final map = Map<String, dynamic>.from(raw);
      map['image'] = _absoluteUrl((map['image'] ?? map['image_url'] ?? '').toString());
      return Customer.fromJson(map);
    }).toList();
  }

  // ---------- SALES ORDERS ----------
  static OrderTotals computeTotals({
    required List<Map<String, dynamic>> products,
    required double diskon1,
    required double diskon2,
    required double diskon3,
    required double diskon4,
    required bool diskonsEnabled,
  }) {
    int total = 0;
    for (final p in products) {
      final qty = (p['quantity'] is int)
          ? p['quantity'] as int
          : int.tryParse('${p['quantity'] ?? 0}') ?? 0;

      final price = (p['price'] is int)
          ? p['price'] as int
          : (p['price'] is double)
              ? (p['price'] as double).round()
              : int.tryParse('${p['price'] ?? 0}') ?? 0;

      total += price * qty;
    }

    if (!diskonsEnabled) {
      return OrderTotals(total: total, totalAfterDiscount: total);
    }

    // normalisasi 4 diskon (0..100) & terapkan berurutan
    final ds = <double>[diskon1, diskon2, diskon3, diskon4]
        .map((d) => (d.isNaN ? 0.0 : d).clamp(0.0, 100.0))
        .toList();

    double after = total.toDouble();
    for (final d in ds) {
      if (d > 0) after -= after * (d / 100.0);
    }

    final int totalAfter = (!after.isFinite) ? total : after.round();
    return OrderTotals(total: total, totalAfterDiscount: totalAfter);
  }

  // CREATE ORDER
  static Future<bool> createOrder({
    required int companyId,
    required int departmentId,
    required int employeeId,
    required int customerId,
    required int categoryId,
    int? programId,
    required String phone,
    required String addressText,
    double diskon1 = 0,
    double diskon2 = 0,
    double diskon3 = 0,
    double diskon4 = 0,
    String? penjelasanDiskon1,
    String? penjelasanDiskon2,
    String? penjelasanDiskon3,
    String? penjelasanDiskon4,
    bool diskonsEnabled = false,
    required List<Map<String, dynamic>> products,
    String paymentMethod = "tempo",
    String? paymentDueUntil,
    String statusPembayaran = "belum bayar",
    String status = "pending",
    List<XFile>? files,
  }) async {
    final url = _buildUri('orders');
    final headers = await _authorizedHeaders();

    final totals = computeTotals(
      products: products,
      diskon1: diskon1,
      diskon2: diskon2,
      diskon3: diskon3,
      diskon4: diskon4,
      diskonsEnabled: diskonsEnabled,
    );

    var request = http.MultipartRequest('POST', url);
    request.headers.addAll(headers);

    request.fields['company_id'] = companyId.toString();
    request.fields['department_id'] = departmentId.toString();
    request.fields['employee_id'] = employeeId.toString();
    request.fields['customer_id'] = customerId.toString();
    request.fields['customer_categories_id'] = categoryId.toString();
    if (programId != null) {
      request.fields['customer_program_id'] = programId.toString();
    }
    request.fields['phone'] = phone;
    request.fields['address'] = addressText;

    
    // Diskon & toggle
    request.fields['diskons_enabled'] = diskonsEnabled ? '1' : '0';
    request.fields['diskon_1'] = diskon1.toString();
    request.fields['diskon_2'] = diskon2.toString();
    request.fields['diskon_3'] = diskon3.toString(); 
    request.fields['diskon_4'] = diskon4.toString(); 

    if ((penjelasanDiskon1 ?? '').isNotEmpty) {
      request.fields['penjelasan_diskon_1'] = penjelasanDiskon1!.trim();
    }
    if ((penjelasanDiskon2 ?? '').isNotEmpty) {
      request.fields['penjelasan_diskon_2'] = penjelasanDiskon2!.trim();
    }
    if ((penjelasanDiskon3 ?? '').isNotEmpty) {        
      request.fields['penjelasan_diskon_3'] = penjelasanDiskon3!.trim();
    }
    if ((penjelasanDiskon4 ?? '').isNotEmpty) {        
      request.fields['penjelasan_diskon_4'] = penjelasanDiskon4!.trim();
    }


   request.fields['payment_method'] = paymentMethod;                 // 'tempo' | 'cash'
  if (paymentMethod == 'tempo' && (paymentDueUntil ?? '').isNotEmpty) {
    request.fields['payment_due_until']       = paymentDueUntil!;
  }

    request.fields['status_pembayaran'] = statusPembayaran;
    request.fields['status'] = status;
    request.fields['total_harga'] = totals.total.toString();
    request.fields['total_harga_after_tax'] = totals.totalAfterDiscount.toString();

    for (int i = 0; i < products.length; i++) {
      final p = products[i];

      final produkId   = (p['produk_id'] ?? p['product_id'] ?? '').toString();
      final warnaId    = (p['warna_id'] ?? p['color'] ?? '').toString(); // FE kirim nama warna
      final qty        = (p['quantity'] ?? 0).toString();
      final price      = (p['price'] ?? 0).toString();

      // >>> NEW: ambil brand & kategori dari berbagai kemungkinan key
      final brandId    = (p['brand_id'] ?? p['brandId'] ?? p['brand']?['id'] ?? '').toString();
      final kategoriId = (p['kategori_id'] ?? p['category_id'] ?? p['category']?['id'] ?? '').toString();

      request.fields['products[$i][produk_id]'] = produkId;

      if (brandId.isNotEmpty) {
        request.fields['products[$i][brand_id]'] = brandId;
      }
      if (kategoriId.isNotEmpty) {
        // kirim dua-duanya biar aman dengan transformer/handler manapun
        request.fields['products[$i][kategori_id]'] = kategoriId;
        request.fields['products[$i][category_id]'] = kategoriId;
      }

      if (warnaId.isNotEmpty) {
        request.fields['products[$i][warna_id]'] = warnaId; // nama warna (string)
      }
      request.fields['products[$i][quantity]'] = qty;
      request.fields['products[$i][price]']    = price;
    }


    if (files != null && files.isNotEmpty) {
      for (final file in files) {
        if (kIsWeb) {
          final bytes = await file.readAsBytes();
          request.files.add(http.MultipartFile.fromBytes(
            'files[]',
            bytes,
            filename: file.name,
          ));
        } else {
          request.files.add(await http.MultipartFile.fromPath('files[]', file.path));
        }
      }
    }

    final streamed = await request.send();
    final res = await http.Response.fromStream(streamed);

    // ignore: avoid_print
    print("DEBUG createOrder => ${res.statusCode} ${res.body}");

    return res.statusCode == 200 || res.statusCode == 201;
  }

   /// Upload bukti pengiriman untuk ORDER.
/// Pakai PUT /api/orders/{id}
static Future<bool> uploadOrderDelivery({
  required int orderId,
  required List<XFile> photos,
}) async {
  if (photos.isEmpty) return false;

  final url = _buildUri('orders/$orderId');
  final headers = await _authorizedHeaders();

  final req = http.MultipartRequest('POST', url)
    ..headers.addAll(headers)
    ..fields['_method'] = 'PUT';

  // Selaraskan dengan Filament: FileUpload::make('delivery_images')
  // kirim semua file sebagai delivery_images[]
  for (final f in photos) {
    if (kIsWeb) {
      final bytes = await f.readAsBytes();
      req.files.add(
        http.MultipartFile.fromBytes(
          'delivery_images[]',
          bytes,
          filename: f.name,
        ),
      );
    } else {
      req.files.add(
        await http.MultipartFile.fromPath(
          'delivery_images[]',
          f.path,
        ),
      );
    }
  }

  final res = await http.Response.fromStream(await req.send());
  debugPrint('DEBUG uploadOrderDelivery => ${res.statusCode} ${res.body}');
  return res.statusCode >= 200 && res.statusCode < 300;
}




  // FETCH ALL ORDER
  static Future<List<OrderRow>> fetchOrderRows({
    int page = 1,
    int perPage = 20,
    String? q,
    String? status,
  }) async {
    final headers = await _authorizedHeaders();
    final paths = ['orders', 'sales-orders', 'sales_orders'];
    for (final p in paths) {
      final params = <String, String>{
        'page': '$page',
        'per_page': '$perPage',
        if (q != null && q.isNotEmpty) 'filter[search]': q,
        if (status != null && status.isNotEmpty) 'filter[status]': status,
      };
      final uri = _buildUri(p, query: params);
      final res = await http.get(uri, headers: headers);
      if (res.statusCode != 200) continue;
      final items = _extractList(_safeDecode(res.body));
      if (items.isEmpty) continue;

      return items.map((raw) {
        final map = Map<String, dynamic>.from(raw);
        map['file_pdf_url'] = _absoluteUrl(
          (map['file_pdf_url'] ??
                  map['invoice_pdf_url'] ??
                  map['order_file'] ??
                  map['pdf_url'] ??
                  map['document_url'] ??
                  '')
              .toString(),
        );
        return OrderRow.fromJson(map);
      }).toList();
    }
    return <OrderRow>[];
  }

  // FETCH DETAIL ORDER
  static Future<OrderRow> fetchOrderRowDetail(int id) async {
    final headers = await _authorizedHeaders();
    final paths = ['orders/$id', 'sales-orders/$id', 'sales_orders/$id'];
    for (final p in paths) {
      final uri = _buildUri(p);
      final res = await http.get(uri, headers: headers);
      if (res.statusCode != 200) continue;
      final decoded = _safeDecode(res.body);
      final data = (decoded is Map) ? (decoded['data'] ?? decoded) : decoded;
      final map = Map<String, dynamic>.from(data as Map);
      map['file_pdf_url'] = _absoluteUrl(
        (map['file_pdf_url'] ??
                map['invoice_pdf_url'] ??
                map['order_file'] ??
                map['pdf_url'] ??
                map['document_url'] ??
                '')
            .toString(),
      );
      return OrderRow.fromJson(map);
    }
    throw Exception('GET /orders/$id not found');
  }

  // ---------- RETURNS ----------

/// POST return pakai multipart agar bisa kirim images + array-like fields
static Future<bool> createReturn({
  required int companyId,
  required int departmentId,
  required int employeeId,
  required int customerId,
  required int categoryId,
  required String phone,
  required List<Map<String, dynamic>> address, // array
  required List<Map<String, dynamic>> products, // array
  required int amount,
  required String reason,
  String? note,
  String status = 'pending',
  List<XFile>? photos,
}) async {
  final url = _buildUri('product-returns'); // sesuaikan jika rute berbeda
  final headers = await _authorizedHeaders();

  final request = http.MultipartRequest('POST', url);
  request.headers.addAll(headers);

  // ===== field utama =====
  request.fields['company_id'] = companyId.toString();
  request.fields['department_id'] = departmentId.toString();
  request.fields['employee_id'] = employeeId.toString();
  request.fields['customer_id'] = customerId.toString();
  request.fields['customer_categories_id'] = categoryId.toString();
  request.fields['phone'] = phone;
  request.fields['amount'] = amount.toString();
  request.fields['reason'] = reason;
  request.fields['status'] = status;
  if (note != null && note.isNotEmpty) {
    request.fields['note'] = note;
  }

  // ===== Address (array-like) =====
  for (int i = 0; i < address.length; i++) {
    final addr = address[i];
    request.fields['address[$i][provinsi]'] =
        (addr['provinsi'] ?? '-').toString();
    request.fields['address[$i][kota_kab]'] =
        (addr['kota_kab'] ?? '-').toString();
    request.fields['address[$i][kecamatan]'] =
        (addr['kecamatan'] ?? '-').toString();
    request.fields['address[$i][kelurahan]'] =
        (addr['kelurahan'] ?? '-').toString();
    request.fields['address[$i][kode_pos]'] =
        (addr['kode_pos'] ?? '-').toString();
    request.fields['address[$i][detail_alamat]'] =
        (addr['detail_alamat'] ?? '-').toString();
  }

  // ===== Products (array-like) =====
  for (int i = 0; i < products.length; i++) {
    final p = products[i];
    request.fields['products[$i][produk_id]'] =
        (p['produk_id'] ?? '-').toString();
    request.fields['products[$i][warna_id]'] =
        (p['warna_id'] ?? '-').toString();
    request.fields['products[$i][quantity]'] =
        (p['quantity'] ?? '0').toString();
    request.fields['products[$i][brand_id]'] =
        (p['brand_id'] ?? '-').toString();
    request.fields['products[$i][kategori_id]'] =
        (p['kategori_id'] ?? '-').toString();
  }

    // ===== Upload foto barang (MULTIPLE) =====
  if (photos != null && photos.isNotEmpty) {
    for (final file in photos) {
      if (kIsWeb) {
        final bytes = await file.readAsBytes();
        request.files.add(
          http.MultipartFile.fromBytes(
            'image[]', // kirim sebagai array
            bytes,
            filename: file.name,
          ),
        );
      } else {
        request.files.add(
          await http.MultipartFile.fromPath(
            'image[]', // kirim sebagai array
            file.path,
          ),
        );
      }
    }
  }


  // ===== Kirim request =====
  final streamed = await request.send();
  final res = await http.Response.fromStream(streamed);

  debugPrint("DEBUG createReturn => ${res.statusCode} ${res.body}");

  return res.statusCode == 200 || res.statusCode == 201;
}

/// Upload bukti pengiriman untuk RETURN.
/// Pakai PUT /api/product-returns/{id}
static Future<bool> uploadReturnDelivery({
  required int returnId,
  required List<XFile> photos,
}) async {
  if (photos.isEmpty) return false;

  final url = _buildUri('product-returns/$returnId');
  final headers = await _authorizedHeaders();

  final req = http.MultipartRequest('POST', url)
    ..headers.addAll(headers)
    ..fields['_method'] = 'PUT';

  // selaraskan dengan Filament: FileUpload::make('delivery_images')
  // kirim semua file sebagai delivery_images[]
  for (final f in photos) {
    if (kIsWeb) {
      final bytes = await f.readAsBytes();
      req.files.add(
        http.MultipartFile.fromBytes(
          'delivery_images[]',
          bytes,
          filename: f.name,
        ),
      );
    } else {
      req.files.add(
        await http.MultipartFile.fromPath('delivery_images[]', f.path),
      );
    }
  }

  final res = await http.Response.fromStream(await req.send());
  debugPrint('DEBUG uploadReturnDelivery => ${res.statusCode} ${res.body}');
  return res.statusCode >= 200 && res.statusCode < 300;
}


static Future<List<ReturnRow>> fetchReturnRows({
  int page = 1,
  int perPage = 20,
  String? q,
  String? status,
}) async {
  final headers = await _authorizedHeaders();
  final paths = ['product-returns', 'product_returns', 'returns'];

  for (final p in paths) {
    final params = <String, String>{
      'page': '$page',
      'per_page': '$perPage',
      if (q != null && q.isNotEmpty) 'filter[search]': q,
      if (status != null && status.isNotEmpty) 'filter[status]': status,
    };
    final uri = _buildUri(p, query: params);
    final res = await http.get(uri, headers: headers);
    if (res.statusCode != 200) continue;

    final items = _extractList(_safeDecode(res.body));
    if (items.isEmpty) continue;

    // Debug sekali aja
    if (items.isNotEmpty) {
      final sample = Map<String, dynamic>.from(items.first);
      debugPrint('RETURN SAMPLE KEYS: ${sample.keys.toList()}');
      debugPrint('RETURN DELIVERY candidates: '
          'proof_url=${sample['delivery_proof_url']} | '
          'image_url=${sample['delivery_image_url']} | '
          'image=${sample['delivery_image']} | '
          'bukti_url=${sample['bukti_pengiriman_url']} | '
          'bukti=${sample['bukti_pengiriman']} | '
          'images=${sample['delivery_images']} | '
          'images_urls=${sample['delivery_images_urls']}');
    }

    return items.map((raw) {
      final map = Map<String, dynamic>.from(raw);

      // PDF absolut
      map['file_pdf_url'] = _absoluteUrl(
        (map['file_pdf_url'] ??
                map['pdf_url'] ??
                map['document_url'] ??
                map['invoice_pdf_url'] ??
                '')
            .toString(),
      );

      // Foto barang absolut (single)
      map['image'] =
          _absoluteUrl((map['image'] ?? map['image_url'] ?? '').toString());

      // ---------- BUKTI PENGIRIMAN ----------
      String delivery = (map['delivery_proof_url'] ??
              map['delivery_image_url'] ??
              map['delivery_image'] ??
              map['bukti_pengiriman_url'] ??
              map['bukti_pengiriman'] ??
              map['delivery_images_url'] ??
              map['delivery_photo'] ??
              '')
          .toString();

      // A) kalau sudah ada accessor array full URL dari backend, biarkan lewat
      //    (map tetap menyimpan 'delivery_images_urls' bila ada)

      // B) delivery_images = List path/url
      if (delivery.isEmpty &&
          map['delivery_images'] is List &&
          (map['delivery_images'] as List).isNotEmpty) {
        final list =
            (map['delivery_images'] as List).map((e) => e.toString()).toList();
        delivery = list.first;
        map['delivery_images_urls'] =
            list.map((e) => _absoluteUrl(e)).toList();
      }

      // C) delivery_images = String JSON atau single path string
      if (delivery.isEmpty && map['delivery_images'] is String) {
        final s = (map['delivery_images'] as String).trim();
        if (s.isNotEmpty) {
          try {
            final decoded = jsonDecode(s);
            if (decoded is List && decoded.isNotEmpty) {
              final list = decoded.map((e) => e.toString()).toList();
              delivery = list.first;
              map['delivery_images_urls'] =
                  list.map((e) => _absoluteUrl(e)).toList();
            } else {
              // single path string
              delivery = s;
            }
          } catch (_) {
            // bukan JSON, anggap single path string
            delivery = s;
          }
        }
      }

      // Set single absolute url supaya selalu ada yang dipakai UI
      map['delivery_image_url'] = _absoluteUrl(delivery);

      // ---------- ADDRESS DISPLAY ----------
      final addrText = (map['address_text'] ?? '').toString().trim();
      final addrDetail = map['address_detail']; // array terstruktur (opsional)
      final rawAddr = map['address']; // bisa array / string JSON / string mentah

      String displayAddr = addrText;
      if (displayAddr.isEmpty) {
        displayAddr = ApiService.formatAddress(addrDetail ?? rawAddr);
        if (displayAddr.isEmpty) displayAddr = '-';
      }
      map['address_display'] = displayAddr;

      return ReturnRow.fromJson(map);
    }).toList();
  }

  return <ReturnRow>[];
}

static Future<ReturnRow> fetchReturnRowDetail(int id) async {
  final headers = await _authorizedHeaders();
  final paths = [
    'product-returns/$id',
    'product_returns/$id',
    'returns/$id',
  ];

  for (final p in paths) {
    final uri = _buildUri(p);
    final res = await http.get(uri, headers: headers);
    if (res.statusCode != 200) continue;

    final decoded = _safeDecode(res.body);
    final data = (decoded is Map) ? (decoded['data'] ?? decoded) : decoded;
    final map = Map<String, dynamic>.from(data as Map);

    // ---------- FILE PDF ----------
    map['file_pdf_url'] = _absoluteUrl(
      (map['file_pdf_url'] ??
              map['pdf_url'] ??
              map['document_url'] ??
              map['invoice_pdf_url'] ??
              '')
          .toString(),
    );

    // ---------- FOTO BARANG ----------
    map['image'] =
        _absoluteUrl((map['image'] ?? map['image_url'] ?? '').toString());

    // ---------- FOTO BARANG MULTIPLE ----------
    if (map['image_urls'] is List) {
      map['image_urls'] = (map['image_urls'] as List)
          .map((e) => _absoluteUrl(e.toString()))
          .toList();
    }


    // ---------- BUKTI PENGIRIMAN ----------
    String delivery = (map['delivery_proof_url'] ??
            map['delivery_image_url'] ??
            map['delivery_image'] ??
            map['bukti_pengiriman_url'] ??
            map['bukti_pengiriman'] ??
            map['delivery_images_url'] ??
            map['delivery_photo'] ??
            '')
        .toString();

    // A) kalau ada array delivery_images_urls dari transformer
    if (map['delivery_images_urls'] is List &&
        (map['delivery_images_urls'] as List).isNotEmpty) {
      final list = (map['delivery_images_urls'] as List)
          .map((e) => _absoluteUrl(e.toString()))
          .toList();
      map['delivery_images_urls'] = list;
      if (delivery.isEmpty) {
        delivery = list.first;
      }
    }

    // B) delivery_images berupa List path/url
    if (delivery.isEmpty &&
        map['delivery_images'] is List &&
        (map['delivery_images'] as List).isNotEmpty) {
      final list =
          (map['delivery_images'] as List).map((e) => e.toString()).toList();
      delivery = list.first;
      map['delivery_images_urls'] =
          list.map((e) => _absoluteUrl(e)).toList();
    }

    // C) delivery_images String JSON atau single path
    if (delivery.isEmpty && map['delivery_images'] is String) {
      final s = (map['delivery_images'] as String).trim();
      if (s.isNotEmpty) {
        try {
          final decodedList = jsonDecode(s);
          if (decodedList is List && decodedList.isNotEmpty) {
            final list =
                decodedList.map((e) => e.toString()).toList();
            delivery = list.first;
            map['delivery_images_urls'] =
                list.map((e) => _absoluteUrl(e)).toList();
          } else {
            delivery = s;
          }
        } catch (_) {
          delivery = s;
        }
      }
    }

    // pastikan single url absolut
    map['delivery_image_url'] = _absoluteUrl(delivery);

    // ---------- ALAMAT DISPLAY ----------
    final addrText = (map['address_text'] ?? '').toString().trim();
    final addrDetail = map['address_detail'];
    final rawAddr = map['address'];
    String displayAddr = addrText.isNotEmpty
        ? addrText
        : ApiService.formatAddress(addrDetail ?? rawAddr);
    map['address_display'] = displayAddr.isNotEmpty ? displayAddr : '-';

    return ReturnRow.fromJson(map);
  }

  throw Exception('GET /product-returns/$id not found');
}



  // ---------- WARRANTIES ----------
  /// CREATE garansi pakai JSON; 'products' & 'address' dikirim sebagai array
  static Future<bool> createWarranty({
    required int companyId,
    required int departmentId,
    required int employeeId,
    required int customerId,
    required int categoryId,
    required String phone,
    required List<Map<String, dynamic>> address,
    required List<Map<String, dynamic>> products,
    required String purchaseDate, // YYYY-MM-DD
    required String claimDate, // YYYY-MM-DD
    String? reason,
    String? note,
    String status = 'pending',
    String? imagePath, // opsional; base64 dataURL string
  }) async {
    final url = _buildUri('garansis'); // sesuaikan jika rute berbeda
    final headers = await _authorizedHeaders(jsonContent: true);

    final payload = <String, dynamic>{
      'company_id': companyId,
      'department_id': departmentId,
      'employee_id': employeeId,
      'customer_id': customerId,
      'customer_categories_id': categoryId,
      'phone': phone,
      'address': address, // array<Map>
      'products': products, // array<Map>
      'purchase_date': purchaseDate,
      'claim_date': claimDate,
      'status': status,
      if (reason != null && reason.isNotEmpty) 'reason': reason,
      if (note != null && note.isNotEmpty) 'note': note,
      if (imagePath != null && imagePath.isNotEmpty) 'image': [imagePath], // kirim sebagai array
    };

    final res = await http.post(url, headers: headers, body: jsonEncode(payload));
    // ignore: avoid_print
    print('DEBUG createWarranty => ${res.statusCode} ${res.body}');
    return res.statusCode == 200 || res.statusCode == 201;
  }

  

  static Future<List<GaransiRow>> fetchWarrantyRows(
    {int page = 1, int perPage = 20, String? q, String? status}) async {
      final headers = await _authorizedHeaders();
      final paths = ['garansis', 'warranties', 'garansi', 'warranty-claims', 'warranty_claims'];

      for (final p in paths) {
        final params = {
          'page': '$page',
          'per_page': '$perPage',
          if (q?.isNotEmpty == true) 'filter[search]': q!,
          if (status?.isNotEmpty == true) 'filter[status]': status!,
        };
        final uri = _buildUri(p, query: params);
        final res = await http.get(uri, headers: headers);
        if (res.statusCode != 200) continue;
        
        final items = _extractList(_safeDecode(res.body));
        if (items.isEmpty) continue;
        if (items.isNotEmpty) {
          final sample = Map<String, dynamic>.from(items.first);
          debugPrint('GARANSI SAMPLE KEYS: ${sample.keys.toList()}');
          debugPrint('GARANSI DELIVERY candidates: '
              'proof_url=${sample['delivery_proof_url']} | '
              'image_url=${sample['delivery_image_url']} | '
              'image=${sample['delivery_image']} | '
              'bukti_url=${sample['bukti_pengiriman_url']} | '
              'bukti=${sample['bukti_pengiriman']} | '
              'images=${sample['delivery_images']} | '
              'images_urls=${sample['delivery_images_urls']}');
        }
        return items.map((raw) {
          final map = Map<String, dynamic>.from(raw);

          // PDF absolut
          map['file_pdf_url'] = _absoluteUrl(
            (map['file_pdf_url'] ??
            map['pdf_url'] ??
            map['document_url'] ??
            map['invoice_pdf_url'] ??
            '').toString(),
          );

      // ---------- FOTO BARANG ----------
      map['image'] =
          _absoluteUrl((map['image'] ?? map['image_url'] ?? '').toString());

      // ---------- FOTO BARANG MULTIPLE ----------
      if (map['image_urls'] is List) {
        map['image_urls'] = (map['image_urls'] as List)
            .map((e) => _absoluteUrl(e.toString()))
            .toList();
      }

      // ---------- BUKTI PENGIRIMAN ----------
      String delivery = (map['delivery_proof_url'] ??
              map['delivery_image_url'] ??
              map['delivery_image'] ??
              map['bukti_pengiriman_url'] ??
              map['bukti_pengiriman'] ??
              map['delivery_images_url'] ??
              map['delivery_photo'] ??
              '')
          .toString();

      // A) kalau ada array delivery_images_urls dari transformer
      if (map['delivery_images_urls'] is List &&
          (map['delivery_images_urls'] as List).isNotEmpty) {
        final list = (map['delivery_images_urls'] as List)
            .map((e) => _absoluteUrl(e.toString()))
            .toList();
        map['delivery_images_urls'] = list;
        if (delivery.isEmpty) {
          delivery = list.first;
        }
      }

      // B) delivery_images berupa List path/url
      if (delivery.isEmpty &&
          map['delivery_images'] is List &&
          (map['delivery_images'] as List).isNotEmpty) {
        final list =
            (map['delivery_images'] as List).map((e) => e.toString()).toList();
        delivery = list.first;
        map['delivery_images_urls'] =
            list.map((e) => _absoluteUrl(e)).toList();
      }

      // C) delivery_images String JSON atau single path
      if (delivery.isEmpty && map['delivery_images'] is String) {
        final s = (map['delivery_images'] as String).trim();
        if (s.isNotEmpty) {
          try {
            final decodedList = jsonDecode(s);
            if (decodedList is List && decodedList.isNotEmpty) {
              final list =
                  decodedList.map((e) => e.toString()).toList();
              delivery = list.first;
              map['delivery_images_urls'] =
                  list.map((e) => _absoluteUrl(e)).toList();
            } else {
              delivery = s;
            }
          } catch (_) {
            delivery = s;
          }
        }
      }

      // pastikan single url absolut
      map['delivery_image_url'] = _absoluteUrl(delivery);

          final addrText   = (map['address_text'] ?? '').toString().trim();
          final addrDetail = map['address_detail']; // array terstruktur (opsional)
          final rawAddr    = map['address'];        // bisa array / string JSON / string mentah

          // gunakan address_text bila ada; kalau tidak, format dari detail/raw
          String displayAddr = addrText;
          if (displayAddr.isEmpty) {
            displayAddr = ApiService.formatAddress(addrDetail ?? rawAddr);
            if (displayAddr.isEmpty) displayAddr = '-';
          }
          map['address_display'] = displayAddr;

          return GaransiRow.fromJson(map);
        }).toList();
      }
      return <GaransiRow>[];
    }


  static Future<GaransiRow> fetchWarrantyRowDetail(int id) async {
    final headers = await _authorizedHeaders();
    final paths = [
      'garansis/$id',
      'warranties/$id',
      'garansi/$id',
      'warranty-claims/$id',
      'warranty_claims/$id'
    ];
    for (final p in paths) {
      final uri = _buildUri(p);
      final res = await http.get(uri, headers: headers);
      if (res.statusCode != 200) continue;
      final decoded = _safeDecode(res.body);
      final data = (decoded is Map) ? (decoded['data'] ?? decoded) : decoded;
      final map = Map<String, dynamic>.from(data as Map);
      map['file_pdf_url'] = _absoluteUrl(
          (map['file_pdf_url'] ??
                  map['pdf_url'] ??
                  map['document_url'] ??
                  map['invoice_pdf_url'] ??
                  '')
              .toString());
      map['image'] = _absoluteUrl((map['image'] ?? map['image_url'] ?? '').toString());

      final addrText   = (map['address_text'] ?? '').toString().trim();
      final addrDetail = map['address_detail'];
      final rawAddr    = map['address'];
      String displayAddr = addrText.isNotEmpty
          ? addrText
          : ApiService.formatAddress(addrDetail ?? rawAddr);
      map['address_display'] = (displayAddr.isNotEmpty ? displayAddr : '-');

      return GaransiRow.fromJson(map);
    }
    throw Exception('GET /garansis/$id not found');
  }

  /// Upload bukti pengiriman garansi lewat route update bawaan:
  ///   PUT /api/garansis/{id}
  /// Kita pakai POST + `_method=PUT` dan kirim field:
  ///   - `delivery_image`  (single) ATAU
  ///   - `delivery_images[]` (multiple)
  static Future<bool> uploadWarrantyDelivery({
    required int garansiId,
    required List<XFile> photos,
  }) async {
    if (photos.isEmpty) return false;

    final url = _buildUri('garansis/$garansiId'); // pakai UPDATE yang sudah ada
    final headers = await _authorizedHeaders();

    final req = http.MultipartRequest('POST', url)
      ..headers.addAll(headers)
      ..fields['_method'] = 'PUT';

    // kirim 1 file => delivery_image; >1 file => delivery_images[]
    if (photos.length == 1) {
      final f = photos.first;
      if (kIsWeb) {
        final bytes = await f.readAsBytes();
        req.files.add(http.MultipartFile.fromBytes('delivery_image', bytes, filename: f.name));
      } else {
        req.files.add(await http.MultipartFile.fromPath('delivery_image', f.path));
      }
    } else {
      for (final f in photos) {
        if (kIsWeb) {
          final bytes = await f.readAsBytes();
          req.files.add(
              http.MultipartFile.fromBytes('delivery_images[]', bytes, filename: f.name));
        } else {
          req.files.add(await http.MultipartFile.fromPath('delivery_images[]', f.path));
        }
      }
    }

    final res = await http.Response.fromStream(await req.send());
    // ignore: avoid_print
    print('DEBUG uploadWarrantyDelivery(UPDATE) => ${res.statusCode} ${res.body}');
    return res.statusCode >= 200 && res.statusCode < 300;
  }

// ---------- PERBAIKAN ----------
   static Future<List<OptionItem>> fetchPerbaikanCustomers({
    int? departmentId,
    int? employeeId,
    int? categoryId,
    String? q,
  }) async {
    // fleksibel: coba beberapa path
    final headers = await _authorizedHeaders();
    final tryUris = <Uri>[
      _buildUri('orders', query: {
        'type': 'customers',
        if (departmentId != null) 'department_id': '$departmentId',
        if (employeeId != null) 'employee_id': '$employeeId',
        if (categoryId != null) 'customer_categories_id': '$categoryId',
        if (q?.isNotEmpty == true) 'filter[search]': q!,
        'per_page': '1000',
      }),
      _buildUri('customers', query: {
        if (q?.isNotEmpty == true) 'filter[search]': q!,
        'per_page': '1000',
      }),
    ];

    for (final uri in tryUris) {
      try {
        final res = await http.get(uri, headers: headers);
        if (res.statusCode != 200) continue;
        final list = _extractList(_safeDecode(res.body));
        if (list.isEmpty) continue;
        return list.map<OptionItem>((m) => _parseCustomer(m)).toList();
      } catch (_) {}
    }
    return <OptionItem>[];
  }

  static Future<List<PerbaikanData>> fetchPerbaikanData({
    int page = 1,
    int perPage = 50,
    String? q,
  }) async {
    final headers = await _authorizedHeaders();

    final uri = _buildUri(perbaikanBasePath, query: {
      'page': '$page',
      'per_page': '$perPage',
      if (q?.isNotEmpty == true) 'filter[search]': q!,
    });

    final res = await http.get(uri, headers: headers);
    if (res.statusCode != 200) {
      throw Exception('GET $uri => ${res.statusCode} ${res.body}');
    }

    final items = _extractList(_safeDecode(res.body));
    if (items.isEmpty) return <PerbaikanData>[];

    return items.map((mRaw) {
      final m = Map<String, dynamic>.from(mRaw);

      // normalisasi alamat -> list seperti Customer
      m['alamat_detail'] = ApiService.formatAddressList(
        m['address'] ?? m['alamat'] ?? m['alamat_detail'] ?? m,
      );

      // siapkan image absolut (kalau ada)
      final img = (m['image_url'] ?? m['image'] ?? m['foto'] ?? m['gambar'] ?? '').toString();
      m['image_url'] = _absoluteUrl(img);

      return PerbaikanData.fromJson(m);
    }).toList();
  }

  static Future<bool> createPerbaikanData({
    required int departmentId,
    required int employeeId,
    required int customerId,
    required int customerCategoryId,
    required String pilihanData,
    String? dataBaru,
    // alamat sederhana (opsional)
    String? provinsiCode,
    String? kotaKabCode,
    String? kecamatanCode,
    String? kelurahanCode,
    String? kodePos,
    String? detailAlamat,
    // nama wilayah (opsional)
    String? provinsiName,
    String? kotaKabName,
    String? kecamatanName,
    String? kelurahanName,
    List<XFile>? photos,
  }) async {
    final headers = await _authorizedHeaders();

    final url = _buildUri(perbaikanBasePath); // <- pakai path benar
    final req = http.MultipartRequest('POST', url)..headers.addAll(headers);

    // field inti
    req.fields['department_id'] = departmentId.toString();
    req.fields['employee_id'] = employeeId.toString();
    req.fields['customer_id'] = customerId.toString();
    req.fields['customer_categories_id'] = customerCategoryId.toString();
    req.fields['pilihan_data'] = pilihanData;
    if ((dataBaru ?? '').trim().isNotEmpty) {
      req.fields['data_baru'] = dataBaru!.trim();
    }

    // alamat (optional)
    if ([provinsiCode, kotaKabCode, kecamatanCode, kelurahanCode, kodePos, detailAlamat]
        .any((e) => (e ?? '').isNotEmpty)) {
      if (provinsiCode != null) req.fields['address[0][provinsi_code]'] = provinsiCode;
      if (kotaKabCode  != null) req.fields['address[0][kota_kab_code]'] = kotaKabCode;
      if (kecamatanCode!= null) req.fields['address[0][kecamatan_code]'] = kecamatanCode;
      if (kelurahanCode!= null) req.fields['address[0][kelurahan_code]'] = kelurahanCode;
      if (kodePos      != null) req.fields['address[0][kode_pos]'] = kodePos;
      if (detailAlamat != null) req.fields['address[0][detail_alamat]'] = detailAlamat;

      // *_name (flat)
      if (provinsiName != null) req.fields['address[0][provinsi_name]'] = provinsiName;
      if (kotaKabName  != null) req.fields['address[0][kota_kab_name]'] = kotaKabName;
      if (kecamatanName!= null) req.fields['address[0][kecamatan_name]'] = kecamatanName;
      if (kelurahanName!= null) req.fields['address[0][kelurahan_name]'] = kelurahanName;

      // nested object
      if (provinsiCode != null) req.fields['address[0][provinsi][code]'] = provinsiCode;
      if (provinsiName != null) req.fields['address[0][provinsi][name]'] = provinsiName;
      if (kotaKabCode  != null) req.fields['address[0][kota_kab][code]'] = kotaKabCode;
      if (kotaKabName  != null) req.fields['address[0][kota_kab][name]'] = kotaKabName;
      if (kecamatanCode!= null) req.fields['address[0][kecamatan][code]'] = kecamatanCode;
      if (kecamatanName!= null) req.fields['address[0][kecamatan][name]'] = kecamatanName;
      if (kelurahanCode!= null) req.fields['address[0][kelurahan][code]'] = kelurahanCode;
      if (kelurahanName!= null) req.fields['address[0][kelurahan][name]'] = kelurahanName;
    }

    // foto (optional)
    if (photos != null && photos.isNotEmpty) {
      for (final x in photos) {
        if (kIsWeb) {
          final bytes = await x.readAsBytes();
          req.files.add(http.MultipartFile.fromBytes('images[]', bytes, filename: x.name));
        } else {
          req.files.add(await http.MultipartFile.fromPath('images[]', x.path));
        }
      }
    }

    final res = await http.Response.fromStream(await req.send());
    debugPrint('DEBUG createPerbaikanData => ${res.statusCode} ${res.body}');
    return res.statusCode >= 200 && res.statusCode < 300;
  }


  // expose absolute url util to models
  static String absoluteUrl(String? s) => _absoluteUrl(s);

  // ---------- Utility ----------
  static String get _origin {
    final u = Uri.parse(baseUrl);
    final port = u.hasPort ? ':${u.port}' : '';
    return '${u.scheme}://${u.host}$port';
  }

  static String _absoluteUrl(String? maybe) {
    if (maybe == null || maybe.isEmpty) return '';
    if (maybe.startsWith('http://') || maybe.startsWith('https://')) {
      return maybe;
    }
    final path = maybe.startsWith('/') ? maybe : '/$maybe';
    return '$_origin$path';
  }
}
