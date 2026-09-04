<?php

namespace App\Filament\Resources\Products\RelationManagers;

use App\Models\ProductImage;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;

class ImagesRelationManager extends RelationManager
{
    protected static string $relationship = 'images';

    protected static ?string $title = 'Images';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            FileUpload::make('path')
                ->label('Image')
                ->image()
                ->disk(ProductImage::storageDisk())
                ->directory('products')
                ->imageEditor()
                ->required()
                ->columnSpanFull(),

            TextInput::make('alt')
                ->label('Alt text')
                ->maxLength(255),

            Toggle::make('is_primary')
                ->label('Primary image')
                ->default(false),

            TextInput::make('sort_order')
                ->numeric()
                ->default(0),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('alt')
            ->reorderable('sort_order')
            ->authorizeReorder(fn (): bool => $this->canManageImages())
            ->defaultSort('sort_order')
            ->columns([
                ImageColumn::make('path')
                    ->label('Image')
                    ->square(),

                TextColumn::make('alt')
                    ->label('Alt text')
                    ->searchable()
                    ->limit(40),

                IconColumn::make('is_primary')
                    ->label('Primary')
                    ->boolean(),

                TextColumn::make('sort_order')
                    ->label('Order')
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make()->authorize(fn (): bool => $this->canManageImages()),
            ])
            ->recordActions([
                EditAction::make()->authorize(fn (): bool => $this->canManageImages()),
                DeleteAction::make()->authorize(fn (): bool => $this->canManageImages()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->authorize(fn (): bool => $this->canManageImages()),
                ]),
            ]);
    }

    private function canManageImages(): bool
    {
        return auth()->user()?->can('update', $this->getOwnerRecord()) === true;
    }

    /** @param array<int|string> $order */
    public function reorderTable(array $order, int|string|null $draggedRecordKey = null): void
    {
        if (! $this->canManageImages()) {
            throw new AuthorizationException;
        }

        $imageIds = array_values(array_unique($order));
        $belongsToProduct = ProductImage::query()
            ->where('product_id', $this->getOwnerRecord()->getKey())
            ->whereIn('id', $imageIds)
            ->count() === count($imageIds);

        if (! $belongsToProduct) {
            throw new AuthorizationException;
        }

        parent::reorderTable($order, $draggedRecordKey);
    }
}
