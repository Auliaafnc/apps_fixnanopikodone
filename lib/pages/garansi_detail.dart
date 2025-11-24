// lib/pages/garansi_detail.dart
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';

import '../models/garansi_row.dart';
import '../services/api_service.dart';
import '../widgets/clickable_thumb.dart';

class GaransiDetailScreen extends StatefulWidget {
  final GaransiRow garansi;

  const GaransiDetailScreen({
    super.key,
    required this.garansi,
  });

  @override
  State<GaransiDetailScreen> createState() => _GaransiDetailScreenState();
}

class _GaransiDetailScreenState extends State<GaransiDetailScreen> {
  final ImagePicker _picker = ImagePicker();

  List<XFile> _pickedPhotos = [];
  bool _submitting = false;

  GaransiRow? _detail;          // <-- detail dari API
  bool _loadingDetail = false;  // <-- loading indicator

  GaransiRow get g => _detail ?? widget.garansi;

  bool get _canUploadDelivery => g.canUploadDelivery;

  @override
  void initState() {
    super.initState();
    _loadDetail(); // ambil detail dari backend
  }

  Future<void> _loadDetail() async {
    setState(() => _loadingDetail = true);
    try {
      final d = await ApiService.fetchWarrantyRowDetail(widget.garansi.id);
      if (!mounted) return;
      setState(() => _detail = d);
    } catch (_) {
      // kalau gagal ya pakai data dari list saja
    } finally {
      if (mounted) setState(() => _loadingDetail = false);
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
        title: const Text(
          'Detail Garansi',
          style: TextStyle(color: Colors.black),
        ),
      ),
      body: SafeArea(
        child: _loadingDetail && _detail == null
            ? const Center(child: CircularProgressIndicator())
            : SingleChildScrollView(
                padding: const EdgeInsets.all(20),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Data Garansi (Read-only)',
                      style: TextStyle(
                        color: Colors.white,
                        fontSize: isTablet ? 20 : 18,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    const SizedBox(height: 16),

                    _buildReadOnlyCard(isTablet),

                    const SizedBox(height: 24),

                    _buildDeliverySection(isTablet),

                    const SizedBox(height: 30),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.end,
                      children: [
                        OutlinedButton(
                          onPressed: () => Navigator.pop(context, false),
                          child: const Text('Tutup'),
                        ),
                        const SizedBox(width: 12),
                        ElevatedButton(
                          onPressed: (!_canUploadDelivery || _submitting)
                              ? null
                              : _submit,
                          style: ElevatedButton.styleFrom(
                            backgroundColor: Colors.blue,
                            foregroundColor: Colors.white,
                          ),
                          child: _submitting
                              ? const SizedBox(
                                  width: 18,
                                  height: 18,
                                  child:
                                      CircularProgressIndicator(strokeWidth: 2),
                                )
                              : const Text('Kirim Bukti'),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
      ),
    );
  }

  // ================= READ ONLY CARD =================

  Widget _buildReadOnlyCard(bool isTablet) {
    Widget _row(String label, String? value) {
      final v = (value == null ||
              value.isEmpty ||
              value.toLowerCase() == 'null')
          ? '-'
          : value;
      return Padding(
        padding: const EdgeInsets.only(bottom: 8),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            SizedBox(
              width: isTablet ? 220 : 150,
              child: Text(
                label,
                style: const TextStyle(
                  color: Colors.white70,
                  fontWeight: FontWeight.w500,
                ),
              ),
            ),
            Expanded(
              child: Text(
                v,
                style: const TextStyle(color: Colors.white),
              ),
            ),
          ],
        ),
      );
    }

    final pengajuanRejected =
        g.statusPengajuanRaw.toLowerCase() == 'rejected';
    final garansiCancelled =
        g.statusGaransiRaw.toLowerCase() == 'cancelled';
    final garansiOnHold =
        g.statusGaransiRaw.toLowerCase() == 'on_hold';

    String _clean(String s) =>
        (s == '-' || s.trim().isEmpty || s.toLowerCase() == 'null')
            ? '-'
            : s.trim();

    final pengajuanNote = _clean(g.statusPengajuanNote);
    final garansiNote = _clean(g.statusGaransiNote);
    final holdUntil = _clean(g.statusGaransiHoldUntil);

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: const Color(0xFF152236),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: Colors.white24),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _row('Garansi Number', g.garansiNo),
          _row('Departemen', g.department),
          _row('Karyawan', g.employee),
          _row('Kategori Customer', g.category),
          _row('Customer', g.customer),
          _row('Telepon', g.phone),
          _row('Alamat', g.address),
          _row('Tanggal Pembelian', g.purchaseDate),
          _row('Tanggal Klaim Garansi', g.claimDate),
          _row('Alasan Pengajuan Garansi', g.reason),
          _row('Catatan Tambahan', g.notes),

          const SizedBox(height: 12),
          const Text(
            'Detail Produk',
            style: TextStyle(
              color: Colors.white70,
              fontWeight: FontWeight.w500,
            ),
          ),
          const SizedBox(height: 4),
          ...g.productDetail
              .split('\n')
              .map((e) => e.trim())
              .where((e) => e.isNotEmpty)
              .map(
                (e) => Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      '• ',
                      style: TextStyle(color: Colors.white, fontSize: 13),
                    ),
                    Expanded(
                      child: Text(
                        e,
                        style: const TextStyle(
                          color: Colors.white,
                          fontSize: 13,
                        ),
                      ),
                    ),
                  ],
                ),
              )
              .toList(),

          const SizedBox(height: 12),
          const Text(
            'Foto Barang',
            style: TextStyle(
              color: Colors.white70,
              fontWeight: FontWeight.w500,
            ),
          ),
          const SizedBox(height: 6),
          (g.imageUrl == null || g.imageUrl!.isEmpty)
              ? const Text(
                  '-',
                  style: TextStyle(color: Colors.white),
                )
              : ClickableThumb(
                  url: g.imageUrl!,
                  heroTag: 'garansi_barang_${g.garansiNo}_${g.createdAt}',
                  size: 70,
                ),

          const SizedBox(height: 12),
          const Text(
            'Status',
            style: TextStyle(
              color: Colors.white70,
              fontWeight: FontWeight.w500,
            ),
          ),
          const SizedBox(height: 4),
          Wrap(
            spacing: 6,
            runSpacing: 6,
            children: [
              _statusChip(
                g.statusPengajuanLabel,
                g.statusPengajuanColorHex,
              ),
              _statusChip(
                g.statusProdukLabel,
                g.statusProdukColorHex,
              ),
              _statusChip(
                g.statusGaransiLabel,
                g.statusGaransiColorHex,
              ),
            ],
          ),

          // ===== ALASAN STATUS TAMBAHAN =====
          const SizedBox(height: 16),

          if (pengajuanRejected) ...[
            const Text(
              'Alasan Pengajuan Ditolak',
              style: TextStyle(
                color: Colors.redAccent,
                fontWeight: FontWeight.w600,
              ),
            ),
            const SizedBox(height: 4),
            Text(
              pengajuanNote,
              style: const TextStyle(color: Colors.white),
            ),
            const SizedBox(height: 12),
          ],

          if (garansiCancelled) ...[
            const Text(
              'Alasan Garansi Dibatalkan',
              style: TextStyle(
                color: Colors.redAccent,
                fontWeight: FontWeight.w600,
              ),
            ),
            const SizedBox(height: 4),
            Text(
              garansiNote,
              style: const TextStyle(color: Colors.white),
            ),
            const SizedBox(height: 12),
          ],

          if (garansiOnHold) ...[
            const Text(
              'Alasan Garansi di-Hold',
              style: TextStyle(
                color: Colors.orangeAccent,
                fontWeight: FontWeight.w600,
              ),
            ),
            const SizedBox(height: 4),
            Text(
              garansiNote,
              style: const TextStyle(color: Colors.white),
            ),
            const SizedBox(height: 8),
            const Text(
              'Batas Hold',
              style: TextStyle(
                color: Colors.orangeAccent,
                fontWeight: FontWeight.w600,
              ),
            ),
            const SizedBox(height: 4),
            Text(
              holdUntil,
              style: const TextStyle(color: Colors.white),
            ),
          ],
        ],
      ),
    );
  }

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

  // ================= DELIVERY SECTION =================

  Widget _buildDeliverySection(bool isTablet) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          'Bukti Pengiriman',
          style: TextStyle(
            color: Colors.white,
            fontSize: isTablet ? 18 : 16,
            fontWeight: FontWeight.bold,
          ),
        ),
        const SizedBox(height: 8),
        Text(
          _canUploadDelivery
              ? 'Status pengajuan APPROVED, produk READY_STOCK dan garansi DELIVERED.\nKamu bisa upload atau mengganti bukti pengiriman.'
              : 'Belum memenuhi status untuk upload bukti pengiriman.\n(Status harus: approved + ready_stock + delivered)',
          style: TextStyle(
            color: Colors.white.withOpacity(0.85),
            fontSize: 13,
          ),
        ),
        const SizedBox(height: 12),

        if (g.deliveryImageUrl != null && g.deliveryImageUrl!.isNotEmpty) ...[
          const Text(
            'Bukti yang sudah diupload:',
            style: TextStyle(
              color: Colors.white70,
              fontWeight: FontWeight.w500,
            ),
          ),
          const SizedBox(height: 6),
          ClickableThumb(
            url: g.deliveryImageUrl!,
            heroTag: 'garansi_delivery_${g.garansiNo}_${g.updatedAt}',
            size: 70,
          ),
          const SizedBox(height: 16),
        ],

        AbsorbPointer(
          absorbing: !_canUploadDelivery,
          child: Opacity(
            opacity: _canUploadDelivery ? 1 : 0.4,
            child: Container(
              width: double.infinity,
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: const Color(0xFF152236),
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: Colors.white24),
              ),
              child: Column(
                children: [
                  const Icon(
                    Icons.cloud_upload,
                    size: 40,
                    color: Colors.white70,
                  ),
                  const SizedBox(height: 8),
                  Text(
                    _pickedPhotos.isEmpty
                        ? 'Tap tombol di bawah untuk memilih foto bukti pengiriman.'
                        : _pickedPhotos.length == 1
                            ? 'File terpilih: ${_pickedPhotos.first.name}'
                            : '${_pickedPhotos.length} file terpilih.',
                    style: const TextStyle(
                      color: Colors.white70,
                      fontSize: 13,
                    ),
                    textAlign: TextAlign.center,
                  ),
                  const SizedBox(height: 12),
                  ElevatedButton.icon(
                    onPressed: _pickImage,
                    icon: const Icon(Icons.photo_camera),
                    label: const Text('Pilih Foto'),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: Colors.blue,
                      foregroundColor: Colors.white,
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ],
    );
  }

  // ================= PICK & SUBMIT =================

  Future<void> _pickImage() async {
    final XFile? img =
        await _picker.pickImage(source: ImageSource.gallery, imageQuality: 85);

    if (img != null) {
      setState(() => _pickedPhotos = [img]);
    }
  }

  Future<void> _submit() async {
    if (_pickedPhotos.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Pilih foto bukti pengiriman dulu')),
      );
      return;
    }

    setState(() => _submitting = true);

    try {
      final ok = await ApiService.uploadWarrantyDelivery(
        garansiId: g.id,
        photos: _pickedPhotos,
      );

      if (!mounted) return;

      if (ok) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Bukti pengiriman berhasil diupload'),
          ),
        );
        Navigator.pop(context, true);
      } else {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Gagal upload bukti pengiriman'),
          ),
        );
      }
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Error upload: $e')),
      );
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }
}
