<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\BrandResource\Pages;
use App\Models\Brand;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\FileUpload;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Notifications\Notification;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\BrandExport;


class BrandResource extends Resource
{
    protected static ?string $model = Brand::class;

    protected static ?string $navigationGroup = 'Product Management';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = -1;

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->label('Nama Brand')
                    ->required()
                    ->maxLength(255),

                Textarea::make('deskripsi')
                    ->label('Deskripsi')
                    ->rows(2)
                    ->maxLength(1000),

                FileUpload::make('image')
                    ->label('Gambar Brand')
                    ->image()
                    ->downloadable()
                    ->openable()
                    ->directory('Brand-logos')
                    ->maxSize(2048),

                
                
                Select::make('status')
                    ->label('Status')
                    ->options([
                        'active'     => 'Aktif',
                        'non-active' => 'Nonaktif',
                    ])
                    ->default('active')
                    ->searchable()
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(Brand::withCount(['products', 'categories']))
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),

                TextColumn::make('name')
                    ->label('Nama Brand')
                    ->searchable()
                    ->sortable(),
                

                TextColumn::make('deskripsi')
                    ->label('Deskripsi')
                    ->limit(500)
                    ->getStateUsing(fn ($record) => $record->deskripsi ?: '-')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('categories_count')
                    ->label('Jumlah Pengguna di Kategori')
                    ->sortable()
                    ->alignCenter(),

                TextColumn::make('products_count')
                    ->label('Jumlah Pengguna di Produk')
                    ->sortable()
                    ->alignCenter(),


                ImageColumn::make('image')
                    ->label('Gambar')
                    ->circular(),

                

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($state) => $state === 'active' ? 'success' : 'danger'),

                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y H:i')
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->label('Diupdate')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->headerActions([
                Action::make('export')
                    ->label('Export Data Brand')
                    ->action(function (array $data) {
                        $brands = \App\Models\Brand::with(['categories', 'products'])->get();

                        if ($brands->isEmpty()) {
                            Notification::make()
                                ->title('Data Brand Kosong')
                                ->body('Tidak ditemukan data brand untuk diekspor.')
                                ->danger()
                                ->send();

                            return null;
                        }

                        return Excel::download(new \App\Exports\BrandExport($brands), 'export_brand.xlsx');
                    }),

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
            'index' => Pages\ListBrands::route('/'),
            'create' => Pages\CreateBrand::route('/create'),
            'edit' => Pages\EditBrand::route('/{record}/edit'),
        ];
    }
}
