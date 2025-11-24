<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\PointMinimumResource\Pages;
use App\Models\PointMinimum;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;

class PointMinimumResource extends Resource
{
    protected static ?string $model = PointMinimum::class;

   
    protected static ?string $navigationGroup = 'Loyalty Management';
    protected static ?string $navigationLabel = 'Minimum Poin';
    protected static ?string $modelLabel       = 'Minimum Poin';
    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Pengaturan Minimum')
                    ->schema([
                        Forms\Components\Radio::make('type')
                            ->label('Jenis Minimum')
                            ->options([
                                'reward'  => 'Reward',
                                'program' => 'Program',
                            ])
                            ->inline()
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                // Jika pindah ke Reward, kosongkan program_id
                                if ($state === 'reward') {
                                    $set('program_id', null);
                                }
                            }),

                        Forms\Components\Select::make('program_id')
                            ->label('Pilih Program')
                            ->relationship('program', 'name') // relasi ke App\Models\CustomerProgram
                            ->searchable()
                            ->preload()
                            ->visible(fn (Get $get) => $get('type') === 'program')
                            ->required(fn (Get $get) => $get('type') === 'program')
                            ->dehydrated(fn (Get $get) => $get('type') === 'program')
                            ->placeholder('Pilih salah satu program'),

                        Forms\Components\TextInput::make('min_amount')
                            ->label('Minimum Pembelian (Rp)')
                            ->numeric()
                            ->minValue(0)
                            ->step(1000)
                            ->prefix('Rp')
                            ->required(),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Aktifkan')
                            ->default(true),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('type')
                    ->label('Tipe')
                    ->colors([
                        'primary' => 'reward',
                        'success' => 'program',
                    ])
                    ->formatStateUsing(fn (string $state) => $state === 'reward' ? 'Reward' : 'Program')
                    ->sortable(),

                Tables\Columns\TextColumn::make('program.name')
                    ->label('Program')
                    ->placeholder('—')
                    ->toggleable()
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('min_amount')
                    ->label('Minimum (Rp)')
                    ->numeric(thousandsSeparator: '.', decimalSeparator: ',', decimalPlaces: 0)
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Diubah')
                    ->dateTime('d M Y H:i')
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Jenis')
                    ->options([
                        'reward'  => 'Reward',
                        'program' => 'Program',
                    ]),
                TernaryFilter::make('is_active')
                    ->label('Aktif'),
            ])
            ->headerActions([
    Tables\Actions\Action::make('export')
        ->label('Export Minimum Poin')
        ->form([
            \Filament\Forms\Components\Grid::make(3)->schema([
                \Filament\Forms\Components\Select::make('type')
                    ->label('Jenis')
                    ->options([
                        'reward'  => 'Reward',
                        'program' => 'Program',
                    ])
                    ->native(false)
                    ->searchable(),

                \Filament\Forms\Components\Select::make('is_active')
                    ->label('Aktif')
                    ->options([
                        ''  => 'Semua',
                        '1' => 'Aktif',
                        '0' => 'Nonaktif',
                    ])
                    ->default('')
                    ->native(false),

                \Filament\Forms\Components\Checkbox::make('export_all')
                    ->label('Print Semua Data')
                    ->reactive(),
            ]),
        ])
        ->action(function (array $data) {
            // Jika tidak centang "Print Semua Data", terapkan filter dari form
            $filters = [];
            if (empty($data['export_all'])) {
                $filters = [
                    'type'      => $data['type']      ?? null,
                    'is_active' => $data['is_active'] ?? '',
                ];
            }

            $export = new \App\Exports\PointMinimumExport($filters);
            $rows = $export->array();

            if (count($rows) <= 2) {
                \Filament\Notifications\Notification::make()
                    ->title('Data Tidak Ditemukan')
                    ->body('Tidak ada data sesuai filter yang dipilih.')
                    ->danger()
                    ->send();
                return null;
            }

            return \Maatwebsite\Excel\Facades\Excel::download($export, 'export_minimum_poin.xlsx');
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
        return [
            // Tidak ada relation manager khusus
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListPointMinimums::route('/'),
            'create' => Pages\CreatePointMinimum::route('/create'),
            'edit'   => Pages\EditPointMinimum::route('/{record}/edit'),
        ];
    }
}
