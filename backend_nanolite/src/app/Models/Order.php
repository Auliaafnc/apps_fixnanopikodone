<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\OrderExport;
use Illuminate\Database\Eloquent\Casts\Attribute;
use App\Models\Product;
use Illuminate\Support\Facades\Mail;
use App\Mail\OrderInvoiceMail;
use App\Models\Concerns\OwnedByEmployee; 
use App\Models\CustomerProgram;
use App\Models\Concerns\LatestFirst; 

class Order extends Model
{
    use HasFactory, OwnedByEmployee, LatestFirst; 

    protected $fillable = [
        'no_order',
        'company_id',
        'department_id',
        'employee_id',
        'customer_categories_id',
        'customer_id',
        'customer_program_id',
        'phone',
        'address',
        'diskons_enabled',
        'diskon_1',
        'diskon_2',
        'diskon_3',
        'diskon_4',
        'penjelasan_diskon_1',
        'penjelasan_diskon_2',
        'penjelasan_diskon_3',
        'penjelasan_diskon_4',
        'products',
        'payment_method',
        'payment_due_until',
        'status_pembayaran',
        'total_harga',
        'total_harga_after_tax',
        'jumlah_program',
        'reward_point',
        //status utama
        'status_pengajuan',
        'status_product',
        'status_order',
        // komentar & by siapa
        'rejection_comment',
        'rejected_by',
        'sold_out_comment',   
        'sold_out_by', 
        'on_hold_comment',
        'on_hold_until',
        'on_hold_by',
        'cancelled_comment',
        'cancelled_by',
        // bukti delivered
        'delivery_images',
        'delivered_at',
        'delivered_by',
        // file export
        'order_file',
        'order_excel',
    ];

    protected $casts = [
        'company_id'             => 'integer',
        'customer_id'            => 'integer',
        'employee_id'            => 'integer',
        'department_id'          => 'integer',
        'customer_categories_id' => 'integer',
        'customer_program_id'    => 'integer',

        'products'               => 'array',
        'address'                => 'array',
        'delivery_images'        => 'array',

        'diskons_enabled'        => 'boolean',
        'diskon_1'               => 'float',
        'diskon_2'               => 'float',
        'diskon_3'               => 'float',
        'diskon_4'               => 'float',

        'jumlah_program'         => 'integer',
        'reward_point'           => 'integer',

        'total_harga'            => 'decimal:2',
        'total_harga_after_tax'  => 'decimal:2',

        'on_hold_until'          => 'datetime',
        'delivered_at'           => 'datetime',
        'payment_due_until'      => 'date',
    ];

    protected $appends = ['invoice_pdf_url', 'invoice_excel_url'];

    protected static function booted()
    {
        static::creating(function (Order $order) {
            $order->no_order = 'ORD-' . now()->format('Ymd') . strtoupper(Str::random(4));
            self::hitungHargaDanSubtotal($order);
            self::hitungProgramDanReward($order);
        });

        static::saving(function (Order $order) {
            self::consumeImageArray($order, 'delivery_images', 'order-delivery-photos');
            
        });

        static::updating(function (Order $order) {
            self::hitungHargaDanSubtotal($order);
            self::hitungProgramDanReward($order);
        });

        static::saved(function (Order $order) {
            if (!is_array($order->products) || empty($order->products)) {
                \Log::warning("Order ID {$order->id} tidak memiliki data produk saat export.");
                return;
            }

            $html = view('invoices.order', compact('order'))->render();
            $pdf  = Pdf::loadHtml($html)->setPaper('a4', 'portrait');

            $pdfFileName = "Order-{$order->no_order}.pdf";
            Storage::disk('public')->put($pdfFileName, $pdf->output());
            $order->updateQuietly(['order_file' => $pdfFileName]);

            $excelFileName = "Order-{$order->no_order}.xlsx";
            Excel::store(new OrderExport($order), $excelFileName, 'public');
            $order->updateQuietly(['order_excel' => $excelFileName]);
        });
    }

    protected static function hitungHargaDanSubtotal(Order $order): void
    {
        $produkBaru = collect($order->products ?? [])->map(function ($item) {
            $priceRaw = $item['price'] ?? 0;
            $qty      = (int) ($item['quantity'] ?? 0);

            $price    = is_string($priceRaw) ? (float) str_replace('.', '', $priceRaw) : (float) $priceRaw;
            $subtotal = $price * $qty;

            $item['price']    = $price;
            $item['subtotal'] = $subtotal;
            return $item;
        });

        $total = (float) $produkBaru->sum('subtotal');

        $totalAfter = $total;

        if ($order->diskons_enabled) {
            $diskons = [
                (float) ($order->diskon_1 ?? 0),
                (float) ($order->diskon_2 ?? 0),
                (float) ($order->diskon_3 ?? 0),
                (float) ($order->diskon_4 ?? 0),
            ];

            foreach ($diskons as $d) {
                if ($d > 0) {
                    $totalAfter -= ($totalAfter * ($d / 100));
                }
            }
        }

        $order->products              = $produkBaru->toArray();
        $order->total_harga           = round($total, 2);
        $order->total_harga_after_tax = round($totalAfter, 2);
    }


