import 'package:flutter/material.dart';

import 'create_sales_order.dart';
import 'home.dart';
import 'profile.dart';

class BulbScreen extends StatelessWidget {
  const BulbScreen({super.key});

  @override
  Widget build(BuildContext context) {
    // Palet & panel seperti ABulb
    const Color bgPage = Color(0xFF0A1B2D);
    const Color headerLight = Color(0xFFE9ECEF);
    const Color blue6500 = Color(0xFF1EA7FF);
    const Color yellow3000 = Color(0xFFFFC107);
    const Color cardDark = Color(0xFF0F2741);

    final bool isTablet = MediaQuery.of(context).size.width >= 600;
    final double hPad = isTablet ? 24 : 16;
    final double vPad = isTablet ? 18 : 12;

    // Brand chip
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
              Text('Nanolite',
                  style: TextStyle(color: Colors.black, fontWeight: FontWeight.w600)),
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
                'assets/images/spekbulb.png',
                width: isTablet ? 320 : 260,
                fit: BoxFit.contain,
              ),
            ),
          ),
        );

    // Kartu spesifikasi (header abu-abu)
    Widget specCard() {
      Widget header = Container(
        width: double.infinity,
        height: 42,
        decoration: const BoxDecoration(
          color: headerLight,
          borderRadius: BorderRadius.vertical(top: Radius.circular(12)),
        ),
        child: const Center(
          child: Text('SPESIFIKASI',
              style: TextStyle(fontWeight: FontWeight.w700, color: Colors.black)),
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
                    row('Tahan Sampai', '25.000 Jam'),
                    row('Fitting', 'E27'),
                    row('Hemat Energi', '90%'),
                    row('LED', 'Samsung'),
                    row('Tegangan', '110–240V'),
                  ],
                ),
              ),
            ],
          ),
        ),
      );
    }

    // ====== builder sel tabel ======
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

    Widget tdYellow(String t) => ConstrainedBox(
          constraints: const BoxConstraints(minHeight: 42),
          child: Container(
            alignment: Alignment.center,
            color: yellow3000,
            padding: const EdgeInsets.symmetric(horizontal: 8),
            child: Text(
              t,
              style: const TextStyle(color: Colors.black, fontWeight: FontWeight.w800, height: 1.1),
            ),
          ),
        );

    // ====== Tabel varian (tanpa kolom Harga) ======
    Widget specTable() {
      // Kolom: Watt | Lumen | Tinggi | Diameter | Warna | Ket | Warna | Ket | Isi/Dus
      const rows = [
        ['5 Watt',  '600 lm',  '105 mm',  '55 mm',   '3000K', 'Cahaya Putih Kekuningan', '6500K', 'Cahaya Putih Kebiruan', '100'],
        ['7 Watt',  '840 lm',  '115 mm',  '60 mm',   '3000K', 'Cahaya Putih Kekuningan', '6500K', 'Cahaya Putih Kebiruan', '100'],
        ['9 Watt',  '1080 lm', '115 mm',  '60 mm',   '3000K', 'Cahaya Putih Kekuningan', '6500K', 'Cahaya Putih Kebiruan', '100'],
        ['12 Watt', '1440 lm', '125.5 mm','65 mm',   '3000K', 'Cahaya Putih Kekuningan', '6500K', 'Cahaya Putih Kebiruan', '100'],
        ['15 Watt', '1800 lm', '137.5 mm','69.8 mm', '3000K', 'Cahaya Putih Kekuningan', '6500K', 'Cahaya Putih Kebiruan', '100'],
      ];

      // Lebar kolom (harga dihapus ⇒ kolom jadi 9)
      const phoneWidths = <int, TableColumnWidth>{
        0: FixedColumnWidth(100), // Watt
        1: FixedColumnWidth(100), // Lumen
        2: FixedColumnWidth(90),  // Tinggi
        3: FixedColumnWidth(90),  // Diameter
        4: FixedColumnWidth(90),  // 3000K
        5: FixedColumnWidth(180), // Ket 3000K
        6: FixedColumnWidth(90),  // 6500K
        7: FixedColumnWidth(180), // Ket 6500K
        8: FixedColumnWidth(90),  // Isi/Dus
      };
      final tabletWidths = <int, TableColumnWidth>{
        0: const FlexColumnWidth(1.0),
        1: const FlexColumnWidth(1.0),
        2: const FlexColumnWidth(1.0),
        3: const FlexColumnWidth(1.0),
        4: const FlexColumnWidth(0.9),
        5: const FlexColumnWidth(1.4),
        6: const FlexColumnWidth(0.9),
        7: const FlexColumnWidth(1.4),
        8: const FlexColumnWidth(0.9),
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
                tdYellow(r[4]), // 3000K kuning
                td(r[5]),
                tdBlue(r[6]),   // 6500K biru
                td(r[7]),
                td(r[8]),
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
                    // dulu 1140 (ada kolom harga), sekarang 1010 cukup
                    constraints: const BoxConstraints(minWidth: 1010),
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

    return Scaffold(
      backgroundColor: bgPage,
      appBar: AppBar(
        backgroundColor: Colors.grey[200],
        elevation: 0,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back, color: Colors.black),
          onPressed: () => Navigator.pop(context),
        ),
      ),
      body: ListView(
        padding: EdgeInsets.fromLTRB(hPad, vPad, hPad, vPad + 16),
        children: [
          brandChip(),
          SizedBox(height: vPad),
          Text(
            'Product Bulb',
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
            final left = roundedImage('assets/images/nanobulbspek.png', isTablet ? 220 : 170);
            final right = roundedImage('assets/images/productkomp.png', isTablet ? 220 : 170);
            if (twoCols) {
              return Row(children: [Expanded(child: left), const SizedBox(width: 16), Expanded(child: right)]);
            }
            return Column(children: [left, const SizedBox(height: 12), right]);
          }),
        ],
      ),

      // Bottom nav (pill abu-abu)
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
