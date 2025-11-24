import 'package:flutter/material.dart';

class FormCustomerScreen extends StatelessWidget {
  // Dummy data for customer information
  final String branch = 'Cengkareng';
  final String name = 'Toko Berkah';
  final String email = 'berkah@gmail.com';
  final String phone = '123456';
  final String sales = 'Aulia';
  final String address = 'Jl. Sudirman';
  final String image = '';  // Placeholder for image (can be updated as needed)

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Row(
          children: [
            Icon(Icons.account_box, color: Colors.white), // Customer Icon
            SizedBox(width: 8),
            Text(
              'Customer',
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
                // View Customer Header
                Text(
                  'View Customer: $name',
                  style: TextStyle(
                    fontSize: 24,
                    fontWeight: FontWeight.bold,
                    color: Colors.blue[800],
                  ),
                ),
                SizedBox(height: 20),

                // Customer Info Fields with two columns
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: <Widget>[
                    Expanded(child: _buildInfoContainer('Branch', branch)),
                    SizedBox(width: 10),
                    Expanded(child: _buildInfoContainer('Name', name)),
                  ],
                ),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: <Widget>[
                    Expanded(child: _buildInfoContainer('Email', email)),
                    SizedBox(width: 10),
                    Expanded(child: _buildInfoContainer('Phone', phone)),
                  ],
                ),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: <Widget>[
                    Expanded(child: _buildInfoContainer('Sales', sales)),
                    SizedBox(width: 10),
                    Expanded(child: _buildInfoContainer('Address', address)),
                  ],
                ),
                _buildInfoContainer('Image', image.isEmpty ? 'No Image' : image), // Display image or 'No Image'

                SizedBox(height: 20),

                // List Customer Button at the bottom-right corner
                Align(
                  alignment: Alignment.bottomRight,
                  child: ElevatedButton(
                    onPressed: () {
                      // Navigate back to the previous page
                      Navigator.pop(context);
                    },
                    style: ElevatedButton.styleFrom(
                      backgroundColor: Colors.blue[700],
                      padding: EdgeInsets.symmetric(horizontal: 20, vertical: 15),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(30),
                      ),
                    ),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Icon(Icons.list, color: Colors.white),
                        SizedBox(width: 10),
                        Text(
                          'List Customer',
                          style: TextStyle(fontSize: 18, color: Colors.white),
                        ),
                      ],
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
      backgroundColor: Colors.blue[800],
    );
  }

  // Helper function to display information in a row with label and value
  Widget _buildInfoContainer(String label, String value) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 15.0),
      child: Container(
        padding: EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: Colors.blue[700], // Light blue background for each item
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
            // If the value is too long, wrap the text inside the box
            Container(
              width: double.infinity,
              padding: EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(8),
              ),
              child: Text(
                value,
                style: TextStyle(fontSize: 16, color: Colors.blue[800]),
                overflow: TextOverflow.ellipsis,
                maxLines: 2, // Limit the lines to avoid overflow
              ),
            ),
          ],
        ),
      ),
    );
  }
}
