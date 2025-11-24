<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\BannerResource\Pages;
use App\Models\Banner;
use Filament\Forms\Form;
use Filament\Forms\Components\FileUpload;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\CreateAction;

class BannerResource extends Resource
{
    protected static ?string $model = Banner::class;
    protected static ?string $navigationGroup = 'Home Management';
    protected static ?int $navigationSort = 5;
    protected static ?string $recordTitleAttribute = 'id';

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                FileUpload::make('image_1')
                    ->label('Gambar 1')
                    ->image()
                    ->downloadable()
                    ->openable()
                    ->directory('Banner-photos')
                    ->maxSize(2048),

                FileUpload::make('image_2')
                    ->label('Gambar 2')
                    ->image()
                    ->downloadable()
                    ->openable()
                    ->directory('Banner-photos')
                    ->maxSize(2048),

                FileUpload::make('image_3')
                    ->label('Gambar 3')
                    ->image()
                    ->downloadable()
                    ->openable()
                    ->directory('Banner-photos')
                    ->maxSize(2048),

                FileUpload::make('image_4')
                    ->label('Gambar 4')
                    ->image()
                    ->downloadable()
                    ->openable()
                    ->directory('Banner-photos')
                    ->maxSize(2048),
            ]);

            
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('ID')->sortable(),

                ImageColumn::make('image_1')->label('Gambar 1')->circular(),
                ImageColumn::make('image_2')->label('Gambar 2')->circular(),
                ImageColumn::make('image_3')->label('Gambar 3')->circular(),
                ImageColumn::make('image_4')->label('Gambar 4')->circular(),

                TextColumn::make('created_at')->label('Dibuat Pada')->dateTime('d M Y H:i')->sortable(),
                TextColumn::make('updated_at')->label('Diupdate')->dateTime('d M Y H:i')->sortable(),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
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
            'index'  => Pages\ListBanners::route('/'),
            'create' => Pages\CreateBanner::route('/create'),
            'edit'   => Pages\EditBanner::route('/{record}/edit'),
        ];
    }
}
