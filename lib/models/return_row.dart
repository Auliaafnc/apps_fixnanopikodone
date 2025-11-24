import '../services/api_service.dart';

class ReturnRow {
  final int id;
  final String returnNo;
  final String department;
  final String employee;
  final String category; // customer category
  final String customer;
  final String phone;
  final String address; // gabungan address_text / address_detail

  /// amount numeric dari API (boleh null kalau backend nggak kirim)
  final int? amount;

  /// nominal string (misal: "Rp 1.000.000"), fallback kalau mau dipakai di list
  final String nominal;

  final String reason;
  final String notes;
  final String productDetail;

  // ===== STATUS UTAMA / FALLBACK =====
  final String status; // label utama (misal dari 'status' API)

  // ===== RAW STATUS =====
  final String statusPengajuanRaw; // pending/approved/rejected
  final String statusProductRaw; // pending/ready_stock/sold_out/rejected
  final String statusReturRaw; // pending/on_hold/delivered/completed/cancelled/rejected

  // ===== KOMENTAR STATUS =====
  final String rejectionComment; // alasan pengajuan ditolak
  final String onHoldComment; // alasan on_hold
  final String cancelledComment; // alasan cancel

  // ===== BATAS HOLD =====
  final String onHoldUntil;

  final String createdAt;
  final String updatedAt;

  /// Foto barang (utama)
  final String? imageUrl;

  /// Foto barang (banyak)
  final List<String> imageUrls;

  /// Foto bukti pengiriman (utama)
  final String? deliveryImageUrl;

  /// Foto bukti pengiriman (banyak)
  final List<String> deliveryImages;

  /// File PDF
  final String? pdfUrl;

  ReturnRow({
    required this.id,
    required this.returnNo,
    required this.department,
    required this.employee,
    required this.category,
    required this.customer,
    required this.phone,
    required this.address,
    required this.amount,
    required this.nominal,
    required this.reason,
    required this.notes,
    required this.productDetail,
    required this.status,
    required this.statusPengajuanRaw,
    required this.statusProductRaw,
    required this.statusReturRaw,
    required this.rejectionComment,
    required this.onHoldComment,
    required this.cancelledComment,
    required this.onHoldUntil,
    required this.createdAt,
    required this.updatedAt,
    this.imageUrl,
    this.imageUrls = const [],
    this.deliveryImageUrl,
    this.deliveryImages = const [],
    this.pdfUrl,
  });

  // ==========================
  // Helpers
  // ==========================

  static String _s(dynamic v) {
    if (v == null) return '-';
    final s = '$v'.trim();
    return s.isEmpty ? '-' : s;
  }

  static String _rp(num? v) {
    final n = (v ?? 0).toInt();
    final s = n.toString().replaceAllMapped(
          RegExp(r'(\d)(?=(\d{3})+(?!\d))'),
          (m) => '${m[1]}.',
        );
    return 'Rp $s';
  }

  static String _addr(Map<String, dynamic> j) {
    // 1) kalau sudah ada address_display / address_text dari API/ApiService
    final disp = '${j['address_display'] ?? ''}'.trim();
    if (disp.isNotEmpty && disp != '-') return disp;

    final txt = '${j['address_text'] ?? ''}'.trim();
    if (txt.isNotEmpty && txt != '-') return txt;

    // 2) fallback: pakai helper ApiService.formatAddress kalau ada struktur
    return ApiService.formatAddress(
      j['address_detail'] ?? j['alamat_detail'] ?? j['address'] ?? j,
    );
  }

  static String _joinProducts(List prods) {
    if (prods.isEmpty) return '-';
    return prods.map((p) {
      final brand = _s(p['brand'] ?? p['brand_name']);
      final cat = _s(p['category'] ?? p['category_name']);
      final prod = _s(p['product'] ?? p['product_name']);
      final col = _s(p['color'] ?? p['warna_id']);
      final qty = p['quantity'] ?? p['qty'] ?? 0;
      return '$brand-$cat-$prod-$col-Qty:$qty';
    }).join('\n');
  }

  // ==========================
  // FROM JSON (API → MODEL)
  // ==========================

