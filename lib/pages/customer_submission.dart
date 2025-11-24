import 'package:flutter/material.dart';

import 'form_customer.dart';  // Import form_customer.dart untuk navigasi ke form

class CustomerSubmissionScreen extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text('Pengajuan Customer'),
        backgroundColor: Colors.blue,
        elevation: 0,
        leading: IconButton(
          icon: Icon(Icons.arrow_back),
          onPressed: () {
            // Navigasi kembali ke halaman sebelumnya
            Navigator.pop(context);
          },
        ),
      ),
      body: Padding(
        padding: const EdgeInsets.all(20.0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            // Title
            Text(
              'Pengajuan Customer:',
              style: TextStyle(
                fontSize: 18,
                fontWeight: FontWeight.bold,
                color: Colors.white,
              ),
            ),
            SizedBox(height: 20),

            // List of customer applications
            Expanded(
              child: ListView.builder(
                itemCount: 2, // Example: 2 customers in the list
                itemBuilder: (context, index) {
                  return CustomerCard();
                },
              ),
            ),
          ],
        ),
      ),
      backgroundColor: Colors.blue[800],
    );
  }
}

class CustomerCard extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Card(
      margin: EdgeInsets.only(bottom: 10),
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(10),
      ),
      color: Colors.blue[700],
      child: ListTile(
        contentPadding: EdgeInsets.all(15),
        leading: Icon(
          Icons.account_circle,
          color: Colors.white,
          size: 40,
        ),
        title: Text(
          'Toko Berkah',
          style: TextStyle(
            color: Colors.white,
            fontSize: 18,
          ),
        ),
        subtitle: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Text(
              'Email: toko.berkah@gmail.com',
              style: TextStyle(color: Colors.white70),
            ),
            Text(
              'Phone: 0123456',
              style: TextStyle(color: Colors.white70),
            ),
          ],
        ),
        trailing: Icon(
          Icons.arrow_forward_ios,
          color: Colors.white,
        ),
        onTap: () {
          // Navigasi ke halaman form_customer.dart ketika card di-tap
          Navigator.push(
            context,
            MaterialPageRoute(builder: (context) => FormCustomerScreen()),
          );
        },
      ),
    );
  }
}
