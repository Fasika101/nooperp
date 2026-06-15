<?php

namespace App\Filament\Projects\Resources\ProjectTaskResource\Pages;

use App\Filament\Projects\Resources\ProjectTaskResource;
use Filament\Resources\Pages\ListRecords;

class ListProjectTasks extends ListRecords
{
    protected static string $resource = ProjectTaskResource::class;
}
