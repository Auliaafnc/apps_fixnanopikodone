// lib/pages/create_customer.dart
import 'dart:io';

import 'package:flutter/foundation.dart' show kIsWeb;
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';

import '../services/api_service.dart'; // ApiService, OptionItem, AddressInput

class CreateCustomerScreen extends StatefulWidget {
  const CreateCustomerScreen({super.key});

  @override
  State<CreateCustomerScreen> createState() => _CreateCustomerScreenState();
}

class _CreateCustomerScreenState extends State<CreateCustomerScreen> {
  // ===== Image picker state (preview only) =====
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
      final file =
          await _picker.pickImage(source: ImageSource.camera, imageQuality: 85);
      if (!mounted) return;
      if (file != null) setState(() => _photos.add(file));
    } catch (_) {}
  }

  void _removePhoto(int index) => setState(() => _photos.removeAt(index));

  // ===== Controllers =====
  final _nameCtrl = TextEditingController();
  final _phoneCtrl = TextEditingController();
  final _emailCtrl = TextEditingController();
  final _gmapsCtrl = TextEditingController();

  final _zipCtrl = TextEditingController();
  final _detailAddrCtrl = TextEditingController();

  // ===== Dropdown data dari backend =====
  List<OptionItem> _departments = [];
  List<OptionItem> _employees = [];
  List<OptionItem> _categories = [];
  List<OptionItem> _programs = [];
  List<OptionItem> _provinces = [];
  List<OptionItem> _cities = [];
  List<OptionItem> _districts = [];
  List<OptionItem> _villages = [];

  // Selected IDs
  int? _deptId;
  int? _empId;
  int? _catId;
  int? _progId;
  int? _provCode;
  int? _cityCode;
  int? _distCode;
  int? _villCode;

  // UI state
  bool _loadingOptions = false;
  bool _loadingEmployees = false;
  bool _loadingPrograms = false;
  bool _submitting = false;

  @override
  void initState() {
    super.initState();
    _loadOptions();
    _loadProvinces();
  }

  @override
  void dispose() {
    _nameCtrl.dispose();
    _phoneCtrl.dispose();
    _emailCtrl.dispose();
    _gmapsCtrl.dispose();
    _zipCtrl.dispose();
    _detailAddrCtrl.dispose();
    super.dispose();
  }

  // ===== helper: masukkan placeholder bila list kosong =====
  List<OptionItem> _withPlaceholder(List<OptionItem> src,
      {String label = 'Tidak ada data'}) {
    if (src.isEmpty || (src.length == 1 && src.first.id == -1)) {
      return [OptionItem(id: -1, name: label)];
    }
    final seen = <int>{};
    return src.where((e) => seen.add(e.id)).toList();
  }

  // ===== helper: cari nama option berdasarkan id (dipakai utk kirim *_name) =====
  String? _findName(List<OptionItem> list, int? id) {
    if (id == null) return null;
    try {
      return list.firstWhere((e) => e.id == id).name;
    } catch (_) {
      return null;
    }
  }

  // ====== LOAD OPTIONS (department, category) ======
  Future<void> _loadOptions() async {
    setState(() => _loadingOptions = true);
    try {
      final depts = await ApiService.fetchDepartments();
      final cats = await ApiService.fetchCustomerCategoriesAll();

      if (!mounted) return;
      setState(() {
        _departments = _withPlaceholder(depts);
        _categories = _withPlaceholder(cats);

        _deptId = (_departments.isNotEmpty && _departments.first.id != -1)
            ? _departments.first.id
            : null;
        _catId = (_categories.isNotEmpty && _categories.first.id != -1)
            ? _categories.first.id
            : null;

        _programs = _withPlaceholder([]);
        _progId = null;
      });

      if (_deptId != null) {
        await _onSelectDepartment(_deptId);
      }
      if (_catId != null) {
        await _onCategoryChanged(_catId);
      }
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Gagal memuat pilihan: $e')),
      );
    } finally {
      if (mounted) setState(() => _loadingOptions = false);
    }
  }

  Future<void> _onSelectDepartment(int? id) async {
    setState(() {
      _deptId = id;
      _empId = null;
      _employees = [];
      _loadingEmployees = true;
    });

    if (id == null || id == -1) {
      setState(() => _loadingEmployees = false);
      return;
    }

    try {
      final emps = await ApiService.fetchEmployees(departmentId: id);
      if (!mounted) return;

      setState(() {
        _employees = _withPlaceholder(emps);
        _empId = (_employees.isNotEmpty && _employees.first.id != -1)
            ? _employees.first.id
            : null;
      });
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Gagal memuat karyawan: $e')),
      );
    } finally {
      if (mounted) setState(() => _loadingEmployees = false);
    }
  }

  // === Load program by category (primary) + fallback all ===
  Future<void> _onCategoryChanged(int? id) async {
    setState(() {
      _catId = id;
      _progId = null;
      _programs = _withPlaceholder([]);
      _loadingPrograms = true;
    });

    if (id == null || id == -1) {
      await _loadAllProgramsFallback();
      return;
    }

    try {
      final progs = await ApiService.fetchCustomerProgramsByCategory(id);
      if (!mounted) return;

      if (progs.isNotEmpty) {
        setState(() {
          _programs = _withPlaceholder(progs);
          _progId = (_programs.isNotEmpty && _programs.first.id != -1)
              ? _programs.first.id
              : null;
        });
      } else {
        await _loadAllProgramsFallback();
      }
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Gagal memuat program: $e')),
      );
    } finally {
      if (mounted) setState(() => _loadingPrograms = false);
    }
  }

  Future<void> _loadAllProgramsFallback() async {
    try {
      final progs = await ApiService.fetchCustomerPrograms();
      if (!mounted) return;
      setState(() {
        _programs = _withPlaceholder(progs);
        _progId = (_programs.isNotEmpty && _programs.first.id != -1)
            ? _programs.first.id
            : null;
      });
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Gagal memuat semua program: $e')),
      );
    } finally {
      if (mounted) setState(() => _loadingPrograms = false);
    }
  }

  Future<void> _loadProvinces() async {
    final provs = await ApiService.fetchProvinces();
    if (!mounted) return;
    setState(() {
      _provinces = _withPlaceholder(provs);
      _provCode = null;
      _cities = [];
      _districts = [];
      _villages = [];
    });
  }

  Future<void> _onProvinceChanged(int? code) async {
    setState(() {
      _provCode = code;
      _cityCode = null;
      _distCode = null;
      _villCode = null;
      _cities = [];
      _districts = [];
      _villages = [];
    });
    if (code != null && code != -1) {
      final cities = await ApiService.fetchCities('$code');
      if (!mounted) return;
      setState(() => _cities = _withPlaceholder(cities));
    }
  }

  Future<void> _onCityChanged(int? code) async {
    setState(() {
      _cityCode = code;
      _distCode = null;
      _villCode = null;
      _districts = [];
      _villages = [];
    });
    if (code != null && code != -1) {
      final dists = await ApiService.fetchDistricts('$code');
      if (!mounted) return;
      setState(() => _districts = _withPlaceholder(dists));
    }
  }

  Future<void> _onDistrictChanged(int? code) async {
    setState(() {
      _distCode = code;
      _villCode = null;
      _villages = [];
    });
    if (code != null && code != -1) {
      final vills = await ApiService.fetchVillages('$code');
      if (!mounted) return;
      setState(() => _villages = _withPlaceholder(vills));
    }
  }

  // ====== SUBMIT ======
  Future<void> _submit() async {
    FocusScope.of(context).unfocus();

    // Validasi minimum
    if (_deptId == null ||
        _empId == null ||
        _catId == null ||
        _nameCtrl.text.trim().isEmpty ||
        _phoneCtrl.text.trim().isEmpty ||
        _provCode == null ||
        _cityCode == null ||
        _distCode == null ||
        _villCode == null ||
        _detailAddrCtrl.text.trim().isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Lengkapi field yang bertanda *')),
      );
      return;
    }

    setState(() => _submitting = true);

    // Address lengkap: kirim CODE + NAME (biar dashboard bisa render full)
    final addr = AddressInput(
      provinsiCode: _provCode!.toString(),
      kotaKabCode: _cityCode!.toString(),
      kecamatanCode: _distCode!.toString(),
      kelurahanCode: _villCode!.toString(),
      detailAlamat: _detailAddrCtrl.text.trim(),
      kodePos: _zipCtrl.text.trim().isEmpty ? null : _zipCtrl.text.trim(),
      provinsiName: _findName(_provinces, _provCode),
      kotaKabName: _findName(_cities, _cityCode),
      kecamatanName: _findName(_districts, _distCode),
      kelurahanName: _findName(_villages, _villCode),
    );

    try {
      final ok = await ApiService.createCustomer(
        companyId: 1, // sesuaikan
        departmentId: _deptId!,
        employeeId: _empId!,
        name: _nameCtrl.text.trim(),
        phone: _phoneCtrl.text.trim(),
        email: _emailCtrl.text.trim().isEmpty
            ? null
            : _emailCtrl.text.trim(), // kirim null jika kosong
        customerCategoryId: _catId!,
        customerProgramId: _progId,
        gmapsLink: _gmapsCtrl.text.trim().isEmpty
            ? null
            : _gmapsCtrl.text.trim(),
        address: addr,
        photos: _photos,
      );

      if (!mounted) return;

      if (ok) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
              content: Text('Customer berhasil dibuat'),
              backgroundColor: Colors.green),
        );
        Navigator.pop(context, true);
      } else {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
              content: Text('Gagal membuat customer'),
              backgroundColor: Colors.red),
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

  // ====== AUTO ZIP BY VILLAGE ======
  Future<void> _onVillageChanged(String code) async {
    final maybeZip = await ApiService.fetchPostalCodeByVillage(code);
    if (!mounted) return;
    if (maybeZip != null && maybeZip.isNotEmpty) {
      setState(() {
        _zipCtrl.text = maybeZip;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final disabledAll = _loadingOptions || _submitting;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Pembuatan Customer'),
        backgroundColor: Colors.white,
        foregroundColor: Colors.black,
        elevation: 1,
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
                    final double fieldWidth = isTablet
                        ? (constraints.maxWidth - 60) / 2
                        : (constraints.maxWidth - 20) / 2;

                    return AbsorbPointer(
                      absorbing: disabledAll,
                      child: Opacity(
                        opacity: disabledAll ? 0.6 : 1,
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

                            // ========= FORM UTAMA =========
                            Wrap(
                              spacing: 20,
                              runSpacing: 16,
                              children: [
                                _dropdownInt(
                                  'Department *',
                                  width: fieldWidth,
                                  value: _deptId,
                                  items: _withPlaceholder(_departments),
                                  onChanged: (v) {
                                    if (v == null || v == -1) {
                                      setState(() {
                                        _deptId = null;
                                        _empId = null;
                                        _employees = _withPlaceholder([]);
                                      });
                                      return;
                                    }
                                    _onSelectDepartment(v);
                                  },
                                ),
                                _dropdownInt(
                                  'Karyawan *',
                                  width: fieldWidth,
                                  value: _empId,
                                  items: _withPlaceholder(_employees),
                                  onChanged: (v) => setState(
                                      () => _empId = (v == -1 ? null : v)),
                                  loading: _loadingEmployees,
                                ),
                                _textField(
                                    'Nama Customer *', _nameCtrl, fieldWidth),
                                _textField('Telepon *', _phoneCtrl, fieldWidth,
                                    keyboard: TextInputType.phone),
                                _textField('Email', _emailCtrl, fieldWidth,
                                    keyboard: TextInputType.emailAddress),

                                // ==== KATEGORI ====
                                _dropdownInt(
                                  'Kategori Customer *',
                                  width: fieldWidth,
                                  value: _catId,
                                  items: _withPlaceholder(_categories),
                                  onChanged: (v) => _onCategoryChanged(v),
                                ),

                                // ==== PROGRAM (by category) ====
                                _dropdownInt(
                                  'Program Customer',
                                  width: fieldWidth,
                                  value: _progId,
                                  items: _withPlaceholder(_programs),
                                  onChanged: (v) => setState(
                                      () => _progId = (v == -1 ? null : v)),
                                  loading: _loadingPrograms,
                                ),

                                _textField(
                                    'Link Google Maps', _gmapsCtrl, fieldWidth),
                              ],
                            ),

                            const SizedBox(height: 30),

                            // ========= ALAMAT =========
                            const Text('Alamat',
                                style: TextStyle(
                                    color: Colors.white,
                                    fontWeight: FontWeight.bold)),
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
                                  const double gap = 20;
                                  final bool isTabletInside =
                                      inner.maxWidth >= 600;
                                  final double innerFieldWidth = isTabletInside
                                      ? (inner.maxWidth - 60) / 2
                                      : (inner.maxWidth - gap) / 2;

                                  return Column(
                                    crossAxisAlignment:
                                        CrossAxisAlignment.start,
                                    children: [
                                      Wrap(
                                        spacing: gap,
                                        runSpacing: 16,
                                        children: [
                                          _dropdownInt('Provinsi *',
                                              width: innerFieldWidth,
                                              value: _provCode,
                                              items: _provinces,
                                              onChanged: (v) =>
                                                  _onProvinceChanged(v)),
                                          _dropdownInt('Kota/Kabupaten *',
                                              width: innerFieldWidth,
                                              value: _cityCode,
                                              items: _cities,
                                              onChanged: (v) =>
                                                  _onCityChanged(v)),
                                          _dropdownInt('Kecamatan *',
                                              width: innerFieldWidth,
                                              value: _distCode,
                                              items: _districts,
                                              onChanged: (v) =>
                                                  _onDistrictChanged(v)),
                                          _dropdownInt(
                                            'Kelurahan *',
                                            width: innerFieldWidth,
                                            value: _villCode,
                                            items: _villages,
                                            onChanged: (v) {
                                              setState(() => _villCode = v);
                                              if (v != null) {
                                                _onVillageChanged(
                                                    v.toString());
                                              }
                                            },
                                          ),
                                          _textField('Kode Pos', _zipCtrl,
                                              innerFieldWidth,
                                              keyboard: TextInputType.number),
                                          _textField('Detail Alamat *',
                                              _detailAddrCtrl, innerFieldWidth,
                                              maxLines: 3),
                                        ],
                                      ),
                                    ],
                                  );
                                },
                              ),
                            ),

                            const SizedBox(height: 30),

                            // ========= GAMBAR (preview only) =========
                            const Text(
                              'Gambar',
                              style: TextStyle(
                                  color: Colors.white,
                                  fontWeight: FontWeight.bold),
                            ),
                            const SizedBox(height: 10),
                            Container(
                              width: double.infinity,
                              constraints:
                                  const BoxConstraints(minHeight: 150),
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
                                          style:
                                              TextStyle(color: Colors.white54),
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
                                              icon: const Icon(
                                                  Icons.photo_library),
                                              label: const Text('Pilih Foto'),
                                              style: OutlinedButton.styleFrom(
                                                foregroundColor: Colors.white,
                                                side: const BorderSide(
                                                    color: Colors.white38),
                                              ),
                                            ),
                                            OutlinedButton.icon(
                                              onPressed: _pickFromCamera,
                                              icon: const Icon(
                                                  Icons.photo_camera),
                                              label: const Text('Kamera'),
                                              style: OutlinedButton.styleFrom(
                                                foregroundColor: Colors.white,
                                                side: const BorderSide(
                                                    color: Colors.white38),
                                              ),
                                            ),
                                          ],
                                        ),
                                        const SizedBox(height: 8),
                                      ],
                                    )
                                  : Column(
                                      crossAxisAlignment:
                                          CrossAxisAlignment.start,
                                      children: [
                                        Wrap(
                                          spacing: 10,
                                          runSpacing: 10,
                                          children:
                                              List.generate(_photos.length,
                                                  (i) {
                                            final photo = _photos[i];

                                            return FutureBuilder<Widget>(
                                              future: () async {
                                                if (kIsWeb) {
                                                  final bytes = await photo
                                                      .readAsBytes();
                                                  return ClipRRect(
                                                    borderRadius:
                                                        BorderRadius.circular(
                                                            8),
                                                    child: Image.memory(
                                                      bytes,
                                                      width: 90,
                                                      height: 90,
                                                      fit: BoxFit.cover,
                                                    ),
                                                  );
                                                } else {
                                                  return ClipRRect(
                                                    borderRadius:
                                                        BorderRadius.circular(
                                                            8),
                                                    child: Image.file(
                                                      File(photo.path),
                                                      width: 90,
                                                      height: 90,
                                                      fit: BoxFit.cover,
                                                    ),
                                                  );
                                                }
                                              }(),
                                              builder: (context, snapshot) {
                                                if (!snapshot.hasData) {
                                                  return const SizedBox(
                                                    width: 90,
                                                    height: 90,
                                                    child: Center(
                                                        child:
                                                            CircularProgressIndicator()),
                                                  );
                                                }
                                                return Stack(
                                                  children: [
                                                    snapshot.data!,
                                                    Positioned(
                                                      right: -6,
                                                      top: -6,
                                                      child: IconButton(
                                                        icon: const Icon(
                                                            Icons.cancel,
                                                            color: Colors
                                                                .redAccent),
                                                        onPressed: () =>
                                                            _removePhoto(i),
                                                      ),
                                                    ),
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
                                              icon: const Icon(Icons
                                                  .add_photo_alternate),
                                              label:
                                                  const Text('Tambah Foto'),
                                              style: OutlinedButton.styleFrom(
                                                foregroundColor: Colors.white,
                                                side: const BorderSide(
                                                    color: Colors.white38),
                                              ),
                                            ),
                                            const SizedBox(width: 10),
                                            OutlinedButton.icon(
                                              onPressed: _pickFromCamera,
                                              icon: const Icon(
                                                  Icons.photo_camera),
                                              label: const Text('Kamera'),
                                              style: OutlinedButton.styleFrom(
                                                foregroundColor: Colors.white,
                                                side: const BorderSide(
                                                    color: Colors.white38),
                                              ),
                                            ),
                                          ],
                                        ),
                                      ],
                                    ),
                            ),

                            const SizedBox(height: 30),

                            // ========= BUTTONS =========
                            Row(
                              mainAxisAlignment: MainAxisAlignment.end,
                              children: [
                                _formButton('Cancel', Colors.grey, () {
                                  Navigator.pop(context, false);
                                }),
                                const SizedBox(width: 12),
                                _formButton(
                                  'Create',
                                  Colors.blue,
                                  _submitting ? null : _submit,
                                  showSpinner: _submitting,
                                ),
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

  // ===== UI helpers (dark style) =====
  Widget _textField(
    String label,
    TextEditingController c,
    double width, {
    int maxLines = 1,
    TextInputType? keyboard,
    ValueChanged<String>? onChanged,
  }) {
    return SizedBox(
      width: width,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(label, style: const TextStyle(color: Colors.white)),
          const SizedBox(height: 6),
          TextFormField(
            maxLines: maxLines,
            controller: c,
            onChanged: onChanged,
            keyboardType: keyboard,
            style: const TextStyle(color: Colors.white),
            decoration: InputDecoration(
              filled: true,
              fillColor: const Color(0xFF22344C),
              border: OutlineInputBorder(borderRadius: BorderRadius.circular(8)),
              contentPadding:
                  const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
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
                const SizedBox(
                  width: 14,
                  height: 14,
                  child: CircularProgressIndicator(strokeWidth: 2),
                ),
              ],
            ],
          ),
          const SizedBox(height: 6),
          DropdownButtonFormField<int>(
            isExpanded: true,
            value: (value == -1) ? null : value,
            items: list
                .map((o) => DropdownMenuItem<int>(
                      value: o.id,
                      child: Text(o.name),
                    ))
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
                  : (list.isEmpty ||
                          (list.length == 1 && list.first.id == -1))
                      ? 'Tidak ada data'
                      : 'Pilih',
              style: const TextStyle(color: Colors.white70),
            ),
            menuMaxHeight: 360,
            decoration: InputDecoration(
              filled: true,
              fillColor: const Color(0xFF22344C),
              border: OutlineInputBorder(borderRadius: BorderRadius.circular(8)),
              contentPadding:
                  const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
            ),
            dropdownColor: Colors.grey[900],
            iconEnabledColor: Colors.white,
            style: const TextStyle(color: Colors.white),
          ),
        ],
      ),
    );
  }

  Widget _formButton(String text, Color color, VoidCallback? onPressed,
      {bool showSpinner = false}) {
    return ElevatedButton(
      onPressed: onPressed,
      style: ElevatedButton.styleFrom(
        backgroundColor: color,
        foregroundColor: Colors.white,
        padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 14),
      ),
      child: showSpinner
          ? const SizedBox(
              width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
          : Text(text),
    );
  }
}