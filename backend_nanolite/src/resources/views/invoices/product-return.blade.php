<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8" />
    <title>Return – {{ $return->customer->name ?? '-' }}</title>
    <style>
        /* reset & base */
        body, h1, h2, h3, p, table, th, td, div, span { margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; color: #333; padding: 0 40px; font-size: 12px; line-height: 1.4; }

        .header-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        .header-left, .header-right { vertical-align: top; padding: 0; }
        .header-left { width: 60%; }
        .header-right { width: 40%; text-align: right; }
        .company-left h2 { font-size: 22px; font-weight: bold; margin-bottom: 4px; }
        .company-left p { font-size: 12px; color: #555; margin: 2px 0; }
        .logo-nano { height: 120px; display: inline-block; margin-bottom: 8px; }
        .meta p { margin: 2px 0; font-size: 12px; color: #333; }

        .bill-to { margin-top: 36px; }
        .bill-to h3 { font-size: 14px; margin-bottom: 6px; }
        .bill-to p { margin: 2px 0; font-size: 12px; }

        .sales { margin-top: 16px; }
        .sales h3 { font-size: 14px; margin-bottom: 6px; }
        .sales p { margin: 2px 0; font-size: 12px; }

        table.items { width: 100%; border-collapse: collapse; margin-top: 12px; font-size: 12px; }
        table.items thead th { background: #f5f5f5; border-bottom: 2px solid #555; padding: 8px; text-align: left; }
        table.items tbody td { border-bottom: 1px solid #ddd; padding: 8px; vertical-align: top; }
        .text-right { text-align: right; }

        .info-totals-table { width: 100%; border-collapse: collapse; margin-top: 18px; }
        .info-cell { vertical-align: top; width: 100%; padding-right: 20px; }

        .return-info h3 { font-size: 14px; margin-bottom: 6px; }
        .return-info p { margin: 2px 0; font-size: 12px; }

        /* grid gambar */
        .grid-photos { display: flex; gap:8px; flex-wrap: wrap; margin-top: 8px; }
        .grid-photos img { width: 110px; height: 110px; object-fit: cover; border:1px solid #ddd; border-radius:6px; padding:2px; }

        /* === 2 kolom foto (aman untuk Dompdf) === */
        .media-2col { width:100%; border-collapse:collapse; margin-top: 12px; }
        .media-2col td { vertical-align: top; width:50%; padding:0; }
        .media-2col td:first-child { padding-right: 16px; }
        .media-block h3 { font-size: 14px; margin-bottom: 6px; }
        .muted { color:#666; }

        footer { margin-top: 48px; font-size: 11px; color: #666; text-align: center; }
    </style>
</head>
<body>

    {{-- HEADER --}}
    <table class="header-table">
        <tr>
            <td class="header-left">
                <div class="company-left">
                    <h2>PT. Berdikari Inti Gemilang</h2>
                    <p><strong>Head Office:</strong> Jl. Boulevard Timur Blok. A/35 Green Court Viko Kapuk, Cengkareng, Jakarta Barat 11730</p>
                    <p><strong>Factory 1:</strong> Kawasan Industri Pasar Kemis. JI Merdeka Km7 No. 8-9 Cilongok, Sukamantri, Pasar Kemis, Tangerang Banten 15560</p>
                    <p><strong>Factory 2:</strong> Kawasan Industri Pasar Kemis. Jl Agarindo No. 9A Cilongok, Sukamantri, Pasar Kemis, Tangerang Banten 15560</p>
                </div>
            </td>
            <td class="header-right">
                <img src="{{ public_path('assets/image/logo-invoice.png') }}" class="logo-nano" alt="Logo">
                <div class="meta" style="margin-top:6px;">
                    <p><strong>No Return#</strong> {{ $return->no_return ?? '-' }}</p>
                    <p><strong>Tanggal</strong> {{ optional($return->created_at)->format('d/m/Y') ?? '-' }}</p>
                </div>
            </td>
        </tr>
    </table>

    {{-- NORMALISASI ALAMAT & GAMBAR --}}
    @php
        $alamatLines = [];
        if (is_array($return->address)) {
            foreach ($return->address as $a) {
                $alamatLines[] = implode(', ', array_filter([
                    $a['detail_alamat'] ?? null,
                    $a['kelurahan'] ?? null,
                    $a['kecamatan'] ?? null,
                    $a['kota_kab'] ?? null,
                    $a['provinsi'] ?? null,
                    $a['kode_pos'] ?? null,
                ], fn($v) => filled($v) && $v !== '-'));
            }
        } else {
            $alamatLines[] = $return->address ?? '-';
        }

        $parseImages = function ($images) {
            if (is_string($images) && str_starts_with($images, '[')) $images = json_decode($images, true) ?: [];
            if (is_string($images) && $images !== '') $images = [$images];
            if (!is_array($images)) $images = [];
            return collect($images)->map(function($p){
                $p = preg_replace('#^/?storage/#','', (string) $p);
                $abs = public_path('storage/' . ltrim($p, '/'));
                return file_exists($abs) ? $abs : null;
            })->filter()->values()->all();
        };

        $fotoBarangPaths = $parseImages($return->image);
        $buktiKirimPaths = $parseImages($return->delivery_images);
    @endphp

    {{-- BILL TO --}}
    <section class="bill-to">
        <h3>To:</h3>
        <p><strong>{{ $return->customer->name ?? '-' }}</strong></p>
        <p>Kategori Customer: {{ $return->category->name ?? '-' }}</p>
        <p>Telp: {{ $return->phone ?? '-' }}</p>
        <p>
            @foreach($alamatLines as $line)
                {{ $line ?: '-' }}@if(!$loop->last)<br>@endif
            @endforeach
        </p>
    </section>

    {{-- SALES --}}
    <section class="sales">
        <h3>Informasi Sales & Toko</h3>
        <p>Department: {{ $return->department->name ?? '-' }}</p>
        <p>Karyawan: {{ $return->employee->name ?? '-' }}</p>
        <p>Telp Sales: {{ $return->employee->phone ?? '-' }}</p>
    </section>

    {{-- PRODUK --}}
    <h3 style="margin-top:12px;">Detail Produk</h3>
    <table class="items">
        <thead>
            <tr>
                <th>Brand</th>
                <th>Kategori</th>
                <th>Produk</th>
                <th>Warna</th>
                <th>Jumlah</th>
            </tr>
        </thead>
        <tbody>
            @foreach($return->productsWithDetails() as $item)
                <tr>
                    <td>{{ $item['brand_name'] }}</td>
                    <td>{{ $item['category_name'] }}</td>
                    <td>{{ $item['product_name'] }}</td>
                    <td>{{ $item['color'] }}</td>
                    <td>{{ (int)($item['quantity'] ?? 0) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- INFORMASI RETURN --}}
    <table class="info-totals-table">
        <tr>
            <td class="info-cell">
                <section class="return-info">
                    <h3>Informasi Return</h3>
                    <p><strong>Alasan Return:</strong> {{ $return->reason ?? '-' }}</p>
                    <p><strong>Nominal:</strong> Rp {{ number_format((int)($return->amount ?? 0), 0, ',', '.') }}</p>
                    <p>Catatan Tambahan: {{ trim((string)($return->note ?? '')) !== '' ? $return->note : '-' }}</p>

                    <p>Status Pengajuan:
                        {{ match($return->status_pengajuan){
                            'approved'=>'Disetujui','rejected'=>'Ditolak','pending'=>'Pending',
                            default=>ucfirst((string)$return->status_pengajuan)
                        } }}
                    </p>

                    <p>Status Produk:
                        {{ match($return->status_product){
                            'ready_stock'=>'Ready Stock','sold_out'=>'Sold Out','rejected'=>'Ditolak','pending'=>'Pending',
                            default=>ucfirst((string)$return->status_product)
                        } }}
                    </p>

                    <p>Status Return:
                        {{ match($return->status_return){
                            'confirmed'=>'Confirmed','processing'=>'Processing','on_hold'=>'On Hold','delivered'=>'Delivered',
                            'completed'=>'Completed','cancelled'=>'Cancelled','rejected'=>'Ditolak','pending'=>'Pending',
                            default=>ucfirst((string)$return->status_return)
                        } }}
                    </p>

                    @if(($return->status_return ?? null) === 'on_hold')
                        <p>Alasan Hold: {{ $return->on_hold_comment ?? '-' }}</p>
                        <p>Batas Hold: {{ optional($return->on_hold_until)->format('d/m/Y') ?? '-' }}</p>
                    @endif

                    @if(!empty($return->delivered_at) || !empty($return->delivered_by))
                        <p>Delivered By: {{ optional($return->deliveredBy)->name ?? ($return->delivered_by ?? '-') }}</p>
                        <p>Delivered At: {{ optional($return->delivered_at)->format('d/m/Y H:i') ?? '-' }}</p>
                    @endif
                </section>
            </td>
        </tr>
    </table>

    {{-- === FOTO BARANG (kiri) & BUKTI PENGIRIMAN (kanan) === --}}
    <table class="media-2col">
        <tr>
            <td>
                <div class="media-block">
                    <h3>Foto Barang</h3>
                    @if(count($fotoBarangPaths))
                        <div class="grid-photos">
                            @foreach($fotoBarangPaths as $p)
                                <img src="{{ $p }}" alt="Foto Barang">
                            @endforeach
                        </div>
                    @else
                        <p class="muted">Tidak ada foto.</p>
                    @endif
                </div>
            </td>
            <td>
                <div class="media-block">
                    <h3>Bukti Pengiriman</h3>
                    @if(count($buktiKirimPaths))
                        <div class="grid-photos">
                            @foreach($buktiKirimPaths as $p)
                                <img src="{{ $p }}" alt="Bukti Pengiriman">
                            @endforeach
                        </div>
                    @else
                        <p class="muted">Tidak ada bukti pengiriman.</p>
                    @endif
                </div>
            </td>
        </tr>
    </table>

    <footer>
        <p>• Terima kasih atas kepercayaan dan kerjasama Anda. •</p>
        <p>#untungpakainanolite #murahbergaransi</p>
    </footer>
</body>
</html>
