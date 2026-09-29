<?php

declare(strict_types=1);

namespace App\Http\Requests\Approvals;

use App\Models\ApprovalRequest;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class RejectRequest extends FormRequest
{
    public function authorize(): bool
    {
        $approval = $this->route('approval');

        if (! $approval instanceof ApprovalRequest) {
            return false;
        }

        $response = Gate::inspect('reject', $approval);

        if ($response->denied()) {
            throw new AuthorizationException($response->message() ?: 'This action is unauthorized.');
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'comment' => ['required', 'string', 'min:3', 'max:1000'],
        ];
    }
}
