<?php

declare(strict_types=1);

namespace App\Http\Requests\Sales;

use App\Models\SalesCancellation;
use App\Rules\OpenAccountingPeriod;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class SalesCancellationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $response = Gate::inspect('create', SalesCancellation::class);

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
            'sale_id' => ['required', 'integer', 'exists:sales,id'],
            'transaction_date' => ['required', 'date', new OpenAccountingPeriod],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
