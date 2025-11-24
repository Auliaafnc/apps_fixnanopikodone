<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\GaransiResource\Pages;
use App\Models\Garansi;
use App\Models\Employee;
use App\Models\Customer;
use App\Models\Brand;
use App\Models\Department;
use App\Models\CustomerCategories;
use App\Models\Category;
use App\Models\Product;
use Filament\Forms\Form;
use Filament\Forms\Components\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Filters\SelectFilter;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Repeater;
use Filament\Resources\Resource;
use Illuminate\Support\Facades\Storage;
use Filament\Forms\Components\FileUpload;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Table;
use Filament\Forms\Components\Grid;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\CreateAction;
use App\Exports\FilteredGaransiExport;
use Filament\Forms\Components\Checkbox;
use Maatwebsite\Excel\Facades\Excel;

class GaransiResource extends Resource
{
    protected static ?string $model = Garansi::class;
    protected static ?string $navigationGroup = 'Sales Management';
    protected static ?int    $navigationSort  = 5;
    protected static ?string $recordTitleAttribute = 'no_garansi';

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
                // === relasi ===
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
                    ->formatStateUsing(function ($state, ?Garansi $record) {
                        // tampilkan address_text yang sudah diformat rapi
                        return $record?->address_text ?? (is_string($state) ? $state : '');
                    })
                    ->dehydrateStateUsing(fn ($state) => $state) // simpan apa adanya sebagai string
                    ->disabled(fn ($record) => $record && auth()->user()->hasAnyRole(['sales','head_sales','head_digital'])),
                

                DatePicker::make('purchase_date')->label('Tanggal Pembelian')->required()->disabled(fn ($record) => $record && auth()->user()->hasAnyRole(['sales','head_sales','head_digital'])),    
                DatePicker::make('claim_date')->label('Tanggal Klaim Garansi')->required()->disabled(fn ($record) => $record && auth()->user()->hasAnyRole(['sales','head_sales','head_digital'])),    

                Textarea::make('reason')->label('Alasan Pengajuan Garansi')->required()->disabled(fn ($record) => $record && auth()->user()->hasAnyRole(['sales','head_sales','head_digital'])),    
                Textarea::make('note')->label('Catatan Tambahan')->nullable()->disabled(fn ($record) => $record && auth()->user()->hasAnyRole(['sales','head_sales','head_digital'])),    

