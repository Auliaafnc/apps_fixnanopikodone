// lib/pages/create_perbaikan_data.dart
import 'dart:io';

import 'package:flutter/foundation.dart' show kIsWeb;
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';

import '../services/api_service.dart'; // ApiService, OptionItem

class CreatePerbaikanDataScreen extends StatefulWidget {
  const CreatePerbaikanDataScreen({super.key});

  @override
  State<CreatePerbaikanDataScreen> createState() =>
      _CreatePerbaikanDataScreenState();
}

class _CreatePerbaikanDataScreenState extends State<CreatePerbaikanDataScreen> {
  // ====== image picker (preview) ======
  final ImagePicker _picker = ImagePicker();
  final List<XFile> _photos = [];

  Future<void> _pickFromGallery() async {
    try {
      final files = await _picker.pickMultiImage(imageQuality: 85);
      if (!mounted) return;
      if (files.isNotEmpty) setState(() => _photos.addAll(files));
    } catch (_) {}
  }

  Future<void> _pickFromCamera() async {
    try {
      final f = await _picker.pickImage(source: ImageSource.camera, imageQuality: 85);
      if (!mounted) return;
      if (f != null) setState(() => _photos.add(f));
    } catch (_) {}
  }

  void _removePhoto(int i) => setState(() => _photos.removeAt(i));

  // ====== controllers ======
  final _formKey = GlobalKey<FormState>();
  final _pilihanCtrl = TextEditingController();
  final _dataBaruCtrl = TextEditingController();
  final _zipCtrl = TextEditingController();
  final _detailAddrCtrl = TextEditingController();

  // ====== dropdown data dari backend ======
  List<OptionItem> _departments = [];
  List<OptionItem> _employees = [];
  List<OptionItem> _categories = [];
  List<OptionItem> _customers = [];

  List<OptionItem> _provinces = [];
  List<OptionItem> _cities = [];
  List<OptionItem> _districts = [];
  List<OptionItem> _villages = [];

  // selected ids
  int? _deptId, _empId, _catId, _custId;
  int? _provCode, _cityCode, _distCode, _villCode;

  // ui state
  bool _loadingOptions = false;
  bool _loadingEmployees = false;
  bool _submitting = false;

  @override
  void initState() {
    super.initState();
    _loadOptions();
    _loadProvinces();
  }

  @override
  void dispose() {
    _pilihanCtrl.dispose();
    _dataBaruCtrl.dispose();
    _zipCtrl.dispose();
    _detailAddrCtrl.dispose();
    super.dispose();
  }

  // ===== helper: placeholder "Tidak ada data" agar sama dengan create_customer =====
  List<OptionItem> _withPlaceholder(List<OptionItem> src, {String label = 'Tidak ada data'}) {
    if (src.isEmpty || (src.length == 1 && src.first.id == -1)) {
      return [OptionItem(id: -1, name: label)];
    }
    final seen = <int>{};
    return src.where((e) => seen.add(e.id)).toList();
  }

  // helper: ambil nama dari list option
  String? _findName(List<OptionItem> list, int? id) {
    if (id == null) return null;
    try { return list.firstWhere((e) => e.id == id).name; } catch (_) { return null; }
  }

  // ====== load data utama ======
  Future<void> _loadOptions() async {
    setState(() => _loadingOptions = true);
    try {
      final depts = await ApiService.fetchDepartments();
      final cats  = await ApiService.fetchCustomerCategoriesAll();

      setState(() {
        _departments = _withPlaceholder(depts);
        _categories  = _withPlaceholder(cats);

        _deptId = (_departments.isNotEmpty && _departments.first.id != -1) ? _departments.first.id : null;
        _catId  = (_categories.isNotEmpty  && _categories.first.id  != -1) ? _categories.first.id  : null;
      });

      if (_deptId != null) {
        await _onDeptChanged(_deptId);
      } else {
        _employees = _withPlaceholder([]);
      }

      await _loadCustomers();
    } finally {
      if (mounted) setState(() => _loadingOptions = false);
    }
  }

  Future<void> _loadCustomers() async {
    if (_deptId == null || _empId == null) {
      setState(() { _customers = _withPlaceholder([]); _custId = null; });
      return;
    }
    final list = await ApiService.fetchPerbaikanCustomers(
      departmentId: _deptId,
      employeeId: _empId,
      categoryId: _catId,
    );
    setState(() {
      _customers = _withPlaceholder(list);
      _custId = (_customers.isNotEmpty && _customers.first.id != -1) ? _customers.first.id : null;
    });
  }

