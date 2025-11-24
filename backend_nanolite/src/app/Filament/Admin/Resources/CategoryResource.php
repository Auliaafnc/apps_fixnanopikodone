<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\CategoryResource\Pages;
use App\Models\Category;
use Filament\Forms;
use App\Models\Brand;
use Filament\Forms\Form;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\FileUpload; 
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Forms\Components\Grid;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use Filament\Forms\Components\CheckboxList;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;
use App\Exports\CategoryExport;
use Filament\Tables\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Forms\Components\Checkbox;
use Maatwebsite\Excel\Facades\Excel;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static ?string $navigationGroup = 'Product Management';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = -1;

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    

    public static function form(Form $form): Form
    {
        return $form->schema([
            Select::make('brand_id')
                    ->label('Brand')
                    ->options(function () {
                        return Brand::where('status', 'active')->pluck('name', 'id');
                    })
                    ->preload()
                    ->required()
                    ->searchable()
                    ->placeholder('Pilih Brand'),

            TextInput::make('name')
                ->label('Nama Kategori')
                ->required()
                ->maxLength(255),

            Textarea::make('deskripsi')
                ->label('Deskripsi')
                ->rows(2)
                ->maxLength(1000),

            Select::make('status')
                    ->label('Status')
                    ->options([
                        'active'     => 'Aktif',
                        'non-active' => 'Nonaktif',
                    ])
                    ->default('active')
                    ->searchable()
                    ->required(),

            FileUpload::make('image')
                ->label('Gambar Kategori')
                ->image()
                ->downloadable()
                ->openable()
                ->directory('category-logos')
                ->maxSize(2048),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
        ->query(Category::withCount(['products']))
        ->columns([
            TextColumn::make('id')
                ->label('ID')
                ->sortable(),

            

            TextColumn::make('brand.name')
                ->label('Brand')
                ->sortable()
                ->searchable()
                ->toggleable(),

            TextColumn::make('name')
                ->label('Nama Kategori')
                ->searchable()
                ->sortable(),

            TextColumn::make('deskripsi')
                ->label('Deskripsi')
                ->limit(50)
                ->getStateUsing(fn ($record) => $record->deskripsi ?: '-')
                ->toggleable()
                ->sortable(),

            TextColumn::make('products_count')
                    ->label('Jumlah Pengguna')
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
                ->label('Export Data Kategori')
                ->form([
                    Grid::make(2)->schema([
                        Select::make('brand_id')
                            ->label('Brand')
                            ->searchable()
                            ->options(function () {
                                    return Brand::where('status', 'active')->pluck('name', 'id');
                                }),

                            Checkbox::make('export_all')
                                ->label('Print Semua Data')
                                ->reactive(),
                        ])

                ]) 
                                
               ->action(function (array $data) {
                    $filters = [];

                    if (empty($data['export_all'])) {
                        $filters = [
                            'brand_id' => $data['brand_id'] ?? null,
                        ];
                    }

                    $export = new \App\Exports\CategoryExport($filters);
                    $rows = $export->array();

                    if (count($rows) <= 2) {
                        \Filament\Notifications\Notification::make()
                            ->title('Data Kategori Tidak Ditemukan')
                            ->body('Tidak ditemukan data kategori berdasarkan filter yang Anda pilih.')
                            ->danger()
                            ->send();

                        return null;
                    }

                    return \Maatwebsite\Excel\Facades\Excel::download($export, 'export_kategori.xlsx');
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
            'index' => Pages\ListCategories::route('/'),
            'create' => Pages\CreateCategory::route('/create'),
            'edit' => Pages\EditCategory::route('/{record}/edit'),
        ];
    }
}
