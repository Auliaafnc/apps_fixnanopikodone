<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\ProductReturnResource\Pages;
use App\Models\ProductReturn;
use App\Models\Product;
use App\Models\Brand;
use App\Models\Department;
use App\Models\Employee;
use App\Models\CustomerCategories;
use App\Models\Customer;
use App\Models\Category;
use Filament\Forms\Form;
use Filament\Forms\Components\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Facades\Excel;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Grid;
use Illuminate\Support\Facades\Storage;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use App\Exports\FilteredReturnExport;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\CreateAction;
use Filament\Forms\Components\Checkbox;

class ProductReturnResource extends Resource
{
    protected static ?string $model = ProductReturn::class;
    protected static ?string $navigationGroup = 'Sales Management';
    protected static ?int    $navigationSort  = 3;
    protected static ?string $recordTitleAttribute = 'no_return';

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
                        } else {
                            $set('phone', null);
                            $set('address', null);
                        }
                    })
                    ->disabled(fn ($record) => $record && auth()->user()->hasAnyRole(['sales','head_sales','head_digital']))
                    ->required()->preload()->searchable()->placeholder('Pilih Customer'),

                TextInput::make('phone')->label('Phone')->reactive()->required()
                                        ->disabled(fn ($record) => $record && auth()->user()->hasAnyRole(['sales','head_sales','head_digital'])),
                                        
                Textarea::make('address')
                    ->label('Alamat')
                    ->rows(3)
                    ->required()
                    ->formatStateUsing(function ($state, ?ProductReturn $record) {
                        // tampilkan versi yang sudah rapi
                        return $record?->address_text ?? (is_string($state) ? $state : '');
                    })
                    ->dehydrateStateUsing(fn ($state) => $state) // simpan apa adanya (string)
                    ->disabled(fn ($record) => $record && auth()->user()->hasAnyRole(['sales','head_sales','head_digital'])),
                
                TextInput::make('amount')->label('Nominal')->numeric()->prefix('Rp')->rules(['min:1'])->required()
                                          ->disabled(fn ($record) => $record && auth()->user()->hasAnyRole(['sales','head_sales','head_digital'])),

                Textarea::make('reason')->label('Alasan Return')->required()
                                        ->disabled(fn ($record) => $record && auth()->user()->hasAnyRole(['sales','head_sales','head_digital'])),

                FileUpload::make('image')
                    ->label('Foto Barasg')
                    ->multiple()
                    ->image()
                    ->panelLayout('grid')
                    ->downloadable()
                    ->openable()
                    ->disk('public') 
                    ->directory('return-photos')
                    ->maxFiles(5)
                    ->disabled(fn ($record) => $record && auth()->user()->hasAnyRole(['sales','head_sales','head_digital'])),  
                    
                Textarea::make('note')->label('Catatan Tambahan')->nullable()
                                        ->disabled(fn ($record) => $record && auth()->user()->hasAnyRole(['sales','head_sales','head_digital'])),

                // ===================== DETAIL PRODUK =====================
                Repeater::make('products')
                    ->label('Detail Produk')
                    ->reactive()
                    ->schema([
                        // 1) BRAND — tidak mereset field lain
                        Select::make('brand_id')
                            ->label('Brand')
                            ->options(fn () => Brand::pluck('name', 'id'))
                            ->reactive()
                            ->required()
                            ->searchable(),

                        // 2) KATEGORI — filter by brand kalau ada, jika tidak, tampilkan semua
                        Select::make('kategori_id')
                            ->label('Kategori')
                            ->options(function (callable $get) {
                                $brandId = $get('brand_id');
                                return $brandId
                                    ? Category::where('brand_id', $brandId)->pluck('name', 'id')
                                    : Category::pluck('name', 'id');
                            })
                            ->reactive()
                            ->required()
                            ->searchable(),

                        // 3) PRODUK — sinkronkan brand & kategori saat hydrate / update
                        Select::make('produk_id')
                            ->label('Produk')
                            ->options(function (callable $get) {
                                $kategoriId = $get('kategori_id');
                                return $kategoriId
                                    ? Product::where('category_id', $kategoriId)->pluck('name', 'id')
                                    : Product::pluck('name', 'id');
                            })
                            ->reactive()
                            ->afterStateHydrated(function ($state, callable $set, callable $get) {
                                if (!$state) return;
                                $p = Product::find($state);
                                if (!$p) return;

                                // sinkron brand & kategori
                                $set('brand_id', $p->brand_id);
                                $set('kategori_id', $p->category_id);

                                // jika warna masih angka (data lama), konversi ke label
                                $warna = $get('warna_id');
                                if ($warna !== null && $warna !== '' && is_numeric($warna)) {
                                    $colors = $p->colors ?? [];
                                    $idx = (int) $warna;
                                    if (isset($colors[$idx])) {
                                        $set('warna_id', $colors[$idx]);
                                    }
                                }
                            })
                            ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                $p = Product::find($state);
                                if (!$p) return;

                                // sinkron brand & kategori
                                $set('brand_id', $p->brand_id);
                                $set('kategori_id', $p->category_id);

                                // validasi warna sekarang
                                $current = $get('warna_id');
                                $colorsMap = collect($p->colors ?? [])->mapWithKeys(fn($c) => [$c => $c])->toArray();
                                if ($current && !array_key_exists($current, $colorsMap)) {
                                    $set('warna_id', null);
                                }
                            })
                            ->required()
                            ->searchable()
                            ->extraAttributes([
                                'class' => 'whitespace-normal',
                                'style' => 'white-space: normal; word-break: break-word; max-width: 280px;',
                            ]),

                        // 4) WARNA — pakai label string (bukan index)
                        Select::make('warna_id')
                            ->label('Warna')
                            ->options(function (callable $get) {
                                $pid = $get('produk_id');
                                if (!$pid) return [];
                                $colors = Product::find($pid)?->colors ?? [];
                                // jadikan ["3000K" => "3000K", ...]
                                return collect($colors)->mapWithKeys(fn($c) => [$c => $c])->toArray();
                            })
                            ->reactive()
                            ->required()
                            ->searchable(),

                        // 5) JUMLAH
                        TextInput::make('quantity')
                            ->label('Jumlah')
                            ->numeric()
                            ->prefix('Qty')
                            ->required(),
                    ])
                    ->columns(3)
                    ->minItems(1)
                    ->defaultItems(1)
                    ->createItemButtonLabel('Tambah Produk')
                    ->disabled(fn ($record) => $record && auth()->user()->hasAnyRole(['sales','head_sales','head_digital']))
                    ->required(),

                // === status ===
                // === status ===
                Select::make('status_pengajuan')
                    ->label('Status Pengajuan Return')
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
                            $set('status_return', 'rejected');
                        }
                    })
                   ->visible(fn ($record) =>
                        auth()->user()->can('updateStatus', $record ?? new \App\Models\ProductReturn)
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
                        auth()->user()->can('updateStatus', $record ?? new \App\Models\ProductReturn)
                        || auth()->user()->hasAnyRole(['admin','manager','head_marketing'])
                    )
                    ->default('pending')
                    ->reactive()
                    ->searchable(),


                
                
                Select::make('status_return')
                    ->label('Status Proses Return')
                    ->options([
                        'pending'=>'Pending','confirmed'=>'Confirmed','processing'=>'Processing',
                        'on_hold'=>'On Hold','delivered'=>'Delivered','completed'=>'Completed',
                        'cancelled'=>'Cancelled','rejected'=>'Ditolak'
                    ])
                    ->visible(fn ($record) =>
                        auth()->user()->can('updateStatus', $record ?? new \App\Models\ProductReturn)
                        || auth()->user()->hasAnyRole(['admin','manager','head_marketing'])
                    )
                    ->default('pending')
                    ->reactive()   
                    ->searchable(),


                Textarea::make('on_hold_comment')
                    ->label('Alasan di Hold')
                    ->visible(fn ($get) => $get('status_return') === 'on_hold')
                    ->required(fn ($get) => $get('status_return') === 'on_hold')
                    ->helperText('Wajib diisi saat status return On Hold'),

                DatePicker::make('on_hold_until')
                    ->label('Batas On Hold')
                    ->visible(fn ($get) => $get('status_return') === 'on_hold')
                    ->required(fn ($get) => $get('status_return') === 'on_hold')
                    ->minDate(today())
                                        ->disabled(fn ($record) => $record && auth()->user()->hasAnyRole(['sales','head_sales','head_digital'])),


                Textarea::make('cancelled_comment')->label('Komentar Cancelled')
                    ->visible(fn ($get) => $get('status_return') === 'cancelled'),
            
             
                Hidden::make('delivered_by')
                    ->dehydrated(fn ($record) => (bool) $record),

                Hidden::make('delivered_at')
                    ->dehydrated(fn ($record) => (bool) $record),
                
                FileUpload::make('delivery_images')
                    ->label('Bukti Pengiriman')
                    ->multiple()
                    ->image()
                    ->panelLayout('grid')
                    ->downloadable()
                    ->openable()
                    ->disk('public')
                    ->directory('return-delivery-photos')
                    ->maxFiles(5)
                    ->maxSize(102400)
                    ->dehydrated(true)
                    // hanya tampil saat edit (record sudah ada)
                    ->visible(fn ($record) => (bool) $record)
                    // tips untuk role sales jika belum delivered
                    ->helperText(fn ($record, $get) =>
                        auth()->user()->hasAnyRole(['sales','head_sales','head_digital'])
                        && $record
                        && $get('status_return') !== 'delivered'
                            ? 'Bukti pengiriman hanya bisa diunggah setelah status return = Delivered.'
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
                            return $get('status_return') !== 'delivered';
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


            ]);
                
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('id')->label('ID')->sortable(),
                TextColumn::make('no_return')->label('Return Number')->sortable()->searchable(),

                TextColumn::make('department.name')->label('Department')->searchable()->sortable(),
                TextColumn::make('employee.name')->label('Karyawan')->searchable()->sortable(),
                TextColumn::make('customer.name')->label('Customer')->sortable()->searchable(),
                TextColumn::make('category.name')->label('Kategori Customer')->sortable()->searchable(),

                TextColumn::make('phone')->label('Phone')->sortable(),

                TextColumn::make('address_text')
                    ->label('Alamat')
                    ->default('-')
                    ->extraAttributes(['class' => 'whitespace-nowrap max-w-xs truncate'])
                    ->tooltip(fn (ProductReturn $record) => $record->address_text)
                    ->sortable()
                    ->searchable(),
                

                TextColumn::make('products_details')->label('Detail Produk')->html()->sortable(),

                TextColumn::make('amount')->label('Nominal (Rp)')
                    ->formatStateUsing(fn ($state) => 'Rp ' . number_format($state, 0, ',', '.'))
                    ->sortable(),

                TextColumn::make('reason')->label('Alasan Return')->toggleable()->limit(500),

                TextColumn::make('note')->label('Catatan Tambahan')
                    ->getStateUsing(fn ($record) => ($note = trim((string) $record->note ?? '')) !== '' ? $note : '-')
                    ->wrap()->extraAttributes(['style' => 'white-space: normal;']),

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

            BadgeColumn::make('status_return')->label('Status Return')
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
                TextColumn::make('on_hold_until')->label('Batas Hold')->searchable()->sortable(), // new
                TextColumn::make('on_hold_comment')->label('Alasan di Hold')->searchable()->sortable(), // new,
                TextColumn::make('rejection_comment')->label('Alasan Penolakan'),
                TextColumn::make('cancelled_comment')->label('Alasan Cancel'),
                TextColumn::make('created_at')->label('Dibuat Pada')->dateTime('d M Y H:i')->sortable(),
                TextColumn::make('updated_at')->label('Diupdate')->dateTime('d M Y H:i')->sortable(),
            ])
            ->headerActions([
                Action::make('export')->label('Export Data Return')
                    ->form([
                        Grid::make(4)->schema([
                            Select::make('department_id')->label('Department')
                                ->options(Department::pluck('name', 'id'))->searchable()->preload(),
                            Select::make('employee_id')->label('Sales')
                                ->options(Employee::pluck('name', 'id'))->searchable()->preload(),
                            Select::make('customer_id')->label('Customer')
                                ->options(Customer::pluck('name', 'id'))->searchable()->preload(),
                            Select::make('customer_categories_id')->label('Kategori Customer')
                                ->options(CustomerCategories::pluck('name', 'id'))->searchable()->preload(),
                            Select::make('status')->label('Status')
                                ->options(['pending' => 'Pending','approved' => 'Disetujui','rejected' => 'Ditolak'])
                                ->searchable(),
                            Select::make('brand_id')->label('Brand')->searchable()->options(Brand::pluck('name', 'id')),
                            Select::make('category_id')->label('Kategori Produk')->searchable()->options(Category::pluck('name', 'id')),
                            Select::make('product_id')->label('Produk')->searchable()->options(Product::pluck('name', 'id')),
                            Checkbox::make('export_all')->label('Print Semua Data')->reactive(),
                        ])
                    ])
                    ->action(function (array $data) {
                        $export = new FilteredReturnExport($data);
                        $rows = $export->array();

                        if (count($rows) <= 2) {
                            \Filament\Notifications\Notification::make()
                                ->title('Data Return Tidak Ditemukan')
                                ->body('Tidak ditemukan data Return produk berdasarkan filter yang Anda pilih. Silakan periksa kembali pilihan filter Anda.')
                                ->danger()->send();
                            return null;
                        }

                        return Excel::download($export, 'export_return.xlsx');
                    })
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),

                Action::make('downloadInvoice')
                    ->label('Download File PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->url(fn (ProductReturn $record) => Storage::url($record->return_file))
                    ->openUrlInNewTab()
                    ->visible(fn (ProductReturn $record) => ! empty($record->return_file)),

                Action::make('downloadExcel')
                    ->label('Download File Excel')
                    ->icon('heroicon-o-document-arrow-down')
                    ->url(fn (ProductReturn $record) => Storage::url($record->return_excel))
                    ->openUrlInNewTab()
                    ->visible(fn (ProductReturn $record) => ! empty($record->return_excel)),
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
            'index'  => Pages\ListProductReturns::route('/'),
            'create' => Pages\CreateProductReturn::route('/create'),
            'edit'   => Pages\EditProductReturn::route('/{record}/edit'),
        ];
    }
}