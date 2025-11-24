import '../services/api_service.dart';

class GaransiRow {
  final int id;

  final String garansiNo;
  final String department;
  final String employee;
  final String category;
  final String customer;
  final String phone;
  final String address;
  final String reason;
  final String notes;
  final String productDetail;

  /// Label lama (fallback)
  final String status;

  /// Raw status dari backend
  final String statusPengajuanRaw;
  final String statusProductRaw;
  final String statusGaransiRaw;

  final String createdAt;
  final String updatedAt;
  final String purchaseDate;
  final String claimDate;

  /// Foto barang (pertama) – untuk kompatibilitas lama
  final String? imageUrl;

  /// 🔥 Foto barang (banyak)
  final List<String> imageUrls;

  /// Foto bukti pengiriman (saat delivered)
  final String? deliveryImageUrl;

  final String? pdfUrl;

  /// --- FIELD BARU UNTUK ALASAN STATUS ---
  /// alasan pengajuan ditolak (status_pengajuan = rejected)
  final String statusPengajuanNote; // dari rejection_comment / status_pengajuan_note
  /// alasan garansi (dipakai untuk cancel / hold)
  final String statusGaransiNote;   // dari cancelled_comment / on_hold_comment / status_garansi_note
  /// batas hold garansi
  final String statusGaransiHoldUntil; // dari on_hold_until / hold_until

  GaransiRow({
    required this.id,
    required this.garansiNo,
    required this.department,
    required this.employee,
    required this.category,
    required this.customer,
    required this.phone,
    required this.address,
    required this.reason,
    required this.notes,
    required this.productDetail,
    required this.status,
    required this.statusPengajuanRaw,
    required this.statusProductRaw,
    required this.statusGaransiRaw,
    required this.createdAt,
    required this.updatedAt,
    required this.purchaseDate,
    required this.claimDate,
    this.imageUrl,
    required this.imageUrls,
    this.deliveryImageUrl,
    this.pdfUrl,
    required this.statusPengajuanNote,
    required this.statusGaransiNote,
    required this.statusGaransiHoldUntil,
  });

  // ---------- helpers ----------
  static String _s(dynamic v) {
    if (v == null) return '-';
    final s = '$v'.trim();
    return s.isEmpty ? '-' : s;
  }

  static String _addr(Map<String, dynamic> j) {
    // 1) kalau sudah ada text siap pakai
    final t = _s(j['address_text']);
    if (t != '-' && t.isNotEmpty) return t;

    String _nameOf(dynamic v, [String? altKey]) {
      if (v == null) return '-';
      if (v is Map) {
        final n = _s(v['name']);
        if (n != '-') return n;
        if (altKey != null) {
          final m = _s(v[altKey]);
          if (m != '-') return m;
        }
        return '-';
      }
      return _s(v);
    }

    String _formatFromEntry(Map e) {
      final detail = _s(e['detail_alamat']);

      final kel = _nameOf(e['kelurahan']) != '-' ? _nameOf(e['kelurahan'])
                : _s(e['kelurahan_name']);
      final kec = _nameOf(e['kecamatan']) != '-' ? _nameOf(e['kecamatan'])
                : _s(e['kecamatan_name']);
      final kota = _nameOf(e['kota_kab']) != '-' ? _nameOf(e['kota_kab'])
                : _s(e['kota_kab_name']);
      final prov = _nameOf(e['provinsi']) != '-' ? _nameOf(e['provinsi'])
                : _s(e['provinsi_name']);
      final kode = _s(e['kode_pos']);

      final parts = [detail, kel, kec, kota, prov, kode]
          .where((x) => x != '-' && x.isNotEmpty)
          .toList();

      return parts.isEmpty ? '-' : parts.join(', ');
    }

    // 2) address: [ {...} ]
    final a = j['address'];
    if (a is List && a.isNotEmpty && a.first is Map) {
      final s = _formatFromEntry(a.first as Map);
      if (s != '-') return s;
    }

    // 3) alamat_detail: [ {...} ]
    final ad = j['alamat_detail'];
    if (ad is List && ad.isNotEmpty && ad.first is Map) {
      final s = _formatFromEntry(ad.first as Map);
      if (s != '-') return s;
    }

    // 4) fallback string polos
    final sAddr = _s(j['address']);
    if (sAddr != '-') return sAddr;

    return '-';
  }

