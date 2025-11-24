import '../services/api_service.dart';

class OrderRow {
  final int id;
  final String orderNo;
  final String department;
  final String employee;
  final String category;
  final String customer;
  final String phone;
  final String address;        // gabungan address_text / address_detail
  final String totalAwal;      // Rp xxx
  final String diskon;         // 10% + 5% + ...
  final String reasonDiskon;   // penjelasan diskon
  final String programName;
  final String programPoint;   // "-" jika 0 / null
  final String rewardPoint;    // "-" jika 0 / null
  final String totalAkhir;     // Rp xxx
  final String metodePembayaran;
  final String statusPembayaran;
  final String paymentDueUntil; // tempo sampai tanggal (string, "-" kalau tidak ada)

  // --- STATUS RAW DARI BACKEND ---
  final String statusPengajuanRaw; // pending | approved | rejected
  final String statusProductRaw;   // pending | ready_stock | sold_out | rejected
  final String statusOrderRaw;     // pending | confirmed | processing | on_hold | delivered | completed | cancelled | rejected

  // --- LABEL UNTUK TAMPILAN (boleh sama dengan raw) ---
  final String statusPengajuan;
  final String statusProduct;
  final String statusOrder;

  final String cancelComment;   // alasan di cancel
  final String onHoldComment;   // alasan di hold
  final String onHoldUntil;     // tanggal batas hold (string)

  // Untuk kompatibel lama: 'status' = statusOrder
  final String status;

  final String productDetail;  // join dari products[]
  final String createdAt;
  final String updatedAt;

  /// URL invoice PDF (kalau ada)
  final String? pdfUrl;

  /// URL bukti pengiriman (foto resi dsb) – single utama (kompat lama)
  final String? deliveryImageUrl;

  /// alasan pengajuan ditolak (atau catatan pengajuan lain)
  final String statusPengajuanNote;

  /// banyak foto bukti pengiriman (baru, bisa > 1)
  final List<String> deliveryImages;

  /// getter praktis untuk dipakai di UI (mirip Return/Garansi)
  List<String> get allDeliveryImages =>
      deliveryImages.where((e) => e.trim().isNotEmpty).toList();

  OrderRow({
    required this.id,
    required this.orderNo,
    required this.department,
    required this.employee,
    required this.category,
    required this.customer,
    required this.phone,
    required this.address,
    required this.totalAwal,
    required this.diskon,
    required this.reasonDiskon,
    required this.programName,
    required this.programPoint,
    required this.rewardPoint,
    required this.totalAkhir,
    required this.metodePembayaran,
    required this.statusPembayaran,
    required this.paymentDueUntil,
    // status
    required this.statusPengajuanRaw,
    required this.statusProductRaw,
    required this.statusOrderRaw,
    required this.statusPengajuan,
    required this.statusProduct,
    required this.statusOrder,
    required this.cancelComment,
    required this.onHoldComment,
    required this.onHoldUntil,
    required this.status,
    // lainnya
    required this.productDetail,
    required this.createdAt,
    required this.updatedAt,
    this.pdfUrl,
    this.deliveryImageUrl,
    required this.statusPengajuanNote,
    required this.deliveryImages,
  });

  // --- helpers internal ---
  static String _s(dynamic v) {
    if (v == null) return '-';
    final s = '$v'.trim();
    return s.isEmpty ? '-' : s;
  }

  static int _toInt(dynamic v) {
    if (v == null) return 0;
    if (v is num) return v.toInt();
    final s = '$v'.replaceAll('.', '').replaceAll(',', '');
    return int.tryParse(s) ?? 0;
  }

  static double _toDouble(dynamic v) {
    if (v == null) return 0.0;
    if (v is num) return v.toDouble();
    return double.tryParse('$v'.replaceAll(',', '.')) ?? 0.0;
  }

  static String _rp(num? v) {
    final n = (v ?? 0).toInt();
    final s = n.toString().replaceAllMapped(
      RegExp(r'(\d)(?=(\d{3})+(?!\d))'),
      (m) => '${m[1]}.',
    );
    return 'Rp $s';
  }

  static String _pointStr(dynamic v) {
    final n = _toInt(v);
    return (n <= 0) ? '-' : '$n';
  }

  static String _addr(Map<String, dynamic> j) {
    final t = _s(j['address_text']);
    if (t != '-' && t.isNotEmpty) return t;

    final detail = j['address_detail'];
    if (detail is List && detail.isNotEmpty) {
      final parts = detail.map((e) {
        if (e is Map) {
          final d   = _s(e['detail_alamat']);
          final kel = _s((e['kelurahan'] is Map) ? e['kelurahan']['name'] : null);
          final kec = _s((e['kecamatan'] is Map) ? e['kecamatan']['name'] : null);
          final kab = _s((e['kota_kab'] is Map) ? e['kota_kab']['name'] : null);
          final prv = _s((e['provinsi'] is Map) ? e['provinsi']['name'] : null);
          final kp  = _s(e['kode_pos']);
          return [d, kel, kec, kab, prv, kp]
              .where((x) => x != '-' && x.isNotEmpty)
              .join(', ');
        }
        return _s(e);
      }).where((x) => x != '-').toList();
      return parts.isEmpty ? '-' : parts.join(' | ');
    }
    return '-';
  }

