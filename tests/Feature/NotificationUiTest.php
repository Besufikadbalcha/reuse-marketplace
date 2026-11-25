<?php

use App\Models\Order;
use App\Models\User;
use App\Notifications\OrderCancelledNotification;
use App\Notifications\OrderPlacedNotification;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @param  array<string, int|string|null>  $params
 */
function createDropdownNotification(User $user, string $translationKey, array $params, string $url, ?Carbon $createdAt = null, bool $read = false): DatabaseNotification
{
    $timestamp = $createdAt ?? now();

    return $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => OrderPlacedNotification::class,
        'data' => [
            'type' => 'order_placed',
            'key' => $translationKey,
            'params' => $params,
            'url' => $url,
        ],
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
        'read_at' => $read ? $timestamp : null,
    ]);
}

test('notification page renders translated notification messages and links', function () {
    $user = User::factory()->create();
    $order = Order::factory()->for($user, 'buyer')->create(['order_number' => 'RM-NOTIFY01']);

    $user->notify(new OrderPlacedNotification($order));

    $this->actingAs($user)
        ->withSession(['locale' => 'am'])
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertSee('ትዕዛዝዎ RM-NOTIFY01 ተቀመጠ።', false)
        ->assertSee(route('orders.show', $order), false)
        ->assertSee(route('notifications.readAll'), false);
});

test('a user can mark a notification as read', function () {
    $user = User::factory()->create();
    $order = Order::factory()->for($user, 'buyer')->create();

    $user->notify(new OrderPlacedNotification($order));

    $notification = $user->notifications()->firstOrFail();

    expect($notification->read_at)->toBeNull();

    $this->actingAs($user)
        ->patch(route('notifications.read', $notification->id))
        ->assertRedirect();

    expect($notification->fresh()->read_at)->not->toBeNull();
});

test('a user can mark all notifications as read', function () {
    $user = User::factory()->create();
    $order = Order::factory()->for($user, 'buyer')->create();

    $user->notify(new OrderPlacedNotification($order));
    $user->notify(new OrderCancelledNotification($order));

    expect($user->unreadNotifications()->count())->toBe(2);

    $this->actingAs($user)
        ->patch(route('notifications.readAll'))
        ->assertRedirect();

    expect($user->unreadNotifications()->count())->toBe(0);
});

test('a user cannot mark another users notification as read', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $order = Order::factory()->for($owner, 'buyer')->create();

    $owner->notify(new OrderPlacedNotification($order));

    $notification = $owner->notifications()->firstOrFail();

    $this->actingAs($otherUser)
        ->patch(route('notifications.read', $notification->id))
        ->assertNotFound();

    expect($notification->fresh()->read_at)->toBeNull();
});

test('guests are redirected from notification pages', function () {
    $this->get(route('notifications.index'))
        ->assertRedirect(route('login'));

    $this->patch(route('notifications.read', 'missing-notification'))
        ->assertRedirect(route('login'));
});

test('notification page shows an empty state', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertSee('No notifications yet.');
});

test('authenticated header shows the unread notification badge', function () {
    $user = User::factory()->create();
    $order = Order::factory()->for($user, 'buyer')->create();

    $user->notify(new OrderPlacedNotification($order));

    $this->actingAs($user)
        ->get(route('home'))
        ->assertOk()
        ->assertSee(route('notifications.index'), false)
        ->assertSee('aria-label="Notifications"', false)
        ->assertSee('min-w-5', false);
});

test('marking all as read preserves notifications that were already read', function () {
    $user = User::factory()->create();
    $order = Order::factory()->for($user, 'buyer')->create();

    $user->notify(new OrderPlacedNotification($order));
    $user->notify(new OrderCancelledNotification($order));

    $alreadyRead = $user->notifications()->firstOrFail();
    $alreadyRead->markAsRead();
    $originalReadAt = $alreadyRead->read_at;

    expect($user->unreadNotifications()->count())->toBe(1);

    $this->actingAs($user)
        ->patch(route('notifications.readAll'))
        ->assertRedirect();

    expect($user->unreadNotifications()->count())->toBe(0)
        ->and($alreadyRead->fresh()->read_at->equalTo($originalReadAt))->toBeTrue();
});

test('malformed notification ids are handled safely', function (string $notificationId) {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('notifications.read', $notificationId))
        ->assertNotFound();

    expect($user->notifications()->count())->toBe(0);
})->with([
    'non-uuid string' => 'not-a-uuid',
    'unknown uuid' => '00000000-0000-0000-0000-000000000000',
]);

