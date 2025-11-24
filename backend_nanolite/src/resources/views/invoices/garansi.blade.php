<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8" />
    <title>Garansi – {{ $garansi->customer->name ?? '-' }}</title>
    <style>
        /* reset sederhana */
        * { box-sizing: border-box; }
        html,body { margin:0; padding:0; }
        body { font-family: Arial, sans-serif; color:#333; font-size:12px; line-height:1.45; padding: 24px 36px; }

        .row { display: table; width: 100%; table-layout: fixed; }
        .col { display: table-cell; vertical-align: top; }
        .text-right { text-align:right; }
        .muted { color:#666; }

        h1 { font-size:22px; margin:0 0 6px; }
        h2 { font-size:16px; margin:18px 0 8px; }
        h3 { font-size:14px; margin:12px 0 6px; }

        .header { margin-bottom: 10px; }
        .logo { height: 80px; }
        .meta p { margin: 2px 0; }

        table.tbl { width:100%; border-collapse: collapse; margin-top: 10px; }
        table.tbl thead th { background:#f5f5f5; border-bottom:2px solid #555; padding:8px; text-align:left; }
        table.tbl tbody td { border-bottom:1px solid #ddd; padding:8px; vertical-align: top; }

        .grid-photos { display: flex; gap:8px; flex-wrap: wrap; }
        .grid-photos img { width: 110px; height: 110px; object-fit: cover; border:1px solid #ddd; border-radius:6px; padding:2px; }

        .section { margin-top: 14px; }
        .pair { display:flex; gap:10px; }
        .pair > div { flex:1; }

        .info-table { width:100%; border-collapse: collapse; }
        .info-table td { padding:4px 0; vertical-align: top; }
        .label { width: 160px; color:#555; }

        footer { margin-top: 36px; text-align:center; font-size:11px; color:#666; }

        .page-break { page-break-before: always; }
    </style>
</head>
<body>

    {{-- HEADER --}}
    <div class="row header">
        <div class="col" style="width:60%;">
            <h1>PT. Berdikari Inti Gemilang</h1>
            <div class="muted"><strong>Head Office:</strong> Jl. Boulevard Timur Blok. A/35 Green Court Viko Kapuk, Cengkareng, Jakarta Barat 11730</div>
            <div class="muted"><strong>Factory 1:</strong> Kawasan Industri Pasar Kemis. JI Merdeka Km7 No. 8-9 Cilongok, Sukamantri, Pasar Kemis, Tangerang Banten 15560</div>
            <div class="muted"><strong>Factory 2:</strong> Kawasan Industri Pasar Kemis. Jl Agarindo No. 9A Cilongok, Sukamantri, Pasar Kemis, Tangerang Banten 15560</div>
        </div>
        <div class="col text-right" style="width:40%;">
            <img src="{{ public_path('assets/image/logo-invoice.png') }}" alt="Logo" class="logo">
            <div class="meta" style="margin-top:6px;">
                <p><strong>No Garansi#</strong> {{ $garansi->no_garansi ?? '-' }}</p>
                <p><strong>Tanggal</strong> {{ optional($garansi->created_at)->format('d/m/Y') ?? '-' }}</p>
            </div>
        </div>
    </div>

    {{-- DATA UTAMA --}}
    @php
        $alamatLines = [];
        if (is_array($garansi->address)) {
            foreach ($garansi->address as $a) {
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
            $alamatLines[] = $garansi->address ?? '-';
        }

        $parseImages = function ($images) {
            if (is_string($images) && str_starts_with($images, '[')) {
                $images = json_decode($images, true) ?: [];
            }
            if (is_string($images) && $images !== '') $images = [$images];
            if (!is_array($images)) $images = [];
            return collect($images)->map(function($p){
                $p = preg_replace('#^/?storage/#','', (string) $p);
                $abs = public_path('storage/' . ltrim($p, '/'));
                return file_exists($abs) ? $abs : null;
            })->filter()->values()->all();
        };

        $fotoBarangPaths = $parseImages($garansi->image);
        $buktiKirimPaths = $parseImages($garansi->delivery_images);
    @endphp

    <div class="section pair">
        <div>
            <h2>Data Customer</h2>
            <table class="info-table">
                <tr><td class="label">Customer</td><td><strong>{{ $garansi->customer->name ?? '-' }}</strong></td></tr>
                <tr><td class="label">Kategori Customer</td><td>{{ $garansi->customerCategory->name ?? '-' }}</td></tr>
                <tr><td class="label">Telepon</td><td>{{ $garansi->phone ?? '-' }}</td></tr>
                <tr>
                    <td class="label">Alamat</td>
                    <td>
                        @foreach($alamatLines as $line)
                            {{ $line ?: '-' }}@if(!$loop->last)<br>@endif
                        @endforeach
                    </td>
                </tr>
            </table>
        </div>
        <div>
            <h2>Informasi Sales</h2>
            <table class="info-table">
                <tr><td class="label">Department</td><td>{{ $garansi->department->name ?? '-' }}</td></tr>
                <tr><td class="label">Karyawan</td><td>{{ $garansi->employee->name ?? '-' }}</td></tr>
                <tr><td class="label">Telepon Sales</td><td>{{ $garansi->employee->phone ?? '-' }}</td></tr>
            </table>
        </div>
    </div>

    {{-- PRODUK --}}
    <h2 style="margin-top:16px;">Detail Produk</h2>
    <table class="tbl">
        <thead>
            <tr>
                <th>Brand</th>
                <th>Kategori</th>
                <th>Produk</th>
                <th>Warna</th>
                <th>Jumlah</th>
                <th>Tanggal Pembelian</th>
                <th>Tanggal Klaim</th>
            </tr>
        </thead>
        <tbody>
            @foreach($garansi->productsWithDetails() as $item)
                <tr>
                    <td>{{ $item['brand_name'] }}</td>
                    <td>{{ $item['category_name'] }}</td>
                    <td>{{ $item['product_name'] }}</td>
                    <td>{{ $item['color'] }}</td>
                    <td>{{ (int)($item['quantity'] ?? 0) }}</td>
                    <td>{{ optional($garansi->purchase_date)->format('d/m/Y') ?? '-' }}</td>
                    <td>{{ optional($garansi->claim_date)->format('d/m/Y') ?? '-' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- INFORMASI GARANSI --}}
    <div class="section">
        <h2>Informasi Klaim Garansi</h2>
        <table class="info-table">
            <tr><td class="label">Alasan Klaim</td><td><strong>{{ $garansi->reason ?? '-' }}</strong></td></tr>
            <tr><td class="label">Catatan</td><td>{{ $garansi->note ?? '-' }}</td></tr>
            <tr><td class="label">Status Pengajuan</td><td>{{ ucfirst($garansi->status_pengajuan ?? 'pending') }}</td></tr>
            <tr><td class="label">Status Produk</td><td>{{ match($garansi->status_product){'ready_stock'=>'Ready Stock','sold_out'=>'Sold Out','rejected'=>'Ditolak','pending'=> 'Pending', default => ucfirst((string)$garansi->status_product)} }}</td></tr>
            <tr><td class="label">Status Garansi</td><td>{{ match($garansi->status_garansi){'confirmed'=>'Confirmed','processing'=>'Processing','on_hold'=>'On Hold','delivered'=>'Delivered','completed'=>'Completed','cancelled'=>'Cancelled','rejected'=>'Ditolak','pending'=>'Pending', default => ucfirst((string)$garansi->status_garansi)} }}</td></tr>
            @if(($garansi->status_garansi ?? null) === 'on_hold')
                <tr><td class="label">Alasan Hold</td><td>{{ $garansi->on_hold_comment ?? '-' }}</td></tr>
                <tr><td class="label">Batas Hold</td><td>{{ optional($garansi->on_hold_until)->format('d/m/Y') ?? '-' }}</td></tr>
            @endif
            @if(!empty($garansi->delivered_at) || !empty($garansi->delivered_by))
                <tr><td class="label">Delivered By</td><td>{{ optional($garansi->deliveredBy)->name ?? ($garansi->delivered_by ?? '-') }}</td></tr>
                <tr><td class="label">Delivered At</td><td>{{ optional($garansi->delivered_at)->format('d/m/Y H:i') ?? '-' }}</td></tr>
            @endif
        </table>
    </div>

    {{-- FOTO BARANG --}}
    <div class="section">
        <h2>Foto Barang</h2>
        @if(count($fotoBarangPaths))
            <div class="grid-photos">
                @foreach($fotoBarangPaths as $p)
                    <img src="{{ $p }}" alt="Foto Barang">
                @endforeach
            </div>
        @else
            <div class="muted">Tidak ada foto.</div>
        @endif
    </div>

    {{-- BUKTI PENGIRIMAN --}}
    <div class="section">
        <h2>Bukti Pengiriman</h2>
        @if(count($buktiKirimPaths))
            <div class="grid-photos">
                @foreach($buktiKirimPaths as $p)
                    <img src="{{ $p }}" alt="Bukti Pengiriman">
                @endforeach
            </div>
        @else
            <div class="muted">Tidak ada bukti pengiriman.</div>
        @endif
    </div>

    <footer>
        <p>• Terima kasih atas kepercayaan dan kerjasama Anda. •</p>
        <p>#untungpakainanolite #murahbergaransi</p>
    </footer>
</body>
</html>
