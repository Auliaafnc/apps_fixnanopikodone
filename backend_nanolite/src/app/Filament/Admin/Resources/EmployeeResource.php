<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\EmployeeResource\Pages;
use App\Models\Employee;
use App\Models\Department;
use App\Models\PostalCode;
use Filament\Forms;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Form;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Forms\Components\Grid;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;
use Laravolt\Indonesia\Models\Provinsi;
use Laravolt\Indonesia\Models\Kabupaten;
use Laravolt\Indonesia\Models\Kecamatan;
use Laravolt\Indonesia\Models\Kelurahan;
use App\Exports\EmployeeExport;
use Filament\Forms\Components\Checkbox;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Str;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\CreateAction;
use Illuminate\Support\Facades\Storage;
use Filament\Tables\Actions\Action;
use Filament\Notifications\Notification;

class EmployeeResource extends Resource
{
    protected static ?string $model = Employee::class;

    protected static ?string $navigationGroup = 'Company Management';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 1;

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'email', 'position'];
    }

    public static function getGlobalSearchResultDetails(\Illuminate\Database\Eloquent\Model $record): array
    {
        return [
            'Email'   => $record->email,
            'Jabatan' => $record->position,
            'Status'  => $record->status,
        ];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('department_id')
                    ->label('Department')
                    ->options(fn () => Department::where('status', 'active')->pluck('name', 'id'))
                    ->preload()->required()->searchable()->placeholder('Pilih Department'),

                TextInput::make('name')->label('Nama')->required()->maxLength(255),

                TextInput::make('email')->label('Email')->email()->required()->unique(ignoreRecord: true)->maxLength(255),

                TextInput::make('phone')->label('Telepon')->tel()->maxLength(20),

                Repeater::make('address')
                    ->label('Alamat')
                    ->schema([
                        Select::make('provinsi')->label('Provinsi')
                            ->options(fn () => Provinsi::pluck('name', 'code')->toArray())
                            ->searchable()->reactive()
                            ->afterStateUpdated(fn (callable $set) => $set('kota_kab', null)),

                        Select::make('kota_kab')->label('Kota/Kabupaten')
                            ->options(function (callable $get) {
                                if ($prov = $get('provinsi')) {
                                    return Kabupaten::where('province_code', $prov)->pluck('name', 'code')->toArray();
                                }
                                return [];
                            })
                            ->searchable()->reactive()
                            ->afterStateUpdated(fn (callable $set) => $set('kecamatan', null)),

                        Select::make('kecamatan')->label('Kecamatan')
                            ->options(function (callable $get) {
                                if ($kab = $get('kota_kab')) {
                                    return Kecamatan::where('city_code', $kab)->pluck('name', 'code')->toArray();
                                }
                                return [];
                            })
                            ->searchable()->reactive()
                            ->afterStateUpdated(fn (callable $set) => $set('kelurahan', null)),

                        Select::make('kelurahan')->label('Kelurahan')
                            ->options(function (callable $get) {
                                if ($kec = $get('kecamatan')) {
                                    return Kelurahan::where('district_code', $kec)->pluck('name', 'code')->toArray();
                                }
                                return [];
                            })
                            ->searchable()->reactive()
                            ->afterStateUpdated(function (callable $set, $state) {
                                $postal = \App\Models\PostalCode::where('village_code', $state)->first();
                                $set('kode_pos', $postal?->postal_code ?? null);
                            }),

                        TextInput::make('kode_pos')->label('Kode Pos')->readOnly(),
                        Textarea::make('detail_alamat')->label('Detail Alamat')->rows(3)->required(),
                    ])
                    ->columns(3)->defaultItems(1)
                    ->disableItemCreation()->disableItemDeletion()->dehydrated(),

                FileUpload::make('photo')->label('Foto')->image()->downloadable()->openable()->directory('employee-photos')->maxSize(2048),

                Select::make('status')->label('Status')
                    ->options(['active' => 'Aktif', 'non-active' => 'Nonaktif'])
                    ->default('active')->searchable()->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc') 
            ->columns([
                TextColumn::make('id')->label('ID')->sortable(),
                ImageColumn::make('photo')->label('Foto')->circular(),

                TextColumn::make('name')->label('Nama')->sortable()->searchable(),
                TextColumn::make('department.name')->label('Departemen')->sortable()->searchable()->toggleable(),
                TextColumn::make('email')->label('Email')->searchable()->toggleable(),
                TextColumn::make('phone')->label('Telepon')->toggleable(),

                TextColumn::make('full_address')->label('Alamat')->toggleable()->limit(50),

                TextColumn::make('status')->label('Status')->badge()
                    ->color(fn ($state) => $state === 'active' ? 'success' : 'danger'),

                TextColumn::make('created_at')->label('Dibuat')->dateTime('d M Y H:i')->sortable(),
                TextColumn::make('updated_at')->label('Diupdate')->dateTime('d M Y H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Filter Status')
                    ->options(['active' => 'Aktif', 'non-active' => 'Nonaktif']),
            ])
            ->headerActions([
                Action::make('export')
                    ->label('Export Data Karyawan')
                    ->form([
                        Grid::make(4)->schema([
                            Select::make('department_id')->label('Departmen')
                                ->options(fn () => Department::where('status', 'active')->pluck('name', 'id'))
                                ->searchable()->preload(),
                            Select::make('status')->label('Status')
                                ->options(['active' => 'Aktif', 'non-active' => 'Nonaktif'])->searchable(),
                            Checkbox::make('export_all')->label('Print Semua Data')->reactive(),
                        ])
                    ])
                    ->action(function (array $data) {
                        $export = new EmployeeExport($data);
                        $rows = $export->array();

                        if (count($rows) <= 2) {
                            \Filament\Notifications\Notification::make()
                                ->title('Data Karyawan Tidak Ditemukan')
                                ->body('Tidak ditemukan data Karyawan produk berdasarkan filter yang Anda pilih. Silakan periksa kembali pilihan filter Anda.')
                                ->danger()->send();
                            return null;
                        }

                        return Excel::download($export, 'export_employee.xlsx');
                    })
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ])
            ->emptyStateActions([
                Tables\Actions\CreateAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListEmployees::route('/'),
            'create' => Pages\CreateEmployee::route('/create'),
            'edit'   => Pages\EditEmployee::route('/{record}/edit'),
        ];
    }
}
