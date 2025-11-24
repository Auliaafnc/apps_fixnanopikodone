import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';

import '../models/return_row.dart';
import '../services/api_service.dart';
import '../widgets/clickable_thumb.dart';

class ReturnDetailScreen extends StatefulWidget {
  final ReturnRow retur;

  const ReturnDetailScreen({
    super.key,
    required this.retur,
  });

  @override
  State<ReturnDetailScreen> createState() => _ReturnDetailScreenState();
}

class _ReturnDetailScreenState extends State<ReturnDetailScreen> {
  final ImagePicker _picker = ImagePicker();

  List<XFile> _pickedPhotos = [];
  bool _submitting = false;

  ReturnRow? _detail; // detail dari API
  bool _loadingDetail = false; // loading indicator

  ReturnRow get r => _detail ?? widget.retur;

  bool get _canUploadDelivery => r.canUploadDelivery;

  @override
  void initState() {
    super.initState();
    _loadDetail();
  }

  Future<void> _loadDetail() async {
    setState(() => _loadingDetail = true);
    try {
      final d = await ApiService.fetchReturnRowDetail(widget.retur.id);
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
          'Detail Return',
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
                      'Data Return (Read-only)',
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

    String _clean(String? s) {
      if (s == null) return '-';
      final v = s.trim();
      return (v == '-' || v.isEmpty || v.toLowerCase() == 'null') ? '-' : v;
    }

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
          _row('Return Number', r.returnNo),
          _row('Departemen', r.department),
          _row('Karyawan', r.employee),
          _row('Kategori Customer', r.category),
          _row('Customer', r.customer),
          _row('Telepon', r.phone),
          _row('Alamat', r.address),
          _row('Nominal', r.amountLabel),
          _row('Alasan Return', r.reason),
          _row('Catatan Tambahan', r.notes),

          const SizedBox(height: 12),
          const Text(
            'Detail Produk',
            style: TextStyle(
              color: Colors.white70,
              fontWeight: FontWeight.w500,
            ),
          ),
          const SizedBox(height: 4),
          ...r.productDetail
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

          Builder(
            builder: (_) {
              final imgs = r.allProductImages;
              if (imgs.isEmpty) {
                return const Text(
                  '-',
                  style: TextStyle(color: Colors.white),
                );
              }
              return Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  for (int i = 0; i < imgs.length; i++)
                    ClickableThumb(
                      url: imgs[i],
                      heroTag:
                          'return_barang_${r.returnNo}_${r.createdAt}_$i',
                      size: 70,
                    ),
                ],
              );
            },
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

          // === 3 CHIP STATUS: pengajuan, produk, retur ===
          Wrap(
            spacing: 6,
            runSpacing: 6,
            children: [
              _statusChip(
                r.statusPengajuanLabel,
                r.statusPengajuanColorHex,
              ),
              _statusChip(
                r.statusProductLabel,
                r.statusProductColorHex,
              ),
              _statusChip(
                r.statusReturLabel,
                r.statusReturColorHex,
              ),
            ],
          ),

          const SizedBox(height: 16),

          if (r.statusPengajuanRaw.toLowerCase() == 'rejected') ...[
            const Text(
              'Alasan Pengajuan Ditolak',
              style: TextStyle(
                color: Colors.redAccent,
                fontWeight: FontWeight.w600,
              ),
            ),
            const SizedBox(height: 4),
            Text(
              r.rejectionComment ?? '-',
              style: const TextStyle(color: Colors.white),
            ),
            const SizedBox(height: 12),
          ],

          if (r.statusReturRaw.toLowerCase() == 'cancelled') ...[
            const Text(
              'Alasan Retur Dibatalkan',
              style: TextStyle(
                color: Colors.redAccent,
                fontWeight: FontWeight.w600,
              ),
            ),
            const SizedBox(height: 4),
            Text(
              r.cancelledComment ?? '-',
              style: const TextStyle(color: Colors.white),
            ),
            const SizedBox(height: 12),
          ],

          if (r.statusReturRaw.toLowerCase() == 'on_hold') ...[
            const Text(
              'Alasan Retur di-Hold',
              style: TextStyle(
                color: Colors.orangeAccent,
                fontWeight: FontWeight.w600,
              ),
            ),
            const SizedBox(height: 4),
            Text(
              r.onHoldComment ?? '-',
              style: const TextStyle(color: Colors.white),
            ),
            const SizedBox(height: 8),
            const Text(
              'Batas Hold Sampai',
              style: TextStyle(
                color: Colors.orangeAccent,
                fontWeight: FontWeight.w600,
              ),
            ),
            const SizedBox(height: 4),
            Text(
              r.onHoldUntil ?? '-',
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
    final existingDelivery = r.allDeliveryImages;

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
              ? 'Status return sudah memenuhi syarat.\nKamu bisa upload atau mengganti bukti pengiriman.'
              : 'Belum memenuhi status untuk upload bukti pengiriman.',
          style: TextStyle(
            color: Colors.white.withOpacity(0.85),
            fontSize: 13,
          ),
        ),
        const SizedBox(height: 12),

        if (existingDelivery.isNotEmpty) ...[
          const Text(
            'Bukti yang sudah diupload:',
            style: TextStyle(
              color: Colors.white70,
              fontWeight: FontWeight.w500,
            ),
          ),
          const SizedBox(height: 6),

          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              for (int i = 0; i < existingDelivery.length; i++)
                ClickableThumb(
                  url: existingDelivery[i],
                  heroTag:
                      'return_delivery_${r.returnNo}_${r.updatedAt}_$i',
                  size: 70,
                ),
            ],
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
      final ok = await ApiService.uploadReturnDelivery(
        returnId: r.id,
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
