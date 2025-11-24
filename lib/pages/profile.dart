// lib/pages/profile.dart
import 'package:flutter/material.dart';

import '../models/employee_profile.dart';
import '../services/api_service.dart';
import 'create_sales_order.dart';
import 'home.dart';
import 'login.dart';
import 'sales_order.dart';

class ProfileScreen extends StatefulWidget {
  const ProfileScreen({super.key});

  @override
  State<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends State<ProfileScreen> {
  EmployeeProfile? _emp;
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final e = await ApiService.fetchMyEmployeeProfile();
      if (!mounted) return;
      setState(() => _emp = e);
    } catch (e) {
      if (!mounted) return;
      setState(() => _error = e.toString());
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final bool isTablet = MediaQuery.of(context).size.width >= 600;

    return Scaffold(
      backgroundColor: const Color(0xFF0A1B2D),
      appBar: AppBar(
          title: const Text('Profile', style: TextStyle(color: Colors.black)),
        
        backgroundColor: Colors.grey[200],
        elevation: 0,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back, color: Colors.black),
          onPressed: () {
            Navigator.pushReplacement(
              context,
              MaterialPageRoute(builder: (_) => HomeScreen()),
            );
          },
        ),
        actions: [
          IconButton(
            onPressed: _load,
            icon: const Icon(Icons.refresh, color: Colors.black),
            tooltip: 'Refresh',
          ),
        ],
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(
                  child: Padding(
                    padding: const EdgeInsets.all(24),
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Text(_error!, style: const TextStyle(color: Colors.white70)),
                        const SizedBox(height: 8),
                        OutlinedButton(onPressed: _load, child: const Text('Coba lagi')),
                      ],
                    ),
                  ),
                )
              : _content(isTablet),
      bottomNavigationBar: _bottomNav(context),
    );
  }

  Widget _content(bool isTablet) {
    final name = _emp?.name ?? '-';
    final email = _emp?.email ?? '-';
    final phone = _emp?.phone ?? '-';
    final status = _emp?.status ?? '-';
    final department = _emp?.department ?? '-';
    final address = (_emp?.address?.isNotEmpty ?? false) ? _emp!.address! : '-';

    return SingleChildScrollView(
      child: Column(
        children: [
          // Header bergelombang + avatar
          Stack(
            alignment: Alignment.center,
            children: [
              ClipPath(
                clipper: const TopCurveClipper(),
                child: Container(height: 180, color: Colors.grey[300]),
              ),
              Transform.translate(
                offset: const Offset(0, 60),
                child: Container(
                  padding: const EdgeInsets.all(6),
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    color: Colors.white,
                    boxShadow: [
                      BoxShadow(
                        color: Colors.black.withOpacity(0.25),
                        spreadRadius: 3,
                        blurRadius: 10,
                        offset: const Offset(0, 3),
                      ),
                    ],
                  ),
                  child: _avatar(isTablet),
                ),
              ),
            ],
          ),

          const SizedBox(height: 80),

          // Judul tetap "PROFILE

          // Nama user
          Text(
            name,
            style: TextStyle(fontSize: isTablet ? 26 : 22, fontWeight: FontWeight.bold, color: Colors.white),
          ),
          const SizedBox(height: 20),

          // Kartu detail
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 40),
            child: Container(
              width: double.infinity,
              padding: EdgeInsets.all(isTablet ? 20 : 15),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(15),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  _buildLabelValue('Departemen', department, isTablet),
                  _buildLabelValue('Email', email, isTablet),
                  _buildLabelValue('Phone', phone, isTablet),
                  _buildLabelValue('Status', status, isTablet),
                  _buildLabelValue('Address', address, isTablet),
                ],
              ),
            ),
          ),

          const SizedBox(height: 30),

          ElevatedButton(
            onPressed: () async {
              await ApiService.logout();
              if (!mounted) return;
              Navigator.pushReplacement(
                context,
                MaterialPageRoute(builder: (_) => LoginScreen()),
              );
            },
            style: ElevatedButton.styleFrom(
              backgroundColor: Colors.white,
              foregroundColor: const Color(0xFF061F3D),
              padding: EdgeInsets.symmetric(
                horizontal: isTablet ? 120 : 100,
                vertical: isTablet ? 18 : 15,
              ),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
            ),
            child: Text('Logout', style: TextStyle(fontSize: isTablet ? 20 : 18)),
          ),

          const SizedBox(height: 40),
        ],
      ),
    );
  }

  /// Avatar tanpa asset (hindari 404 di web).
  Widget _avatar(bool isTablet) {
    final photoUrl = _emp?.photoUrl;
    final double r = isTablet ? 80 : 60;

    if (photoUrl != null && photoUrl.isNotEmpty) {
      return CircleAvatar(
        radius: r,
        backgroundImage: NetworkImage(photoUrl),
      );
    }

    // Placeholder jika tidak ada foto
    return CircleAvatar(
      radius: r,
      backgroundColor: const Color(0xFFE9E6FF),
      child: Icon(Icons.person, size: r * 0.8, color: const Color(0xFF6B6B6B)),
    );
    // Jika tetap ingin pakai asset, pastikan tambahkan di pubspec.yaml:
    // flutter:
    //   assets:
    //     - assets/images/avatar.jpg
  }

  Widget _bottomNav(BuildContext context) {
    return Container(
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
            _navItem(context, Icons.home, 'Home', () {
              Navigator.pushReplacement(context, MaterialPageRoute(builder: (_) => HomeScreen()));
            }),
            _navItem(context, Icons.shopping_cart, 'Create Order', () async {
              final created = await Navigator.push<bool>(
                context,
                MaterialPageRoute(builder: (_) => const CreateSalesOrderScreen()),
              );
              if (created == true && mounted) {
                Navigator.pushReplacement(
                  context,
                  MaterialPageRoute(builder: (_) => SalesOrderScreen(showCreatedSnack: true)),
                );
              }
            }),
            _navItem(context, Icons.person, 'Profile', () {}),
          ],
        ),
      ),
    );
  }

  Widget _navItem(BuildContext context, IconData icon, String label, VoidCallback onPressed) {
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

  Widget _buildLabelValue(String label, String value, bool isTablet) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            label,
            style: TextStyle(
              fontWeight: FontWeight.bold,
              color: const Color(0xFF061F3D),
              fontSize: isTablet ? 16 : 14,
            ),
          ),
          const SizedBox(height: 2),
          Text(
            value,
            style: TextStyle(fontSize: isTablet ? 16 : 14, color: Colors.black87),
          ),
        ],
      ),
    );
  }
}

class TopCurveClipper extends CustomClipper<Path> {
  const TopCurveClipper();

  @override
  Path getClip(Size size) {
    final Path path = Path();
    path.lineTo(0, size.height - 60);
    path.quadraticBezierTo(size.width / 2, size.height, size.width, size.height - 60);
    path.lineTo(size.width, 0);
    path.close();
    return path;
  }

  @override
  bool shouldReclip(CustomClipper<Path> oldClipper) => false;
}