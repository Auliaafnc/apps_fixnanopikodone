<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\ProductResource\Pages;
use App\Models\Brand;
use App\Models\Category; 
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\FileUpload;
use Filament\Tables\Filters\SelectFilter;
use Filament\Forms\Components\CheckboxList;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use App\Exports\ProductExport;
use Filament\Forms\Components\Checkbox;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Str;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Facades\Storage;
use Filament\Tables\Actions\Action;
use Filament\Notifications\Notification;


class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

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
                Select::make('brand_id')
                    ->label('Brand')
                    ->options(function () {
                        return Brand::where('status', 'active')->pluck('name', 'id');
                    })
                    ->preload()
                    ->required()
                    ->reactive()
                    ->searchable()
                    ->afterStateUpdated(fn ($state, callable $set) => 
                        $set('category_id', null) // reset kategori saat brand berubah
                    )
                    ->placeholder('Pilih Brand'),

                Select::make('category_id')
                    ->label('Category')
                    ->options(function (callable $get) {
                        $brandId = $get('brand_id');
                        return $brandId
                            ? Category::where('brand_id', $brandId)->where('status', 'active')->pluck('name', 'id')
                            : Category::where('status', 'active')->pluck('name', 'id');
                    })
                    ->preload()
                    ->required()
                    ->reactive()
                    ->searchable()
                    ->placeholder('Pilih Kategori'),

            
                

                TextInput::make('name')
                    ->label('Product Name')
                    ->required()
                    ->maxLength(255),

                TextInput::make('price')
                    ->label('Price')
                    ->numeric()
                    ->prefix('Rp')
                    ->required(),
                    
                Textarea::make('description')
                    ->label('Description')
                    ->nullable(),
                

                CheckboxList::make('colors')
                    ->label('Available Colors')
                    ->options([
                        '3000K' => '3000K',
                        '4000K' => '4000K',
                        '6500K' => '6500K',
                    ])
                    ->columns(3)
                    ->required(),

                

                FileUpload::make('image')
                    ->label('Product Image')
                    ->image()
                    ->downloadable()
                    ->openable()
                    ->directory('products')
                    ->nullable(),

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
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),

                

                TextColumn::make('brand.name')
                    ->label('Brand')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('name')
                    ->label('Nama Produk')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('category.name')
                    ->label('Kategori')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('colors')
                    ->label('Warna')
                    ->formatStateUsing(fn ($state) => is_array($state) ? implode(', ', $state) : $state),

                
                TextColumn::make('price')
                    ->label('Harga')
                    ->formatStateUsing(fn ($state) => 'Rp ' . number_format($state, 0, ',', '.'))
                    ->sortable(),
 

                TextColumn::make('description')
                    ->label('Deskripsi')
                    ->limit(50)
                    ->getStateUsing(fn ($record) => $record->description ?: '-')
                    ->toggleable(),

                ImageColumn::make('image')
                    ->label('Gambar Produk')
                    ->square()
                    ->circular(),
                
                
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($state) => $state === 'active' ? 'success' : 'danger'),

                TextColumn::make('created_at')
                    ->label('Created At')
                    ->dateTime('d M Y H:i')
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->label('Diupdate')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])



            ->headerActions([
                Action::make('export')
                    ->label('Export Data Product')
                    ->form([
                        Grid::make(2)->schema([
                            Select::make('brand_id')
                                ->label('Brand')
                                ->searchable()
                                ->options(function () {
                                        return Brand::where('status', 'active')->pluck('name', 'id');
                                    }),
                                
                            Select::make('category_id')
                                ->label('Kategori Produk')
                                ->searchable()
                                ->options(function () {
                                        return Category::where('status', 'active')->pluck('name', 'id');
                                    }),

                            
                            Select::make('colors')
                                 ->label('Warna Produk')
                                 ->options([
                                     '3000K' => '3000K',
                                     '4000K' => '4000K',
                                     '6500K' => '6500K',
                                 ])
                                 ->searchable(),
                                

                            Select::make('status')
                                ->label('Status')
                                ->options([
                                    'active' => 'Aktif',
                                    'non-active' => 'Nonaktif',
                                ])
                                ->searchable(),

                            Checkbox::make('export_all')
                                ->label('Print Semua Data')
                                ->reactive(),
                        ])
                    ])
                    ->action(function (array $data) {
                        // Susun query sesuai filter
                        $query = Product::query();

                        if (empty($data['export_all'])) {
                            if (!empty($data['brand_id'])) {
                                $query->where('brand_id', $data['brand_id']);
                            }
                            if (!empty($data['category_id'])) {
                                $query->where('category_id', $data['category_id']);
                            }
                            if (!empty($data['status'])) {
                                $query->where('status', $data['status']);
                            }
                            if (!empty($data['colors'])) {
                                // Pastikan kolom 'colors' di model di-cast ke array & bertipe JSON di DB
                                $query->whereJsonContains('colors', $data['colors']);
                            }
                        }

                        // Jika tidak ada data, kirim notifikasi & hentikan proses
                        if (!$query->exists()) {
                            Notification::make()
                                ->title('Data Produk Tidak Ditemukan')
                                ->body('Tidak ditemukan data produk berdasarkan filter yang Anda pilih. Silakan periksa kembali pilihan filter Anda.')
                                ->danger()
                                ->send();

                            return null;
                        }

                        // Jika ada data, lanjutkan export seperti biasa
                        return Excel::download(new ProductExport($data), 'export_product.xlsx');
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
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
