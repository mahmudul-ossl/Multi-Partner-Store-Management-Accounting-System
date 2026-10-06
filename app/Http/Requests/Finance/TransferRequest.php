<?php

declare(strict_types=1);

namespace App\Http\Requests\Finance;

use App\Models\PartnerTransfer;
use App\Rules\OpenAccountingPeriod;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class TransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        $response = $this->isMethod('POST')
            ? Gate::inspect('create', PartnerTransfer::class)
            : Gate::inspect('update', $this->route('transfer'));

        if ($response->denied()) {
            throw new AuthorizationException($response->message() ?: 'This action is unauthorized.');
        }

        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_int($this->input('amount'))) {
            $this->merge(['amount' => (string) $this->input('amount')]);
        }

        if ($this->input('note') === '') {
            $this->merge(['note' => null]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'from_partner_id' => ['required', 'integer', 'exists:partners,id'],
            'to_partner_id' => ['required', 'integer', 'exists:partners,id', 'different:from_partner_id'],
            'amount' => ['required', 'regex:/^\d+(\.\d{1,2})?$/', 'not_in:0,0.0,0.00'],
            'transaction_date' => ['required', 'date', new OpenAccountingPeriod],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
