<?php

namespace App\Exports;

use App\Models\AppraisalUserConsent;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class AppraisalUserConsentExport implements FromQuery, WithHeadings, WithMapping
{
    public function __construct(protected Builder $query) {}

    public function query()
    {
        return $this->query
            ->with([
                'user:id,name,email',
                'document:id,title',
            ])
            ->select([
                'id',
                'user_id',
                'consent_document_id',
                'code',
                'version',
                'hash',
                'accepted_at',
                'ip',
                'user_agent',
            ]);
    }

    public function headings(): array
    {
        return [
            'user_name',
            'user_email',
            'document_title',
            'code',
            'version',
            'hash',
            'accepted_at',
            'ip',
            'user_agent',
        ];
    }

    public function map($row): array
    {
        /** @var AppraisalUserConsent $row */
        return [
            $row->user?->name,
            $row->user?->email,
            $row->document?->title,
            $row->code,
            $row->version,
            $row->hash,
            $row->accepted_at?->toDateTimeString(),
            $row->ip,
            $row->user_agent,
        ];
    }
}