  factory ReturnRow.fromJson(Map<String, dynamic> j) {
    // ===== amount & nominal =====
    int? amount;
    String nominal;

    final rawAmount = j['amount'];
    if (rawAmount is num) {
      amount = rawAmount.toInt();
      nominal = _rp(amount);
    } else {
      final rawStr = _s(rawAmount);
      final numVal = num.tryParse(
        rawStr.replaceAll('.', '').replaceAll(',', '.'),
      );
      if (numVal != null) {
        amount = numVal.toInt();
        nominal = _rp(amount);
      } else {
        amount = null;
        nominal = rawStr.startsWith('Rp ') ? rawStr : rawStr;
      }
    }

    // ===== address =====
    final address = _addr(j);

    // ===== FOTO BARANG (LIST) =====
    final List<String> imageUrls = (j['image_urls'] is List)
        ? (j['image_urls'] as List)
            .map((e) => e.toString())
            .where((e) => e.trim().isNotEmpty)
            .toList()
        : <String>[];

    String? imageUrl;
    if (imageUrls.isNotEmpty) {
      imageUrl = imageUrls.first;
    } else if (j['image'] != null && '${j['image']}'.isNotEmpty) {
      imageUrl = '${j['image']}';
    } else if (j['image_url'] != null && '${j['image_url']}'.isNotEmpty) {
      imageUrl = '${j['image_url']}';
    }

    // ===== bukti pengiriman =====
    final List<String> deliveryImages = (j['delivery_images_urls'] is List)
        ? (j['delivery_images_urls'] as List)
            .map((e) => e.toString())
            .where((e) => e.trim().isNotEmpty)
            .toList()
        : <String>[];

    String? deliveryUrl;
    if (deliveryImages.isNotEmpty) {
      deliveryUrl = deliveryImages.first;
    }
    if ((deliveryUrl == null || deliveryUrl.isEmpty) &&
        j['delivery_image_url'] != null &&
        '${j['delivery_image_url']}'.isNotEmpty) {
      deliveryUrl = '${j['delivery_image_url']}';
    }
    if ((deliveryUrl == null || deliveryUrl.isEmpty) &&
        j['delivery_images'] is List &&
        (j['delivery_images'] as List).isNotEmpty) {
      deliveryUrl = '${(j['delivery_images'] as List).first}';
    }
    if ((deliveryUrl == null || deliveryUrl.isEmpty) &&
        j['delivery_image'] is String &&
        (j['delivery_image'] as String).isNotEmpty) {
      deliveryUrl = j['delivery_image'] as String;
    }

    // ===== helper: map or string =====
    String mapOrString(dynamic v) {
      if (v is Map) return _s(v['name']);
      return _s(v);
    }

    // ===== komentar status =====
    final rejectionComment = _s(
      j['status_pengajuan_note'] ?? j['rejection_comment'],
    );
    final onHoldComment = _s(
      j['status_retur_note'] ?? j['on_hold_comment'],
    );
    final cancelledComment = _s(
      j['status_retur_cancel_note'] ?? j['cancelled_comment'],
    );
    final onHoldUntil = _s(
      j['on_hold_until'] ?? j['hold_until'],
    );

    // ===== return number (kode) =====
    final returnNo = () {
      final a = _s(j['no_return']);
      if (a != '-') return a;
      final b = _s(j['return_no']);
      if (b != '-') return b;
      final c = _s(j['code']);
      if (c != '-') return c;
      return '-';
    }();

    // ===== product detail =====
    final String productDetail = (j['products'] is List)
        ? _joinProducts(j['products'] as List)
        : _s(j['products_details']);

    return ReturnRow(
      id: int.tryParse('${j['id'] ?? 0}') ?? 0,
      returnNo: returnNo,
      department: mapOrString(j['department']),
      employee: mapOrString(j['employee']),
      category: mapOrString(j['customer_category'] ?? j['category']),
      customer: mapOrString(j['customer']),
      phone: _s(j['phone']),
      address: address,
      amount: amount,
      nominal: nominal,
      reason: _s(j['reason']),
      notes: _s(j['note'] ?? j['notes']),
      productDetail: productDetail,
      status: _s(j['status']),
      statusPengajuanRaw:
          _s(j['status_pengajuan_raw'] ?? j['status_pengajuan']),
      statusProductRaw: _s(j['status_product_raw'] ?? j['status_product']),
      statusReturRaw: _s(
          j['status_retur_raw'] ?? j['status_return_raw'] ?? j['status_retur']),
      rejectionComment: rejectionComment,
      onHoldComment: onHoldComment,
      cancelledComment: cancelledComment,
      onHoldUntil: onHoldUntil,
      createdAt: _s(j['created_at']),
      updatedAt: _s(j['updated_at']),
      imageUrl: imageUrl,
      imageUrls: imageUrls,
      deliveryImageUrl: deliveryUrl,
      deliveryImages: deliveryImages,
      pdfUrl: j['file_pdf_url']?.toString() ?? j['pdf_url']?.toString(),
    );
  }

