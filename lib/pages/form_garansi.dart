import 'package:flutter/material.dart';

class GaransiFormScreen extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Row(
          children: [
            Icon(Icons.card_giftcard, color: Colors.white), // Garansi Icon
            SizedBox(width: 8),
            Text(
              'Garansi',
              style: TextStyle(color: Colors.white),
            ),
          ],
        ),
        backgroundColor: Colors.blue[800],
        elevation: 0,
        leading: IconButton(
          icon: Icon(Icons.arrow_back),
          onPressed: () {
            Navigator.pop(context); // Kembali ke halaman sebelumnya
          },
        ),
      ),
      body: Padding(
        padding: const EdgeInsets.all(20.0),
        child: SingleChildScrollView(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              // Header Create Garansi
              Text(
                'Create Garansi:',
                style: TextStyle(
                  fontSize: 24,
                  fontWeight: FontWeight.bold,
                  color: Colors.white,
                ),
              ),
              SizedBox(height: 20),

              // Fields section with 2 fields side-by-side
              _buildInfoRow('Branch', 'Cengkareng', 'Sales', 'Aulia'),
              _buildInfoRow('Customer', 'Toko Berkah', 'Phone', '0123456'),
              _buildInfoRow('Tanggal Pembelian', '16/06/2025', 'Tanggal Klaim Garansi', '16/06/2025'),
              _buildInfoRow('Alasan Pengajuan Garansi', 'Tidak Nyala', '', ''),
              SizedBox(height: 10),

              // Image Upload Section
              _buildImageSection('Image', 'Pilih Gambar'),

              // Product Details
              Text(
                'Detail Products',
                style: TextStyle(
                  fontSize: 18,
                  fontWeight: FontWeight.bold,
                  color: Colors.white,
                ),
              ),
              SizedBox(height: 10),

              // 3 Labels side-by-side
              _buildInfoRow('Brand', 'Nanolite', 'Categories', 'Bulb', 'Products', 'Bulb 5 Watt'),
              SizedBox(height: 10),

              // 2 columns for Colors and Jumlah
              _buildInfoRow('Colors', '3000K', 'Jumlah', '2'),
              SizedBox(height: 20),
            ],
          ),
        ),
      ),
      backgroundColor: Colors.blue[800],
    );
  }

  // Helper function to display information in a row with label and value, side by side
  Widget _buildInfoRow(String label1, String value1, String label2, String value2, [String label3 = '', String value3 = '']) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 15.0),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          // First Label-Value
          _buildInfoColumn(label1, value1),
          // Second Label-Value
          _buildInfoColumn(label2, value2),
          // Third Label-Value if it exists
          label3.isNotEmpty ? _buildInfoColumn(label3, value3) : Container(),
        ],
      ),
    );
  }

  // Helper function to display label and value in a box
  Widget _buildInfoColumn(String label, String value) {
    return Expanded(
      child: Padding(
        padding: const EdgeInsets.only(right: 15.0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              '$label:',
              style: TextStyle(
                fontSize: 16,
                fontWeight: FontWeight.bold,
                color: Colors.white,
              ),
            ),
            SizedBox(height: 5),
            Container(
              padding: EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: Colors.blue[700],
                borderRadius: BorderRadius.circular(8.0),
                border: Border.all(color: Colors.blue[800]!),
              ),
              child: Text(
                value.isEmpty ? 'No data available' : value,
                style: TextStyle(fontSize: 16, color: Colors.white),
                overflow: TextOverflow.ellipsis,
                maxLines: 2,
              ),
            ),
          ],
        ),
      ),
    );
  }

  // Helper function to display the image picker section
  Widget _buildImageSection(String label, String placeholder) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 15.0),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            '$label:',
            style: TextStyle(
              fontSize: 16,
              fontWeight: FontWeight.bold,
              color: Colors.white,
            ),
          ),
          SizedBox(height: 10),
          GestureDetector(
            onTap: () {
              // Implement image picker functionality here
            },
            child: Container(
              width: double.infinity,
              padding: EdgeInsets.symmetric(vertical: 20),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(8.0),
                border: Border.all(color: Colors.blue[800]!),
              ),
              child: Center(
                child: Text(
                  placeholder,
                  style: TextStyle(
                    color: Colors.blue[800],
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