  // Format persen tanpa trailing .0 / .00
  static String _pct(double v) {
    final fixed2 = v.toStringAsFixed(2);
    final trimmed = fixed2.replaceFirst(RegExp(r'\.?0+$'), '');
    return trimmed;
  }

  /// combine 4 diskon (diskon_1..4)
  static String _combineDiskon(Map? d) {
    if (d == null) return '-';
    final enabled = d['enabled'] == true;

    final values = List.generate(4, (i) {
      final n = i + 1;
      return _toDouble(d['diskon_$n']);
    }).map((x) {
      if (x.isNaN) return 0.0;
      return x.clamp(0.0, 100.0);
    }).toList();

    if (!enabled || values.every((v) => v == 0)) return '-';

    final parts = <String>[];
    for (final v in values) {
      if (v > 0) parts.add('${_pct(v)}%');
    }
    return parts.join(' + ');
  }

  /// combine penjelasan diskon 1..4
  static String _combineExplain(Map? d) {
    if (d == null || d['enabled'] != true) return '-';
    final parts = <String>[];
    for (var i = 1; i <= 4; i++) {
      final p = _s(d['penjelasan_diskon_$i']);
      if (p != '-') parts.add(p);
    }
    return parts.isEmpty ? '-' : parts.join(' + ');
  }

  static String _joinProducts(List prods) {
    if (prods.isEmpty) return '-';
    return prods.map((p) {
      final brand = _s(p['brand']);
      final cat   = _s(p['category']);
      final prod  = _s(p['product']);
      final col   = _s(p['color']);
      final qty   = p['quantity'] ?? 0;
      final price = p['price'] ?? 0;
      return '$brand-$cat-$prod-$col-Rp$price-Qty:$qty';
    }).join('\n');
  }

  /// apakah boleh upload bukti pengiriman (LOGIKA SAMA DENGAN GARANSI/RETURN)
  bool get canUploadDelivery {
    final a = statusPengajuanRaw.toLowerCase();
    final b = statusProductRaw.toLowerCase();
    final c = statusOrderRaw.toLowerCase();
    return a == 'approved' && b == 'ready_stock' && c == 'delivered';
  }

