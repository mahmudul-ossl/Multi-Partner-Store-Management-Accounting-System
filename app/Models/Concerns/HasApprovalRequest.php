<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Enums\ApprovalStatus;
use App\Enums\DocumentStatus;
use App\Models\ApprovalRequest;
use Illuminate\Database\Eloquent\Relations\MorphOne;

trait HasApprovalRequest
{
    public function approvalRequest(): MorphOne
    {
        return $this->morphOne(ApprovalRequest::class, 'reference');
    }

    public function isEditable(): bool
    {
        $approval = $this->relationLoaded('approvalRequest')
            ? $this->approvalRequest
            : $this->approvalRequest()->first();

        return $this->status === DocumentStatus::Pending
            && $approval instanceof ApprovalRequest
            && $approval->status === ApprovalStatus::Pending
            && (int) $approval->completed_approvals === 0;
    }
}