  static String _joinProducts(List prods) {
  if (prods.isEmpty) return '-';
  return prods.map((p) {
    final brand = _s(p['brand'] ?? p['brand_name']);
    final cat   = _s(p['category'] ?? p['category_name']);
    final prod  = _s(p['product'] ?? p['product_name']);
    final col   = _s(p['color'] ?? p['warna_id']); // nama warna kalau ada
    final qty   = p['quantity'] ?? p['qty'] ?? 0;
    // (kalau mau ikut OrderRow yang tampilkan harga juga, bisa tambahkan price di sini)
    return '$brand-$cat-$prod-$col-Qty:$qty';
  }).join('\n');
}


  String get statusPengajuanLabel {
    switch (statusPengajuanRaw.toLowerCase()) {
      case 'approved': return 'Disetujui';
      case 'rejected': return 'Ditolak';
      case 'pending':
      default:         return 'Pending';
    }
  }

  int get statusPengajuanColorHex {
    switch (statusPengajuanRaw.toLowerCase()) {
      case 'approved': return 0xFF2E7D32; // green
      case 'rejected': return 0xFFD32F2F; // red
      case 'pending':
      default:         return 0xFFFFA000; // amber
    }
  }

  String get statusProdukLabel {
    switch (statusProductRaw.toLowerCase()) {
      case 'ready_stock': return 'Ready Stock';
      case 'sold_out':    return 'Sold Out';
      case 'rejected':    return 'Ditolak';
      case 'pending':
      default:            return 'Pending';
    }
  }

  int get statusProdukColorHex {
    switch (statusProductRaw.toLowerCase()) {
      case 'ready_stock': return 0xFF2E7D32; // green
      case 'sold_out':
      case 'rejected':    return 0xFFD32F2F; // red
      case 'pending':
      default:            return 0xFFFFA000; // amber
    }
  }

  String get statusGaransi => statusGaransiRaw;

  bool get canUploadDelivery {
    final a = statusPengajuanRaw.toLowerCase();
    final b = statusProductRaw.toLowerCase();
    final c = statusGaransiRaw.toLowerCase();
    return a == 'approved' && b == 'ready_stock' && c == 'delivered';
  }

  String get statusGaransiLabel {
    switch (statusGaransiRaw.toLowerCase()) {
      case 'confirmed':  return 'Confirmed';
      case 'processing': return 'Processing';
      case 'on_hold':    return 'On Hold';
      case 'delivered':  return 'Delivered';
      case 'completed':  return 'Completed';
      case 'cancelled':  return 'Cancelled';
      case 'rejected':   return 'Ditolak';
      case 'pending':
      default:
        return (statusPengajuanRaw.toLowerCase() == 'rejected')
            ? 'Ditolak'
            : 'Pending';
    }
  }

  int get statusGaransiColorHex {
    switch (statusGaransiLabel) {
      case 'Delivered':
      case 'Processing':
      case 'Confirmed': return 0xFF1976D2; // blue
      case 'Completed': return 0xFF2E7D32; // green
      case 'On Hold':
      case 'Pending':  return 0xFFFFA000; // amber
      case 'Ditolak':
      case 'Cancelled': return 0xFFD32F2F; // red
      default: return 0xFF607D8B;
    }
  }

