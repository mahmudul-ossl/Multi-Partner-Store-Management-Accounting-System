<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\NotificationFeed;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $perPage = 15;
        $page = max(1, (int) $request->integer('page', 1));
        $query = $request->user()->notifications()->latest();
        $total = (clone $query)->count();
        $rows = (clone $query)->forPage($page, $perPage)->get();

        return Inertia::render('Notifications/Index', [
            'notifications' => [
                'data' => $rows->map(fn ($notification): array => NotificationFeed::present($notification))->all(),
                'current_page' => $page,
                'last_page' => max(1, (int) ceil($total / $perPage)),
                'from' => $total === 0 ? null : (($page - 1) * $perPage) + 1,
                'to' => $total === 0 ? null : min($total, $page * $perPage),
                'total' => $total,
                'per_page' => $perPage,
            ],
        ]);
    }

    public function read(Request $request, string $notification): RedirectResponse
    {
        $alert = $request->user()?->notifications()->whereKey($notification)->firstOrFail();
        $alert->markAsRead();

        return back();
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()?->unreadNotifications->markAsRead();

        return back();
    }
}
