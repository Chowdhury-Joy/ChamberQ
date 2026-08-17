<?php

namespace App\Filament\TenantAdmin\Resources\CashCategories\Pages;

use App\Filament\TenantAdmin\Resources\CashCategories\CashCategoryResource;
use App\Models\CashCategory;
use App\Services\CashCategoryService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ListCashCategories extends ListRecords
{
    protected static string $resource = CashCategoryResource::class;

    protected string $view = 'filament.tenant-admin.pages.cash-categories';

    public string $categoryType = CashCategory::TYPE_INCOME;

    public function mount(): void
    {
        parent::mount();

        app(CashCategoryService::class)->ensureDefaults();

        $type = request()->query('type');

        if (in_array($type, [CashCategory::TYPE_INCOME, CashCategory::TYPE_EXPENSE], true)) {
            $this->categoryType = $type;
        }
    }

    public function updatedCategoryType(): void
    {
        $this->resetTable();
    }

    public function table(Table $table): Table
    {
        return parent::table($table)
            ->query(fn (): Builder => CashCategory::query()
                ->where('type', $this->categoryType)
                ->orderBy('sort_order')
                ->orderBy('name'))
            ->recordActions([
                Action::make('toggleActive')
                    ->label(fn (CashCategory $record): string => $record->is_active
                        ? __('Hide from dropdown')
                        : __('Show in dropdown'))
                    ->icon(fn (CashCategory $record): string => $record->is_active
                        ? 'heroicon-o-eye-slash'
                        : 'heroicon-o-eye')
                    ->action(function (CashCategory $record): void {
                        $record->update(['is_active' => ! $record->is_active]);

                        Notification::make()
                            ->title($record->is_active
                                ? __('Category is visible in the cashbook dropdown.')
                                : __('Category hidden from the cashbook dropdown.'))
                            ->success()
                            ->send();
                    }),
                EditAction::make()
                    ->visible(fn (CashCategory $record): bool => ! $record->is_locked)
                    ->form([
                        TextInput::make('name')
                            ->label(__('Name'))
                            ->required()
                            ->maxLength(255),
                    ]),
                DeleteAction::make()
                    ->visible(fn (CashCategory $record): bool => ! $record->is_locked)
                    ->before(function (DeleteAction $action, CashCategory $record): void {
                        if (! app(CashCategoryService::class)->canDelete($record)) {
                            Notification::make()
                                ->title(__('Cannot delete — cashbook rows use this category.'))
                                ->body(__('Hide it instead, or leave it for old entries.'))
                                ->danger()
                                ->send();

                            $action->cancel();
                        }
                    }),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(__('Add category'))
                ->form([
                    TextInput::make('name')
                        ->label(__('Name'))
                        ->required()
                        ->maxLength(255)
                        ->placeholder($this->categoryType === CashCategory::TYPE_INCOME
                            ? __('Room rent, Training…')
                            : __('Cleaning, Facebook ads…')),
                ])
                ->action(function (array $data): void {
                    app(CashCategoryService::class)->createCustom($data['name'], $this->categoryType);

                    Notification::make()
                        ->title(__('Category added'))
                        ->success()
                        ->send();

                    $this->resetTable();
                }),
        ];
    }
}
