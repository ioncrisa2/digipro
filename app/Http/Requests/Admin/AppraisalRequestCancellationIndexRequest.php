<?php

namespace App\Http\Requests\Admin;

class AppraisalRequestCancellationIndexRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [];
    }

    public function filters(): array
    {
        return $this->filtersFromQuery([
            'q' => '',
            'review_status' => 'all',
            'status_before' => 'all',
        ]);
    }
}
