// lib/pages/sales_order.dart
import 'package:flutter/material.dart';

import '../models/order_row.dart';
import '../services/api_service.dart';
import '../utils/downloader.dart';    // <--- baru
import 'create_sales_order.dart';
import 'home.dart';
import 'order_detail.dart';
import 'profile.dart';


class SalesOrderScreen extends StatefulWidget {
  final bool showCreatedSnack;
  const SalesOrderScreen({super.key, this.showCreatedSnack = false});

  @override
  State<SalesOrderScreen> createState() => _SalesOrderScreenState();
}

class _SalesOrderScreenState extends State<SalesOrderScreen> {
  final TextEditingController _searchCtrl = TextEditingController();

  List<OrderRow> _all = [];
  bool _loading = false;
  String? _error;

  String get _q => _searchCtrl.text.trim().toLowerCase();

  List<OrderRow> get _filtered {
    if (_q.isEmpty) return _all;
    return _all.where((o) {
      final blob = [
        o.orderNo,
        o.department,
        o.employee,
        o.customer,
        o.category,
        o.phone,
        o.address,
        o.productDetail,
        o.totalAwal,
        o.diskon,
        o.reasonDiskon,
        o.programName,
        o.programPoint,
        o.rewardPoint,
        o.totalAkhir,
        o.metodePembayaran,
        o.statusPembayaran,
        o.statusPengajuan,
        o.statusProduct,
        o.statusOrder,
        o.cancelComment,
        o.onHoldComment,
        o.onHoldUntil,
        o.status,
        o.createdAt,
        o.updatedAt,
      ].join(' ').toLowerCase();
      return blob.contains(_q);
    }).toList();
  }