  Future<void> _loadProvinces() async {
  try {
    final provs = await ApiService.fetchProvinces();
    if (!mounted) return;
    setState(() {
      _provinces = _withPlaceholder(provs);
      _provCode = null;

      // kosongkan turunan
      _cities     = _withPlaceholder([]);
      _districts  = _withPlaceholder([]);
      _villages   = _withPlaceholder([]);

      _cityCode = _distCode = _villCode = null;
      _zipCtrl.clear();
    });
  } catch (_) {
    if (!mounted) return;
    setState(() {
      _provinces = _withPlaceholder([]);
      _cities    = _withPlaceholder([]);
      _districts = _withPlaceholder([]);
      _villages  = _withPlaceholder([]);
    });
  }
}

  // ====== wilayah ======
  Future<void> _onProvinceChanged(int? code) async {
    setState(() {
      _provCode = code;
      _cityCode = _distCode = _villCode = null;
      _cities = _districts = _villages = [];
      _zipCtrl.clear();
    });
    if (code != null && code != -1) {
      final cities = await ApiService.fetchCities('$code');
      setState(() => _cities = _withPlaceholder(cities));
    }
  }

  Future<void> _onCityChanged(int? code) async {
    setState(() {
      _cityCode = code;
      _distCode = _villCode = null;
      _districts = _villages = [];
      _zipCtrl.clear();
    });
    if (code != null && code != -1) {
      final dists = await ApiService.fetchDistricts('$code');
      setState(() => _districts = _withPlaceholder(dists));
    }
  }

  Future<void> _onDistrictChanged(int? code) async {
    setState(() {
      _distCode = code;
      _villCode = null;
      _villages = [];
      _zipCtrl.clear();
    });
    if (code != null && code != -1) {
      final vills = await ApiService.fetchVillages('$code');
      setState(() => _villages = _withPlaceholder(vills));
    }
  }

  Future<void> _onVillageChanged(int? code) async {
    setState(() => _villCode = code);
    _zipCtrl.clear();
    if (code != null && code != -1) {
      final zip = await ApiService.fetchPostalCodeByVillage('$code');
      if (zip != null && zip.isNotEmpty) {
        setState(() => _zipCtrl.text = zip);
      }
    }
  }

  // ====== on change dept/emp/cat ======
  Future<void> _onDeptChanged(int? id) async {
    setState(() {
      _deptId = id; _empId = null; _loadingEmployees = true;
      _employees = _withPlaceholder([]);
    });
    if (id == null || id == -1) {
      setState(() => _loadingEmployees = false);
      await _loadCustomers();
      return;
    }
    final emps = await ApiService.fetchEmployees(departmentId: id);
    setState(() {
      _employees = _withPlaceholder(emps);
      _empId = (_employees.isNotEmpty && _employees.first.id != -1)
          ? _employees.first.id : null;
      _loadingEmployees = false;
    });
    await _loadCustomers();
  }

  Future<void> _onEmpChanged(int? id) async {
    setState(() => _empId = id);
    await _loadCustomers();
  }

  Future<void> _onCatChanged(int? id) async {
    setState(() => _catId = id);
    await _loadCustomers();
  }

  // ====== submit ======
  Future<void> _save() async {
    FocusScope.of(context).unfocus();

    // minimal validation
    if (_deptId == null ||
        _empId == null ||
        _custId == null ||
        _catId == null ||
        _pilihanCtrl.text.trim().isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Lengkapi field bertanda *')),
      );
      return;
    }

