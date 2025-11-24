<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Role as SpatieRole;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationGroup = 'Administration';
    protected static ?string $recordTitleAttribute = 'name';
    protected static ?int $navigationSort = -2;

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getEloquentQuery()->count();
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'email', 'roles.name', 'employee.name', 'department.name'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Role'  => $record->roles->pluck('name')->implode(', '),
            'Email' => $record->email,
            'Emp.'  => optional($record->employee)->name,
            'Dept.' => optional($record->department)->name,
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if (! auth()->user()?->hasRole('super_admin')) {
            $query->whereDoesntHave('roles', fn ($q) => $q->where('name', 'super_admin'));
        }

        return $query;
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        $query = parent::getGlobalSearchEloquentQuery();

        if (! auth()->user()?->hasRole('super_admin')) {
            $query->whereDoesntHave('roles', fn ($q) => $q->where('name', 'super_admin'));
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Grid::make(2)->schema([
                Forms\Components\TextInput::make('name')
                    ->minLength(2)->maxLength(255)
                    ->columnSpan('full')->required(),

                Forms\Components\FileUpload::make('avatar_url')
                    ->label('Avatar')->image()
                    ->optimize('webp')->imageEditor()
                    ->imagePreviewHeight('250')
                    ->panelAspectRatio('7:2')->panelLayout('integrated')
                    ->columnSpan('full'),

                Forms\Components\TextInput::make('email')
                    ->required()->prefixIcon('heroicon-m-envelope')
                    ->columnSpan('full')->email()
                    ->unique(ignoreRecord: true),

                Forms\Components\TextInput::make('password')
                    ->password()->confirmed()->columnSpan(1)
                    ->dehydrateStateUsing(fn ($state) => filled($state) ? Hash::make($state) : null)
                    ->dehydrated(fn ($state) => filled($state))
                    ->required(fn (string $context): bool => $context === 'create'),

                Forms\Components\TextInput::make('password_confirmation')
                    ->required(fn (string $context): bool => $context === 'create')
                    ->columnSpan(1)->password(),
            ]),

            Forms\Components\Select::make('roles')
                ->label('Roles')
                ->multiple()
                ->relationship(
                    name: 'roles',
                    titleAttribute: 'name',
                    modifyQueryUsing: function (Builder $query) {
                        if (! auth()->user()?->hasRole('super_admin')) {
                            $query->where('name', '!=', 'super_admin');
                        }
                    }
                )
                ->preload()
                ->searchable()
                ->required()
                ->rule(function () {
                    return function (string $attribute, $value, \Closure $fail) {
                        if (! auth()->user()?->hasRole('super_admin')) {
                            $superId = SpatieRole::where('name', 'super_admin')->value('id');
                            if (is_array($value) && in_array($superId, $value, true)) {
                                $fail('Role super_admin tidak boleh di-assign.');
                            }
                        }
                    };
                }),

            Forms\Components\Section::make('Employment')
                ->schema([
                    Forms\Components\Select::make('employee_id')
                        ->label('Employee')
                        ->relationship('employee', 'name')
                        ->searchable()->preload()
                        ->reactive()
                        ->afterStateUpdated(function ($state, Forms\Set $set) {
                            if (!$state) return;
                            $deptId = \App\Models\Employee::whereKey($state)->value('department_id');
                            $set('department_id', $deptId);
                        })
                        ->helperText('Pilih karyawan yang mewakili user ini.'),

                    Forms\Components\Select::make('department_id')
                        ->label('Department')
                        ->relationship('department', 'name')
                        ->searchable()->preload(),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc') //  terbaru dulu
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('name')->sortable()->searchable(),
                Tables\Columns\ImageColumn::make('avatar_url')
                    ->defaultImageUrl(url('https://www.gravatar.com/avatar/64e1b8d34f425d19e1ee2ea7236d3028?d=mp&r=g&s=250'))
                    ->label('Avatar')->circular(),
                Tables\Columns\TextColumn::make('email')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('roles.name')->badge()->label('Roles')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('employee.name')->label('Employee')->sortable()->toggleable()->searchable(),
                Tables\Columns\TextColumn::make('department.name')->label('Department')->sortable()->toggleable()->searchable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime('d M Y H:i')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('updated_at')->label('Diupdate')->dateTime('d M Y H:i')->sortable(),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([]),
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
            'index'  => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit'   => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
