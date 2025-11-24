<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\PerbaikandataResource\Pages;
use App\Filament\Admin\Resources\PerbaikandataResource\RelationManagers;
use App\Models\Perbaikandata;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Models\Garansi;
use App\Models\Employee;
use App\Models\Customer;
use App\Models\Department;
use App\Models\CustomerCategories;
use Filament\Forms\Components\Hidden;
use Filament\Tables\Filters\SelectFilter;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Repeater;
use Illuminate\Support\Facades\Storage;
use Filament\Forms\Components\FileUpload;
use Filament\Tables\Columns\ImageColumn;
use Filament\Forms\Components\Grid;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\CreateAction;
use App\Exports\FilteredPerbaikandataExport;
use Filament\Forms\Components\Checkbox;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\PostalCode;
use Illuminate\Support\Facades\Auth;
use Laravolt\Indonesia\Models\Provinsi;
use Laravolt\Indonesia\Models\Kabupaten;
use Laravolt\Indonesia\Models\Kecamatan;
use Laravolt\Indonesia\Models\Kelurahan;
use Illuminate\Support\Str;
use Filament\Tables\Actions\ExportAction;
use App\Exports\PerbaikandataExport;

class PerbaikandataResource extends Resource
{
    protected static ?string $model = Perbaikandata::class;

    protected static ?string $navigationGroup = 'Client Management';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 4;
     protected static ?string $navigationLabel = 'Perbaikan Data';
    protected static ?string $modelLabel       = 'Perbaikan Data';