test('authenticated users get an accessible notification dropdown from the bell', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('home'))
        ->assertOk()
        ->assertSee('data-notification-dropdown', false)
        ->assertSee('aria-label="Notifications"', false)
        ->assertSee('aria-haspopup="true"', false)
        ->assertSee('aria-expanded="false"', false)
        ->assertSee('aria-controls="notification-dropdown-panel"', false)
        ->assertSee('role="region"', false)
        ->assertSee('data-notification-panel', false)
        ->assertSee(__('Recent notifications'))
        ->assertSee(__('No recent notifications.'))
        ->assertDontSee(__('View all notifications'))
        ->assertDontSee(__('Mark all as read'))
        ->assertSee('data-notification-list', false)
        ->assertSee('max-h-[14rem]', false)
        ->assertSee('overflow-y-auto', false)
        ->assertSee('w-[min(24rem,calc(100vw-2rem))]', false)
        ->assertSee('max-w-[calc(100vw-2rem)]', false)
        ->assertSee('left-1/2', false)
        ->assertSee('-translate-x-1/2', false)
        ->assertSee('origin-top', false)
        ->assertDontSee('sm:left-auto sm:right-0', false)
        ->assertSee('dark:bg-forest-900/95', false)
        ->assertSee('transition duration-200', false)
        ->assertSee('motion-reduce:transition-none', false)
        ->assertSee('root.contains(event.target)', false)
        ->assertSee("event.key === 'Escape'", false);
});

test('the notification dropdown shows only the authenticated users notifications', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    createDropdownNotification($user, 'Your order :order was placed.', ['order' => 'RM-OWN'], route('orders.index'));
    createDropdownNotification($otherUser, 'Your order :order was placed.', ['order' => 'RM-FOREIGN'], route('orders.index'));

    $this->actingAs($user)
        ->get(route('home'))
        ->assertOk()
        ->assertSee('RM-OWN')
        ->assertDontSee('RM-FOREIGN');
});

test('the notification dropdown shows the newest notifications first', function () {
    $user = User::factory()->create();

    foreach (range(1, 7) as $index) {
        createDropdownNotification(
            $user,
            'Your order :order was placed.',
            ['order' => 'RM-NEWS-'.str_pad((string) $index, 2, '0', STR_PAD_LEFT)],
            route('orders.index'),
            now()->subMinutes(8 - $index),
        );
    }

    $response = $this->actingAs($user)
        ->get(route('home'))
        ->assertOk()
        ->assertSee('<time datetime=', false)
        ->assertSee('RM-NEWS-04')
        ->assertSee('RM-NEWS-01');

    $content = (string) $response->getContent();
    $newestPosition = strpos($content, 'RM-NEWS-07');
    $middlePosition = strpos($content, 'RM-NEWS-04');
    $oldestVisiblePosition = strpos($content, 'RM-NEWS-01');

    expect($newestPosition)->not->toBeFalse()
        ->and($middlePosition)->not->toBeFalse()
        ->and($oldestVisiblePosition)->not->toBeFalse()
        ->and($newestPosition)->toBeLessThan($middlePosition)
        ->and($middlePosition)->toBeLessThan($oldestVisiblePosition);
});

test('the notification list scrolls through more than six recent notifications', function () {
    $user = User::factory()->create();

    foreach (range(1, 8) as $index) {
        createDropdownNotification(
            $user,
            'Your order :order was placed.',
            ['order' => 'RM-SCROLL-'.str_pad((string) $index, 2, '0', STR_PAD_LEFT)],
            route('orders.index'),
            now()->subMinutes(9 - $index),
        );
    }

    $response = $this->actingAs($user)
        ->get(route('home'))
        ->assertOk()
        ->assertSee('data-notification-list', false)
        ->assertSee('overflow-y-auto', false)
        ->assertSee('RM-SCROLL-08')
        ->assertSee('RM-SCROLL-01');

    $content = (string) $response->getContent();

    expect(strpos($content, 'RM-SCROLL-08'))->toBeLessThan(strpos($content, 'RM-SCROLL-01'));
});