    protected static function hitungProgramDanReward(Order $order): void
{
    $afterTax = (float) $order->total_harga_after_tax;

    // reward point tetap pakai ambang reward aktif
    $rewardMin = PointMinimum::rewardMin();
    $order->reward_point = (int) floor($afterTax / max(1, $rewardMin));

    // program point:
    // - kalau tidak ikut program => 0
    // - kalau ikut tapi tidak ada ambang aktif => 0
    // - kalau ada ambang aktif => hitung
    $programMin = PointMinimum::programMin($order->customer_program_id);
    if (empty($programMin)) {
        $order->jumlah_program = 0;
    } else {
        $order->jumlah_program = (int) floor($afterTax / max(1, $programMin));
    }
}

    // ================= IMAGE HELPERS =================
    protected static function consumeImageArray(Order $order, string $field, string $folder): void
    {
        $imgs = $order->$field ?? [];
        if (is_string($imgs)) {
            $imgs = json_decode($imgs, true) ?: [];
        }
        if (!is_array($imgs)) return;

        $saved = [];
        foreach ($imgs as $img) {
            if (is_string($img) && preg_match('/^data:image\/([a-zA-Z0-9.+-]+);base64,/', $img, $m)) {
                $ext  = strtolower($m[1] ?? 'png');
                $data = substr($img, strpos($img, ',') + 1);
                $bin  = base64_decode($data, true);
                if ($bin === false) continue;

                $name = $folder . '/' . now()->format('Ymd_His') . '_' . Str::random(8) . '.' . $ext;
                Storage::disk('public')->put($name, $bin);
                $saved[] = $name;
            } elseif (is_string($img)) {
                $saved[] = $img;
            }
        }

        $order->$field = $saved;
    }


    // ===== RELATIONS =====
    public function company(){ return $this->belongsTo(Company::class); }
    public function department(){ return $this->belongsTo(Department::class, 'department_id'); }
    public function employee(){ return $this->belongsTo(Employee::class); }
    public function customer(){ return $this->belongsTo(Customer::class); }
    public function customerCategory(){ return $this->belongsTo(CustomerCategories::class, 'customer_categories_id'); }
    public function customerProgram(){ return $this->belongsTo(CustomerProgram::class, 'customer_program_id'); }

    // ===== ACCESSORS =====
    public function getInvoicePdfUrlAttribute(): ?string
    {
        if (empty($this->order_file)) return null;
        return url(Storage::disk('public')->url($this->order_file));
    }

    public function getInvoiceExcelUrlAttribute(): ?string
    {
        if (empty($this->order_excel)) return null;
        return url(Storage::disk('public')->url($this->order_excel));
    }

    public function productsWithDetails(): array
    {
        $raw = $this->products;
        if (is_string($raw)) $raw = json_decode($raw, true) ?: [];
        elseif (!is_array($raw)) $raw = [];

        return array_map(function ($item) {
            $product = Product::find($item['produk_id'] ?? null);

            return [
                'brand_id'      => $product?->brand_id ?? null,
                'category_id'   => $product?->category_id ?? null,
                'product_id'    => $product?->id ?? null,
                'brand_name'    => $product?->brand?->name ?? '(Brand hilang)',
                'category_name' => $product?->category?->name ?? '(Kategori hilang)',
                'product_name'  => $product?->name ?? '(Produk hilang)',
                'color'         => $item['warna_id'] ?? '-',
                'price'         => $item['price'] ?? 0,
                'quantity'      => $item['quantity'] ?? 0,
                'subtotal'      => $item['subtotal'] ?? (($item['price'] ?? 0) * ($item['quantity'] ?? 0)),
            ];
        }, $raw);
    }

    public function getProductsDetailsAttribute(): string
    {
        $items = $this->productsWithDetails();
        if (empty($items)) return '';
        return collect($items)->map(fn($i) =>
            "{$i['brand_name']} – {$i['category_name']} – {$i['product_name']} – {$i['color']} – Rp"
            . number_format($i['price'], 0, ',', '.')
            . " – Qty: {$i['quantity']}"
        )->implode('<br>');
    }

   public function getTotalDiscountAttribute(): float
    {
        if (!$this->diskons_enabled) {
            return 0.0;
        }

        $faktor = 1.0;

        foreach ([
            (float) ($this->diskon_1 ?? 0),
            (float) ($this->diskon_2 ?? 0),
            (float) ($this->diskon_3 ?? 0),
            (float) ($this->diskon_4 ?? 0),
        ] as $d) {
            // clamp 0–100 biar aman
            $d = max(0.0, min(100.0, $d));
            if ($d > 0) {
                $faktor *= (1 - $d / 100);
            }
        }

        $effectivePercent = (1 - $faktor) * 100;

        return round($effectivePercent, 2); // mis. 14.5 (%)
    }


    
}