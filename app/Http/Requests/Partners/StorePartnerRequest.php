<?php

declare(strict_types=1);

namespace App\Http\Requests\Partners;

use App\Models\Partner;

class StorePartnerRequest extends PartnerRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Partner::class) ?? false;
    }
}
