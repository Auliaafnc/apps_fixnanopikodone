import 'package:flutter/material.dart';

import 'login.dart';

class WelcomeScreen extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    final bool isTablet = MediaQuery.of(context).size.width >= 600;

    return Scaffold(
      backgroundColor: const Color(0xFF0A1B2D), // Warna background
      appBar: AppBar(
        
        backgroundColor: Colors.grey[200],
        elevation: 0,
      ),
      body: Center(
        child: SingleChildScrollView(
          padding: const EdgeInsets.symmetric(horizontal: 24.0, vertical: 32),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              // Logo bulat
              Container(
                padding: const EdgeInsets.all(24),
                decoration: BoxDecoration(
                  color: Colors.white,
                  shape: BoxShape.circle,
                  boxShadow: [
                    BoxShadow(
                      color: Colors.black.withOpacity(0.3),
                      spreadRadius: 3,
                      blurRadius: 10,
                      offset: const Offset(0, 3),
                    ),
                  ],
                ),
                child: Image.asset(
                  'assets/images/nanolite_pikolite_logo.png',
                  width: isTablet ? 260 : 180,
                  height: isTablet ? 260 : 180,
                  fit: BoxFit.cover,
                ),
              ),
              const SizedBox(height: 32),

              // Tagline miring
              Text(
                '#untungpakainanolite\n#murahbergaransi',
                textAlign: TextAlign.center,
                style: TextStyle(
                  fontSize: isTablet ? 20 : 16,
                  color: Colors.white,
                  fontWeight: FontWeight.w400,
                  fontStyle: FontStyle.italic,
                ),
              ),
              const SizedBox(height: 40),

              // Tombol Get Started
              ElevatedButton(
                onPressed: () {
                  Navigator.push(
                    context,
                    MaterialPageRoute(builder: (context) => LoginScreen()),
                  );
                },
                style: ElevatedButton.styleFrom(
                  backgroundColor: Colors.grey[300],
                  shape: const StadiumBorder(),
                  padding: EdgeInsets.symmetric(
                    horizontal: isTablet ? 70 : 50,
                    vertical: isTablet ? 20 : 15,
                  ),
                ),
                child: Text(
                  'Get Started',
                  style: TextStyle(
                    fontSize: isTablet ? 20 : 18,
                    color: Colors.black,
                  ),
                ),
              ),
              const SizedBox(height: 40),
            ],
          ),
        ),
      ),
    );
  }
}
