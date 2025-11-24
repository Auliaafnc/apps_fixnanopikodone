<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8" />
    <title>Order {{ $order->no_order }}</title>
    <style>
        /* ===== Reset & Layout Compact ===== */
        * { box-sizing: border-box; }
        html,body { margin:0; padding:0; }
        body { font-family: Arial, sans-serif; color:#333; font-size:10px; line-height:1.3; padding:16px 24px; }

        .row { display: table; width: 100%; table-layout: fixed; }
        .col { display: table-cell; vertical-align: top; }
        .text-right { text-align:right; }
        .muted { color:#666; }

        h1 { font-size:18px; margin:0 0 4px; }
        h2 { font-size:13px; margin:12px 0 6px; }
        h3 { font-size:11px; margin:8px 0 4px; }

        .header { margin-bottom: 6px; }
        .logo { height: 60px; }
        .meta p { margin: 1px 0; }

        /* ===== Tabel Produk (kolom fixed + align konsisten) ===== */
        table.tbl{
            width:100%; border-collapse:collapse; margin-top:6px; font-size:9px;
            table-layout:fixed;
        }
        table.tbl th, table.tbl td{
            padding:4px 6px;
            vertical-align:middle;
            border-bottom:1px solid #ddd;
            word-break:break-word;
        }
        table.tbl thead th{
            background:#f5f5f5;
            border-bottom:1px solid #555;
            white-space:nowrap;
        }

        /* Kolom 1–4 di tengah, kolom 5–7 sesuai peran */
        table.tbl thead th:nth-child(1),
        table.tbl thead th:nth-child(2),
        table.tbl thead th:nth-child(3),
        table.tbl thead th:nth-child(4),
        table.tbl tbody td:nth-child(1),
        table.tbl tbody td:nth-child(2),
        table.tbl tbody td:nth-child(3),
        table.tbl tbody td:nth-child(4) { text-align:center; }

        table.tbl thead th:nth-child(5),
        table.tbl tbody td:nth-child(5){ text-align:center; }       /* Pcs */

        table.tbl thead th:nth-child(6),
        table.tbl tbody td:nth-child(6),
        table.tbl thead th:nth-child(7),
        table.tbl tbody td:nth-child(7){ text-align:right; }        /* Harga & Subtotal */

        table.tbl tbody td:nth-child(6),
        table.tbl tbody td:nth-child(7){ font-variant-numeric: tabular-nums; }

        /* ===== Info blocks & totals ===== */
        .section { margin-top:10px; }
        .pair { display:flex; gap:8px; }
        .pair > div { flex:1; }

        .info-table { width:100%; border-collapse:collapse; font-size:9px; }
        .info-table td { padding:2px 0; vertical-align:top; }
        .label { width:140px; color:#555; }

        .totals { width:100%; border-collapse:collapse; font-size:10px; }
        .totals th, .totals td { padding:4px 0; }
        .totals th { text-align:left; border:none; }
        .totals td { text-align:right; border:none; }
        .line-strong { border-top:1px solid #333; padding-top:4px; }

        footer { margin-top:24px; text-align:center; font-size:9px; color:#666; }
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
            <div class="meta" style="margin-top:4px;">
                <p><strong>No Order#</strong> {{ $order->no_order ?? '-' }}</p>
                <p><strong>Tanggal</strong> {{ optional($order->created_at)->format('d/m/Y') ?? '-' }}</p>
            </div>
        </div>
    </div>

    {{-- Normalisasi alamat --}}
    @php
        $alamatLines = [];
        if (is_array($order->address)) {
            foreach ($order->address as $a) {
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
            $alamatLines[] = $order->address ?? '-';
        }
    @endphp

    <div class="section pair">
        <div>
            <h2>Bill To</h2>
            <table class="info-table">
                <tr><td class="label">Customer</td><td><strong>{{ $order->customer->name ?? '-' }}</strong></td></tr>
                <tr><td class="label">Kategori Customer</td><td>{{ $order->customerCategory->name ?? '-' }}</td></tr>
                <tr><td class="label">Telepon</td><td>{{ $order->phone ?? '-' }}</td></tr>
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
                <tr><td class="label">Department</td><td>{{ $order->department->name ?? '-' }}</td></tr>
                <tr><td class="label">Karyawan</td><td>{{ $order->employee->name ?? '-' }}</td></tr>
                <tr><td class="label">Telepon Sales</td><td>{{ $order->employee->phone ?? '-' }}</td></tr>
                <tr><td class="label">Program Pelanggan</td><td>{{ $order->customerProgram->name ?? '-' }}</td></tr>
            </table>
        </div>
    </div>

    {{-- DETAIL PRODUK --}}
    <h2 style="margin-top:12px;">Detail Produk</h2>
    <table class="tbl">
        <colgroup>
            <col style="width:16%">
            <col style="width:16%">
            <col style="width:26%">
            <col style="width:12%">
            <col style="width:6%">
            <col style="width:12%">
            <col style="width:12%">
        </colgroup>
        <thead>
            <tr>
                <th>Brand</th>
                <th>Kategori</th>
                <th>Produk</th>
                <th>Warna</th>
                <th>Pcs</th>
                <th>Harga</th>
                <th>Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach($order->productsWithDetails() as $item)
                <tr>
                    <td>{{ $item['brand_name'] }}</td>
                    <td>{{ $item['category_name'] }}</td>
                    <td>{{ $item['product_name'] }}</td>
                    <td>{{ $item['color'] }}</td>
                    <td>{{ (int)($item['quantity'] ?? 0) }}</td>
                    <td>Rp {{ number_format((int)($item['price'] ?? 0), 0, ',', '.') }}</td>
                    <td>Rp {{ number_format((int)($item['subtotal'] ?? 0), 0, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- INFORMASI ORDER & TOTAL --}}
    <div class="section pair" style="gap:12px;">
        <div>
            <h2>Informasi Pembayaran & Pesanan</h2>
            <table class="info-table">
                <tr><td class="label">Metode Pembayaran</td><td>{{ ucfirst($order->payment_method ?? '-') }}</td></tr>
                @if(($order->payment_method ?? null) === 'tempo')
                    <tr><td class="label">Jatuh Tempo</td><td>{{ optional($order->payment_due_until)->format('d/m/Y') ?? '-' }}</td></tr>
                @endif
                <tr><td class="label">Status Pembayaran</td><td>{{ ucfirst($order->status_pembayaran ?? '-') }}</td></tr>
                <tr><td class="label">Status Pengajuan</td>
                    <td>{{ match($order->status_pengajuan){
                        'approved'=>'Disetujui','rejected'=>'Ditolak','pending'=>'Pending',
                        default=>ucfirst((string)$order->status_pengajuan)
                    } }}</td></tr>
                <tr><td class="label">Status Produk</td>
                    <td>{{ match($order->status_product){
                        'ready_stock'=>'Ready Stock','sold_out'=>'Sold Out','rejected'=>'Ditolak','pending'=>'Pending',
                        default=>ucfirst((string)$order->status_product)
                    } }}</td></tr>
                <tr><td class="label">Status Order</td>
                    <td>{{ match($order->status_order){
                        'confirmed'=>'Confirmed','processing'=>'Processing','on_hold'=>'On Hold','delivered'=>'Delivered',
                        'completed'=>'Completed','cancelled'=>'Cancelled','rejected'=>'Ditolak','pending'=>'Pending',
                        default=>ucfirst((string)$order->status_order)
                    } }}</td></tr>
                @if(($order->status_order ?? null)==='on_hold')
                    <tr><td class="label">Alasan Hold</td><td>{{ $order->on_hold_comment ?? '-' }}</td></tr>
                    <tr><td class="label">Batas Hold</td><td>{{ optional($order->on_hold_until)->format('d/m/Y') ?? '-' }}</td></tr>
                @endif
            </table>
        </div>

        <div>
            <h2>Total</h2>
            <table class="totals">
                <tr>
                    <th>Subtotal</th>
                    <td>Rp {{ number_format((int)($order->total_harga ?? 0), 0, ',', '.') }}</td>
                </tr>

                @php
                    $diskonsEnabled = (bool) ($order->diskons_enabled ?? false);
                    $ds = [
                        ['p'=>$order->diskon_1 ?? 0, 'desc'=>$order->penjelasan_diskon_1 ?? null],
                        ['p'=>$order->diskon_2 ?? 0, 'desc'=>$order->penjelasan_diskon_2 ?? null],
                        ['p'=>$order->diskon_3 ?? 0, 'desc'=>$order->penjelasan_diskon_3 ?? null],
                        ['p'=>$order->diskon_4 ?? 0, 'desc'=>$order->penjelasan_diskon_4 ?? null],
                    ];
                    $running = (float) ($order->total_harga ?? 0);
                @endphp

                @if($diskonsEnabled)
                    @foreach($ds as $i => $d)
                        @php
                            $p = max(0,min(100,(float)$d['p']));
                            $cut = 0;
                            if ($p > 0) { $cut = $running * ($p/100); $running -= $cut; }
                        @endphp
                        @if($p > 0)
                            <tr>
                                <th>Diskon {{ $i+1 }} ({{ rtrim(rtrim(number_format($p,2,'.',''), '0'),'.') }}%)</th>
                                <td>- Rp {{ number_format((int)round($cut), 0, ',', '.') }}</td>
                            </tr>
                            @if(!empty($d['desc']))
                                <tr>
                                    <th>Penjelasan Diskon {{ $i+1 }}</th>
                                    <td style="text-align:left;">{{ $d['desc'] }}</td>
                                </tr>
                            @endif
                        @endif
                    @endforeach
                @endif

                @if((int)($order->reward_point ?? 0) > 0)
                    <tr><th>Reward Point</th><td>{{ (int) $order->reward_point }}</td></tr>
                @endif
                @if((int)($order->jumlah_program ?? 0) > 0)
                    <tr><th>Program Point</th><td>{{ (int) $order->jumlah_program }}</td></tr>
                @endif

                <tr>
                    <th class="line-strong"><strong>Grand Total</strong></th>
                    <td class="line-strong"><strong>Rp {{ number_format((int)($order->total_harga_after_tax ?? round($running)), 0, ',', '.') }}</strong></td>
                </tr>
            </table>
        </div>
    </div>

    <footer>
        <p>• Terima kasih atas kepercayaan dan kerjasama Anda. •</p>
        <p>#untungpakainanolite #murahbergaransi</p>
    </footer>
</body>
</html>
