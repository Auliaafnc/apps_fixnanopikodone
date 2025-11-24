<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\CustomerCategoriesResource\Pages;
use App\Models\CustomerCategories;
use App\Models\Customer;
use App\Exports\CustomerCategoriesExport;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components\Checkbox;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Str;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Forms\Components\FileUpload;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;

class CustomerCategoriesResource extends Resource
{
    protected static ?string $model = CustomerCategories::class;

    protected static ?string $navigationGroup = 'Client Management';
    
    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 1;

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->label('Customer Categories')
                    ->required()
                    ->maxLength(255),


                Select::make('status')
                    ->label('Status')
                    ->options([
                        'active'     => 'Aktif',
                        'non-active' => 'Nonaktif',
                    ])
                    ->default('active')
                    ->searchable()
                    ->required(),


                Textarea::make('deskripsi')
                    ->label('Deskripsi')
                    ->rows(3)
                    ->maxLength(1000),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(CustomerCategories::withCount('customers')) 
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),

                TextColumn::make('name')
                    ->label('Customer Categories')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('deskripsi')
                    ->label('Deskripsi')
                    ->limit(500)
                    ->getStateUsing(fn ($record) => $record->deskripsi ?: '-')
                    ->toggleable()
                    ->sortable(),

                TextColumn::make('customers_count')
                    ->label('Jumlah Pengguna')
                    ->sortable()
                    ->alignCenter(),

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
                    ->label('Export Data Customer Categories')
                    ->action(function (array $data) {
                        $categories = CustomerCategories::with('customers')->get();

                        if ($categories->isEmpty()) {
                            Notification::make()
                                ->title('Data Customer Categories Kosong')
                                ->body('Tidak ditemukan data untuk diekspor.')
                                ->danger()
                                ->send();

                            return null;
                        }

                        return Excel::download(new CustomerCategoriesExport($categories), 'export_customerCategories.xlsx');
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
            'index' => Pages\ListCustomerCategories::route('/'),
            'create' => Pages\CreateCustomerCategories::route('/create'),
            'edit' => Pages\EditCustomerCategories::route('/{record}/edit'),
        ];
    }
}
