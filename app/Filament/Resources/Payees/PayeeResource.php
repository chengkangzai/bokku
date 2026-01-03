<?php

namespace App\Filament\Resources\Payees;

use App\Enums\PayeeType;
use App\Filament\Resources\Payees\Pages\CreatePayee;
use App\Filament\Resources\Payees\Pages\EditPayee;
use App\Filament\Resources\Payees\Pages\ListPayees;
use App\Models\Payee;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PayeeResource extends Resource
{
    protected static ?string $model = Payee::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-storefront';

    protected static string|\UnitEnum|null $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Payee Information')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g., Starbucks, Amazon, Electric Company'),

                        Select::make('type')
                            ->options(PayeeType::class)
                            ->native(false),

                        Select::make('default_category_id')
                            ->label('Default Category')
                            ->relationship(
                                'defaultCategory',
                                'name',
                                fn (Builder $query) => $query->where('user_id', auth()->id())
                            )
                            ->native(false)
                            ->searchable()
                            ->preload()
                            ->helperText('Automatically suggest this category when selecting this payee'),

                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Inactive payees will not appear in transaction forms'),

                        Textarea::make('notes')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])->columns(2)->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('type')
                    ->badge()
                    ->placeholder('—'),

                TextColumn::make('defaultCategory.name')
                    ->label('Default Category')
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('transactions_count')
                    ->counts('transactions')
                    ->label('Transactions')
                    ->sortable(),

                TextColumn::make('total_amount')
                    ->label('Total Spent')
                    ->money('MYR')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active'),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options(PayeeType::class),

                TernaryFilter::make('is_active')
                    ->label('Active')
                    ->placeholder('All payees')
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->requiresConfirmation(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name')
            ->modifyQueryUsing(fn (Builder $query) => $query->where('user_id', auth()->id()));
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPayees::route('/'),
            'create' => CreatePayee::route('/create'),
            'edit' => EditPayee::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('user_id', auth()->id())->count() ?: null;
    }
}