  // ---------- mapper dari API ----------
  factory OrderRow.fromJson(Map<String, dynamic> j) {
    final products = (j['products'] is List) ? j['products'] as List : const [];

    final diskon = (j['diskon'] is Map)
        ? Map<String, dynamic>.from(j['diskon'])
        : {
            'enabled': j['diskons_enabled'] == true,
            for (var i = 1; i <= 4; i++) ...{
              'diskon_$i': j['diskon_$i'],
              'penjelasan_diskon_$i': j['penjelasan_diskon_$i'],
            },
          };

    // payment due / tempo
    final paymentDueStr = _s(
      j['payment_due_until'] ??
      j['tempo_until'] ??
      j['payment_due_date'],
    );

    final rewardPointsRaw  = (j['reward'] is Map) ? (j['reward'] as Map)['points'] : j['reward_point'];
    final programPointsRaw = (j['program_point'] is Map) ? (j['program_point'] as Map)['points'] : j['jumlah_program'];

    final totalAwalVal  = j['total_harga'];
    final totalAkhirVal = j['total_harga_after_tax'];

    // RAW status dari backend
    final statusOrderRaw     = _s(j['status_order_raw'] ?? j['status_order'] ?? j['status']);
    final statusPengajuanRaw = _s(j['status_pengajuan_raw'] ?? j['status_pengajuan']);
    final statusProductRaw   = _s(j['status_product_raw'] ?? j['status_product']);

    // Label untuk tampilan (sementara samakan dengan raw)
    final statusOrderLabel     = statusOrderRaw;
    final statusPengajuanLabel = statusPengajuanRaw;
    final statusProductLabel   = statusProductRaw;

    final cancelCommentStr   = _s(
      j['cancel_comment'] ??
      j['alasan_cancel'] ??
      j['cancel_reason'] ??
      j['cancelled_comment'],
    );

    final rawPdf = j['invoice_pdf_url'] ??
                   j['invoicePdfUrl'] ??
                   j['order_file_url'] ??
                   j['file_pdf_url'] ??
                   j['pdf_url'];

    final pdfUrl = (rawPdf == null)
        ? null
        : ('$rawPdf'.trim().isEmpty ? null : ApiService.absoluteUrl('$rawPdf'.trim()));

    // alasan pengajuan ditolak (atau catatan pengajuan lain)
    final pengajuanNote = _s(
      j['status_pengajuan_note'] ??
      j['rejection_comment'],
    );

    // ---------- BUKTI PENGIRIMAN (LOGIC MIRIP GARANSI/RETURN) ----------
    String? deliveryUrl;
    final List<String> deliveryImages = [];

    // 1) Kalau ada array URL penuh dari backend
    if (j['delivery_images_urls'] is List &&
        (j['delivery_images_urls'] as List).isNotEmpty) {
      final list = (j['delivery_images_urls'] as List)
          .map((e) => ApiService.absoluteUrl(e.toString()))
          .where((s) => s.trim().isNotEmpty)
          .toList();
      if (list.isNotEmpty) {
        deliveryImages.addAll(list);
        deliveryUrl = list.first;
      }
    }

    // 2) Single url dari delivery_image_url
    if ((deliveryUrl == null || deliveryUrl.isEmpty) &&
        j['delivery_image_url'] != null &&
        '${j['delivery_image_url']}'.trim().isNotEmpty) {
      final u = ApiService.absoluteUrl('${j['delivery_image_url']}');
      deliveryUrl = u;
      if (u.isNotEmpty) deliveryImages.add(u);
    }

    // 3) Array path di delivery_images
    if ((deliveryUrl == null || deliveryUrl.isEmpty) &&
        j['delivery_images'] is List &&
        (j['delivery_images'] as List).isNotEmpty) {
      final list = (j['delivery_images'] as List)
          .map((e) => ApiService.absoluteUrl(e.toString()))
          .where((s) => s.trim().isNotEmpty)
          .toList();
      if (list.isNotEmpty) {
        deliveryImages.addAll(list);
        deliveryUrl = list.first;
      }
    }

    // 4) Single path di delivery_image
    if ((deliveryUrl == null || deliveryUrl.isEmpty) &&
        j['delivery_image'] is String &&
        (j['delivery_image'] as String).trim().isNotEmpty) {
      final u = ApiService.absoluteUrl(j['delivery_image'] as String);
      deliveryUrl = u;
      if (u.isNotEmpty) deliveryImages.add(u);
    }

    // 5) Fallback lama (bukti_pengiriman_url / delivery_photo / deliveryImageUrl)
    if (deliveryUrl == null || deliveryUrl.isEmpty) {
      final deliveryUrlRaw = j['deliveryImageUrl'] ??
          j['bukti_pengiriman_url'] ??
          j['delivery_photo'];
      if (deliveryUrlRaw is String && deliveryUrlRaw.trim().isNotEmpty) {
        final u = ApiService.absoluteUrl(deliveryUrlRaw.trim());
        deliveryUrl = u;
        if (u.isNotEmpty) deliveryImages.add(u);
      }
    }

    return OrderRow(
      id: _toInt(j['id']),
      orderNo: _s(j['no_order']),
      department: _s(j['department'] ?? j['department_name']),
      employee: _s(j['employee'] ?? j['employee_name']),
      category: _s(j['customer_category'] ?? j['customer_category_name']),
      customer: _s(j['customer'] ?? j['customer_name']),
      phone: _s(j['phone']),
      address: _addr(j),
      totalAwal: (totalAwalVal is num) ? _rp(totalAwalVal) : _s(totalAwalVal),
      diskon: _combineDiskon(diskon),
      reasonDiskon: _combineExplain(diskon),
      programName: _s(j['customer_program'] ?? j['customer_program_name']),
      programPoint: _pointStr(programPointsRaw),
      rewardPoint: _pointStr(rewardPointsRaw),
      totalAkhir: (totalAkhirVal is num) ? _rp(totalAkhirVal) : _s(totalAkhirVal),
      metodePembayaran: _s(j['payment_method']),
      statusPembayaran: _s(j['status_pembayaran']),
      paymentDueUntil: paymentDueStr,

      statusPengajuanRaw: statusPengajuanRaw,
      statusProductRaw:   statusProductRaw,
      statusOrderRaw:     statusOrderRaw,
      statusPengajuan:    statusPengajuanLabel,
      statusProduct:      statusProductLabel,
      statusOrder:        statusOrderLabel,

      onHoldComment:   _s(j['on_hold_comment']),
      onHoldUntil:     _s(j['on_hold_until']),
      cancelComment:   cancelCommentStr,
      status:          statusOrderLabel, // kompatibel lama

      productDetail: _joinProducts(products),
      createdAt: _s(j['created_at']),
      updatedAt: _s(j['updated_at']),
      pdfUrl: pdfUrl,
      deliveryImageUrl: deliveryUrl,
      statusPengajuanNote: pengajuanNote,
      deliveryImages: deliveryImages,
    );
  }
}
