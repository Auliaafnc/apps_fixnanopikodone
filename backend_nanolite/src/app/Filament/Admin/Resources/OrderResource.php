<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\OrderResource\Pages;
use App\Models\Order;
use App\Models\Brand;
use App\Models\Department;
use App\Models\Employee;
use App\Models\CustomerProgram;
use App\Models\Category;
use App\Models\CustomerCategories;
use App\Models\Product;
use App\Models\Customer;
use App\Models\PointMinimum;
use Filament\Forms\Get;
use Filament\Forms\Form;
use Filament\Tables\Filters\SelectFilter;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource; 
use Filament\Forms\Components\Grid;
use Filament\Notifications\Notification;
use Filament\Tables\Table;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\Action;
use Illuminate\Support\Facades\Storage;
use App\Exports\FilteredOrdersExport;
use Filament\Forms\Components\Checkbox;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Tables\Columns\ImageColumn;
use Illuminate\Support\Str;


class OrderResource extends Resource
{
    protected static ?string $model                = Order::class;
    protected static ?string $navigationGroup      = 'Sales Management';
    protected static ?int    $navigationSort       = 2;
    protected static ?string $recordTitleAttribute = 'no_order';

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user  = auth()->user();

        if ($user->hasAnyRole(['sales','head_sales','head_digital'])) {
            // tampilkan hanya data milik departemen & karyawan ini
            $deptId = optional($user->employee)->department_id;
            $empId  = $user->employee_id;

            $query->where(function ($q) use ($deptId, $empId) {
                $q->where('department_id', $deptId)
                ->where('employee_id', $empId);
            });
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('department_id')
                    ->label('Department')
                    ->reactive()
                    ->afterStateUpdated(fn($state, callable $set) => [
                        $set('employee_id', null),
                        $set('customer_categories_id', null),
                        $set('customer_id', null),
                    ])
                    ->options(function () {
                        $user = auth()->user();
                        if ($user->hasAnyRole(['sales','head_sales','head_digital'])) {
                            $deptId = optional($user->employee)->department_id;
                            return \App\Models\Department::whereKey($deptId)->pluck('name','id');
                        }
                        return \App\Models\Department::where('status','active')->pluck('name','id');
                    })
                    ->default(fn () => optional(auth()->user()->employee)->department_id)
                    ->disabled(fn () => auth()->user()->hasAnyRole(['sales','head_sales','head_digital']))
                    ->required()->searchable()->preload()->placeholder('Pilih Department'),


                Select::make('employee_id')
                ->label('Karyawan')
                ->reactive()
                ->afterStateUpdated(fn($state, callable $set) => [
                    $set('customer_categories_id', null),
                    $set('customer_id', null),
                ])
                ->options(function (callable $get) {
                    $user   = auth()->user();
                    if ($user->hasAnyRole(['sales','head_sales','head_digital'])) {
                        return \App\Models\Employee::whereKey($user->employee_id)->pluck('name','id');
                    }
                    $deptId = $get('department_id');
                    if (!$deptId) return [];
                    return \App\Models\Employee::where('status','active')
                        ->where('department_id',$deptId)
                        ->pluck('name','id');
                })
                ->default(fn () => auth()->user()->employee_id)
                ->disabled(fn () => auth()->user()->hasAnyRole(['sales','head_sales','head_digital']))
                ->required()->searchable()->preload()->placeholder('Pilih Karyawan'),

                Select::make('customer_categories_id')
                    ->label('Kategori Customer')
                    ->reactive()
                    ->afterStateUpdated(fn($state, callable $set) => $set('customer_id', null))
                    ->options(function (callable $get) {
                        $employeeId = $get('employee_id');
                        if (!$employeeId) return [];
                        return CustomerCategories::whereHas('customers', function ($q) use ($employeeId) {
                                $q->where('employee_id', $employeeId);
                            })
                            ->pluck('name', 'id');
                    })
                    ->disabled(fn ($record) => $record && auth()->user()->hasAnyRole(['sales','head_sales','head_digital']))    
                    ->required()->searchable()->preload()->placeholder('Pilih Kategori Customer'),


                Select::make('customer_id')
                    ->label('Customer')
                    ->reactive()
                    ->options(function (callable $get) {
                        $employeeId = $get('employee_id');
                        $categoryId = $get('customer_categories_id');
                        if (blank($employeeId) || blank($categoryId)) return [];
                        return Customer::where('status', 'active')
                            ->where('employee_id', $employeeId)
                            ->where('customer_categories_id', $categoryId)
                            ->pluck('name', 'id');
                    })
                    ->afterStateUpdated(function ($state, callable $set) {
                        $customer = Customer::with('customerProgram')->find($state);
                        if ($customer) {
                            $set('phone', $customer->phone);
                            $set('address', $customer->full_address);
                            $set('customer_program_id', $customer->customer_program_id ?? null);
                        } else {
                            $set('phone', null);
                            $set('address', null);
                            $set('customer_program_id', null);
                        }
                    })
                    ->disabled(fn ($record) => $record && auth()->user()->hasAnyRole(['sales','head_sales','head_digital']))   
                    ->required()->preload()->searchable()->placeholder('Pilih Customer'),

                TextInput::make('phone')->label('Phone')->reactive()->required() ->disabled(fn ($record) => $record && auth()->user()->hasAnyRole(['sales','head_sales','head_digital'])),    

                Textarea::make('address')
                    ->label('Alamat')
                    ->rows(3)
                    ->required()
                    ->formatStateUsing(function ($state) {
                        if (is_array($state)) {
                            return collect($state)->map(function ($i) {
                                return implode(', ', array_filter([
                                    $i['detail_alamat'] ?? null,
                                    $i['kelurahan'] ?? null,
                                    $i['kecamatan'] ?? null,
                                    $i['kota_kab'] ?? null,
                                    $i['provinsi'] ?? null,
                                    $i['kode_pos'] ?? null,
                                ], fn ($v) => !empty($v) && $v !== '-'));
                            })->implode("\n");
                        }
                        return $state;
                    })
                    ->dehydrateStateUsing(fn ($state) => $state)
                    ->disabled(fn ($record) => $record && auth()->user()->hasAnyRole(['sales','head_sales','head_digital'])),    

            Toggle::make('diskons_enabled')->label('Diskon Aktif')->live()
                    ->afterStateUpdated(fn($state, callable $set) => $set('total_harga_after_tax', null))
                    ->disabled(fn ($record) => $record && auth()->user()->hasAnyRole(['sales','head_sales','head_digital']))
                    ->reactive(),

            Select::make('customer_program_id')
                ->label('Program Pelanggan')
                ->options(fn () => CustomerProgram::pluck('name', 'id'))
                ->disabled()
                ->dehydrated()
                ->searchable(),

            // === DISKON 1 ===
            TextInput::make('diskon_1')
            ->label('Diskon 1 (%)')
            ->numeric()
            ->live()
            ->reactive()
            ->dehydrated(fn (Get $get) => $get('diskons_enabled'))
            ->disabled(fn (Get $get, $record) =>
                ! $get('diskons_enabled')
                || ($record && auth()->user()->hasAnyRole(['sales','head_sales','head_digital']))
            )
            ->afterStateUpdated(fn($state, callable $set) => $set('total_harga_after_tax', null))
            ->default(0)
            ->helperText('Masukkan persentase diskon pertama (contoh: 10 untuk 10%)'),

        TextInput::make('penjelasan_diskon_1')
            ->label('Penjelasan Diskon 1')
            ->nullable()
            ->dehydrated(fn (Get $get) => $get('diskons_enabled'))
            ->disabled(fn (Get $get, $record) =>
                ! $get('diskons_enabled')
                || ($record && auth()->user()->hasAnyRole(['sales','head_sales','head_digital']))
            ),


        // === DISKON 2 ===
        TextInput::make('diskon_2')
            ->label('Diskon 2 (%)')
            ->numeric()
            ->live()
            ->reactive()
            ->dehydrated(fn (Get $get) => $get('diskons_enabled'))
            ->disabled(fn (Get $get, $record) =>
                ! $get('diskons_enabled')
                || ($record && auth()->user()->hasAnyRole(['sales','head_sales','head_digital']))
            )
            ->afterStateUpdated(fn($state, callable $set) => $set('total_harga_after_tax', null))
            ->default(0)
            ->helperText('Masukkan persentase diskon kedua (contoh: 5 untuk 5%)'),

        TextInput::make('penjelasan_diskon_2')
            ->label('Penjelasan Diskon 2')
            ->nullable()
            ->dehydrated(fn (Get $get) => $get('diskons_enabled'))
            ->disabled(fn (Get $get, $record) =>
                ! $get('diskons_enabled')
                || ($record && auth()->user()->hasAnyRole(['sales','head_sales','head_digital']))
            ),


        // === DISKON 3 ===
        TextInput::make('diskon_3')
            ->label('Diskon 3 (%)')
            ->numeric()
            ->live()
            ->reactive()
            ->dehydrated(fn (Get $get) => $get('diskons_enabled'))
            ->disabled(fn (Get $get, $record) =>
                ! $get('diskons_enabled')
                || ($record && auth()->user()->hasAnyRole(['sales','head_sales','head_digital']))
            )
            ->afterStateUpdated(fn($state, callable $set) => $set('total_harga_after_tax', null))
            ->default(0)
            ->helperText('Masukkan persentase diskon ketiga (contoh: 2 untuk 2%)'),

        TextInput::make('penjelasan_diskon_3')
            ->label('Penjelasan Diskon 3')
            ->nullable()
            ->dehydrated(fn (Get $get) => $get('diskons_enabled'))
            ->disabled(fn (Get $get, $record) =>
                ! $get('diskons_enabled')
                || ($record && auth()->user()->hasAnyRole(['sales','head_sales','head_digital']))
            ),


        // === DISKON 4 ===
        TextInput::make('diskon_4')
            ->label('Diskon 4 (%)')
            ->numeric()
            ->live()
            ->reactive()
            ->dehydrated(fn (Get $get) => $get('diskons_enabled'))
            ->disabled(fn (Get $get, $record) =>
                ! $get('diskons_enabled')
                || ($record && auth()->user()->hasAnyRole(['sales','head_sales','head_digital']))
            )
            ->afterStateUpdated(fn($state, callable $set) => $set('total_harga_after_tax', null))
            ->default(0)
            ->helperText('Masukkan persentase diskon keempat (contoh: 1 untuk 1%)'),

        TextInput::make('penjelasan_diskon_4')
            ->label('Penjelasan Diskon 4')
            ->nullable()
            ->dehydrated(fn (Get $get) => $get('diskons_enabled'))
            ->disabled(fn (Get $get, $record) =>
                ! $get('diskons_enabled')
                || ($record && auth()->user()->hasAnyRole(['sales','head_sales','head_digital']))
            ),


            Repeater::make('products')
    ->label('Kebutuhan Order')
    ->reactive()
    ->live()
    ->schema([
        // === BRAND ===
        Select::make('brand_id')
            ->label('Brand')
            ->options(fn () => Brand::pluck('name', 'id'))
            ->reactive()
            ->afterStateUpdated(function ($state, callable $set) {
                // reset field yg bergantung ke brand
                $set('kategori_id', null);
                $set('produk_id', null);
                $set('warna_id', null);
            })
            ->required()
            ->searchable(),

        // === KATEGORI (filter by brand) ===
        Select::make('kategori_id')
            ->label('Kategori')
            ->options(function (callable $get) {
                $brandId = $get('brand_id');
                if (! $brandId) {
                    return [];
                }

                // kategori yg ada produknya utk brand tsb
                return Category::whereHas('products', function ($q) use ($brandId) {
                        $q->where('brand_id', $brandId);
                    })
                    ->pluck('name', 'id');
            })
            ->reactive()
            ->afterStateUpdated(function ($state, callable $set) {
                // reset field yg bergantung ke kategori
                $set('produk_id', null);
                $set('warna_id', null);
            })
            ->required()
            ->searchable(),

        // === PRODUK (filter brand + kategori) ===
        Select::make('produk_id')
            ->label('Produk')
            ->options(function (callable $get) {
                $brandId    = $get('brand_id');
                $categoryId = $get('kategori_id');

                $query = Product::query();

                if ($brandId) {
                    $query->where('brand_id', $brandId);
                }
                if ($categoryId) {
                    $query->where('category_id', $categoryId);
                }

                return $query->pluck('name', 'id');
            })
            ->reactive()
            ->afterStateUpdated(function ($state, callable $set, callable $get) {

                $p = Product::find($state);

                if ($p) {
                    // sinkron brand & kategori supaya aman
                    $set('brand_id', $p->brand_id);
                    $set('kategori_id', $p->category_id);

                    // harga otomatis
                    $price = (int) ($p->price ?? 0);
                    $set('price', $price);

                    // qty min 1 + subtotal
                    $qty = (int) ($get('quantity') ?? 1);
                    if ($qty <= 0) {
                        $qty = 1;
                        $set('quantity', 1);
                    }
                    $set('subtotal', $price * $qty);

                } else {
                    $set('price', 0);
                    $set('subtotal', 0);
                }

                // hitung total + diskon + poin
                $products   = $get('../../products') ?? [];
                $totalHarga = collect($products)->sum(fn ($i) => (int) ($i['subtotal'] ?? 0));
                $set('../../total_harga', $totalHarga);

                $enabled = $get('../../diskons_enabled') ?? false;
                $ds = [
                    (float) ($get('../../diskon_1') ?? 0),
                    (float) ($get('../../diskon_2') ?? 0),
                    (float) ($get('../../diskon_3') ?? 0),
                    (float) ($get('../../diskon_4') ?? 0),
                ];

                $after = (float) $totalHarga;
                if ($enabled) {
                    foreach ($ds as $d) {
                        $d = max(0, min(100, $d));
                        if ($d > 0) {
                            $after -= $after * ($d / 100);
                        }
                    }
                }

                $after = (int) round($after);
                $set('../../total_harga_after_tax', $after);

                // poin & program
                $rewardMin  = PointMinimum::rewardMin();
                $programMin = PointMinimum::programMin($get('../../customer_program_id'));

                $set('../../reward_point', (int) floor($after / max(1, $rewardMin)));
                $set('../../jumlah_program', $programMin ? (int) floor($after / max(1, $programMin)) : 0);
            })
            ->required()
            ->searchable(),

        // === WARNA ===
        Select::make('warna_id')
            ->label('Warna')
            ->options(function (callable $get) {
                $productId = $get('produk_id');
                if (! $productId) {
                    return [];
                }

                $product = Product::find($productId);
                $colors  = $product?->colors ?? [];

                // key & value = teks warna (mis: "3000K")
                return collect($colors)->mapWithKeys(fn ($c) => [$c => $c]);
            })
            ->required()
            ->searchable(),

        // === HARGA / PRODUK ===
        TextInput::make('price')
            ->label('Harga / Produk')
            ->prefix('Rp')
            ->disabled()
            ->live()
            ->numeric()
            ->dehydrated()
            ->dehydrateStateUsing(fn ($state) =>
                is_string($state) ? (int) str_replace('.', '', $state) : $state
            ),

        // === JUMLAH ===
        TextInput::make('quantity')
            ->label('Jumlah')
            ->prefix('Qty')
            ->reactive()
            ->numeric()
            ->afterStateUpdated(function ($state, callable $set, callable $get) {
                $price = (int) ($get('price') ?? 0);
                $qty   = (int) $state;

                if ($qty <= 0) {
                    $qty = 1;
                    $set('quantity', 1);
                }

                // subtotal per item
                $set('subtotal', $price * $qty);

                // total semua produk
                $products   = $get('../../products') ?? [];
                $totalHarga = collect($products)->sum(fn ($item) => (int) ($item['subtotal'] ?? 0));
                $set('../../total_harga', $totalHarga);

                // diskon bertingkat
                $enabled = $get('../../diskons_enabled') ?? false;
                $d = [
                    (float) ($get('../../diskon_1') ?? 0),
                    (float) ($get('../../diskon_2') ?? 0),
                    (float) ($get('../../diskon_3') ?? 0),
                    (float) ($get('../../diskon_4') ?? 0),
                ];

                $after = (float) $totalHarga;
                if ($enabled) {
                    foreach ($d as $x) {
                        $x = max(0, min(100, $x));
                        if ($x > 0) {
                            $after -= $after * ($x / 100);
                        }
                    }
                }

                $after = (int) round($after);
                $set('../../total_harga_after_tax', $after);

                // poin
                $rewardMin  = PointMinimum::rewardMin();
                $programMin = PointMinimum::programMin($get('../../customer_program_id'));

                $set('../../reward_point', (int) floor($after / max(1, $rewardMin)));
                $set('../../jumlah_program', $programMin ? (int) floor($after / max(1, $programMin)) : 0);
            })
            ->required(),

        // === SUBTOTAL ===
        TextInput::make('subtotal')
            ->label('Subtotal')
            ->prefix('Rp')
            ->disabled()
            ->dehydrated()
            ->numeric()
            ->live(),
    ])
    ->columns(3)
    ->minItems(1)
    ->defaultItems(1)
    ->createItemButtonLabel('Tambah Kebutuhan')
    ->disabled(fn ($record) => $record && auth()->user()->hasAnyRole(['sales', 'head_sales', 'head_digital']))
    ->dehydrated(),



                Select::make('payment_method')
                    ->label('Metode Pembayaran')
                    ->options(['tempo' => 'Tempo','cash' => 'Cash'])
                    ->required()
                    ->reactive()
                    ->disabled(fn ($record) => $record && auth()->user()->hasAnyRole(['sales','head_sales','head_digital']))
                    ->searchable(),

                DatePicker::make('payment_due_until')
                    ->label('Jatuh Tempo (Tempo)')
                    ->visible(fn ($get) => $get('payment_method') === 'tempo')
                    ->required(fn ($get) => $get('payment_method') === 'tempo')
                    ->minDate(today())
                    ->disabled(fn ($record) => $record && auth()->user()->hasAnyRole(['sales','head_sales','head_digital']))
                    ->helperText('Wajib diisi jika metode pembayaran = Tempo'),

                Select::make('status_pembayaran')->label('Status Pembayaran')
                    ->disabled(fn ($record) => $record && auth()->user()->hasAnyRole(['sales','head_sales','head_digital']))
                    ->options(['belum bayar' => 'Belum Bayar','sudah bayar' => 'Sudah Bayar', 'belum lunas' => 'Belum Lunas', 'sudah lunas' => 'Sudah Lunas'])->required()->searchable(),

                TextInput::make('total_harga')
                ->label('Total Harga')
                ->disabled()->prefix('Rp')->dehydrated()
                ->reactive()->numeric()->default(0)->live()
                ->afterStateHydrated(fn (callable $set, $state) => $set('total_harga', $state)),

                TextInput::make('reward_point')
                    ->label('Poin Reward (auto)')
                    ->numeric()->disabled()->dehydrated(true)
                    ->afterStateHydrated(function (callable $set, callable $get) {
                        $afterTax  = (float) ($get('total_harga_after_tax') ?? 0);
                        $rewardMin = PointMinimum::rewardMin();
                        $set('reward_point', (int) floor($afterTax / max(1, $rewardMin)));
                    })->reactive(),

                TextInput::make('total_harga_after_tax')
                    ->label('Total Harga Akhir')
                    ->disabled()->prefix('Rp')->numeric()->dehydrated()->reactive()
                    ->afterStateHydrated(function (callable $set, callable $get) {
                        $total = (float) ($get('total_harga') ?? 0);
                        $after = $total;

                        if ($get('diskons_enabled')) {
                            foreach ([
                                (float) ($get('diskon_1') ?? 0),
                                (float) ($get('diskon_2') ?? 0),
                                (float) ($get('diskon_3') ?? 0),
                                (float) ($get('diskon_4') ?? 0),
                            ] as $x) {
                                $x = max(0, min(100, $x));
                                if ($x > 0) $after -= $after * ($x / 100);
                            }
                        }

                        $after = (int) round($after);
                        $set('total_harga_after_tax', $after);

                        // hitung poin
                        $rewardMin  = PointMinimum::rewardMin();
                        $programMin = PointMinimum::programMin($get('customer_program_id'));
                        $set('reward_point', (int) floor($after / max(1, $rewardMin)));
                        $set('jumlah_program', $programMin ? (int) floor($after / max(1, $programMin)) : 0);
                    })

                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                        $after      = (float) ($state ?? 0);
                        $rewardMin  = PointMinimum::rewardMin();
                        $programMin = PointMinimum::programMin($get('customer_program_id'));

                        $set('reward_point', (int) floor($after / max(1, $rewardMin)));
                        $set('jumlah_program', $programMin ? (int) floor($after / max(1, $programMin)) : 0);
                    }),


                TextInput::make('jumlah_program')
                    ->label('Poin Program (auto)')
                    ->numeric()->disabled()->dehydrated(true)
                    ->afterStateHydrated(function (callable $set, callable $get) {
                        $afterTax   = (float) ($get('total_harga_after_tax') ?? 0);
                        $programMin = PointMinimum::programMin($get('customer_program_id'));
                        $set('jumlah_program', $programMin ? (int) floor($afterTax / max(1, $programMin)) : 0);
                    })->reactive(),


                // === status ===
                Select::make('status_pengajuan')
                    ->label('Status Pengajuan Order')
                    ->options([
                        'pending'  => 'Pending',
                        'approved' => 'Disetujui',
                        'rejected' => 'Ditolak',
                    ])
                    ->default('pending')
                    ->reactive()
                    ->afterStateUpdated(function ($state, callable $set) {
                        if ($state === 'rejected') {
                            $set('status_product', 'rejected');
                            $set('status_order', 'rejected');
                        }
                    })
                   ->visible(fn ($record) =>
                        auth()->user()->can('updateStatus', $record ?? new \App\Models\Order)
                        || auth()->user()->hasAnyRole(['admin','manager','head_marketing'])
                    )
                    ->searchable(),


                Textarea::make('rejection_comment')
                    ->label('Komentar Ditolak')
                    ->visible(fn ($get) => $get('status_pengajuan') === 'rejected'),

                Select::make('status_product')
                    ->label('Status Produk')
                    ->options([
                        'pending'=>'Pending','ready_stock'=>'Ready Stock','sold_out'=>'Sold Out','rejected'=>'Ditolak',
                    ])
                    ->visible(fn ($record) =>
                        auth()->user()->can('updateStatus', $record ?? new \App\Models\Order)
                        || auth()->user()->hasAnyRole(['admin','manager','head_marketing'])
                    )
                    ->default('pending')
                    ->reactive()
                    ->searchable(),


                FileUpload::make('delivery_images')
                    ->label('Bukti Pengiriman')
                    ->multiple()
                    ->image()
                    ->panelLayout('grid')
                    ->downloadable()
                    ->openable()
                    ->disk('public')
                    ->directory('order-delivery-photos')
                    ->maxFiles(5)
                    ->maxSize(102400)
                    ->dehydrated(true)
                    // hanya tampil saat edit (record sudah ada)
                    ->visible(fn ($record) => (bool) $record)
                    // tips untuk role sales jika belum delivered
                    ->helperText(fn ($record, $get) =>
                        auth()->user()->hasAnyRole(['sales','head_sales','head_digital'])
                        && $record
                        && $get('status_order') !== 'delivered'
                            ? 'Bukti pengiriman hanya bisa diunggah setelah status order = Delivered.'
                            : null
                    )
                    // aturan enable/disable upload
                    ->disabled(function ($record, callable $get) {
                        // di halaman create, field tidak dipakai
                        if (! $record) return true;

                        $user = auth()->user();

                        // admin/manager/head_marketing atau user yang punya policy khusus: bebas upload
                        if (
                            $user->can('updateStatus', $record)
                            || $user->can('updateAll', $record)
                            || $user->hasAnyRole(['admin','manager','head_marketing'])
                        ) {
                            return false;
                        }

                        // sales / head_sales / head_digital hanya boleh saat delivered
                        if ($user->hasAnyRole(['sales','head_sales','head_digital'])) {
                            return $get('status_order') !== 'delivered';
                        }

                        // default: boleh
                        return false;
                    })
                    ->afterStateUpdated(function ($state, callable $set) {
                        if (!empty($state)) {
                            $set('delivered_at', now());
                            $set('delivered_by', auth()->user()?->employee_id);
                        }
                    }),


                
                Select::make('status_order')
                    ->label('Status Proses Order')
                    ->options([
                        'pending'=>'Pending','confirmed'=>'Confirmed','processing'=>'Processing',
                        'on_hold'=>'On Hold','delivered'=>'Delivered','completed'=>'Completed',
                        'cancelled'=>'Cancelled','rejected'=>'Ditolak'
                    ])
                    ->visible(fn ($record) =>
                        auth()->user()->can('updateStatus', $record ?? new \App\Models\Order)
                        || auth()->user()->hasAnyRole(['admin','manager','head_marketing'])
                    )
                    ->default('pending')
                    ->reactive()   
                    ->searchable(),


               Textarea::make('on_hold_comment')
                    ->label('Alasan di Hold')
                    ->visible(fn ($get) => $get('status_order') === 'on_hold')
                    ->required(fn ($get) => $get('status_order') === 'on_hold')
                    ->helperText('Wajib diisi saat status order On Hold'),

                DatePicker::make('on_hold_until')
                    ->label('Batas On Hold')
                    ->visible(fn ($get) => $get('status_order') === 'on_hold')
                    ->required(fn ($get) => $get('status_order') === 'on_hold')
                    ->minDate(today())
                                        ->disabled(fn ($record) => $record && auth()->user()->hasAnyRole(['sales','head_sales','head_digital'])),


                Textarea::make('cancelled_comment')->label('Komentar Cancelled')
                    ->visible(fn ($get) => $get('status_order') === 'cancelled'),
            
             
                Hidden::make('delivered_by')
                    ->dehydrated(fn ($record) => (bool) $record),

                Hidden::make('delivered_at')
                    ->dehydrated(fn ($record) => (bool) $record),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc') 
            ->columns([
                TextColumn::make('id')->label('ID')->sortable(),
                TextColumn::make('no_order')->label('Order Number')->sortable()->searchable(),
                TextColumn::make('department.name')->label('Department')->searchable()->sortable(),
                TextColumn::make('employee.name')->label('Karyawan')->searchable()->sortable(),
                TextColumn::make('customer.name')->label('Customer')->sortable()->searchable(),
                TextColumn::make('customerCategory.name')->label('Kategori Customer')->sortable()->searchable(),
                TextColumn::make('address')->label('Alamat')->limit(50)->sortable()->searchable(),
                TextColumn::make('phone')->label('Telepon')->sortable(),

                TextColumn::make('products_details')->label('Detail Produk')->html()->searchable()->sortable(),

                TextColumn::make('total_harga')->label('Total Harga')
                    ->formatStateUsing(fn ($state) => 'Rp ' . number_format($state, 0, ',', '.'))
                    ->sortable(),

                TextColumn::make('semua_diskon')->label('Diskon')
                    ->getStateUsing(function ($record) {
                        $arr = collect([
                            $record->diskon_1 ?? 0,
                            $record->diskon_2 ?? 0,
                            $record->diskon_3 ?? 0,
                            $record->diskon_4 ?? 0,
                        ])->filter(fn ($v) => (float) $v > 0)
                          ->map(fn ($v) => rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.') . '%');
                        return $arr->isEmpty() ? '-' : $arr->implode(' + ');
                    })->sortable(),

                TextColumn::make('penjelasan_diskon')->label('Penjelasan Diskon')
                    ->getStateUsing(function ($record) {
                        $arr = collect([
                            trim($record->penjelasan_diskon_1 ?? ''),
                            trim($record->penjelasan_diskon_2 ?? ''),
                            trim($record->penjelasan_diskon_3 ?? ''),
                            trim($record->penjelasan_diskon_4 ?? ''),
                        ])->filter();
                        return $arr->isEmpty() ? '-' : $arr->implode(' + ');
                    })
                    ->wrap()
                    ->extraAttributes(['style' => 'white-space: normal;'])
                    ->sortable(),

                TextColumn::make('customerProgram.name')->label('Program Pelanggan')->searchable()
                    ->getStateUsing(fn ($record) => $record->customerProgram->name ?? '-'),

                TextColumn::make('jumlah_program')->label('Program Point')
                    
                    ->formatStateUsing(fn ($state) => !$state ? '-' : "{$state}"),

                TextColumn::make('reward_point')->label('Reward Point')
                    
                    ->formatStateUsing(fn ($state) => !$state ? '-' : "{$state}"),

                TextColumn::make('total_harga_after_tax')->label('Total Akhir')
                    ->formatStateUsing(fn ($state) => 'Rp ' . number_format($state, 0, ',', '.'))
                    ->sortable(),

                TextColumn::make('payment_method')->label('Metode Pembayaran')->searchable()->sortable(),
                TextColumn::make('payment_due_until')->label('Jatuh Tempo')->searchable()->sortable(),
                
                
                ImageColumn::make('delivery_images')->label('Bukti Pengiriman')->circular()->stacked()->limit(3),

                BadgeColumn::make('status_pembayaran')->label('Status Pembayaran')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'belum bayar' => 'Belum Bayar',
                        'sudah bayar' => 'Sudah Bayar',
                        'belum lunas' => 'Belum lunas',
                        'sudah lunas' => 'Sudah lunas',
                        default => ucfirst($state),
                    })
                    
                    ->colors(['warning' => 'belum bayar','success' => 'sudah bayar'])
                    ->sortable(),

                BadgeColumn::make('status_pengajuan')->label('Status Pengajuan')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'Pending', 'approved' => 'Disetujui', 'rejected' => 'Ditolak',
                        default => ucfirst($state),
                    })
                    
                    ->colors(['warning' => 'pending','success' => 'approved','danger' => 'rejected'])
                    ->sortable(),

                BadgeColumn::make('status_product')->label('Status Produk')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'Pending', 'ready_stock' => 'Ready Stock',
                        'sold_out' => 'Sold Out', 'rejected' => 'Ditolak', default => ucfirst($state),
                    })
                    
                    ->colors(['warning' => 'pending','success' => 'ready_stock','danger' => ['sold_out','rejected']])
                    ->sortable(),

                BadgeColumn::make('status_order')->label('Status Proses Order')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'Pending','confirmed' => 'Confirmed','processing' => 'Processing',
                        'on_hold' => 'On Hold','delivered' => 'Delivered','completed' => 'Completed',
                        'cancelled' => 'Cancelled','rejected' => 'Ditolak', default => ucfirst($state),
                    })
                    
                    ->colors([
                        'warning' => ['pending','on_hold'],
                        'info'    => ['confirmed','processing','delivered'],
                        'success' => 'completed',
                        'danger'  => ['cancelled','rejected'],
                    ])->sortable(),
                TextColumn::make('rejection_comment')->label('Alasan Ditolak')->searchable()->sortable(), // new
                TextColumn::make('cancelled_comment')->label('Alasan di Cancel')->searchable()->sortable(), // new
                TextColumn::make('on_hold_until')->label('Batas Hold')->searchable()->sortable(), // new
                TextColumn::make('on_hold_comment')->label('Alasan di Hold')->searchable()->sortable(), // new


                TextColumn::make('created_at')->label('Dibuat')->dateTime('d M Y H:i')->sortable(),
                TextColumn::make('updated_at')->label('Diupdate')->dateTime('d M Y H:i')->sortable(),
            ])

            ->filters([
                SelectFilter::make('status_pengajuan')
                    ->label('Status Pengajuan')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Disetujui',
                        'rejected' => 'Ditolak',
                    ]),
                SelectFilter::make('status_product')
                    ->label('Status Produk')
                    ->options([
                        'pending' => 'Pending',
                        'ready_stock' => 'Ready Stock',
                        'sold_out' => 'Sold Out',
                        'rejected' => 'Ditolak',
                    ]),
                SelectFilter::make('status_order')
                    ->label('Status Order')
                    ->options([
                        'pending' => 'Pending',
                        'confirmed' => 'Confirmed',
                        'processing' => 'Processing',
                        'on_hold' => 'On Hold',
                        'delivered' => 'Delivered',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                        'rejected' => 'Ditolak',
                    ]),


            ])
            ->headerActions([
                Action::make('export')->label('Export Data Order')
                    ->form([
                        Grid::make(4)->schema([
                            Select::make('department_id')->label('Department')
                                ->options(Department::pluck('name', 'id'))->searchable()->preload(),

                            Select::make('employee_id')->label('Karyawan')
                                ->options(Employee::pluck('name', 'id'))->searchable()->preload(),

                            Select::make('customer_id')->label('Customer')
                                ->options(Customer::pluck('name', 'id'))->searchable()->preload(),

                            Select::make('customer_categories_id')->label('Kategori Customer')
                                ->options(CustomerCategories::pluck('name', 'id'))->searchable()->preload(),

                            Select::make('payment_method')->label('Metode Pembayaran')
                                ->options(['cash' => 'Cash','tempo' => 'Tempo'])->searchable(),

                            Select::make('status_pembayaran')->label('Status Pembayaran')
                                ->options([
                                    'belum bayar' => 'Belum Bayar',
                                    'sudah bayar' => 'Sudah Bayar',
                                ])->searchable(),

                            Select::make('status_pengajuan')->label('Status Pengajuan')
                                ->options([
                                    'pending'  => 'Pending',
                                    'approved' => 'Disetujui',
                                    'rejected' => 'Ditolak',
                                ])->searchable(),

                            Select::make('status_order')->label('Status Order')
                                ->options([
                                    'pending'    => 'Pending',
                                    'confirmed'  => 'Confirmed',
                                    'processing' => 'Processing',
                                    'on_hold'    => 'On Hold',
                                    'delivered'  => 'Delivered',
                                    'completed'  => 'Completed',
                                    'cancelled'  => 'Cancelled',
                                    'rejected'   => 'Ditolak',
                                ])->searchable(),

                            Select::make('status_product')->label('Status Produk')
                                ->options([
                                    'pending'     => 'Pending',
                                    'ready_stock' => 'Ready Stock',
                                    'sold_out'    => 'Sold Out',
                                    'rejected'    => 'Ditolak',
                                ])->searchable(),

                            Select::make('customer_program_id')->label('Program Pelanggan')
                                ->options(CustomerProgram::pluck('name', 'id'))->searchable()->preload(),

                            Select::make('brand_id')->label('Brand')
                                ->options(Brand::pluck('name', 'id'))->searchable(),

                            Select::make('product_id')->label('Produk')
                                ->options(Product::pluck('name', 'id'))->searchable(),

                            Select::make('category_id')->label('Kategori Produk')
                                ->options(Category::pluck('name', 'id'))->searchable(),

                            Select::make('has_diskon')->label('Ada Diskon?')->options([
                                'ya' => 'Ya', 'tidak' => 'Tidak',
                            ])->searchable(),

                            Select::make('has_reward_point')->label('Ada Reward Point?')->options([
                                'ya' => 'Ya', 'tidak' => 'Tidak',
                            ])->searchable(),

                            Select::make('has_program_point')->label('Ada Program Point?')->options([
                                'ya' => 'Ya', 'tidak' => 'Tidak',
                            ])->searchable(),

                            // 🔹 FILTER TANGGAL DIBUAT
                            DatePicker::make('created_from')
                                ->label('Dibuat dari tanggal'),

                            DatePicker::make('created_until')
                                ->label('Sampai tanggal'),

                            Checkbox::make('export_all')->label('Print Semua Data')->reactive(),
                        ]),
                    ])

                    ->action(function (array $data) {
                        $export = new FilteredOrdersExport($data);
                        $rows = $export->array();

                        if (count($rows) <= 2) {
                            \Filament\Notifications\Notification::make()
                                ->title('Data Order Tidak Ditemukan')
                                ->body('Tidak ditemukan data Order produk berdasarkan filter yang Anda pilih. Silakan periksa kembali pilihan filter Anda.')
                                ->danger()->send();
                            return null;
                        }

                        return Excel::download($export, 'export_orders.xlsx');
                    }),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),

                Action::make('downloadInvoice')
                    ->label('Download File PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->visible(fn (Order $record) =>
                        filled($record->order_file) && Storage::disk('public')->exists($record->order_file)
                    )
                    ->action(function (Order $record) {
                        abort_unless(
                            filled($record->order_file) && Storage::disk('public')->exists($record->order_file),
                            404
                        );

                        return Storage::disk('public')->download(
                            $record->order_file,
                            "Order-{$record->no_order}.pdf",
                            ['Content-Type' => 'application/pdf']
                        );
                    }),

                Action::make('downloadExcel')
                    ->label('Download File Excel')
                    ->icon('heroicon-o-document-arrow-down')
                    ->visible(fn (Order $record) =>
                        filled($record->order_excel) && Storage::disk('public')->exists($record->order_excel)
                    )
                    ->action(function (Order $record) {
                        abort_unless(
                            filled($record->order_excel) && Storage::disk('public')->exists($record->order_excel),
                            404
                        );

                        return Storage::disk('public')->download(
                            $record->order_excel,
                            "Order-{$record->no_order}.xlsx",
                            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
                        );
                    }),
            ])
            ->bulkActions([
                DeleteBulkAction::make(),
            ])
            ->emptyStateActions([
                CreateAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            'edit'   => Pages\EditOrder::route('/{record}/edit'),
        ];
    }

    public static function mutateFormDataBeforeSave(array $data): array
    {
        $data['products'] = collect($data['products'] ?? [])->map(function ($item) {
            $priceRaw    = $item['price'] ?? 0;
            $subtotalRaw = $item['subtotal'] ?? 0;
            $price    = is_string($priceRaw) ? (int) str_replace('.', '', $priceRaw) : (int) $priceRaw;
            $subtotal = is_string($subtotalRaw) ? (int) str_replace('.', '', $subtotalRaw) : (int) $subtotalRaw;
            $item['price']    = $price;
            $item['subtotal'] = $subtotal;
            return $item;
        })->toArray();

        foreach (['diskon_1','diskon_2','diskon_3','diskon_4'] as $k) {
            $v = $data[$k] ?? 0;
            $v = is_string($v) ? floatval(str_replace(',', '.', $v)) : floatval($v);
            $data[$k] = max(0, min(100, $v));
        }

        return $data;
    }
}