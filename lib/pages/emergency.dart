import 'package:flutter/material.dart';

import 'create_sales_order.dart';
import 'home.dart';
import 'profile.dart';

class EmergencyScreen extends StatelessWidget {
  const EmergencyScreen({super.key});

  @override
  Widget build(BuildContext context) {
    // Palet & panel (selaras Capsule)
    const Color bgPage = Color(0xFF0A1B2D);
    const Color headerLight = Color(0xFFE9ECEF);
    const Color blue6500 = Color(0xFF1EA7FF);
    const Color cardDark = Color(0xFF0F2741);

    final bool isTablet = MediaQuery.of(context).size.width >= 600;
    final double hPad = isTablet ? 24 : 16;
    final double vPad = isTablet ? 18 : 12;

    // Brand chip
    Widget brandChip() => Container(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
          decoration: BoxDecoration(color: Colors.grey[300], borderRadius: BorderRadius.circular(24)),
          child: const Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(Icons.lightbulb, color: Colors.black),
              SizedBox(width: 8),
              Text('Nanolite', style: TextStyle(color: Colors.black, fontWeight: FontWeight.w600)),
            ],
          ),
        );

    // HERO image: panel gelap + radius (gaya Capsule)
    Widget productImage() => ClipRRect(
          borderRadius: BorderRadius.circular(isTablet ? 20 : 16),
          child: Container(
            color: cardDark,
            padding: const EdgeInsets.all(16),
            child: Center(
              child: Image.asset(
                'assets/images/emergency.jpg',
                height: isTablet ? 260 : 220,
                fit: BoxFit.contain,
              ),
            ),
          ),
        );

    // Kartu spesifikasi (header abu-abu)
    Widget specCard() => _SpecCard(
          items: const [
            ['Tahan Sampai', 'Hingga 4 Jam'],
            ['Baterai', '2200mAh Lithium'],
            ['Hemat Energi', '90%'],
            ['Arus', 'AC/DC'],
            ['Tegangan', '100–240V'],
          ],
        );

    // ===== builder sel tabel (selaras Capsule) =====
    Widget th(String t) => ConstrainedBox(
          constraints: const BoxConstraints(minHeight: 44),
          child: Container(
            color: headerLight,
            alignment: Alignment.center,
            padding: const EdgeInsets.symmetric(horizontal: 8),
            child: Text(
              t,
              textAlign: TextAlign.center,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(color: Colors.black, fontWeight: FontWeight.w700, height: 1.1),
            ),
          ),
        );

    Widget td(String t) => ConstrainedBox(
          constraints: const BoxConstraints(minHeight: 42),
          child: Container(
            alignment: Alignment.center,
            padding: const EdgeInsets.symmetric(horizontal: 8),
            child: Text(
              t,
              textAlign: TextAlign.center,
              style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w600, height: 1.1),
            ),
          ),
        );

    Widget tdBlue(String t) => ConstrainedBox(
          constraints: const BoxConstraints(minHeight: 42),
          child: Container(
            alignment: Alignment.center,
            color: blue6500,
            padding: const EdgeInsets.symmetric(horizontal: 8),
            child: Text(
              t,
              style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, height: 1.1),
            ),
          ),
        );

    // ===== Tabel varian (tanpa kolom Harga) =====
    Widget emergencyTable() {
      // Kolom: Watt | Lumen | Tinggi | Diameter | Warna | Keterangan | Isi/Dus
      const rows = [
        ['10 Watt', 'AC 1000lm / DC 700lm', '141 mm', '70 mm', '6500K', 'Cahaya Putih Kebiruan', '80'],
        // Tambah baris lain di sini jika perlu
      ];

      // Lebar kolom: fixed (HP) / flex (Tablet)
      const phoneWidths = <int, TableColumnWidth>{
        0: FixedColumnWidth(120), // Watt
        1: FixedColumnWidth(200), // Lumen
        2: FixedColumnWidth(100), // Tinggi
        3: FixedColumnWidth(100), // Diameter
        4: FixedColumnWidth(90),  // Warna
        5: FixedColumnWidth(220), // Keterangan
        6: FixedColumnWidth(90),  // Isi/Dus
      };
      final tabletWidths = <int, TableColumnWidth>{
        0: const FlexColumnWidth(1.1),
        1: const FlexColumnWidth(1.5),
        2: const FlexColumnWidth(1.0),
        3: const FlexColumnWidth(1.0),
        4: const FlexColumnWidth(0.9),
        5: const FlexColumnWidth(1.6),
        6: const FlexColumnWidth(0.9),
      };

      final table = Table(
        columnWidths: isTablet ? tabletWidths : phoneWidths,
        defaultVerticalAlignment: TableCellVerticalAlignment.middle,
        border: const TableBorder.symmetric(
          inside: BorderSide(color: Colors.white24, width: 1),
          outside: BorderSide(color: Colors.white24, width: 1),
        ),
        children: [
          TableRow(children: [
            th('Watt'),
            th('Lumen'),
            th('Tinggi'),
            th('Diameter'),
            th('Warna'),
            th('Keterangan'),
            th('Isi/Dus'),
          ]),
          for (final r in rows)
            TableRow(
              decoration: const BoxDecoration(color: bgPage),
              children: [
                td(r[0]),
                td(r[1]),
                td(r[2]),
                td(r[3]),
                tdBlue(r[4]), // Warna (biru)
                td(r[5]),
                td(r[6]),
              ],
            ),
        ],
      );

      return ClipRRect(
        borderRadius: BorderRadius.circular(12),
        child: Container(
          color: cardDark,
          padding: const EdgeInsets.all(10),
          child: isTablet
              ? table
              : SingleChildScrollView(
                  scrollDirection: Axis.horizontal,
                  child: ConstrainedBox(
                    // semula 1050 (ada kolom harga), sekarang cukup ~940
                    constraints: const BoxConstraints(minWidth: 940),
                    child: table,
                  ),
                ),
        ),
      );
    }

    // Panel gambar perbandingan (rounded seperti Capsule)
    Widget roundedImage(String path, double h) => ClipRRect(
          borderRadius: BorderRadius.circular(16),
          child: Container(
            color: cardDark,
            padding: const EdgeInsets.all(12),
            child: Image.asset(path, height: h, fit: BoxFit.contain),
          ),
        );

    // ==== PAGE ====
    return Scaffold(
      backgroundColor: bgPage,
      appBar: AppBar(
        backgroundColor: Colors.grey[200],
        elevation: 0,
        leading: const BackButton(color: Colors.black),
        
      ),
      body: ListView(
        padding: EdgeInsets.fromLTRB(hPad, vPad, hPad, vPad + 16),
        children: [
          brandChip(),
          SizedBox(height: vPad),
          Text(
            'Product Emergency',
            style: TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: isTablet ? 18 : 16),
          ),
          SizedBox(height: vPad),

          // HERO (row di tablet, stack di HP)
          LayoutBuilder(builder: (context, c) {
            final row = isTablet && c.maxWidth >= 680;
            final img = productImage();
            final spec = specCard();
            if (row) {
              return Row(children: [Expanded(child: img), const SizedBox(width: 16), Expanded(child: spec)]);
            }
            return Column(children: [img, const SizedBox(height: 12), spec]);
          }),

          SizedBox(height: vPad),

          // TABLE
          emergencyTable(),

          SizedBox(height: vPad * 1.5),

          // PERBANDINGAN (dua gambar, panel rounded)
          LayoutBuilder(builder: (context, c) {
            final twoCols = isTablet && c.maxWidth >= 680;
            final left = roundedImage('assets/images/emerspek.jpg', isTablet ? 220 : 180);
            final right = roundedImage('assets/images/emerkomp.jpg', isTablet ? 220 : 180);
            if (twoCols) {
              return Row(children: [Expanded(child: left), const SizedBox(width: 16), Expanded(child: right)]);
            }
            return Column(children: [left, const SizedBox(height: 12), right]);
          }),
        ],
      ),

      // Bottom nav (pill abu-abu seperti Capsule)
      bottomNavigationBar: Container(
        color: bgPage,
        padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 20),
        child: Container(
          padding: const EdgeInsets.symmetric(vertical: 12),
          decoration: BoxDecoration(color: Colors.grey[300], borderRadius: BorderRadius.circular(40)),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.spaceAround,
            children: [
              _navItem(context, Icons.home, 'Home', onPressed: () {
                Navigator.pushReplacement(context, MaterialPageRoute(builder: (_) => HomeScreen()));
              }),
              _navItem(context, Icons.shopping_cart, 'Create Order', onPressed: () {
                Navigator.push(context, MaterialPageRoute(builder: (_) => CreateSalesOrderScreen()));
              }),
              _navItem(context, Icons.person, 'Profile', onPressed: () {
                Navigator.push(context, MaterialPageRoute(builder: (_) => ProfileScreen()));
              }),
            ],
          ),
        ),
      ),
    );
  }

  // Bottom nav item (selaras Capsule)
  Widget _navItem(BuildContext context, IconData icon, String label, {VoidCallback? onPressed}) {
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
          Text(label, style: TextStyle(color: const Color(0xFF0A1B2D), fontSize: fontSize)),
        ],
      ),
    );
  }
}

