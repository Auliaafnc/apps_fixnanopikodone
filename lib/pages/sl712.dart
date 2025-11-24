// lib/pages/sl712.dart
import 'package:flutter/material.dart';

import 'create_sales_order.dart';
import 'home.dart';
import 'profile.dart';

class StreetLight712Page extends StatelessWidget {
  const StreetLight712Page({super.key});

  @override
  Widget build(BuildContext context) {
    // === Palet & panel (gaya 711 / Bulb) ===
    const Color bgPage = Color(0xFF0A1B2D);
    const Color headerLight = Color(0xFFE9ECEF);
    const Color cardDark = Color(0xFF0F2741);
    const Color blue6500 = Color(0xFF1EA7FF);

    final bool isTablet = MediaQuery.of(context).size.width >= 600;
    final double hPad = isTablet ? 24 : 16;
    final double vPad = isTablet ? 18 : 12;

    // Brand chip (abu-abu, icon/teks hitam)
    Widget brandChip() => Container(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
          decoration: BoxDecoration(
            color: Colors.grey[300],
            borderRadius: BorderRadius.circular(24),
          ),
          child: const Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(Icons.lightbulb, color: Colors.black),
              SizedBox(width: 8),
              Text('Nanolite', style: TextStyle(color: Colors.black, fontWeight: FontWeight.w600)),
            ],
          ),
        );

    // HERO image: panel gelap + radius
    Widget productImage() => ClipRRect(
          borderRadius: BorderRadius.circular(isTablet ? 20 : 16),
          child: Container(
            color: cardDark,
            padding: const EdgeInsets.all(16),
            child: Center(
              child: Image.asset(
                'assets/images/sladjustnano.jpg', // gambar Street Light 712 (adjustable)
                width: isTablet ? 340 : 260,
                fit: BoxFit.contain,
              ),
            ),
          ),
        );

    // Kartu spesifikasi putih (header abu-abu)
    Widget specCard() {
      Widget header = Container(
        width: double.infinity,
        height: 42,
        decoration: const BoxDecoration(
          color: headerLight,
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
        child: Padding(
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
                  children: [
                    row('Tahan Sampai', '50.000 Jam'),
                    row('Power Faktor', '>0.5'),
                    row('Tegangan', '100–300V (50/60Hz)'),
                    row('Jenis', 'Lampu Jalan (Adjustable)'),
                    row('CRI', '>85'),
                    row('IP', '65'),
                    row('Ukuran Lubang Tiang', '60mm'),
                    row('Sudut', 'Berputar sampai 90°'),
                  ],
                ),
              ),
            ],
          ),
        ),
      );
    }

    // ====== util sel tabel (gaya 711) ======
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

    // ====== Tabel varian (tanpa kolom Harga) ======
    Widget specTable() {
      // Kolom: Watt | Lumen | Ukuran | Warna | Keterangan | Isi/Dus
      const rows = [
        ['150 Watt', '19.500 lm', '553 × 212 × 84 mm', '6500K', 'Cahaya Putih Kebiruan', '10'],
      ];

      // Lebar kolom: fixed (HP) / flex (Tablet)
      const phoneWidths = <int, TableColumnWidth>{
        0: FixedColumnWidth(110), // Watt
        1: FixedColumnWidth(120), // Lumen
        2: FixedColumnWidth(210), // Ukuran
        3: FixedColumnWidth(90),  // Warna
        4: FixedColumnWidth(180), // Keterangan
        5: FixedColumnWidth(90),  // Isi/Dus
      };
      final tabletWidths = <int, TableColumnWidth>{
        0: const FlexColumnWidth(1.0),
        1: const FlexColumnWidth(1.0),
        2: const FlexColumnWidth(1.6),
        3: const FlexColumnWidth(0.9),
        4: const FlexColumnWidth(1.4),
        5: const FlexColumnWidth(0.9),
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
            th('Varian Watt'),
            th('Lumen'),
            th('Ukuran'),
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
                tdBlue(r[3]),
                td(r[4]),
                td(r[5]),
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
                    // 110+120+210+90+180+90 ≈ 800
                    constraints: const BoxConstraints(minWidth: 800),
                    child: table,
                  ),
                ),
        ),
      );
    }

    // Gambar perbandingan (panel rounded)
    Widget roundedImage(String path, double h) => ClipRRect(
          borderRadius: BorderRadius.circular(16),
          child: Container(
            color: cardDark,
            padding: const EdgeInsets.all(12),
            child: Image.asset(path, height: h, fit: BoxFit.contain),
          ),
        );

    // ===== PAGE =====
    return Scaffold(
      backgroundColor: bgPage,
      appBar: AppBar(
        backgroundColor: Colors.grey[200],
        elevation: 0,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back, color: Colors.black),
          onPressed: () {
            if (Navigator.of(context).canPop()) {
              Navigator.of(context).pop();
            } else {
              Navigator.pushReplacement(context, MaterialPageRoute(builder: (_) => HomeScreen()));
            }
          },
        ),
        
      ),
      body: ListView(
        padding: EdgeInsets.fromLTRB(hPad, vPad, hPad, vPad + 16),
        children: [
          brandChip(),
          SizedBox(height: vPad),
          Text(
            'Product Street Light 712',
            style: TextStyle(
              fontSize: isTablet ? 18 : 16,
              color: Colors.white,
              fontWeight: FontWeight.w700,
            ),
          ),
          SizedBox(height: vPad),

          // HERO (row di tablet, stack di HP)
          LayoutBuilder(
            builder: (context, c) {
              final row = isTablet && c.maxWidth >= 680;
              final img = productImage();
              final spec = specCard();
              if (row) {
                return Row(children: [Expanded(child: img), const SizedBox(width: 16), Expanded(child: spec)]);
              }
              return Column(children: [img, const SizedBox(height: 12), spec]);
            },
          ),

          SizedBox(height: vPad),

          // TABLE
          specTable(),

          SizedBox(height: vPad * 1.5),

          // Perbandingan (dua gambar, panel rounded)
          LayoutBuilder(builder: (context, c) {
            final twoCols = isTablet && c.maxWidth >= 680;
            final left = roundedImage('assets/images/sladjnano.jpg', isTablet ? 220 : 170);
            final right = roundedImage('assets/images/sladjkom.jpg', isTablet ? 220 : 170);
            if (twoCols) {
              return Row(children: [Expanded(child: left), const SizedBox(width: 16), Expanded(child: right)]);
            }
            return Column(children: [left, const SizedBox(height: 12), right]);
          }),
        ],
      ),

      // Bottom nav (kapsul abu-abu – sama seperti 711)
      bottomNavigationBar: Container(
        color: bgPage,
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

  // Bottom-nav item
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
