<?php

namespace App\Services\Admin;

use App\Models\ContactMessage;
use App\Models\SupportContactSetting;
use App\Support\SupportContact;
use Illuminate\Support\Str;

class AdminCommunicationWorkspaceService
{
    public function contactMessagesIndexPayload(array $filters, int $perPage): array
    {
        $records = ContactMessage::query()
            ->with('handledBy:id,name')
            ->when($filters['q'] !== '', function ($query) use ($filters): void {
                $query->where(function ($innerQuery) use ($filters): void {
                    $innerQuery
                        ->where('name', 'like', '%' . $filters['q'] . '%')
                        ->orWhere('email', 'like', '%' . $filters['q'] . '%')
                        ->orWhere('subject', 'like', '%' . $filters['q'] . '%')
                        ->orWhere('message', 'like', '%' . $filters['q'] . '%');
                });
            })
            ->when(
                $filters['status'] !== 'all',
                fn ($query) => $query->where('status', $filters['status'])
            )
            ->when(
                $filters['unread'] === 'yes',
                fn ($query) => $query->whereNull('read_at')
            )
            ->when(
                $filters['source'] !== 'all',
                fn ($query) => $query->where('source', $filters['source'])
            )
            ->latest('created_at')
            ->paginate($perPage)
            ->withQueryString();

        $records->through(fn (ContactMessage $message) => $this->contactMessageRow($message));

        return [
            'filters' => $filters,
            'statusOptions' => $this->contactMessageStatusOptions(withAll: true),
            'sourceOptions' => $this->contactMessageSourceOptions(),
            'unreadOptions' => [
                ['value' => 'all', 'label' => 'Semua'],
                ['value' => 'yes', 'label' => 'Unread'],
            ],
            'summary' => [
                'total' => ContactMessage::query()->count(),
                'new' => ContactMessage::query()->where('status', 'new')->count(),
                'unread' => ContactMessage::query()->whereNull('read_at')->count(),
                'done' => ContactMessage::query()->where('status', 'done')->count(),
            ],
            'records' => $this->paginatedRecordsPayload($records),
            'supportContactEditUrl' => route('admin.communications.support-contact.edit'),
        ];
    }

    public function readContactMessage(ContactMessage $contactMessage): ContactMessage
    {
        $contactMessage->loadMissing('handledBy:id,name');

        if (blank($contactMessage->read_at)) {
            $contactMessage->forceFill([
                'read_at' => now(),
                'status' => $contactMessage->status === 'new' ? 'in_progress' : $contactMessage->status,
            ])->save();

            $contactMessage->refresh();
            $contactMessage->loadMissing('handledBy:id,name');
        }

        return $contactMessage;
    }

    public function contactMessageShowPayload(ContactMessage $contactMessage): array
    {
        return [
            'record' => $this->contactMessagePayload($contactMessage),
            'indexUrl' => route('admin.communications.contact-messages.index'),
        ];
    }

    public function markContactMessageInProgress(ContactMessage $contactMessage): void
    {
        $contactMessage->forceFill([
            'status' => 'in_progress',
            'read_at' => $contactMessage->read_at ?? now(),
        ])->save();
    }

    public function markContactMessageDone(ContactMessage $contactMessage, ?int $actorId = null): void
    {
        $contactMessage->forceFill([
            'status' => 'done',
            'read_at' => $contactMessage->read_at ?? now(),
            'handled_at' => now(),
            'handled_by' => $actorId,
        ])->save();
    }

    public function archiveContactMessage(ContactMessage $contactMessage): void
    {
        $contactMessage->forceFill([
            'status' => 'archived',
        ])->save();
    }

    public function deleteContactMessage(ContactMessage $contactMessage): void
    {
        $contactMessage->delete();
    }

