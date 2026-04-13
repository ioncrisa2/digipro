<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ContactMessageIndexRequest;
use App\Http\Requests\Admin\UpdateSupportContactSettingRequest;
use App\Models\ContactMessage;
use App\Services\Admin\AdminCommunicationWorkspaceService;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;

class CommunicationController extends Controller
{
    public function __construct(
        private readonly AdminCommunicationWorkspaceService $workspaceService,
    ) {
    }

    public function contactMessagesIndex(ContactMessageIndexRequest $request): Response
    {
        return inertia('Admin/ContactMessages/Index', $this->workspaceService
            ->contactMessagesIndexPayload($request->filters(), $request->perPage()));
    }

    public function contactMessagesShow(ContactMessage $contactMessage): Response
    {
        return inertia('Admin/ContactMessages/Show', $this->workspaceService
            ->contactMessageShowPayload($this->workspaceService->readContactMessage($contactMessage)));
    }

    public function contactMessagesMarkInProgress(ContactMessage $contactMessage): RedirectResponse
    {
        $this->workspaceService->markContactMessageInProgress($contactMessage);

        return redirect()
            ->route('admin.communications.contact-messages.show', $contactMessage)
            ->with('success', 'Pesan ditandai sedang diproses.');
    }

    public function contactMessagesMarkDone(ContactMessage $contactMessage): RedirectResponse
    {
        $this->workspaceService->markContactMessageDone($contactMessage, auth()->id());

        return redirect()
            ->route('admin.communications.contact-messages.show', $contactMessage)
            ->with('success', 'Pesan ditandai selesai.');
    }

    public function contactMessagesArchive(ContactMessage $contactMessage): RedirectResponse
    {
        $this->workspaceService->archiveContactMessage($contactMessage);

        return redirect()
            ->route('admin.communications.contact-messages.show', $contactMessage)
            ->with('success', 'Pesan berhasil diarsipkan.');
    }

    public function contactMessagesDestroy(ContactMessage $contactMessage): RedirectResponse
    {
        $this->workspaceService->deleteContactMessage($contactMessage);

        return redirect()
            ->route('admin.communications.contact-messages.index')
            ->with('success', 'Pesan berhasil dihapus.');
    }

    public function supportContactEdit(): Response
    {
        return inertia('Admin/CommunicationSettings/SupportContact', $this->workspaceService
            ->supportContactEditPayload());
    }

    public function supportContactUpdate(UpdateSupportContactSettingRequest $request): RedirectResponse
    {
        $this->workspaceService->saveSupportContactSetting($request->validated());

        return redirect()
            ->route('admin.communications.support-contact.edit')
            ->with('success', 'Kontak support berhasil diperbarui.');
    }
}
