<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Partners\LinkPartnerUser;
use App\Actions\Partners\UnlinkPartnerUser;
use App\Http\Requests\Partners\LinkPartnerUserRequest;
use App\Models\Partner;
use Illuminate\Http\RedirectResponse;

class PartnerUserController extends Controller
{
    public function update(LinkPartnerUserRequest $request, Partner $partner, LinkPartnerUser $action): RedirectResponse
    {
        $action->execute($partner, (int) $request->validated('user_id'));

        return redirect()
            ->route('partners.show', $partner)
            ->with('success', 'Partner user account linked.');
    }

    public function destroy(Partner $partner, UnlinkPartnerUser $action): RedirectResponse
    {
        $this->authorize('update', $partner);

        $action->execute($partner);

        return redirect()
            ->route('partners.show', $partner)
            ->with('success', 'Partner user account unlinked.');
    }
}
