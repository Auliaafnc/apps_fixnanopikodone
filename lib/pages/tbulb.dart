// lib/pages/tbulb.dart
import 'package:flutter/material.dart';

import 'categories_nano.dart';
import 'create_sales_order.dart';
import 'home.dart';
import 'profile.dart';
import 'sales_order.dart'; // untuk flow setelah create

class TBulbPage extends StatelessWidget {
  const TBulbPage({super.key});

  @override
  Widget build(BuildContext context) {
    // === PALETTE (mengikuti Downlight) ===
    const Color bgPage = Color(0xFF0A1B2D);
    const Color headerLight = Color(0xFFE9ECEF);
    const Color blue6500 = Color(0xFF1EA7FF);
    const Color cardDark = Color(0xFF0F2741); // panel gelap biar radius terlihat

    final size = MediaQuery.of(context).size;
    final bool isTablet = size.width >= 600;
    final double hPad = isTablet ? 24 : 16;
    final double vPad = isTablet ? 18 : 12;

    // ---- Brand chip abu-abu (ikon & teks hitam) ----
    Widget brandChip() => Container(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
          decoration: BoxDecoration(color: Colors.grey[300], borderRadius: BorderRadius.circular(24)),
          child: const Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(Icons.lightbulb_outline, color: Colors.black),
              SizedBox(width: 8),
              Text('Nanolite', style: TextStyle(color: Colors.black, fontWeight: FontWeight.w600)),
            ],
          ),
        );

    // ---- Hero image: panel gelap + sudut melengkung (seperti Downlight) ----
    Widget productImage() => ClipRRect(
          borderRadius: BorderRadius.circular(isTablet ? 20 : 16),
          child: Container(
            color: cardDark,
            padding: const EdgeInsets.all(16),
            child: Image.asset('assets/images/tbulbpiko.jpg',
                height: isTablet ? 220 : 170, fit: BoxFit.contain),
          ),
        );

    // ---- Kartu spesifikasi: header bar abu-abu seperti Downlight ----
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
              const SizedBox(height: 2),
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    row('Tahan Sampai', '15.000 Jam'),
                    row('Fitting', 'E27'),
                    row('Hemat Energi', '90%'),
                    row('Tegangan', '110–240V'),
                    row('CRI', '>80Ra'),
                  ],
                ),
              ),
            ],
          ),
        ),
      );
    }

    // ---- Header & cell builder (gaya Bulb/Downlight) ----
    Widget th(String t) => ConstrainedBox(
          constraints: const BoxConstraints(minHeight: 44),
          child: Container(
            color: headerLight,
            alignment: Alignment.center,
            padding: const EdgeInsets.symmetric(horizontal: 8),
            child: Text(t,
                textAlign: TextAlign.center,
                style: const TextStyle(color: Colors.black, fontWeight: FontWeight.w700, height: 1.1)),
          ),
        );

    Widget td(String t) => ConstrainedBox(
          constraints: const BoxConstraints(minHeight: 42),
          child: Container(
            alignment: Alignment.center,
            padding: const EdgeInsets.symmetric(horizontal: 8),
            child: Text(t,
                textAlign: TextAlign.center,
                style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w600, height: 1.1)),
          ),
        );

    Widget tdBlue(String t) => ConstrainedBox(
          constraints: const BoxConstraints(minHeight: 42),
          child: Container(
            alignment: Alignment.center,
            color: blue6500,
            padding: const EdgeInsets.symmetric(horizontal: 8),
            child: Text(t, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, height: 1.1)),
          ),
        );

    // ---- Tabel T-Bulb (tanpa kolom Harga) ----
    Widget specTable() {
      // Kolom: Varian Watt | Dimensi Produk | Warna | Keterangan
      const rows = [
        ['5 Watt',  '45mm x 100mm',     '6500K', 'Cahaya Putih Kebiruan'],
        ['10 Watt', '59mm x 115mm',     '6500K', 'Cahaya Putih Kebiruan'],
        ['15 Watt', '69mm x 133mm',     '6500K', 'Cahaya Putih Kebiruan'],
        ['20 Watt', '80mm x 145mm',     '6500K', 'Cahaya Putih Kebiruan'],
        ['30 Watt', '100mm x 180mm',    '6500K', 'Cahaya Putih Kebiruan'],
        ['40 Watt', '113mm x 200mm',    '6500K', 'Cahaya Putih Kebiruan'],
        ['50 Watt', '113mm x 215mm',    '6500K', 'Cahaya Putih Kebiruan'],
        ['60 Watt', '113.4mm x 215mm',  '6500K', 'Cahaya Putih Kebiruan'],
      ];

      // Lebar kolom (HP fixed, Tablet flex)
      const phoneWidths = <int, TableColumnWidth>{
        0: FixedColumnWidth(120), // Varian Watt
        1: FixedColumnWidth(200), // Dimensi
        2: FixedColumnWidth(90),  // Warna
        3: FixedColumnWidth(220), // Keterangan
      };
      final tabletWidths = <int, TableColumnWidth>{
        0: const FlexColumnWidth(1.1),
        1: const FlexColumnWidth(1.6),
        2: const FlexColumnWidth(.9),
        3: const FlexColumnWidth(1.6),
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
            th('Dimensi Produk'),
            th('Warna'),
            th('Keterangan'),
          ]),
          for (final r in rows)
            TableRow(
              decoration: const BoxDecoration(color: bgPage),
              children: [td(r[0]), td(r[1]), tdBlue(r[2]), td(r[3])],
            ),
        ],
      );

      // Panel tabel dibungkus ClipRRect agar sudut melengkung terlihat
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
                    // 120 + 200 + 90 + 220 ≈ 630
                    constraints: const BoxConstraints(minWidth: 650),
                    child: table,
                  ),
                ),
        ),
      );
    }

    // Gambar perbandingan pakai panel rounded (gaya Downlight)
    Widget roundedImage(String path) => ClipRRect(
          borderRadius: BorderRadius.circular(16),
          child: Container(
            color: cardDark,
            padding: const EdgeInsets.all(12),
            child: Image.asset(path, height: isTablet ? 170 : 150, fit: BoxFit.contain),
          ),
        );

    // ---- Page ----
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
              // Perbaikan: jangan pakai `const` kalau ctor bukan const
              Navigator.pushReplacement(
                context,
                MaterialPageRoute(builder: (_) => CategoriesNanoScreen()),
              );
            }
          },
        ),
        
        centerTitle: false,
      ),
      body: ListView(
        padding: EdgeInsets.fromLTRB(hPad, vPad, hPad, vPad + 16),
        children: [
          brandChip(),
          SizedBox(height: vPad),
          Text('Product T-Bulb',
              style: TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: isTablet ? 18 : 16)),
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

          // TABLE (rounded panel)
          specTable(),

          SizedBox(height: vPad * 1.5),

          // Perbandingan (gambar saja, rounded panel)
          LayoutBuilder(builder: (context, c) {
            final twoCols = isTablet && c.maxWidth >= 680;
            final left = roundedImage('assets/images/tpiko.jpg');
            final right = roundedImage('assets/images/tkom.jpg');
            if (twoCols) {
              return Row(children: [Expanded(child: left), const SizedBox(width: 16), Expanded(child: right)]);
            }
            return Column(children: [left, const SizedBox(height: 12), right]);
          }),
        ],
      ),

      // ===== BOTTOM NAVIGATION: sama persis dengan Home =====
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
              _navItem(context, Icons.shopping_cart, 'Create Order', onPressed: () async {
                final created = await Navigator.push<bool>(
                  context,
                  MaterialPageRoute(builder: (_) => CreateSalesOrderScreen()),
                );
                if (created == true) {
                  if (!context.mounted) return;
                  Navigator.pushReplacement(
                    context,
                    MaterialPageRoute(
                      builder: (_) => SalesOrderScreen(showCreatedSnack: true),
                    ),
                  );
                }
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

  // ---- Nav item versi Home (responsive size & warna sama) ----
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
