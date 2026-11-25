<?php

namespace App\Http\Controllers;

use App\Notifications\MarketplaceNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function __invoke(Request $request): View
    {
        if (! MarketplaceNotification::databaseTableExists()) {
            return view('notifications.index', [
                'notifications' => new LengthAwarePaginator([], 0, 10, 1, ['path' => $request->url()]),
            ]);
        }

        $notifications = $request->user()->notifications()
            ->latest()
            ->paginate(10);

        return view('notifications.index', ['notifications' => $notifications]);
    }

    public function read(Request $request, string $notification): RedirectResponse
    {
        if (! MarketplaceNotification::databaseTableExists()) {
            return back();
        }

        $notificationRecord = $request->user()->notifications()->findOrFail($notification);

        $notificationRecord->markAsRead();

        if (! $request->has('redirect_to')) {
            return back();
        }

        return redirect()->to(MarketplaceNotification::safeDestination($request->string('redirect_to')->toString()));
    }

    public function readAll(Request $request): RedirectResponse
    {
        if (! MarketplaceNotification::databaseTableExists()) {
            return back();
        }

        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back();
    }
}