    setState(() => _submitting = true);
    try {
      final ok = await ApiService.createPerbaikanData(
        departmentId: _deptId!,
        employeeId: _empId!,
        customerId: _custId!,
        customerCategoryId: _catId!,
        pilihanData: _pilihanCtrl.text.trim(),
        dataBaru: _dataBaruCtrl.text.trim().isEmpty ? null : _dataBaruCtrl.text.trim(),

        // kode wilayah (opsional)
        provinsiCode: _provCode?.toString(),
        kotaKabCode: _cityCode?.toString(),
        kecamatanCode: _distCode?.toString(),
        kelurahanCode: _villCode?.toString(),

        // detail alamat
        kodePos: _zipCtrl.text.trim().isEmpty ? null : _zipCtrl.text.trim(),
        detailAlamat: _detailAddrCtrl.text.trim().isEmpty ? null : _detailAddrCtrl.text.trim(),

        // nama wilayah (opsional) — sama dengan create_customer
        provinsiName: _findName(_provinces, _provCode),
        kotaKabName: _findName(_cities, _cityCode),
        kecamatanName: _findName(_districts, _distCode),
        kelurahanName: _findName(_villages, _villCode),

        photos: _photos,
      );

      if (!mounted) return;
      if (ok) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Perbaikan data tersimpan'), backgroundColor: Colors.green),
        );
        Navigator.pop(context, true);
      } else {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Gagal menyimpan'), backgroundColor: Colors.red),
        );
      }
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Error: $e'), backgroundColor: Colors.red),
      );
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  // ====== UI ======
  @override
  Widget build(BuildContext context) {
    final disabledAll = _loadingOptions || _submitting;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Create Perbaikan Data'),
        backgroundColor: Colors.white,
        foregroundColor: Colors.black,
        elevation: 1,
        actions: [
          TextButton.icon(
            onPressed: disabledAll ? null : _save,
            icon: const Icon(Icons.save, color: Colors.blue),
            label: const Text('Simpan', style: TextStyle(color: Colors.blue)),
          ),
        ],
      ),
      backgroundColor: const Color(0xFF0A1B2D),
      body: _loadingOptions
          ? const Center(child: CircularProgressIndicator())
          : SafeArea(
              child: SingleChildScrollView(
                padding: const EdgeInsets.all(20),
                child: LayoutBuilder(
                  builder: (context, constraints) {
                    final bool isTablet = constraints.maxWidth >= 600;
                    final double fieldW = isTablet
                        ? (constraints.maxWidth - 60) / 2
                        : (constraints.maxWidth - 20) / 2;

                    return AbsorbPointer(
                      absorbing: disabledAll,
                      child: Opacity(
                        opacity: disabledAll ? .6 : 1,
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Text(
                              'Data Utama',
                              style: TextStyle(
                                color: Colors.white,
                                fontSize: 18,
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                            const SizedBox(height: 16),

                            // ====== form utama ======
                            Wrap(
                              spacing: 20,
                              runSpacing: 16,
                              children: [
                                _dropdownInt(
                                  'Department *',
                                  width: fieldW,
                                  value: _deptId,
                                  items: _withPlaceholder(_departments),
                                  onChanged: (v) {
                                    if (v == null || v == -1) {
                                      setState(() {
                                        _deptId = null;
                                        _empId = null;
                                        _employees = _withPlaceholder([]);
                                      });
                                      _loadCustomers();
                                      return;
                                    }
                                    _onDeptChanged(v);
                                  },
                                  loading: _loadingEmployees,
                                ),
                                _dropdownInt(
                                  'Karyawan *',
                                  width: fieldW,
                                  value: _empId,
                                  items: _withPlaceholder(_employees),
                                  onChanged: (v) {
                                    setState(() => _empId = (v == -1 ? null : v));
                                    _loadCustomers();
                                  },
                                  loading: _loadingEmployees,
                                ),
                                _dropdownInt(
                                  'Kategori Customer *',
                                  width: fieldW,
                                  value: _catId,
                                  items: _withPlaceholder(_categories),
                                  onChanged: (v) {
                                    setState(() => _catId = (v == -1 ? null : v));
                                    _loadCustomers();
                                  },
                                ),
                                _dropdownInt(
                                  'Customer *',
                                  width: fieldW,
                                  value: _custId,
                                  items: _withPlaceholder(_customers),
                                  onChanged: (v) => setState(() => _custId = (v == -1 ? null : v)),
                                ),
                                _textField('Pilihan Data * (cth: Telepon)', _pilihanCtrl, fieldW),
                                _textField('Data Baru (cth: 089888899998)', _dataBaruCtrl, fieldW, maxLines: 3),
                              ],
                            ),

                            const SizedBox(height: 30),

                            // ====== alamat (sama gaya create_customer) ======
                            const Text('Alamat',
                                style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
                            const SizedBox(height: 12),
                            Container(
                              width: double.infinity,
                              padding: const EdgeInsets.all(16),
                              decoration: BoxDecoration(
                                border: Border.all(color: Colors.white30),
                                borderRadius: BorderRadius.circular(8),
                              ),
                              child: LayoutBuilder(
                                builder: (context, inner) {
                                  final bool innerTablet = inner.maxWidth >= 600;
                                  final double innerW = innerTablet
                                      ? (inner.maxWidth - 60) / 2
                                      : (inner.maxWidth - 20) / 2;
                                  const double gap = 20;

                                  return Wrap(
                                    spacing: gap,
                                    runSpacing: 16,
                                    children: [
                                      _dropdownInt('Provinsi', width: innerW, value: _provCode, items: _provinces,
                                          onChanged: _onProvinceChanged),
                                      _dropdownInt('Kota/Kabupaten', width: innerW, value: _cityCode, items: _cities,
                                          onChanged: _onCityChanged),
                                      _dropdownInt('Kecamatan', width: innerW, value: _distCode, items: _districts,
                                          onChanged: _onDistrictChanged),
                                      _dropdownInt('Kelurahan', width: innerW, value: _villCode, items: _villages,
                                          onChanged: _onVillageChanged),
                                      _textField('Kode Pos', _zipCtrl, innerW, keyboard: TextInputType.number),
                                      _textField('Detail Alamat', _detailAddrCtrl, innerW, maxLines: 3),
                                    ],
                                  );
                                },
                              ),
                            ),

                            const SizedBox(height: 30),

                            // ====== Gambar (sama gaya create_customer) ======
                            const Text('Gambar',
                                style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
                            const SizedBox(height: 10),
                            Container(
                              width: double.infinity,
                              constraints: const BoxConstraints(minHeight: 150),
                              padding: const EdgeInsets.all(16),
                              decoration: BoxDecoration(
                                border: Border.all(color: Colors.white54),
                                borderRadius: BorderRadius.circular(8),
                              ),
                              child: _photos.isEmpty
                                  ? Column(
                                      mainAxisSize: MainAxisSize.min,
                                      children: [
                                        const SizedBox(height: 12),
                                        const Text(
                                          'Drag & Drop your files or Browse',
                                          style: TextStyle(color: Colors.white54),
                                          textAlign: TextAlign.center,
                                        ),
                                        const SizedBox(height: 16),
                                        Wrap(
                                          spacing: 12,
                                          runSpacing: 12,
                                          alignment: WrapAlignment.center,
                                          children: [
                                            OutlinedButton.icon(
                                              onPressed: _pickFromGallery,
                                              icon: const Icon(Icons.photo_library),
                                              label: const Text('Pilih Foto'),
                                              style: OutlinedButton.styleFrom(
                                                foregroundColor: Colors.white,
                                                side: const BorderSide(color: Colors.white38),
                                              ),
                                            ),
                                            OutlinedButton.icon(
                                              onPressed: _pickFromCamera,
                                              icon: const Icon(Icons.photo_camera),
                                              label: const Text('Kamera'),
                                              style: OutlinedButton.styleFrom(
                                                foregroundColor: Colors.white,
                                                side: const BorderSide(color: Colors.white38),
                                              ),
                                            ),
                                          ],
                                        ),
                                        const SizedBox(height: 8),
                                      ],
                                    )
                                  : Column(
                                      crossAxisAlignment: CrossAxisAlignment.start,
                                      children: [
                                        Wrap(
                                          spacing: 10,
                                          runSpacing: 10,
                                          children: List.generate(_photos.length, (i) {
                                            final x = _photos[i];
                                            return FutureBuilder<Widget>(
                                              future: () async {
                                                if (kIsWeb) {
                                                  final b = await x.readAsBytes();
                                                  return ClipRRect(
                                                    borderRadius: BorderRadius.circular(8),
                                                    child: Image.memory(b, width: 90, height: 90, fit: BoxFit.cover),
                                                  );
                                                } else {
                                                  return ClipRRect(
                                                    borderRadius: BorderRadius.circular(8),
                                                    child: Image.file(File(x.path),
                                                        width: 90, height: 90, fit: BoxFit.cover),
                                                  );
                                                }
                                              }(),
                                              builder: (ctx, snap) {
                                                if (!snap.hasData) {
                                                  return const SizedBox(
                                                    width: 90, height: 90,
                                                    child: Center(child: CircularProgressIndicator(strokeWidth: 2)),
                                                  );
                                                }
                                                return Stack(
                                                  children: [
                                                    snap.data!,
                                                    Positioned(
                                                      right: -6, top: -6,
                                                      child: IconButton(
                                                        icon: const Icon(Icons.cancel, color: Colors.redAccent),
                                                        onPressed: () => _removePhoto(i),
                                                      ),
                                                    )
                                                  ],
                                                );
                                              },
                                            );
                                          }),
                                        ),
                                        const SizedBox(height: 12),
                                        Row(
                                          children: [
                                            OutlinedButton.icon(
                                              onPressed: _pickFromGallery,
                                              icon: const Icon(Icons.add_photo_alternate),
                                              label: const Text('Tambah Foto'),
                                              style: OutlinedButton.styleFrom(
                                                foregroundColor: Colors.white,
                                                side: const BorderSide(color: Colors.white38),
                                              ),
                                            ),
                                            const SizedBox(width: 10),
                                            OutlinedButton.icon(
                                              onPressed: _pickFromCamera,
                                              icon: const Icon(Icons.photo_camera),
                                              label: const Text('Kamera'),
                                              style: OutlinedButton.styleFrom(
                                                foregroundColor: Colors.white,
                                                side: const BorderSide(color: Colors.white38),
                                              ),
                                            ),
                                          ],
                                        ),
                                      ],
                                    ),
                            ),

                            const SizedBox(height: 30),

                            // ====== button bawah ======
                            Row(
                              mainAxisAlignment: MainAxisAlignment.end,
                              children: [
                                _formButton('Batal', Colors.grey, () => Navigator.pop(context, false)),
                                const SizedBox(width: 12),
                                _formButton('Simpan', Colors.blue, _submitting ? null : _save,
                                    showSpinner: _submitting),
                              ],
                            ),
                          ],
                        ),
                      ),
                    );
                  },
                ),
              ),
            ),
    );
  }

  // ====== dark style helpers (sama gaya create_customer) ======
  Widget _textField(
    String label,
    TextEditingController c,
    double width, {
    int maxLines = 1,
    TextInputType? keyboard,
  }) {
    return SizedBox(
      width: width,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(label, style: const TextStyle(color: Colors.white)),
          const SizedBox(height: 6),
          TextFormField(
            controller: c,
            maxLines: maxLines,
            keyboardType: keyboard,
            style: const TextStyle(color: Colors.white),
            decoration: InputDecoration(
              filled: true,
              fillColor: const Color(0xFF22344C),
              border: OutlineInputBorder(borderRadius: BorderRadius.circular(8)),
              contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
            ),
          ),
        ],
      ),
    );
  }

  Widget _dropdownInt(
    String label, {
    required double width,
    required int? value,
    required List<OptionItem> items,
    required ValueChanged<int?> onChanged,
    bool loading = false,
  }) {
    final list = items;
    return SizedBox(
      width: width,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Text(label, style: const TextStyle(color: Colors.white)),
              if (loading) ...[
                const SizedBox(width: 8),
                const SizedBox(width: 14, height: 14, child: CircularProgressIndicator(strokeWidth: 2)),
              ],
            ],
          ),
          const SizedBox(height: 6),
          DropdownButtonFormField<int>(
            isExpanded: true,
            value: (value == -1) ? null : value,
            items: list
                .map((o) => DropdownMenuItem<int>(value: o.id, child: Text(o.name)))
                .toList(),
            onChanged: (val) {
              if (loading) return;
              if (val == -1) {
                onChanged(null);
                return;
              }
              onChanged(val);
            },
            hint: Text(
              loading
                  ? 'Memuat...'
                  : (list.isEmpty || (list.length == 1 && list.first.id == -1))
                      ? 'Tidak ada data'
                      : 'Pilih',
              style: const TextStyle(color: Colors.white70),
            ),
            menuMaxHeight: 360,
            decoration: InputDecoration(
              filled: true,
              fillColor: const Color(0xFF22344C),
              border: OutlineInputBorder(borderRadius: BorderRadius.circular(8)),
              contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
            ),
            dropdownColor: Colors.grey[900],
            iconEnabledColor: Colors.white,
            style: const TextStyle(color: Colors.white),
          ),
        ],
      ),
    );
  }

  Widget _formButton(String text, Color color, VoidCallback? onPressed, {bool showSpinner = false}) {
    return ElevatedButton(
      onPressed: onPressed,
      style: ElevatedButton.styleFrom(
        backgroundColor: color,
        foregroundColor: Colors.white,
        padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 14),
      ),
      child: showSpinner
          ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
          : Text(text),
    );
  }
}
