<?php

namespace App\Filament\Resources\ConsentDocumentResource\Pages;

use App\Filament\Resources\ConsentDocumentResource;
use App\Models\ConsentDocument;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;

class ViewConsentDocument extends ViewRecord
{
    protected static string $resource = ConsentDocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('publish')
                ->label('Publish')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (ConsentDocument $record) => $record->status === 'draft')
                ->action(function (ConsentDocument $record) {
                    // auto-archive previous published for same code
                    ConsentDocument::query()
                        ->forCode($record->code)
                        ->published()
                        ->where('id', '!=', $record->id)
                        ->update(['status' => 'archived']);

                    $record->status = 'published';
                    $record->published_at = now();
                    $record->hash = ConsentDocument::computeHash($record->payloadForHash());
                    $record->updated_by = Auth::id();
                    $record->save();
                }),

            Actions\Action::make('duplicate')
                ->label('Duplicate as Draft')
                ->icon('heroicon-o-document-duplicate')
                ->color('gray')
                ->visible(fn (ConsentDocument $record) => in_array($record->status, ['published', 'archived'], true))
                ->action(function (ConsentDocument $record) {
                    $copy = $record->replicate();
                    $copy->status = 'draft';
                    $copy->published_at = null;
                    $copy->hash = str_repeat('0', 64);
                    $copy->version = $record->version . '-rev';
                    $copy->created_by = Auth::id();
                    $copy->updated_by = Auth::id();
                    $copy->save();
                }),

            Actions\EditAction::make()
                ->visible(fn (ConsentDocument $record) => $record->status === 'draft'),

            Actions\DeleteAction::make()
                ->visible(fn (ConsentDocument $record) => $record->status === 'draft'),
        ];
    }
}
