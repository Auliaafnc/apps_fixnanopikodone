<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\CompanyResource\Pages;
use App\Models\Company;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Model;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use App\Models\PostalCode;
use Laravolt\Indonesia\Models\Provinsi;
use Laravolt\Indonesia\Models\Kabupaten;
use Laravolt\Indonesia\Models\Kecamatan;
use Laravolt\Indonesia\Models\Kelurahan;

class CompanyResource extends Resource
{
    protected static ?string $model = Company::class;

    protected static ?string $navigationGroup = 'Company Management';

    protected static ?int $navigationSort = -3;

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'email', 'phone', 'address'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Email' => $record->email,
            'Phone' => $record->phone,
            'Address' => $record->address,
        ];
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('name')
                ->label('Nama Perusahaan')
                ->required()
                ->maxLength(255),

            TextInput::make('email')
                ->label('Email')
                ->email()
                ->maxLength(255),

            TextInput::make('phone')
                ->label('No. Telepon')
                ->tel()
                ->maxLength(20),

            Select::make('status')
                ->label('Status')
                ->options([
                    'active' => 'Aktif',
                    'non-active' => 'Nonaktif',
                ])
                ->default('active')
                ->required(),

            Repeater::make('address')
                ->label('Alamat')
                ->schema([
                    Select::make('provinsi')
                        ->label('Provinsi')
                        ->options(fn () => Provinsi::pluck('name', 'code')->toArray())
                        ->searchable()
                        ->reactive()
                        ->afterStateUpdated(fn (callable $set) => $set('kota_kab', null)),

                    Select::make('kota_kab')
                        ->label('Kota/Kabupaten')
                        ->options(fn (callable $get) => $get('provinsi') ? Kabupaten::where('province_code', $get('provinsi'))->pluck('name', 'code')->toArray() : [])
                        ->searchable()
                        ->reactive()
                        ->afterStateUpdated(fn (callable $set) => $set('kecamatan', null)),

                    Select::make('kecamatan')
                        ->label('Kecamatan')
                        ->options(fn (callable $get) => $get('kota_kab') ? Kecamatan::where('city_code', $get('kota_kab'))->pluck('name', 'code')->toArray() : [])
                        ->searchable()
                        ->reactive()
                        ->afterStateUpdated(fn (callable $set) => $set('kelurahan', null)),

                    Select::make('kelurahan')
                        ->label('Kelurahan')
                        ->options(fn (callable $get) => $get('kecamatan') ? Kelurahan::where('district_code', $get('kecamatan'))->pluck('name', 'code')->toArray() : [])
                        ->searchable()
                        ->reactive()
                        ->afterStateUpdated(function (callable $set, $state) {
                            $postal = PostalCode::where('village_code', $state)->first();
                            $set('kode_pos', $postal?->postal_code ?? null);
                        }),

                    TextInput::make('kode_pos')
                        ->label('Kode Pos')
                        ->readOnly(),

                    Textarea::make('detail_alamat')
                        ->label('Detail Alamat')
                        ->rows(3)
                        ->required(),
                ])
                ->columns(3)
                ->defaultItems(1)
                ->disableItemCreation()
                ->disableItemDeletion()
                ->dehydrated(),

            Forms\Components\FileUpload::make('image')
                ->label('Logo / Gambar Perusahaan')
                ->image()
                ->downloadable()
                ->openable()
                ->directory('company-logos')
                ->maxSize(2048),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('id')->label('ID')->sortable()->toggleable(),
            ImageColumn::make('image')->label('Logo')->circular(),
            TextColumn::make('name')->label('Nama Perusahaan')->sortable()->searchable(),
            TextColumn::make('email')->label('Email')->toggleable(),
            TextColumn::make('phone')->label('Telepon')->toggleable(),
            TextColumn::make('full_address')->label('Alamat')->toggleable()->limit(50),
            TextColumn::make('status')->label('Status')->badge()->color(fn ($state) => $state === 'active' ? 'success' : 'danger'),
            TextColumn::make('created_at')->label('Dibuat')->dateTime('d M Y H:i')->sortable(),
            TextColumn::make('updated_at')->label('Diupdate')->dateTime('d M Y H:i')->sortable(),
        ])
        ->filters([
            SelectFilter::make('status')
                ->label('Filter Status')
                ->options([
                    'active' => 'Aktif',
                    'non-active' => 'Nonaktif',
                ]),
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
            'index'  => Pages\ListCompanies::route('/'),
            'create' => Pages\CreateCompany::route('/create'),
            'edit'   => Pages\EditCompany::route('/{record}/edit'),
        ];
    }
}
