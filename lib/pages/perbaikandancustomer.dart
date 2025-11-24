// lib/pages/perbaikandancustomer.dart
import 'package:apps_nanolite/pages/perbaikandata.dart';
import 'package:flutter/material.dart';

import 'create_sales_order.dart';
import 'customer.dart';
import 'data_customer.dart'; // <-- IMPORT BARU
import 'home.dart';
import 'profile.dart';
import 'sales_order.dart';

class PerbaikanDanCustomerScreen extends StatefulWidget {
  const PerbaikanDanCustomerScreen({Key? key}) : super(key: key);

  @override
  State<PerbaikanDanCustomerScreen> createState() =>
      _PerbaikanDanCustomerScreenState();
}

class _PerbaikanDanCustomerScreenState
    extends State<PerbaikanDanCustomerScreen> {
  @override
  Widget build(BuildContext context) {
    final shortest = MediaQuery.of(context).size.shortestSide;
    final isTablet = shortest >= 600;
    final double padding = isTablet ? 40.0 : 20.0;

    return WillPopScope(
      onWillPop: () async {
        Navigator.pushReplacement(
          context,
          MaterialPageRoute(builder: (_) => HomeScreen()),
        );
        return false;
      },
      child: Scaffold(
        backgroundColor: const Color(0xFF0A1B2D),
        appBar: AppBar(
          leading: IconButton(
            icon: const Icon(Icons.arrow_back, color: Colors.black),
            onPressed: () {
              Navigator.pushReplacement(
                context,
                MaterialPageRoute(builder: (_) => HomeScreen()),
              );
            },
          ),
          backgroundColor: Colors.grey[200],
          elevation: 0,
        ),
        body: SafeArea(
          child: LayoutBuilder(
            builder: (context, constraints) {
              // Responsif: 1 kolom (≤420), 2 kolom (≤1000), 3 kolom (>1000)
              final double w = constraints.maxWidth;
              final int crossAxisCount =
                  w > 1000 ? 3 : (w > 420 ? 2 : 1);
              final double ratio = isTablet ? 3.2 : 2.6; // lebar/tinggi

              return SingleChildScrollView(
                padding: EdgeInsets.all(padding),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Pilih Menu',
                      style: TextStyle(
                        fontSize: isTablet ? 28 : 22,
                        fontWeight: FontWeight.bold,
                        color: Colors.white,
                      ),
                    ),
                    const SizedBox(height: 20),

                    // === GRID MENU ===
                    GridView(
                      shrinkWrap: true,
                      physics: const NeverScrollableScrollPhysics(),
                      gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
                        crossAxisCount: crossAxisCount,
                        mainAxisSpacing: 16,
                        crossAxisSpacing: 16,
                        childAspectRatio: ratio,
                      ),
                      children: [
                        _menuCard(
                          icon: Icons.account_box,
                          title: 'Pengajuan Customer',
                          subtitle: 'Kelola data customer',
                          onTap: () {
                            Navigator.push(
                              context,
                              MaterialPageRoute(
                                  builder: (_) => CustomerScreen()),
                            );
                          },
                        ),
                        _menuCard(
                          icon: Icons.edit_note,
                          title: 'Perbaikan Data',
                          subtitle: 'List hasil perbaikan data',
                          onTap: () {
                            Navigator.push(
                              context,
                              MaterialPageRoute(
                                  builder: (_) =>
                                      const PerbaikanDataScreen()),
                            );
                          },
                        ),
                        // === MENU BARU: DATA CUSTOMER DISETUJUI ===
                        _menuCard(
                          icon: Icons.people_alt,
                          title: 'Data Customer',
                          subtitle: 'Customer yang sudah disetujui',
                          onTap: () {
                            Navigator.push(
                              context,
                              MaterialPageRoute(
                                  builder: (_) =>
                                      const DataCustomerScreen()),
                            );
                          },
                        ),
                      ],
                    ),

                    const SizedBox(height: 30),
                  ],
                ),
              );
            },
          ),
        ),

        // Bottom Navigation (sama seperti home.dart)
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
                _navItem(Icons.home, 'Home', onPressed: () {
                  Navigator.pushReplacement(
                    context,
                    MaterialPageRoute(builder: (_) => HomeScreen()),
                  );
                }),
                _navItem(Icons.shopping_cart, 'Create Order',
                    onPressed: () async {
                  final created = await Navigator.push<bool>(
                    context,
                    MaterialPageRoute(
                        builder: (_) => CreateSalesOrderScreen()),
                  );
                  if (!mounted) return;
                  if (created == true) {
                    Navigator.pushReplacement(
                      context,
                      MaterialPageRoute(
                        builder: (_) =>
                            SalesOrderScreen(showCreatedSnack: true),
                      ),
                    );
                  }
                }),
                _navItem(Icons.person, 'Profile', onPressed: () {
                  Navigator.push(
                    context,
                    MaterialPageRoute(builder: (_) => ProfileScreen()),
                  );
                }),
              ],
            ),
          ),
        ),
      ),
    );
  }

  // === Kartu menu ===
  Widget _menuCard({
    required IconData icon,
    required String title,
    String? subtitle,
    required VoidCallback onTap,
  }) {
    return Card(
      elevation: 4,
      color: Colors.white,
      shadowColor: Colors.black.withOpacity(0.25),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      child: InkWell(
        borderRadius: BorderRadius.circular(16),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 16),
          child: Row(
            children: [
              Container(
                width: 52,
                height: 52,
                decoration: const BoxDecoration(
                  color: Color(0xFF0A1B2D),
                  shape: BoxShape.circle,
                ),
                child: Icon(icon, color: Colors.white, size: 30),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(title,
                        style: const TextStyle(
                          color: Color(0xFF0A1B2D),
                          fontSize: 18,
                          fontWeight: FontWeight.w700,
                        )),
                    if (subtitle != null) ...[
                      const SizedBox(height: 4),
                      Text(
                        subtitle!,
                        style: TextStyle(
                          color: Colors.black.withOpacity(0.6),
                          fontSize: 13.5,
                        ),
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ],
                  ],
                ),
              ),
              const Icon(Icons.chevron_right, color: Color(0xFF0A1B2D)),
            ],
          ),
        ),
      ),
    );
  }

  Widget _navItem(IconData icon, String label, {VoidCallback? onPressed}) {
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
          Text(label,
              style: TextStyle(
                  color: const Color(0xFF0A1B2D), fontSize: fontSize)),
        ],
      ),
    );
  }
}
