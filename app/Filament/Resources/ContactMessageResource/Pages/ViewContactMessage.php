<?php

namespace App\Filament\Resources\ContactMessageResource\Pages;

use App\Filament\Resources\ContactMessageResource;
use Filament\Resources\Pages\ViewRecord;

class ViewContactMessage extends ViewRecord
{
    protected static string $resource = ContactMessageResource::class;

    public function mount($record): void
    {
        parent::mount($record);

        if (blank($this->record->read_at)) {
            $this->record->update([
                'read_at' => now(),
                // opsional: otomatis jadi in_progress saat dibuka pertama kali
                'status' => $this->record->status === 'new' ? 'in_progress' : $this->record->status,
            ]);
        }
    }
}
