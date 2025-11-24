class EmployeeProfile {
  final int id;
  final String name;
  final String? department;   // string sesuai EmployeeTransformer
  final String? email;
  final String? phone;
  final String? status;       // "Aktif" / "Nonaktif"
  final String? address;      // string siap tampil
  final String? photoUrl;     // Storage::url(...) dari transformer

  EmployeeProfile({
    required this.id,
    required this.name,
    this.department,
    this.email,
    this.phone,
    this.status,
    this.address,
    this.photoUrl,
  });

  factory EmployeeProfile.fromJson(Map<String, dynamic> json) {
    return EmployeeProfile(
      id: int.tryParse('${json['id'] ?? 0}') ?? 0,
      name: (json['name'] ?? '').toString(),
      department: json['department']?.toString(),
      email: json['email']?.toString(),
      phone: json['phone']?.toString(),
      status: json['status']?.toString(),
      address: json['address']?.toString(),
      photoUrl: json['photo']?.toString(),
    );
  }
}