  @override
  void initState() {
    super.initState();
    if (widget.showCreatedSnack) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (!mounted) return;
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Sales Order berhasil dibuat'),
            backgroundColor: Colors.green,
          ),
        );
      });
    }
    _fetch();
  }

  @override
  void dispose() {
    _searchCtrl.dispose();
    super.dispose();
  }

  Future<void> _fetch() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final items = await ApiService.fetchOrderRows(perPage: 1000);
      setState(() => _all = items);
    } catch (e) {
      setState(() => _error = e.toString());
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  String _safeFilename(String raw) =>
      raw.replaceAll(RegExp(r'[^A-Za-z0-9._-]'), '_');

  Future<void> _downloadPdf(String? url, String orderNo) async {
    if (url == null || url.isEmpty) return;
    final fname = _safeFilename('Order_$orderNo.pdf');
    await downloadFile(url, fileName: fname);
  }

  // ===== Status chip (sama gaya dengan Garansi/Return) =====
  Widget _statusChip(String raw) {
    final v = (raw.isEmpty ? '-' : raw).toLowerCase();
    String label;
    Color bg;
    switch (v) {
      case 'approved':
      case 'disetujui':
      case 'approve':
      case 'acc':
        label = 'Disetujui';
        bg = Colors.green.withOpacity(0.18);
        break;
      case 'rejected':
      case 'ditolak':
      case 'reject':
      case 'tolak':
        label = 'Ditolak';
        bg = Colors.red.withOpacity(0.18);
        break;
      case 'pending':
      case 'menunggu':
      case '-':
      default:
        label = 'Pending';
        bg = Colors.amber.withOpacity(0.18);
        break;
    }
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: Colors.white24),
      ),
      child: Text(
        label,
        style: const TextStyle(fontSize: 12, color: Colors.white),
      ),
    );
  }

  // Status Pembayaran: Sudah Bayar hijau, Belum Bayar kuning, lainnya abu
  Widget _paymentStatusChip(String raw) {
    final v = (raw.isEmpty ? '-' : raw).toLowerCase();
    String label;
    Color bg;

    if (v.contains('sudah') || v.contains('lunas') || v.contains('paid')) {
      label = raw.isEmpty ? 'Sudah Bayar' : raw;
      bg = Colors.green.withOpacity(0.18);
    } else if (v.contains('belum') || v.contains('partial')) {
      label = raw.isEmpty ? 'Belum Bayar' : raw;
      bg = Colors.amber.withOpacity(0.18);
    } else {
      label = raw.isEmpty ? '-' : raw;
      bg = Colors.grey.withOpacity(0.18);
    }

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: Colors.white24),
      ),
      child: Text(
        label,
        style: const TextStyle(fontSize: 12, color: Colors.white),
      ),
    );
  }

  // Status Produk: pending / ready_stock / sold_out / rejected
  Widget _productStatusChip(String raw) {
    final v = (raw.isEmpty ? '-' : raw).toLowerCase();
    String label;
    Color bg;

    switch (v) {
      case 'ready_stock':
      case 'ready':
      case 'available':
        label = 'Ready Stock';
        bg = Colors.green.withOpacity(0.18);
        break;
      case 'sold_out':
      case 'habis':
      case 'out_of_stock':
        label = 'Sold Out';
        bg = Colors.red.withOpacity(0.18);
        break;
      case 'rejected':
      case 'ditolak':
      case 'reject':
        label = 'Ditolak';
        bg = Colors.red.withOpacity(0.18);
        break;
      case 'pending':
      case 'menunggu':
      case '-':
      default:
        label = raw.isEmpty ? 'Pending' : raw;
        bg = Colors.amber.withOpacity(0.18);
        break;
    }

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: Colors.white24),
      ),
      child: Text(
        label,
        style: const TextStyle(fontSize: 12, color: Colors.white),
      ),
    );
  }

  // Status Proses Order: pending / processing / on_hold / delivered / completed / cancelled
  Widget _orderStatusChip(String raw) {
    final v = (raw.isEmpty ? '-' : raw).toLowerCase();
    String label;
    Color bg;

    switch (v) {
      case 'confirmed':
      case 'dikonfirmasi':
        label = 'Confirmed';
        bg = Colors.blue.withOpacity(0.20);
        break;
      case 'processing':
      case 'diproses':
      case 'process':
        label = 'Processing';
        bg = Colors.blue.withOpacity(0.20);
        break;
      case 'on_hold':
      case 'hold':
        label = 'On Hold';
        bg = Colors.deepPurple.withOpacity(0.20);
        break;
      case 'delivered':
      case 'shipped':
      case 'terkirim':
        label = 'Delivered';
        bg = Colors.teal.withOpacity(0.20);
        break;
      case 'completed':
      case 'done':
      case 'selesai':
        label = 'Completed';
        bg = Colors.green.withOpacity(0.20);
        break;
      case 'cancelled':
      case 'canceled':
      case 'dibatalkan':
        label = 'Cancelled';
        bg = Colors.red.withOpacity(0.20);
        break;
      case 'rejected':
      case 'ditolak':
      case 'reject':
        label = 'Ditolak';
        bg = Colors.red.withOpacity(0.18);
        break;
      case 'pending':
      case 'menunggu':
      case '-':
      default:
        label = 'Pending';
        bg = Colors.amber.withOpacity(0.18);
        break;
    }

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: Colors.white24),
      ),
      child: Text(
        label,
        style: const TextStyle(fontSize: 12, color: Colors.white),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final bool isTablet = MediaQuery.of(context).size.width >= 600;

    return Scaffold(
      backgroundColor: const Color(0xFF0A1B2D),
      appBar: AppBar(
        backgroundColor: Colors.white,
        foregroundColor: Colors.black,
        elevation: 1,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back, color: Colors.black),
          onPressed: () => Navigator.pop(context),
        ),
      ),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(20),
          child: LayoutBuilder(
            builder: (context, constraints) {
              final bool wide = constraints.maxWidth >= 900;

              return Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Expanded(
                        child: Text(
                          'Order List',
                          style: TextStyle(
                            color: Colors.white,
                            fontSize: isTablet ? 20 : 18,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                      ),
                      if (wide) ...[
                        _buildSearchField(isTablet ? 320 : 260),
                        const SizedBox(width: 12),
                        _buildCreateButton(context),
                      ],
                    ],
                  ),
                  if (!wide) ...[
                    const SizedBox(height: 12),
                    _buildSearchField(double.infinity),
                    const SizedBox(height: 12),
                    Align(
                      alignment: Alignment.centerRight,
                      child: _buildCreateButton(context),
                    ),
                  ],
                  const SizedBox(height: 16),
                  Expanded(
                    child: RefreshIndicator(
                      onRefresh: _fetch,
                      child: SingleChildScrollView(
                        physics: const AlwaysScrollableScrollPhysics(),
                        child: Container(
                          decoration: BoxDecoration(
                            color: const Color(0xFF152236),
                            borderRadius: BorderRadius.circular(12),
                            border: Border.all(color: Colors.white24),
                          ),
                          padding: const EdgeInsets.all(12),
                          child: _loading
                              ? const Center(child: CircularProgressIndicator())
                              : _error != null
                                  ? Center(
                                      child: Column(
                                        mainAxisSize: MainAxisSize.min,
                                        children: [
                                          Text(
                                            _error!,
                                            style: const TextStyle(
                                                color: Colors.white70),
                                            textAlign: TextAlign.center,
                                          ),
                                          const SizedBox(height: 8),
                                          OutlinedButton(
                                            onPressed: _fetch,
                                            child: const Text('Coba lagi'),
                                          ),
                                        ],
                                      ),
                                    )
                                  : _buildTable(),
                        ),
                      ),
                    ),
                  ),
                ],
              );
            },
          ),
        ),
      ),

      // Bottom nav (gaya sama seperti layar lain)
      bottomNavigationBar: Container(
        color: const Color(0xFF0A1B2D),
        padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 20),
        child: Container(
          padding: const EdgeInsets.symmetric(vertical: 12),
          decoration: BoxDecoration(
            color: Colors.grey[300],
            borderRadius: BorderRadius.circular(40),
          ),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.spaceAround,
            children: [
              _navItem(context, Icons.home, 'Home', onPressed: () {
                Navigator.pushReplacement(
                  context,
                  MaterialPageRoute(builder: (_) => HomeScreen()),
                );
              }),
              _navItem(context, Icons.shopping_cart, 'Create Order',
                  onPressed: () async {
                final created = await Navigator.push<bool>(
                  context,
                  MaterialPageRoute(
                      builder: (_) => const CreateSalesOrderScreen()),
                );
                if (created == true && mounted) {
                  await _fetch();
                  ScaffoldMessenger.of(context).showSnackBar(
                    const SnackBar(
                      content: Text('Order berhasil dibuat'),
                      backgroundColor: Colors.green,
                    ),
                  );
                }
              }),
              _navItem(context, Icons.person, 'Profile', onPressed: () {
                Navigator.push(
                  context,
                  MaterialPageRoute(builder: (_) => ProfileScreen()),
                );
              }),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildSearchField(double width) {
    return SizedBox(
      width: width,
      height: 44,
      child: TextField(
        controller: _searchCtrl,
        onChanged: (_) => setState(() {}),
        style: const TextStyle(color: Colors.white),
        decoration: InputDecoration(
          hintText: 'Search...',
          hintStyle: const TextStyle(color: Colors.white60),
          prefixIcon: const Icon(Icons.search, color: Colors.white70),
          filled: true,
          fillColor: const Color(0xFF22344C),
          contentPadding:
              const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
          border: OutlineInputBorder(
            borderRadius: BorderRadius.circular(22),
            borderSide: const BorderSide(color: Colors.white24),
          ),
          enabledBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(22),
            borderSide: const BorderSide(color: Colors.white24),
          ),
          focusedBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(22),
            borderSide: const BorderSide(color: Colors.white54),
          ),
        ),
      ),
    );
  }

  Widget _buildCreateButton(BuildContext context) {
    return ElevatedButton.icon(
      onPressed: () async {
        final created = await Navigator.push<bool>(
          context,
          MaterialPageRoute(
              builder: (_) => const CreateSalesOrderScreen()),
        );
        if (created == true && mounted) {
          await _fetch();
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
              content: Text('Order berhasil dibuat'),
              backgroundColor: Colors.green,
            ),
          );
        }
      },
      icon: const Icon(Icons.add),
      label: const Text('Pembuatan Order'),
      style: ElevatedButton.styleFrom(
        backgroundColor: Colors.blue,
        foregroundColor: Colors.white,
        shape: const StadiumBorder(),
        padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 12),
      ),
    );
  }

  Widget _buildTable() {
    DataCell _textCell(String v, {double width = 180}) => DataCell(
          SizedBox(
            width: width,
            child: Text(
              (v.isEmpty || v == 'null') ? '-' : v,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(fontSize: 13, color: Colors.white),
            ),
          ),
        );

    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      child: DataTable(
        columnSpacing: 10,
        horizontalMargin: 8,
        headingRowHeight: 38,
        dataRowHeight: 40,
        headingRowColor: MaterialStateProperty.all(const Color(0xFF22344C)),
        dataRowColor: MaterialStateProperty.resolveWith(
          (s) => s.contains(MaterialState.hovered)
              ? const Color(0xFF1B2B42)
              : const Color(0xFF152236),
        ),
        headingTextStyle: const TextStyle(
          color: Colors.white,
          fontWeight: FontWeight.w600,
          fontSize: 13,
        ),
        dataTextStyle: const TextStyle(color: Colors.white, fontSize: 13),

        // ====== 11 kolom: 10 data + 1 kolom Aksi (kaya garansi) ======
        columns: const [
          DataColumn(label: Text('Order Number')),         // o.orderNo
          DataColumn(label: Text('Kategori Customer')),    // o.category
          DataColumn(label: Text('Customer')), 
          DataColumn(label: Text('Dokumen')),
          DataColumn(label: Text('Status Pembayaran')),    // o.statusPembayaran
          DataColumn(label: Text('Status Pengajuan')),     // o.statusPengajuan
          DataColumn(label: Text('Status Produk')),        // o.statusProduct
          DataColumn(label: Text('Status Order')),         // o.statusOrder        // o.status
          DataColumn(label: Text('Tanggal Dibuat')),       // o.createdAt
          DataColumn(label: Text('Tanggal Diperbarui')),   // o.updatedAt
          DataColumn(label: Text('Aksi')),                 // tombol Detail / Upload
        ],
        rows: _filtered.map((o) {
          return DataRow(cells: [
            // 1 Order Number
            _textCell(o.orderNo, width: 130),

            // 2 Kategori Customer
            _textCell(o.category, width: 140),

            // 3 Customer
            _textCell(o.customer, width: 140),

            // 4 Dokumen (PDF Invoice)
            DataCell(
              (o.pdfUrl != null && o.pdfUrl!.isNotEmpty) 
                  ? IconButton(
                      tooltip: 'Unduh PDF',
                      icon: const Icon(Icons.picture_as_pdf,
                          color: Colors.white),
                      onPressed: () => _downloadPdf(o.pdfUrl, o.orderNo), 
                    )
                  : const Text('-', style: TextStyle(color: Colors.white)),
            ),

            // 4 Status Pembayaran -> chip
            DataCell(_paymentStatusChip(o.statusPembayaran)),

            // 5 Status Pengajuan -> chip
            DataCell(_statusChip(o.statusPengajuan)),

            // 6 Status Produk -> chip
            DataCell(_productStatusChip(o.statusProduct)),

            // 7 Status Order -> chip
            DataCell(_orderStatusChip(o.statusOrder)),

            // 9 Tanggal Dibuat
            _textCell(o.createdAt, width: 120),

            // 10 Tanggal Diperbarui
            _textCell(o.updatedAt, width: 120),

            // 11 Aksi -> ke halaman detail / upload bukti
            DataCell(
              ElevatedButton.icon(
                icon: const Icon(Icons.visibility),
                label: const Text('Detail / Upload Bukti'),
                style: ElevatedButton.styleFrom(
                  backgroundColor: Colors.blue,
                  foregroundColor: Colors.white,
                  padding: const EdgeInsets.symmetric(
                    horizontal: 14,
                    vertical: 8,
                  ),
                ),
                onPressed: () async {
                  final updated = await Navigator.push<bool>(
                    context,
                    MaterialPageRoute(
                      builder: (_) => OrderDetailScreen(order: o),
                    ),
                  );
                  if (updated == true) {
                    _fetch();
                  }
                },
              ),
            ),
          ]);
        }).toList(),
      ),
    );
  }

  static Widget _navItem(BuildContext context, IconData icon, String label,
      {VoidCallback? onPressed}) {
    final bool isTablet = MediaQuery.of(context).size.shortestSide >= 600;
    final double iconSize = isTablet ? 32 : 28;
    final double fontSize = isTablet ? 14 : 12;

    return InkWell(
      onTap: onPressed,
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: iconSize, color: const Color(0xFF0A1B2D)),
          const SizedBox(height: 4),
          Text(
            label,
            style: TextStyle(
              color: const Color(0xFF0A1B2D),
              fontSize: fontSize,
            ),
          ),
        ],
      ),
    );
  }
}