  // ==========================
  // LABEL & WARNA STATUS
  // ==========================

  /// Nilai numeric, kalau null dianggap 0
  int get amountValue => amount ?? 0;

  /// Label rupiah yang sudah dirapikan
  String get amountLabel => _rp(amountValue);

  String get statusPengajuanLabel {
    switch (statusPengajuanRaw.toLowerCase()) {
      case 'approved':
        return 'Disetujui';
      case 'rejected':
        return 'Ditolak';
      case 'pending':
      default:
        return 'Pending';
    }
  }

  int get statusPengajuanColorHex {
    switch (statusPengajuanRaw.toLowerCase()) {
      case 'approved':
        return 0xFF2E7D32; // green
      case 'rejected':
        return 0xFFD32F2F; // red
      case 'pending':
      default:
        return 0xFFFFA000; // amber
    }
  }

  String get statusProductLabel {
    switch (statusProductRaw.toLowerCase()) {
      case 'ready_stock':
        return 'Ready Stock';
      case 'sold_out':
        return 'Sold Out';
      case 'rejected':
        return 'Ditolak';
      case 'pending':
      default:
        return 'Pending';
    }
  }

  int get statusProductColorHex {
    switch (statusProductRaw.toLowerCase()) {
      case 'ready_stock':
        return 0xFF2E7D32; // green
      case 'sold_out':
      case 'rejected':
        return 0xFFD32F2F; // red
      case 'pending':
      default:
        return 0xFFFFA000; // amber
    }
  }

  String get statusReturLabel {
    switch (statusReturRaw.toLowerCase()) {
      case 'confirmed':
        return 'Confirmed';
      case 'processing':
        return 'Processing';
      case 'on_hold':
        return 'On Hold';
      case 'delivered':
        return 'Delivered';
      case 'completed':
        return 'Completed';
      case 'cancelled':
        return 'Cancelled';
      case 'rejected':
        return 'Ditolak';
      case 'pending':
      default:
        return 'Pending';
    }
  }

  int get statusReturColorHex {
    switch (statusReturRaw.toLowerCase()) {
      case 'delivered':
      case 'processing':
      case 'confirmed':
        return 0xFF1976D2; // blue
      case 'completed':
        return 0xFF2E7D32; // green
      case 'on_hold':
      case 'pending':
        return 0xFFFFA000; // amber
      case 'cancelled':
      case 'rejected':
        return 0xFFD32F2F; // red
      default:
        return 0xFF607D8B; // grey
    }
  }

  /// Untuk `return_detail.dart` yang pakai 1 chip saja
  String get statusLabel => statusReturLabel;
  int get statusColorHex => statusReturColorHex;

  /// Catatan status gabungan (rejected / cancelled / on_hold)
  String get statusNote {
    final a = statusPengajuanRaw.toLowerCase();
    final r = statusReturRaw.toLowerCase();

    if (a == 'rejected' &&
        rejectionComment != '-' &&
        rejectionComment.isNotEmpty) {
      return rejectionComment;
    }
    if (r == 'cancelled' &&
        cancelledComment != '-' &&
        cancelledComment.isNotEmpty) {
      return cancelledComment;
    }
    if (r == 'on_hold' &&
        onHoldComment != '-' &&
        onHoldComment.isNotEmpty) {
      return onHoldComment;
    }
    return '-';
  }

  /// Aturan boleh upload bukti pengiriman
  /// (samakan dengan Garansi: approved + ready_stock + delivered)
  bool get canUploadDelivery {
    final a = statusPengajuanRaw.toLowerCase();
    final b = statusProductRaw.toLowerCase();
    final c = statusReturRaw.toLowerCase();
    return a == 'approved' && b == 'ready_stock' && c == 'delivered';
  }

  /// Semua foto BARANG (utamakan list; kalau kosong pakai single)
  List<String> get allProductImages {
    if (imageUrls.isNotEmpty) return imageUrls;
    if (imageUrl != null && imageUrl!.isNotEmpty) {
      return [imageUrl!];
    }
    return const [];
  }

  /// Semua foto BUKTI PENGIRIMAN
  List<String> get allDeliveryImages {
    if (deliveryImages.isNotEmpty) return deliveryImages;
    if (deliveryImageUrl != null && deliveryImageUrl!.isNotEmpty) {
      return [deliveryImageUrl!];
    }
    return const [];
  }
}
