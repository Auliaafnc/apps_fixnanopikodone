import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';

import '../models/order_row.dart';
import '../services/api_service.dart';
import '../widgets/clickable_thumb.dart';

class OrderDetailScreen extends StatefulWidget {
  final OrderRow order;

  const OrderDetailScreen({
    super.key,
    required this.order,
  });

  @override
  State<OrderDetailScreen> createState() => _OrderDetailScreenState();
}

class _OrderDetailScreenState extends State<OrderDetailScreen> {
  final ImagePicker _picker = ImagePicker();

  List<XFile> _pickedPhotos = [];
  bool _submitting = false;

  OrderRow? _detail; // detail dari API (kalau ada)
  bool _loadingDetail = false;

  OrderRow get o => _detail ?? widget.order;

  // LOGIKA SAMA DENGAN GARANSI/RETURN: ambil dari model
  bool get _canUploadDelivery => o.canUploadDelivery;

  @override
  void initState() {
    super.initState();
    _loadDetail();
  }

  Future<void> _loadDetail() async {
    setState(() => _loadingDetail = true);
    try {
      // pakai ID numeric
      final d = await ApiService.fetchOrderRowDetail(o.id);
      if (!mounted) return;
      setState(() => _detail = d);
    } catch (_) {
      // kalau gagal ya tetap pakai data dari list
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
          'Detail Order',
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
                      'Data Order (Read-only)',
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

    String _clean(String s) =>
        (s == '-' || s.trim().isEmpty || s.toLowerCase() == 'null')
            ? '-'
            : s.trim();

    // Status untuk alasan
    final pengajuanRejected =
        o.statusPengajuan.toLowerCase() == 'rejected';
    final orderCancelled =
        o.statusOrder.toLowerCase() == 'cancelled';
    final orderOnHold =
        o.statusOrder.toLowerCase() == 'on_hold';

    // pakai statusPengajuanNote khusus buat pengajuan
    final pengajuanNote = _clean(o.statusPengajuanNote);
    final cancelNote = _clean(o.cancelComment);
    final holdNote = _clean(o.onHoldComment);
    final holdUntil = _clean(o.onHoldUntil);

    // tempo
    final isTempo = o.metodePembayaran.toLowerCase() == 'tempo';
    final tempoUntil = _clean(o.paymentDueUntil);

    // Utility warna status
    Color _colorPengajuan(String s) {
      switch (s.toLowerCase()) {
        case 'approved':
          return const Color(0xFF2E7D32); // green
        case 'rejected':
          return const Color(0xFFD32F2F); // red
        case 'pending':
        default:
          return const Color(0xFFFFA000); // amber
      }
    }

    Color _colorProduct(String s) {
      switch (s.toLowerCase()) {
        case 'ready_stock':
          return const Color(0xFF2E7D32); // green
        case 'sold_out':
        case 'rejected':
          return const Color(0xFFD32F2F); // red
        case 'pending':
        default:
          return const Color(0xFFFFA000); // amber
      }
    }

    Color _colorOrder(String s) {
      switch (s.toLowerCase()) {
        case 'confirmed':
        case 'processing':
        case 'delivered':
          return const Color(0xFF1976D2); // blue
        case 'completed':
          return const Color(0xFF2E7D32); // green
        case 'on_hold':
          return const Color(0xFFFFA000); // amber
        case 'cancelled':
        case 'rejected':
          return const Color(0xFFD32F2F); // red
        default:
          return const Color(0xFF607D8B); // grey-blue
      }
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
          _row('Order Number', o.orderNo),
          _row('Departemen', o.department),
          _row('Karyawan', o.employee),
          _row('Kategori Customer', o.category),
          _row('Customer', o.customer),
          _row('Telepon', o.phone),
          _row('Alamat', o.address),

          const SizedBox(height: 8),
          _row('Program Customer', o.programName),
          _row('Program Point', o.programPoint),
          _row('Reward Point', o.rewardPoint),

          const SizedBox(height: 12),
          _row('Total Awal', o.totalAwal),
          _row('Diskon', o.diskon),
          _row('Alasan Diskon', o.reasonDiskon),
          _row('Total Akhir', o.totalAkhir),
          _row('Metode Pembayaran', o.metodePembayaran),
          _row('Status Pembayaran', o.statusPembayaran),

          if (isTempo) _row('Tempo sampai tanggal', tempoUntil),

          const SizedBox(height: 12),
          const Text(
            'Detail Produk',
            style: TextStyle(
              color: Colors.white70,
              fontWeight: FontWeight.w500,
            ),
          ),
          const SizedBox(height: 4),
          ...o.productDetail
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
                o.statusPengajuan,
                _colorPengajuan(o.statusPengajuan),
              ),
              _statusChip(
                o.statusProduct,
                _colorProduct(o.statusProduct),
              ),
              _statusChip(
                o.statusOrder,
                _colorOrder(o.statusOrder),
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

          if (orderCancelled) ...[
            const Text(
              'Alasan Order Dibatalkan',
              style: TextStyle(
                color: Colors.redAccent,
                fontWeight: FontWeight.w600,
              ),
            ),
            const SizedBox(height: 4),
            Text(
              cancelNote,
              style: const TextStyle(color: Colors.white),
            ),
            const SizedBox(height: 12),
          ],

          if (orderOnHold) ...[
            const Text(
              'Alasan Order di-Hold',
              style: TextStyle(
                color: Colors.orangeAccent,
                fontWeight: FontWeight.w600,
              ),
            ),
            const SizedBox(height: 4),
            Text(
              holdNote,
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

  Widget _statusChip(String label, Color color) {
    final bg = color.withOpacity(0.18);
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
    final existingDelivery = o.allDeliveryImages;

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
              ? 'Status pengajuan APPROVED, produk READY_STOCK dan order DELIVERED.\nKamu bisa upload atau mengganti bukti pengiriman.'
              : 'Belum memenuhi status untuk upload bukti pengiriman.\n(Status harus: approved + ready_stock + delivered)',
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
                      'order_delivery_${o.orderNo}_${o.updatedAt}_$i',
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
      // pakai orderId (int)
      final ok = await ApiService.uploadOrderDelivery(
        orderId: o.id,
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