    public static function getApiTransformer()
    {
        return CustomerTransformer::class;
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
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
                    ->disabled(fn ($record) => $record && auth()->user()->hasAnyRole(['sales','head_sales','head_digital']))   
                    ->required()->preload()->searchable()->placeholder('Pilih Customer'),

                TextInput::make('pilihan_data')
                    ->label('Pilihan Data')
                    ->required()
                    ->maxLength(255),
                
                Textarea::make('data_baru')
                    ->label('Data Baru')
                    ->rows(3)
                    ->required()
                    ->nullable()
                    ->maxLength(65535),
                
                Repeater::make('address')
                    ->label('Alamat')
                    ->schema([
                        Select::make('provinsi')
                            ->label('Provinsi')
                            ->options(fn () => Provinsi::pluck('name', 'code')->toArray())
                            ->searchable()
                            ->reactive()
                            ->afterStateHydrated(function ($state, callable $set) {
                                // Kalau data lama bentuknya array, ambil kode-nya
                                if (is_array($state)) {
                                    $set('provinsi', $state['code'] ?? ($state['id'] ?? null));
                                }
                            })
                            ->afterStateUpdated(fn (callable $set) => $set('kota_kab', null)),
                
                        Select::make('kota_kab')
                            ->label('Kota/Kabupaten')
                            ->options(function (callable $get) {
                                if ($prov = $get('provinsi')) {
                                    return Kabupaten::where('province_code', $prov)->pluck('name', 'code')->toArray();
                                }
                                return [];
                            })
                            ->searchable()
                            ->reactive()
                            ->afterStateHydrated(function ($state, callable $set) {
                                if (is_array($state)) {
                                    $set('kota_kab', $state['code'] ?? ($state['id'] ?? null));
                                }
                            })
                            ->afterStateUpdated(fn (callable $set) => $set('kecamatan', null)),
                
                        Select::make('kecamatan')
                            ->label('Kecamatan')
                            ->options(function (callable $get) {
                                if ($kab = $get('kota_kab')) {
                                    return Kecamatan::where('city_code', $kab)->pluck('name', 'code')->toArray();
                                }
                                return [];
                            })
                            ->searchable()
                            ->reactive()
                            ->afterStateHydrated(function ($state, callable $set) {
                                if (is_array($state)) {
                                    $set('kecamatan', $state['code'] ?? ($state['id'] ?? null));
                                }
                            })
                            ->afterStateUpdated(fn (callable $set) => $set('kelurahan', null)),
                
                        Select::make('kelurahan')
                            ->label('Kelurahan')
                            ->options(function (callable $get) {
                                if ($kec = $get('kecamatan')) {
                                    return Kelurahan::where('district_code', $kec)->pluck('name', 'code')->toArray();
                                }
                                return [];
                            })
                            ->searchable()
                            ->reactive()
                            ->afterStateHydrated(function ($state, callable $set) {
                                if (is_array($state)) {
                                    $set('kelurahan', $state['code'] ?? ($state['id'] ?? null));
                                }
                            })
                            ->afterStateUpdated(function (callable $set, $state) {
                                $postal = \App\Models\PostalCode::where('village_code', $state)->first();
                                $set('kode_pos', $postal?->postal_code ?? null);
                            }),
                
                        TextInput::make('kode_pos')->label('Kode Pos')->readOnly(),
                        Textarea::make('detail_alamat')->label('Detail Alamat')->rows(3)->nullable(),
                    ])
                    ->columns(3)
                    ->defaultItems(1)
                    ->disableItemCreation()
                    ->disableItemDeletion()
                    ->dehydrated(),                

            FileUpload::make('image')
                ->label('Gambar')
                ->image()
                ->multiple()
                ->directory('customers')
                ->downloadable()
                ->openable()
                ->reorderable()
                ->panelLayout('grid')
                ->nullable(),

            Select::make('status_pengajuan')
                ->label('Status Pengajuan')
                ->options([
                    'pending' => 'Pending',
                    'approved' => 'Disetujui',
                    'rejected' => 'Ditolak',
                ])
                ->default('pending')->visibleOn('edit')->searchable()->required(),
      ]);
    }
    
    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('id')->label('ID')->sortable(),
                TextColumn::make('department.name')->label('Department')->searchable()->sortable(),
                TextColumn::make('employee.name')->label('Karyawan')->searchable()->sortable(),
                TextColumn::make('customerCategory.name')->label('Kategori Customer')->sortable()->searchable(),
                TextColumn::make('customer.name')->label('Customer')->searchable()->sortable(),
                TextColumn::make('pilihan_data')->label('Pilihan Data')->searchable()->sortable(),
                TextColumn::make('data_baru')->label('Data Baru')->searchable()->sortable(),
                TextColumn::make('full_address')->label('Alamat')->toggleable()->limit(50),
                ImageColumn::make('image')
                    ->label('Gambar')
                    ->getStateUsing(function ($record) {
                        $val = $record->image;

                        // Kalau datanya berupa JSON string → ambil index pertama
                        if (is_string($val) && str_starts_with($val, '[')) {
                            $decoded = json_decode($val, true);
                            $val = is_array($decoded) ? ($decoded[0] ?? null) : $val;
                        }
                        // Kalau array → ambil index pertama
                        if (is_array($val)) {
                            $val = $val[0] ?? null;
                        }
                        if (blank($val)) {
                            return null;
                        }

                        // Hilangkan prefix storage/ biar konsisten
                        $val = preg_replace('#^/?storage/#', '', $val);

                        // Kalau sudah URL langsung, pakai saja
                        if (preg_match('#^https?://#', $val)) {
                            return $val;
                        }

                        // Default → ambil dari storage public
                        return asset('storage/' . ltrim($val, '/'));
                    })
                    ->disk('public')
                    ->circular(), 
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

                TextColumn::make('created_at')->label('Diajukan')->dateTime('d M Y H:i')->sortable(),
                TextColumn::make('updated_at')->label('Diupdate')->dateTime('d M Y H:i')->sortable(),
            ])
            ->headerActions([
                Action::make('export')
                    ->label('Export Perbaikan Data Customer')
                    ->form([
                        Grid::make(4)->schema([
                            Select::make('department_id')->label('Department')->options(Department::pluck('name','id'))->searchable()->preload(),
                            Select::make('employee_id')->label('Karyawan')->options(Employee::pluck('name','id'))->searchable()->preload(),
                            Select::make('customer_id')->label('Customer')->options(Customer::pluck('name','id'))->searchable()->preload(),
                            Select::make('customer_categories_id')->label('Kategori Customer')->options(CustomerCategories::pluck('name','id'))->searchable()->preload(),
                            Select::make('status_pengajuan')->label('Status Pengajuan')->options(['pending'=>'Pending','approved'=>'Disetujui','rejected'=>'Ditolak']),
                            Checkbox::make('export_all')->label('Print Semua Data')->reactive(),
                        ])
                    ])
                    ->action(function (array $data) {
                        $export = new FilteredPerbaikandataExport($data);
                        $rows = $export->array();

                        if (count($rows) <= 2) {
                            \Filament\Notifications\Notification::make()
                                ->title('Perbaikan Data Baru Tidak Ditemukan')
                                ->body('Tidak ditemukan perbaikan data  berdasarkan filter.')
                                ->danger()->send();
                            return null;
                        }

                        return Excel::download($export, 'export_perbaikandata.xlsx');
                    })
            ])
            ->filters([
                SelectFilter::make('status_pengajuan')
                    ->label('Status Pengajuan')
                    ->options([
                        'pending'  => 'Pending',
                        'approved' => 'Disetujui',
                        'rejected' => 'Ditolak',
                    ])->searchable(),
                SelectFilter::make('department_id')
                    ->label('Department')
                    ->relationship('department', 'name')->searchable(),
                SelectFilter::make('employee_id')
                    ->label('Karyawan')
                    ->relationship('employee', 'name')->searchable(),
                SelectFilter::make('customer_categories_id')
                    ->label('Kategori Customer')
                    ->relationship('customerCategory', 'name')->searchable(),
                SelectFilter::make('customer_id')
                    ->label('Customer')
                    ->relationship('customer', 'name')->searchable(),   
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPerbaikandatas::route('/'),
            'create' => Pages\CreatePerbaikandata::route('/create'),
            'edit' => Pages\EditPerbaikandata::route('/{record}/edit'),
        ];
    }
}