/// ---------- Spec Card Putih ----------
class _SpecCard extends StatelessWidget {
  final List<List<String>> items;
  const _SpecCard({required this.items, super.key});

  @override
  Widget build(BuildContext context) {
    Widget header = Container(
      width: double.infinity,
      height: 42,
      decoration: const BoxDecoration(
        color: Color(0xFFE9ECEF),
        borderRadius: BorderRadius.vertical(top: Radius.circular(12)),
      ),
      child: const Center(
        child: Text('SPESIFIKASI', style: TextStyle(fontWeight: FontWeight.w700, color: Colors.black)),
      ),
    );

    Widget row(String l, String r) => Padding(
          padding: const EdgeInsets.symmetric(vertical: 6),
          child: RichText(
            text: TextSpan(
              style: const TextStyle(color: Colors.black87, fontSize: 14, height: 1.4),
              children: [
                TextSpan(text: '$l: ', style: const TextStyle(fontWeight: FontWeight.w800)),
                TextSpan(text: r, style: const TextStyle(fontWeight: FontWeight.w600)),
              ],
            ),
          ),
        );

    return Container(
      decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(12)),
      padding: const EdgeInsets.only(bottom: 14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          header,
          const SizedBox(height: 6),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [for (final i in items) row(i[0], i[1])],
            ),
          ),
        ],
      ),
    );
  }
}
