// lib/pages/categories_nano.dart
import 'package:flutter/material.dart';

import 'bulb.dart';
import 'capsule.dart';
import 'create_sales_order.dart';
import 'emergency.dart';
import 'home.dart';
import 'profile.dart';
import 'sales_order.dart';
import 'multipack.dart';
import 'lsindoor.dart';
import 'lsoutdoor.dart';
import 'powersupply.dart';
import 'ls50m.dart';
import 'floodlight.dart';
import 'sl711.dart';
import 'sl712.dart';
import 't8tubelight.dart';


// Pakai alias biar nggak bentrok nama
import 'downlight_round.dart' as dr;
import 'downlight_square.dart' as ds;


class CategoriesNanoScreen extends StatelessWidget {
  const CategoriesNanoScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFF0A1B2D),
      appBar: AppBar(
        backgroundColor: Colors.grey[200],
        elevation: 0,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back, color: Colors.black),
          onPressed: () => Navigator.pop(context),
        ),
        title: const Row(
          children: [
            Icon(Icons.lightbulb, color: Colors.black),
            SizedBox(width: 8),
            Text('Nanolite', style: TextStyle(color: Colors.black)),
          ],
        ),
      ),
      body: SafeArea(
        child: LayoutBuilder(
          builder: (context, constraints) {
            final bool isTablet = constraints.maxWidth >= 600;
            final int crossAxisCount = isTablet ? 3 : 2;

            return Padding(
              padding: const EdgeInsets.all(20.0),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'Categories',
                    style: TextStyle(
                      fontSize: 30,
                      fontWeight: FontWeight.bold,
                      color: Colors.white,
                    ),
                  ),
                  const SizedBox(height: 20),
                  Expanded(
                    child: GridView.builder(
                      itemCount: _categoryNames.length,
                      gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
                        crossAxisCount: crossAxisCount,
                        crossAxisSpacing: 12,
                        mainAxisSpacing: 12,
                        childAspectRatio: 0.85,
                      ),
                      itemBuilder: (context, index) =>
                          _buildCategoryCard(context, index, isTablet),
                    ),
                  ),
                ],
              ),
            );
          },
        ),
      ),

      // Bottom Navigation
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
              _navItem(context, Icons.shopping_cart, 'Create Order', onPressed: () async {
                final created = await Navigator.push<bool>(
                  context,
                  MaterialPageRoute(builder: (_) => CreateSalesOrderScreen()),
                );
                if (created == true && context.mounted) {
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

  Widget _buildCategoryCard(BuildContext context, int index, bool isTablet) {
    final double imageSize = isTablet ? 100 : 70;

    // Hanya halaman yang SUDAH ada yang dipanggil.
    final List<WidgetBuilder?> pages = [
      (_) => const BulbScreen(),             // 0 Bulb
      (_) => const CapsuleScreen(),          // 1 Capsule
      (_) => const EmergencyScreen(),        // 2 Emergency
      (_) => const MultipackPage(),          // 3 Multipack
      (_) => const dr.DownlightRoundPage(),  // 4 Downlight Round
      (_) => const ds.DownlightSquarePage(), // 5 Downlight Square
      (_) => const PowerSupplyPage(),        // 6 Power Supply (belum)
      (_) => const T8TubeLightPage(),        // 7 T8-Tube Light (hapus const jika konstruktor non-const)
      (_) => const FloodLightPage(),         // 8 Flood Light (belum)
      (_) => const StreetLight712Page(),         // 9 Street Light 712 (belum)
      (_) => const StreetLight711Page(),        // 10 Street Light 711 (belum)
      (_) => const LightStrip50MPage(),      // 11 Light Strip 50M (belum)
      (_) => const LSIndoorPage(),           // 12 Light Strip Indoor
      (_) => const LSOutdoorPage(),          // 13 Light Strip Outdoor
    ];

    return GestureDetector(
      onTap: () {
        final builder = pages[index];
        if (builder != null) {
          Navigator.push(context, MaterialPageRoute(builder: builder));
        } else {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text('Halaman ${_categoryNames[index]} belum tersedia')),
          );
        }
      },
      child: Container(
        decoration: BoxDecoration(
          color: const Color(0xFF12355C),
          borderRadius: BorderRadius.circular(12),
        ),
        padding: const EdgeInsets.all(10),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Container(
              height: imageSize,
              width: imageSize,
              decoration: BoxDecoration(
                image: DecorationImage(
                  image: AssetImage(_imagePaths[index]),
                  fit: (index == 3 || index == 7 || index == 10)
                      ? BoxFit.contain
                      : BoxFit.cover,
                  alignment: Alignment.center,
                ),
                borderRadius: BorderRadius.circular(12),
              ),
            ),
            const SizedBox(height: 10),
            Text(
              _categoryNames[index],
              style: TextStyle(
                color: Colors.white,
                fontWeight: FontWeight.bold,
                fontSize: isTablet ? 16 : 14,
              ),
              textAlign: TextAlign.center,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
            ),
          ],
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

// ==== Data statis ====
const List<String> _categoryNames = [
  'Bulb', 'Capsule', 'Emergency', 'Multipack', 'Downlight Round', 'Downlight Square',
  'Power Supply', 'T8-Tube Light', 'Flood Light', 'Street Light 712', 'Street Light 711',
  'Light Strip 50M', 'Light Strip Indoor', 'Light Strip Outdoor'
];

const List<String> _imagePaths = [
  'assets/images/bulb.png', 'assets/images/capsule.png', 'assets/images/emergency.png',
  'assets/images/MULTIPAK1.png', 'assets/images/round.png', 'assets/images/square.png',
  'assets/images/powersuplay1.png', 'assets/images/t81.png', 'assets/images/FloodLight00011.png',
  'assets/images/SL712.png', 'assets/images/SL711.png', 'assets/images/50m1.png',
  'assets/images/indoor1.png', 'assets/images/outdoor1.png'
];
