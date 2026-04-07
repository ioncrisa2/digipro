<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ContactMessageIndexRequest;
use App\Models\ContactMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Response;

class CommunicationController extends Controller
{
    public function contactMessagesIndex(ContactMessageIndexRequest $request): Response
    {
        $filters = $request->filters();

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
            ->paginate($request->perPage())
            ->withQueryString();

        $records->through(fn (ContactMessage $message) => $this->transformContactMessageRow($message));

        return inertia('Admin/ContactMessages/Index', [
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
        ]);
    }

    public function contactMessagesShow(ContactMessage $contactMessage): Response
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

        return inertia('Admin/ContactMessages/Show', [
            'record' => $this->contactMessagePayload($contactMessage),
            'indexUrl' => route('admin.communications.contact-messages.index'),
        ]);
    }

    public function contactMessagesMarkInProgress(ContactMessage $contactMessage): RedirectResponse
    {
        $contactMessage->forceFill([
            'status' => 'in_progress',
            'read_at' => $contactMessage->read_at ?? now(),
        ])->save();

        return redirect()
            ->route('admin.communications.contact-messages.show', $contactMessage)
            ->with('success', 'Pesan ditandai sedang diproses.');
    }

    public function contactMessagesMarkDone(ContactMessage $contactMessage): RedirectResponse
    {
        $contactMessage->forceFill([
            'status' => 'done',
            'read_at' => $contactMessage->read_at ?? now(),
            'handled_at' => now(),
            'handled_by' => auth()->id(),
        ])->save();

        return redirect()
            ->route('admin.communications.contact-messages.show', $contactMessage)
            ->with('success', 'Pesan ditandai selesai.');
    }

    public function contactMessagesArchive(ContactMessage $contactMessage): RedirectResponse
    {
        $contactMessage->forceFill([
            'status' => 'archived',
        ])->save();

        return redirect()
            ->route('admin.communications.contact-messages.show', $contactMessage)
            ->with('success', 'Pesan berhasil diarsipkan.');
    }

    public function contactMessagesDestroy(ContactMessage $contactMessage): RedirectResponse
    {
        $contactMessage->delete();

        return redirect()
            ->route('admin.communications.contact-messages.index')
            ->with('success', 'Pesan berhasil dihapus.');
    }

    private function transformContactMessageRow(ContactMessage $message): array
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

}