                // === produk ===
                Repeater::make('products')
                ->label('Detail Produk')
                ->schema([
                    // BRAND
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

                    // KATEGORI (filter berdasarkan brand)
                    Select::make('kategori_id')
                        ->label('Kategori')
                        ->options(function (callable $get) {
                            $brandId = $get('brand_id');
                            if (! $brandId) {
                                return [];
                            }

                            // ambil kategori yg dipakai oleh product dengan brand tsb
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

                    // PRODUK (filter by brand + kategori)
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
                        ->afterStateUpdated(fn ($state, callable $set) => $set('warna_id', null))
                        ->required()
                        ->searchable(),

                    // WARNA (sudah pakai colors di product)
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

                    TextInput::make('quantity')
                        ->label('Jumlah')
                        ->numeric()
                        ->required(),
                ])
                ->columns(3)
                ->minItems(1)
                ->defaultItems(1)
                ->createItemButtonLabel('Tambah Produk')
                ->disabled(fn ($record) => $record && auth()->user()->hasAnyRole(['sales','head_sales','head_digital']))
                ->required(),

                // === upload foto ===
                FileUpload::make('image')
                    ->label('Foto Barang')
                    ->multiple()
                    ->image()
                    ->panelLayout('grid')
                    ->downloadable()
                    ->openable()
                    ->disk('public') 
                    ->directory('garansi-photos')
                    ->maxFiles(5)
                    ->disabled(fn ($record) => $record && auth()->user()->hasAnyRole(['sales','head_sales','head_digital'])),
    
                // === status ===
                Select::make('status_pengajuan')
                    ->label('Status Pengajuan Garansi')
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
                            $set('status_garansi', 'rejected');
                        }
                    })
                   ->visible(fn ($record) =>
                        auth()->user()->can('updateStatus', $record ?? new \App\Models\Garansi)
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
                        auth()->user()->can('updateStatus', $record ?? new \App\Models\Garansi)
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
                    ->directory('garansi-delivery-photos')
                    ->maxFiles(5)
                    ->maxSize(102400)
                    ->dehydrated(true)
                    // hanya tampil saat edit (record sudah ada)
                    ->visible(fn ($record) => (bool) $record)
                    // tips untuk role sales jika belum delivered
                    ->helperText(fn ($record, $get) =>
                        auth()->user()->hasAnyRole(['sales','head_sales','head_digital'])
                        && $record
                        && $get('status_garansi') !== 'delivered'
                            ? 'Bukti pengiriman hanya bisa diunggah setelah status garansi = Delivered.'
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
                            return $get('status_garansi') !== 'delivered';
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

                
                Select::make('status_garansi')
                    ->label('Status Proses Garansi')
                    ->options([
                        'pending'=>'Pending','confirmed'=>'Confirmed','processing'=>'Processing',
                        'on_hold'=>'On Hold','delivered'=>'Delivered','completed'=>'Completed',
                        'cancelled'=>'Cancelled','rejected'=>'Ditolak'
                    ])
                    ->visible(fn ($record) =>
                        auth()->user()->can('updateStatus', $record ?? new \App\Models\Garansi)
                        || auth()->user()->hasAnyRole(['admin','manager','head_marketing'])
                    )
                    ->default('pending')
                    ->reactive()   
                    ->searchable(),


                Textarea::make('on_hold_comment')
                    ->label('Alasan di Hold')
                    ->visible(fn ($get) => $get('status_garansi') === 'on_hold')
                    ->required(fn ($get) => $get('status_garansi') === 'on_hold')
                    ->helperText('Wajib diisi saat status garansi On Hold'),

                DatePicker::make('on_hold_until')
                    ->label('Batas On Hold')
                    ->visible(fn ($get) => $get('status_garansi') === 'on_hold')
                    ->required(fn ($get) => $get('status_garansi') === 'on_hold')
                    ->minDate(today())
                                        ->disabled(fn ($record) => $record && auth()->user()->hasAnyRole(['sales','head_sales','head_digital'])),


                Textarea::make('cancelled_comment')->label('Komentar Cancelled')
                    ->visible(fn ($get) => $get('status_garansi') === 'cancelled'),
            
             
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
                TextColumn::make('no_garansi')->label('Garansi Number')->sortable()->searchable(),
                TextColumn::make('department.name')->label('Department')->searchable()->sortable(),
                TextColumn::make('employee.name')->label('Karyawan')->searchable()->sortable(),
                TextColumn::make('customer.name')->label('Customer')->sortable()->searchable(),
                TextColumn::make('customerCategory.name')->label('Kategori Customer')->sortable()->searchable(),
                TextColumn::make('phone')->label('Phone')->sortable(),
                TextColumn::make('address_text')
                    ->label('Alamat')
                    ->default('-')
                    ->extraAttributes([
                        'class' => 'whitespace-nowrap max-w-xs truncate', // 1 baris, kalau kepanjangan di-ellipsis
                    ])
                    ->tooltip(fn (Garansi $record) => $record->address_text) // hover: lihat alamat full
                    ->sortable(),

                TextColumn::make('products_details')->label('Detail Produk')->html()->sortable(),
                TextColumn::make('purchase_date')->label('Tanggal Pembelian')->date()->sortable(),
                TextColumn::make('claim_date')->label('Tanggal Klaim')->date()->sortable(),
                TextColumn::make('reason')->label('Alasan Klaim Garansi')->toggleable()->limit(500),
                TextColumn::make('note')->label('Catatan Tambahan')->default('-')->wrap(),

                ImageColumn::make('image')->label('Foto Barang')->stacked()->circular()->limit(3),
                ImageColumn::make('delivery_images')->label('Bukti Pengiriman')->circular()->stacked()->limit(3),

                // ===== BADGE STATUS BARU =====
            BadgeColumn::make('status_pengajuan')->label('Status Pengajuan')
                ->formatStateUsing(fn (string $state): string => match ($state) {
                    'pending'  => 'Pending',
                    'approved' => 'Disetujui',
                    'rejected' => 'Ditolak',
                    default    => ucfirst($state),
                })
                ->colors([
                    'warning' => 'pending',
                    'success' => 'approved',
                    'danger'  => 'rejected',
                ])
                ->sortable(),

            BadgeColumn::make('status_product')->label('Status Produk')
                ->formatStateUsing(fn (string $state): string => match ($state) {
                    'pending'     => 'Pending',
                    'ready_stock' => 'Ready Stock',
                    'sold_out'    => 'Sold Out',
                    'rejected'    => 'Ditolak',
                    default       => ucfirst($state),
                })
                ->colors([
                    'warning' => 'pending',
                    'success' => 'ready_stock',
                    'danger'  => ['sold_out', 'rejected'],
                ])
                ->sortable(),

            BadgeColumn::make('status_garansi')->label('Status Garansi')
                ->formatStateUsing(fn (string $state): string => match ($state) {
                    'pending'    => 'Pending',
                    'confirmed'  => 'Confirmed',
                    'processing' => 'Processing',
                    'on_hold'    => 'On Hold',
                    'delivered'  => 'Delivered',
                    'completed'  => 'Completed',
                    'cancelled'  => 'Cancelled',
                    'rejected'   => 'Ditolak',
                    default      => ucfirst($state),
                })
                ->colors([
                    'warning' => ['pending','on_hold'],
                    'info'    => ['confirmed','processing','delivered'],
                    'success' => 'completed',
                    'danger'  => ['cancelled','rejected'],
                ])
                ->sortable(),
                TextColumn::make('rejection_comment')->label('Komentar Ditolak')->toggleable()->limit(50),
                TextColumn::make('on_hold_until')->label('Batas Hold')->searchable()->sortable(), // new
                TextColumn::make('on_hold_comment')->label('Alasan di Hold')->searchable()->sortable(), // new
                TextColumn::make('created_at')->label('Dibuat Pada')->dateTime('d M Y H:i')->sortable(),
                TextColumn::make('updated_at')->label('Diupdate')->dateTime('d M Y H:i')->sortable(),
            ])
            ->headerActions([
                Action::make('export')
                    ->label('Export Data Garansi')
                    ->form([
                        Grid::make(4)->schema([
                            Select::make('department_id')->label('Department')->options(Department::pluck('name','id'))->searchable()->preload(),
                            Select::make('employee_id')->label('Karyawan')->options(Employee::pluck('name','id'))->searchable()->preload(),
                            Select::make('customer_id')->label('Customer')->options(Customer::pluck('name','id'))->searchable()->preload(),
                            Select::make('customer_categories_id')->label('Kategori Customer')->options(CustomerCategories::pluck('name','id'))->searchable()->preload(),
                            Select::make('status_pengajuan')->label('Status Pengajuan')->options(['pending'=>'Pending','approved'=>'Disetujui','rejected'=>'Ditolak']),
                            Select::make('status_product')->label('Status Produk')->options(['pending'=>'Pending','ready_stock'=>'Ready Stock','sold_out'=>'Sold Out','rejected'=>'Ditolak']),
                            Select::make('status_garansi')->label('Status Garansi')->options([
                                'pending'=>'Pending','confirmed'=>'Confirmed','processing'=>'Processing',
                                'on_hold'=>'On Hold','delivered'=>'Delivered','completed'=>'Completed','cancelled'=>'Cancelled','rejected'=>'Ditolak'
                            ]),
                            Select::make('brand_id')->label('Brand')->searchable()->options(Brand::pluck('name','id')),
                            Select::make('category_id')->label('Kategori Produk')->searchable()->options(Category::pluck('name','id')),
                            Select::make('product_id')->label('Produk')->searchable()->options(Product::pluck('name','id')),
                            Checkbox::make('export_all')->label('Print Semua Data')->reactive(),
                        ])
                    ])
                    ->action(function (array $data) {
                        $export = new FilteredGaransiExport($data);
                        $rows = $export->array();

                        if (count($rows) <= 2) {
                            \Filament\Notifications\Notification::make()
                                ->title('Data Garansi Tidak Ditemukan')
                                ->body('Tidak ditemukan data Garansi produk berdasarkan filter.')
                                ->danger()->send();
                            return null;
                        }

                        return Excel::download($export, 'export_garansi.xlsx');
                    })
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
                Action::make('downloadInvoice')
                    ->label('Download File PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->url(fn (Garansi $record) => Storage::url($record->garansi_file))
                    ->openUrlInNewTab()
                    ->visible(fn (Garansi $record) => ! empty($record->garansi_file)),
                Action::make('downloadExcel')
                    ->label('Download File Excel')
                    ->icon('heroicon-o-document-arrow-down')
                    ->url(fn (Garansi $record) => Storage::url($record->garansi_excel))
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([DeleteBulkAction::make()])
            ->emptyStateActions([CreateAction::make()]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListGaransis::route('/'),
            'create' => Pages\CreateGaransi::route('/create'),
            'edit'   => Pages\EditGaransi::route('/{record}/edit'),
        ];
    }
}