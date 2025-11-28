// lib/pages/perbaikandata.dart
import 'package:flutter/material.dart';

import '../models/perbaikan_data.dart';
import '../services/api_service.dart';
import '../widgets/clickable_thumb.dart';
import 'create_perbaikan_data.dart';
import 'create_sales_order.dart';
import 'home.dart';
import 'profile.dart';

class PerbaikanDataScreen extends StatefulWidget {
  const PerbaikanDataScreen({super.key});

  @override
  State<PerbaikanDataScreen> createState() => _PerbaikanDataScreenState();
}

class _PerbaikanDataScreenState extends State<PerbaikanDataScreen> {
  final TextEditingController _searchCtrl = TextEditingController();

  List<PerbaikanData> _all = [];
  bool _loading = false;
  String? _error;

  String get _q => _searchCtrl.text.trim().toLowerCase();

  List<PerbaikanData> get _filtered {
    if (_q.isEmpty) return _all;
    return _all.where((d) {
      final blob =
          '${d.departmentName ?? ''} '
          '${d.employeeName ?? ''} '
          '${d.customerName ?? ''} '
          '${d.customerCategoryName ?? ''} '
          '${d.pilihanData ?? ''} '
          '${d.dataBaru ?? ''} '
          '${d.alamatDisplay} '
          '${d.statusPengajuan ?? ''} '        // ⬅️ masuk pencarian
          '${d.alasanPenolakan ?? ''}'         // ⬅️ masuk pencarian
              .toLowerCase();
      return blob.contains(_q);
    }).toList();
  }

  @override
  void initState() {
    super.initState();
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
      final items = await ApiService.fetchPerbaikanData(perPage: 1000);
      setState(() => _all = items);
    } catch (e) {
      setState(() => _error = e.toString());
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _goCreate() async {
    final saved = await Navigator.push<bool>(
      context,
      MaterialPageRoute(builder: (_) => const CreatePerbaikanDataScreen()),
    );
    if (!mounted) return;
    if (saved == true) {
      await _fetch();
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Perbaikan data berhasil dibuat'),
          backgroundColor: Colors.green,
        ),
      );
    }
  }

  // Format tanggal (YYYY-MM-DD HH:mm)
  String? _fmtDate(DateTime? d) {
    if (d == null) return null;
    final y = d.year.toString().padLeft(4, '0');
    final m = d.month.toString().padLeft(2, '0');
    final day = d.day.toString().padLeft(2, '0');
    final hh = d.hour.toString().padLeft(2, '0');
    final mm = d.minute.toString().padLeft(2, '0');
    return '$y-$m-$day $hh:$mm';
  }

  // ==== helper badge (mirip di Customer) ====
  Widget _statusBadge(String label, Color bg) {
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

  // chip status pengajuan (pending/approved/rejected)
  Widget _statusPengajuanChip(String? raw) {
    final v = (raw ?? '').toLowerCase();
    switch (v) {
      case 'approved':
      case 'disetujui':
        return _statusBadge('Disetujui', Colors.green.withOpacity(0.18));
      case 'rejected':
      case 'ditolak':
        return _statusBadge('Ditolak', Colors.red.withOpacity(0.18));
      case 'pending':
      case 'menunggu':
      default:
        return _statusBadge('Pending', Colors.amber.withOpacity(0.18));
    }
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
                          'List Perbaikan Data',
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
                        _buildCreateButton(),
                      ],
                    ],
                  ),
                  if (!wide) ...[
                    const SizedBox(height: 12),
                    _buildSearchField(double.infinity),
                    const SizedBox(height: 12),
                    Align(
                      alignment: Alignment.centerRight,
                      child: _buildCreateButton(),
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

      // Bottom nav — sama seperti halaman Customers
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
              _navItem(context, Icons.shopping_cart, 'Create Order', onPressed: () {
                Navigator.push(
                  context,
                  MaterialPageRoute(
                      builder: (_) => const CreateSalesOrderScreen()),
                );
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

  // ===== UI helpers =====

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

  Widget _buildCreateButton() {
    return ElevatedButton.icon(
      onPressed: _goCreate,
      icon: const Icon(Icons.add),
      label: const Text('Create Perbaikan'),
      style: ElevatedButton.styleFrom(
        backgroundColor: Colors.blue,
        foregroundColor: Colors.white,
        shape: const StadiumBorder(),
        padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 12),
      ),
    );
  }

  Widget _buildTable() {
    // bikin gaya tabel agak rapat
    DataCell t(String? v, {double width = 180}) => DataCell(
          SizedBox(
            width: width,
            child: Text(
              (v == null || v.isEmpty || v == 'null') ? '-' : v,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(fontSize: 13),
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
        headingRowColor:
            MaterialStateProperty.all(const Color(0xFF22344C)),
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
        columns: const [
          DataColumn(label: Text('Department')),
          DataColumn(label: Text('Karyawan')),
          DataColumn(label: Text('Customer')),
          DataColumn(label: Text('Kategori')),
          DataColumn(label: Text('Pilihan Data')),
          DataColumn(label: Text('Data Baru')),
          DataColumn(label: Text('Alamat')),
          DataColumn(label: Text('Gambar')),
          DataColumn(label: Text('Status Pengajuan')),   // ⬅️ baru
          DataColumn(label: Text('Alasan Penolakan')),   // ⬅️ baru
          DataColumn(label: Text('Dibuat')),
          DataColumn(label: Text('Diubah')),
        ],
        rows: _filtered.map((d) {
          return DataRow(
            cells: [
              t(d.departmentName, width: 130),
              t(d.employeeName, width: 130),
              t(d.customerName, width: 150),
              t(d.customerCategoryName, width: 150),
              t(d.pilihanData, width: 150),
              t(d.dataBaru, width: 200),
              t(d.alamatDisplay, width: 260),
              DataCell(
                (d.imageUrl == null || d.imageUrl!.isEmpty)
                    ? const Text('-')
                    : ClickableThumb(
                        url: d.imageUrl!,
                        heroTag: 'perbaikan_${d.id}',
                        size: 40,
                      ),
              ),
              // chip status pengajuan
              DataCell(_statusPengajuanChip(d.statusPengajuan)),
              // teks alasan penolakan
              t(d.alasanPenolakan, width: 200),
              t(_fmtDate(d.createdAt), width: 140),
              t(_fmtDate(d.updatedAt), width: 140),
            ],
          );
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
