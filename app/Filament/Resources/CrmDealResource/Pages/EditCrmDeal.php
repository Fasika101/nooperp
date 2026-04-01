<?php

namespace App\Filament\Resources\CrmDealResource\Pages;

use App\Filament\Resources\CrmDealResource;
use App\Models\CrmDeal;
use App\Models\Project;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditCrmDeal extends EditRecord
{
    protected static string $resource = CrmDealResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createLinkedProject')
                ->label('Create linked project')
                ->icon('heroicon-o-folder-plus')
                ->visible(fn (CrmDeal $record): bool => $record->project_id === null)
                ->form([
                    TextInput::make('project_name')
                        ->label('Project name')
                        ->required()
                        ->maxLength(255)
                        ->default(fn (): string => $this->record->title),
                ])
                ->action(function (array $data): void {
                    /** @var CrmDeal $record */
                    $record = $this->record;

                    $project = Project::query()->create([
                        'name' => $data['project_name'],
                        'description' => $record->notes,
                        'customer_id' => $record->customer_id,
                        'created_by' => auth()->id(),
                        'status' => Project::STATUS_ACTIVE,
                    ]);

                    $userIds = array_unique(array_filter([
                        auth()->id(),
                        $record->assigned_user_id,
                    ]));
                    foreach ($userIds as $uid) {
                        $project->members()->syncWithoutDetaching([
                            $uid => ['role' => 'member'],
                        ]);
                    }

                    $record->update(['project_id' => $project->id]);

                    Notification::make()
                        ->success()
                        ->title('Project created')
                        ->body('The deal is now linked to this project.')
                        ->send();
                }),
            DeleteAction::make(),
        ];
    }
}
