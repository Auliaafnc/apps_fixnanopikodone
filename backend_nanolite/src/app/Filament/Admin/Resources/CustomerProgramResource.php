<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\CustomerProgramResource\Pages;
use App\Models\CustomerProgram;
use Filament\Forms;
use App\Models\Customer;
use App\Exports\CustomerProgramExport;
use Filament\Forms\Form;
use Filament\Forms\Components\Checkbox;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Str;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Forms\Components\Select;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\DatePicker;

class CustomerProgramResource extends Resource
{
    protected static ?string $model = CustomerProgram::class;

        protected static ?string $navigationGroup = 'Loyalty Management';
    protected static ?string $navigationLabel = 'Program Penjualan';
    protected static ?string $modelLabel       = 'Program Penjualan';
    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 1;

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('name')
                ->label('Nama Program')
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

            TextArea::make('deskripsi')
                ->label('Deskripsi')
                ->rows(3)
                ->maxLength(1000),

            DatePicker::make('starts_at')
                ->label('Mulai Program')
                ->native(false)
                ->closeOnDateSelection(),

            DatePicker::make('ends_at')
                ->label('Berakhir Program')
                ->native(false)
                ->closeOnDateSelection()
                ->rule('after_or_equal:starts_at'),

        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
        ->query(CustomerProgram::withCount('customers'))
        ->columns([
            TextColumn::make('id')
                ->label('ID')
                ->sortable(),

            TextColumn::make('name')
                ->label('Nama Program')
                ->searchable()
                ->sortable(),

            TextColumn::make('deskripsi')
                ->label('Deskripsi')
                ->limit(500)
                ->getStateUsing(fn ($record) => $record->deskripsi ?: '-')
                ->toggleable(),

            TextColumn::make('customers_count')
                    ->label('Jumlah Pengguna')
                    ->sortable()
                    ->alignCenter(),

            TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($state) => $state === 'active' ? 'success' : 'danger'),
            
            TextColumn::make('starts_at')
                ->label('Mulai')
                ->date('d M Y')
                ->sortable(),

            TextColumn::make('ends_at')
                ->label('Berakhir')
                ->date('d M Y')
                ->sortable(),

            TextColumn::make('created_at')
                ->label('Dibuat')
                ->dateTime('d M Y H:i')
                ->sortable()
                ->toggleable(),

            TextColumn::make('updated_at')
                ->label('Diupdate')
                ->dateTime('d M Y H:i')
                ->sortable()
                ->toggleable(),
        ])

        ->headerActions([
            Action::make('export')
                ->label('Export Data Customer Program')
                    ->action(function (array $data) {
                        $program = CustomerProgram::with(['customers'])->get();


                        if ($program->isEmpty()) {
                            Notification::make()
                                ->title('Data Customer Program Kosong')
                                ->body('Tidak ditemukan data untuk diekspor.')
                                ->danger()
                                ->send();

                            return null;
                        }

                        return Excel::download(
                            new CustomerProgramExport($program),
                            'export_customerProgram.xlsx'
                        );
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
            'index'  => Pages\ListCustomerPrograms::route('/'),
            'create' => Pages\CreateCustomerProgram::route('/create'),
            'edit'   => Pages\EditCustomerProgram::route('/{record}/edit'),
        ];
    }
}