    public function supportContactEditPayload(): array
    {
        $setting = SupportContactSetting::query()->first();
        $defaults = SupportContact::defaults();

        return [
            'record' => [
                'name' => $setting?->name ?? $defaults['name'],
                'phone' => $setting?->phone ?? $defaults['phone'],
                'whatsapp' => $setting?->whatsapp ?? $defaults['whatsapp'],
                'email' => $setting?->email ?? $defaults['email'],
                'availability_label' => $setting?->availability_label ?? $defaults['availability_label'],
            ],
            'submitUrl' => route('admin.communications.support-contact.update'),
            'indexUrl' => route('admin.communications.contact-messages.index'),
        ];
    }

    public function saveSupportContactSetting(array $validated): void
    {
        $setting = SupportContactSetting::query()->first() ?? new SupportContactSetting();
        $setting->fill($validated);
        $setting->save();
    }

    private function contactMessageRow(ContactMessage $message): array
    {
        $message->loadMissing('handledBy:id,name');

        return [
            'id' => $message->id,
            'name' => $message->name,
            'email' => $message->email,
            'subject' => $message->subject,
            'message_excerpt' => Str::limit($message->message, 90),
            'status' => $message->status,
            'status_label' => $this->contactMessageStatusLabel($message->status),
            'source' => $message->source ?: '-',
            'is_unread' => blank($message->read_at),
            'handled_by_name' => $message->handledBy?->name ?? '-',
            'created_at' => $message->created_at?->toIso8601String(),
            'show_url' => route('admin.communications.contact-messages.show', $message),
        ];
    }

    private function contactMessagePayload(ContactMessage $message): array
    {
        return [
            'id' => $message->id,
            'name' => $message->name,
            'email' => $message->email,
            'subject' => $message->subject,
            'message' => $message->message,
            'status' => $message->status,
            'status_label' => $this->contactMessageStatusLabel($message->status),
            'source' => $message->source ?: '-',
            'ip_address' => $message->ip_address ?: '-',
            'user_agent' => $message->user_agent ?: '-',
            'read_at' => $message->read_at?->toIso8601String(),
            'handled_at' => $message->handled_at?->toIso8601String(),
            'handled_by_name' => $message->handledBy?->name ?? '-',
            'created_at' => $message->created_at?->toIso8601String(),
            'available_actions' => [
                'in_progress' => in_array($message->status, ['new', 'in_progress'], true),
                'done' => $message->status !== 'done',
                'archive' => $message->status !== 'archived',
                'delete' => true,
            ],
        ];
    }

    private function contactMessageStatusOptions(bool $withAll = false): array
    {
        $options = [
            ['value' => 'new', 'label' => 'New'],
            ['value' => 'in_progress', 'label' => 'In Progress'],
            ['value' => 'done', 'label' => 'Done'],
            ['value' => 'archived', 'label' => 'Archived'],
        ];

        if (! $withAll) {
            return $options;
        }

        return [
            ['value' => 'all', 'label' => 'Semua Status'],
            ...$options,
        ];
    }

    private function contactMessageSourceOptions(): array
    {
        return ContactMessage::query()
            ->distinct()
            ->orderBy('source')
            ->pluck('source')
            ->filter()
            ->values()
            ->map(fn (string $source) => [
                'value' => $source,
                'label' => $source,
            ])
            ->all();
    }

    private function contactMessageStatusLabel(?string $status): string
    {
        return match ($status) {
            'new' => 'New',
            'in_progress' => 'In Progress',
            'done' => 'Done',
            'archived' => 'Archived',
            default => '-',
        };
    }

    private function paginatedRecordsPayload(object $records): array
    {
        return [
            'data' => $records->items(),
            'meta' => [
                'from' => $records->firstItem(),
                'to' => $records->lastItem(),
                'total' => $records->total(),
                'current_page' => $records->currentPage(),
                'last_page' => $records->lastPage(),
                'per_page' => $records->perPage(),
                'links' => $records->linkCollection()->toArray(),
            ],
        ];
    }
}
