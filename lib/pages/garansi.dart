// lib/pages/garansi.dart
import 'package:flutter/material.dart';

import '../models/garansi_row.dart';
import '../services/api_service.dart';
import '../utils/downloader.dart';
import 'create_garansi.dart';
import 'create_sales_order.dart';
import 'garansi_detail.dart';
import 'home.dart';
import 'profile.dart';
import 'sales_order.dart';

class GaransiScreen extends StatefulWidget {
  const GaransiScreen({super.key});
  @override
  State<GaransiScreen> createState() => _GaransiScreenState();
}

class _GaransiScreenState extends State<GaransiScreen> {
  final TextEditingController _searchCtrl = TextEditingController();

  List<GaransiRow> _all = [];
  bool _loading = false;
  String? _error;

  String get _q => _searchCtrl.text.trim().toLowerCase();

  List<GaransiRow> get _filtered {
    if (_q.isEmpty) return _all;
    return _all.where((g) {
      final blob = [
        g.garansiNo,
        g.department,           // masih boleh ikut di-search
        g.employee,             // masih boleh ikut di-search
        g.category,
        g.customer,
        g.statusPengajuanLabel,
        g.statusProdukLabel,
        g.statusGaransiLabel,
        g.createdAt,
        g.updatedAt,
      ].join(' ').toLowerCase();
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
      final items = await ApiService.fetchWarrantyRows(perPage: 1000);
      if (!mounted) return;
      setState(() => _all = items);
    } catch (e) {
      if (!mounted) return;
      setState(() => _error = e.toString());
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  String _safeFilename(String raw) =>
      raw.replaceAll(RegExp(r'[^A-Za-z0-9._-]'), '_');

  Future<void> _downloadPdf(String? url, String garansiNo) async {
    if (url == null || url.isEmpty) return;
    final fname = _safeFilename('Garansi_$garansiNo.pdf');
    await downloadFile(url, fileName: fname);
  }

  // chip status generik
  Widget _statusChip(String label, int colorHex) {
    final bg = Color(colorHex).withOpacity(0.18);
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
                          'Garansi List',
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
                              ? const Center(
                                  child: Padding(
                                    padding: EdgeInsets.all(24.0),
                                    child: CircularProgressIndicator(),
                                  ),
                                )
                              : _error != null
                                  ? Center(
                                      child: Column(
                                        mainAxisSize: MainAxisSize.min,
                                        children: [
                                          Text(
                                            _error!,
                                            style: const TextStyle(
                                              color: Colors.white70,
                                            ),
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
                if (created == true) {
                  if (!context.mounted) return;
                  Navigator.pushReplacement(
                    context,
                    MaterialPageRoute(
                      builder: (_) =>
                          const SalesOrderScreen(showCreatedSnack: true),
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
          MaterialPageRoute(builder: (_) => const CreateGaransiScreen()),
        );
        if (!mounted) return;
        if (created == true) await _fetch();
      },
      icon: const Icon(Icons.workspace_premium),
      label: const Text('Create Garansi'),
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
        dataRowMinHeight: 40,
        dataRowMaxHeight: 40,
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
        dataTextStyle:
            const TextStyle(color: Colors.white, fontSize: 13),

        // ====== 10 kolom (tanpa Department & Karyawan) ======
        columns: const [
          DataColumn(label: Text('Garansi Number')),
          DataColumn(label: Text('Kategori Customer')),
          DataColumn(label: Text('Customer')),
          DataColumn(label: Text('Dokumen')),
          DataColumn(label: Text('Status Pengajuan')),
          DataColumn(label: Text('Status Produk')),
          DataColumn(label: Text('Status Garansi')),
          DataColumn(label: Text('Tanggal Dibuat')),
          DataColumn(label: Text('Tanggal Diperbarui')),
          DataColumn(label: Text('Aksi')),
        ],
        rows: _filtered.map((g) {
          return DataRow(cells: [
            _textCell(g.garansiNo, width: 130),
            _textCell(g.category, width: 140),
            _textCell(g.customer, width: 140),

            // Dokumen PDF
            DataCell(
              (g.pdfUrl != null && g.pdfUrl!.isNotEmpty)
                  ? IconButton(
                      tooltip: 'Unduh PDF',
                      icon: const Icon(Icons.picture_as_pdf,
                          color: Colors.white),
                      onPressed: () => _downloadPdf(g.pdfUrl, g.garansiNo),
                    )
                  : const Text('-', style: TextStyle(color: Colors.white)),
            ),

            // Status
            DataCell(_statusChip(
                g.statusPengajuanLabel, g.statusPengajuanColorHex)),
            DataCell(
                _statusChip(g.statusProdukLabel, g.statusProdukColorHex)),
            DataCell(
                _statusChip(g.statusGaransiLabel, g.statusGaransiColorHex)),

            _textCell(g.createdAt, width: 120),
            _textCell(g.updatedAt, width: 120),

            // Aksi → ke halaman detail / upload bukti
            DataCell(
              ElevatedButton.icon(
                icon: const Icon(Icons.visibility),
                label: const Text('Detail / Upload Bukti'),
                style: ElevatedButton.styleFrom(
                  backgroundColor: Colors.blue,
                  foregroundColor: Colors.white,
                  padding:
                      const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
                ),
                onPressed: () async {
                  final updated = await Navigator.push<bool>(
                    context,
                    MaterialPageRoute(
                      builder: (_) => GaransiDetailScreen(garansi: g),
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

  static Widget _navItem(
    BuildContext context,
    IconData icon,
    String label, {
    VoidCallback? onPressed,
  }) {
    final bool isTablet =
        MediaQuery.of(context).size.shortestSide >= 600;
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
