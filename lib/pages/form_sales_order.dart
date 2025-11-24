import 'package:flutter/material.dart';

class SalesOrderFormScreen extends StatelessWidget {
  // Dummy data for display purposes
  final String branch = 'Cengkareng';
  final String sales = 'Aulia';
  final String customer = 'Toko Berkah';
  final String phone = '0123456';
  final String address = 'Jl. Sudirman';
  final String diskon1 = '2%';
  final String diskon2 = '0%';
  final String pajak = '0%';
  final String diskon1Penjelasan = 'Tahun Baru'; // Penjelasan Diskon 1
  final String diskon2Penjelasan = ''; // Penjelasan Diskon 2
  final String detailProducts = 'Nanolite Bulb 5 Watt';
  final String total = 'Rp 4000';
  final String totalAkhir = 'Rp 3920';
  final String metodePembayaran = 'Tempo';
  final String statusPembayaran = 'Belum Bayar';
  final String statusPesanan = 'Proses';

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Row(
          children: [
            Icon(Icons.shopping_cart, color: Colors.white), // Sales Order Icon
            SizedBox(width: 8),
            Text(
              'Sales Order',
              style: TextStyle(color: Colors.white),
            ),
          ],
        ),
        backgroundColor: Colors.blue[800],
        elevation: 0,
        leading: IconButton(
          icon: Icon(Icons.arrow_back),
          onPressed: () {
            Navigator.pop(context); // Go back to the previous page
          },
        ),
      ),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(20.0),
          child: SingleChildScrollView(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                // Title of the page
                Text(
                  'View RF1234',
                  style: TextStyle(
                    fontSize: 24,
                    fontWeight: FontWeight.bold,
                    color: Colors.white,
                  ),
                ),
                SizedBox(height: 20),

                // Fields section with proper wrapping and boxes for better design
                _buildInfoRow('Branch', branch, 'Sales', sales),
                _buildInfoRow('Customer', customer, 'Phone', phone),
                _buildInfoRow('Address', address, '', ''),
                SizedBox(height: 10),
                _buildInfoRow('Diskon 1 (%)', diskon1, 'Penjelasan Diskon 1', diskon1Penjelasan),
                _buildInfoRow('Diskon 2 (%)', diskon2, 'Penjelasan Diskon 2', diskon2Penjelasan),
                _buildInfoRow('Pajak (%)', pajak, '', ''),
                SizedBox(height: 10),
                _buildBox('Detail Products', detailProducts),
                _buildInfoRow('Total', total, 'Total Akhir', totalAkhir),
                _buildInfoRow('Metode Pembayaran', metodePembayaran, 'Status Pembayaran', statusPembayaran),
                _buildInfoRow('Status Pesanan', statusPesanan, '', ''),
              ],
            ),
          ),
        ),
      ),
      backgroundColor: Colors.blue[800],
    );
  }

  // Helper function to display each field in a row with label and value, side by side
  Widget _buildInfoRow(String label1, String value1, String label2, String value2) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 15.0),
      child: Row(
        children: [
          // First Label-Value
          _buildInfoColumn(label1, value1),
          // Second Label-Value (only if needed)
          if (label2.isNotEmpty) _buildInfoColumn(label2, value2),
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
                value.isEmpty ? '' : value,  // No "No data available" here
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

  // Helper function to display field value inside a box (for products)
  Widget _buildBox(String label, String value) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 15.0),
      child: Container(
        padding: EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: Colors.blue[700],
          borderRadius: BorderRadius.circular(8.0),
          border: Border.all(color: Colors.blue[800]!),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Text(
              '$label:',
              style: TextStyle(
                fontSize: 16,
                fontWeight: FontWeight.bold,
                color: Colors.white,
              ),
            ),
            SizedBox(height: 5),
            Text(
              value,
              style: TextStyle(fontSize: 16, color: Colors.white),
            ),
          ],
        ),
      ),
    );
  }
}