  // ---------- mapper dari API ----------
  factory GaransiRow.fromJson(Map<String, dynamic> j) {
    final garansiNo = () {
      final a = _s(j['no_garansi']);
      if (a != '-') return a;
      final b = _s(j['warranty_no']);
      if (b != '-') return b;
      final c = _s(j['no']);
      if (c != '-') return c;
      return '-';
    }();

    // address: pakai address_display / address_text kalau ada, else format dari detail
    final address = () {
      final disp = '${j['address_display'] ?? ''}'.trim();
      if (disp.isNotEmpty && disp != '-') return disp;

      final txt = '${j['address_text'] ?? ''}'.trim();
      if (txt.isNotEmpty && txt != '-') return txt;

      return ApiService.formatAddress(
        j['address_detail'] ?? j['address'] ?? j,
      );
    }();

    final String productDetail = (j['products'] is List)
        ? _joinProducts(j['products'] as List)
        : _s(j['products_details']);

    // ---------- FOTO BARANG (multi + single) ----------
    List<String> imageUrls = [];

    // 1) dari field image_urls (hasil GaransiTransformer baru)
    if (j['image_urls'] is List) {
      imageUrls = (j['image_urls'] as List)
          .map((e) => ApiService.absoluteUrl(e.toString()))
          .where((s) => s.trim().isNotEmpty)
          .toList();
    }

    // 2) fallback dari image (list/string) kalau image_urls kosong
    if (imageUrls.isEmpty) {
      if (j['image'] is List && (j['image'] as List).isNotEmpty) {
        imageUrls = (j['image'] as List)
            .map((e) => ApiService.absoluteUrl(e.toString()))
            .where((s) => s.trim().isNotEmpty)
            .toList();
      } else if (j['image'] is String &&
          (j['image'] as String).trim().isNotEmpty) {
        imageUrls = [ApiService.absoluteUrl(j['image'] as String)];
      }
    }

    // single utama (untuk UI lama)
    String? imageUrl;
    if (imageUrls.isNotEmpty) {
      imageUrl = imageUrls.first;
    } else if (j['image_url'] != null &&
        '${j['image_url']}'.trim().isNotEmpty) {
      imageUrl = ApiService.absoluteUrl('${j['image_url']}');
    }

    // ---------- BUKTI PENGIRIMAN ----------
    String? deliveryUrl;
    if (j['delivery_images_urls'] is List &&
        (j['delivery_images_urls'] as List).isNotEmpty) {
      deliveryUrl = ApiService.absoluteUrl(
        '${(j['delivery_images_urls'] as List).first}',
      );
    }
    if ((deliveryUrl == null || deliveryUrl.isEmpty) &&
        j['delivery_image_url'] != null &&
        '${j['delivery_image_url']}'.isNotEmpty) {
      deliveryUrl = ApiService.absoluteUrl('${j['delivery_image_url']}');
    }
    if ((deliveryUrl == null || deliveryUrl.isEmpty) &&
        j['delivery_images'] is List &&
        (j['delivery_images'] as List).isNotEmpty) {
      deliveryUrl = ApiService.absoluteUrl(
        '${(j['delivery_images'] as List).first}',
      );
    }
    if ((deliveryUrl == null || deliveryUrl.isEmpty) &&
        j['delivery_image'] is String &&
        (j['delivery_image'] as String).isNotEmpty) {
      deliveryUrl =
          ApiService.absoluteUrl(j['delivery_image'] as String);
    }

    // Helper aman untuk field yang bisa berupa Map{name} atau String langsung
    String _mapOrString(dynamic v) {
      if (v is Map) return _s(v['name']);
      return _s(v);
    }

    // --- Field alasan dari backend ---
    final pengajuanNote = _s(
      j['status_pengajuan_note'] ??
          j['rejection_comment'],
    );

    final garansiNote = _s(
      j['status_garansi_note'] ??
          j['on_hold_comment'] ??
          j['cancelled_comment'],
    );

    final holdUntil = _s(
      j['on_hold_until'] ??
          j['hold_until'],
    );

    return GaransiRow(
      id: int.tryParse('${j['id'] ?? 0}') ?? 0,
      garansiNo: garansiNo,
      department: _mapOrString(j['department']),
      employee: _mapOrString(j['employee']),
      category: _mapOrString(j['customer_category'] ?? j['category']),
      customer: _mapOrString(j['customer']),
      phone: _s(j['phone']),
      address: address,
      reason: _s(j['reason']),
      notes: _s(j['note'] ?? j['notes']),
      productDetail: productDetail,
      status: _s(j['status']),
      statusPengajuanRaw:
          _s(j['status_pengajuan_raw'] ?? j['status_pengajuan']),
      statusProductRaw:
          _s(j['status_product_raw'] ?? j['status_product']),
      statusGaransiRaw:
          _s(j['status_garansi_raw'] ?? j['status_garansi']),
      createdAt: _s(j['created_at']),
      updatedAt: _s(j['updated_at']),
      purchaseDate:
          _s(j['purchase_date'] ?? j['tanggal_pembelian']),
      claimDate: _s(j['claim_date'] ?? j['tanggal_klaim']),
      imageUrl: imageUrl,
      imageUrls: imageUrls,
      deliveryImageUrl: deliveryUrl,
      pdfUrl:
          j['file_pdf_url']?.toString() ?? j['pdf_url']?.toString(),
      statusPengajuanNote: pengajuanNote,
      statusGaransiNote: garansiNote,
      statusGaransiHoldUntil: holdUntil,
    );
  }
}
