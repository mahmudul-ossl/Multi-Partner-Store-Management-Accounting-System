<?php

declare(strict_types=1);

namespace App\Http\Requests\Accounting;

use App\Exceptions\UnbalancedEntryException;
use App\Models\ManualJournal;
use App\Rules\OpenAccountingPeriod;
use App\Services\Ledger\BalancedEntry;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;
use InvalidArgumentException;

class ManualJournalRequest extends FormRequest
{
    public function authorize(): bool
    {
        $response = $this->isMethod('POST')
            ? Gate::inspect('create', ManualJournal::class)
            : Gate::inspect('update', $this->route('manual_journal'));

        if ($response->denied()) {
            throw new AuthorizationException($response->message() ?: 'This action is unauthorized.');
        }

        return true;
    }

    protected function prepareForValidation(): void
    {
        $lines = $this->input('lines', []);

        if (! is_array($lines)) {
            return;
        }

        foreach ($lines as $index => $line) {
            if (! is_array($line)) {
                continue;
            }

            foreach (['debit', 'credit'] as $side) {
                if (! isset($line[$side]) || $line[$side] === '') {
                    $lines[$index][$side] = '0.00';
                }
            }

            foreach (['financial_account_id', 'partner_id', 'description'] as $field) {
                if (($lines[$index][$field] ?? null) === '') {
                    $lines[$index][$field] = null;
                }
            }
        }

        $this->merge(['lines' => $lines]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'entry_date' => ['required', 'date', new OpenAccountingPeriod],
            'description' => ['required', 'string', 'max:255'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_code' => ['required', 'string', 'exists:chart_of_accounts,code'],
            'lines.*.partner_id' => ['nullable', 'integer', 'exists:partners,id'],
            'lines.*.financial_account_id' => ['nullable', 'integer', 'exists:financial_accounts,id'],
            'lines.*.debit' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
            'lines.*.credit' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $lines = $this->input('lines', []);

            if (! is_array($lines)) {
                return;
            }

            try {
                BalancedEntry::assertBalanced($lines);
            } catch (UnbalancedEntryException|InvalidArgumentException $exception) {
                $validator->errors()->add('lines', $exception->getMessage());
            }
        });
    }
}