test('the bell count and dropdown reflect only unread notifications', function () {
    $user = User::factory()->create();

    createDropdownNotification($user, 'Your order :order was placed.', ['order' => 'RM-UNREAD-A'], route('orders.index'));
    createDropdownNotification($user, 'Your order :order was placed.', ['order' => 'RM-UNREAD-B'], route('orders.index'));
    createDropdownNotification($user, 'Your order :order was placed.', ['order' => 'RM-UNREAD-C'], route('orders.index'));
    createDropdownNotification($user, 'Your order :order was placed.', ['order' => 'RM-READ'], route('orders.index'), null, true);

    $this->actingAs($user)
        ->get(route('home'))
        ->assertOk()
        ->assertSee('data-unread-count="3"', false)
        ->assertDontSee(route('notifications.readAll'), false)
        ->assertDontSee(__('Mark all as read'))
        ->assertSee(__('Unread'))
        ->assertSee(__('Read'));

    expect($user->unreadNotifications()->count())->toBe(3);
});

test('clicking a dropdown notification marks it read and redirects to its safe destination', function () {
    $user = User::factory()->create();
    $notification = createDropdownNotification($user, 'Your order :order was placed.', ['order' => 'RM-CLICK'], route('orders.index'));

    $this->actingAs($user)
        ->get(route('home'))
        ->assertOk()
        ->assertSee('action="'.route('notifications.read', $notification->id).'"', false)
        ->assertSee('value="'.route('orders.index').'"', false)
        ->assertSee('refreshUnreadBadge(form)', false)
        ->assertSee('badge.remove()', false)
        ->assertSee('window.location.assign(response.url || destination)', false);

    $this->actingAs($user)
        ->patch(route('notifications.read', $notification->id), ['redirect_to' => route('orders.index')])
        ->assertRedirect(route('orders.index'));

    expect($notification->fresh()->read_at)->not->toBeNull();
});

test('reading the last unread notification removes the bell badge', function () {
    $user = User::factory()->create();
    $notification = createDropdownNotification($user, 'Your order :order was placed.', ['order' => 'RM-BADGE'], route('orders.index'));

    $this->actingAs($user)
        ->patch(route('notifications.read', $notification->id), ['redirect_to' => route('orders.index')])
        ->assertRedirect(route('orders.index'));

    $this->actingAs($user)
        ->get(route('home'))
        ->assertOk()
        ->assertSee('data-unread-count="0"', false)
        ->assertDontSee('data-notification-badge="true"', false)
        ->assertSee('data-notification-unread="false"', false);
});

test('notification redirects ignore external destinations', function () {
    $user = User::factory()->create();
    $notification = createDropdownNotification($user, 'Your order :order was placed.', ['order' => 'RM-EXTERNAL'], route('orders.index'));

    $this->actingAs($user)
        ->patch(route('notifications.read', $notification->id), ['redirect_to' => 'https://example.com/steal'])
        ->assertRedirect(route('notifications.index'));

    expect($notification->fresh()->read_at)->not->toBeNull();
});

test('mark all as read only affects the authenticated users notifications', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    createDropdownNotification($user, 'Your order :order was placed.', ['order' => 'RM-MINE'], route('orders.index'));
    createDropdownNotification($otherUser, 'Your order :order was placed.', ['order' => 'RM-THEIRS'], route('orders.index'));

    $otherNotification = $otherUser->unreadNotifications()->firstOrFail();

    $this->actingAs($user)
        ->patch(route('notifications.readAll'))
        ->assertRedirect();

    expect($user->unreadNotifications()->count())->toBe(0)
        ->and($otherUser->unreadNotifications()->count())->toBe(1)
        ->and($otherNotification->fresh()->read_at)->toBeNull();
});

test('the dropdown has no view all navigation or mark all control', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('home'))
        ->assertOk()
        ->assertDontSee(__('View all notifications'))
        ->assertDontSee(route('notifications.index'), false)
        ->assertDontSee(route('notifications.readAll'), false);
});

test('guests do not receive notification dropdown data', function () {
    $user = User::factory()->create();
    createDropdownNotification($user, 'Your order :order was placed.', ['order' => 'RM-GUEST-SECRET'], route('orders.index'));

    $this->get(route('home'))
        ->assertOk()
        ->assertDontSee('RM-GUEST-SECRET')
        ->assertDontSee('data-notification-dropdown', false)
        ->assertDontSee('aria-label="Notifications"', false);
});

test('notification links ignore unsafe destinations', function () {
    $user = User::factory()->create();
    createDropdownNotification($user, 'Your order :order was placed.', ['order' => 'RM-SAFE'], 'https://example.com/steal');

    $this->actingAs($user)
        ->get(route('home'))
        ->assertOk()
        ->assertDontSee('https://example.com/steal', false)
        ->assertSee('value="'.route('notifications.index').'"', false);

    $this->actingAs($user)
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertDontSee('https://example.com/steal', false);
});
