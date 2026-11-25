@extends('layouts.marketplace')

@section('title', __('Notifications').' - '.config('app.name'))

@section('content')
    <x-page-heading
        :title="__('Notifications')"
        :description="__('Stay up to date with your orders and listings.')"
    />

    <div class="mx-auto max-w-3xl space-y-5">
        @if ($notifications->total() > 0)
            <form method="POST" action="{{ route('notifications.readAll') }}" class="flex justify-end">
                @csrf
                @method('PATCH')
                <x-button type="submit" variant="secondary" class="px-4 py-2 text-sm">{{ __('Mark all as read') }}</x-button>
            </form>
        @endif

        @forelse ($notifications as $notification)
            @php
                $notificationData = is_array($notification->data) ? $notification->data : [];
                $message = __($notificationData['key'] ?? '', $notificationData['params'] ?? []);
                $reason = $notificationData['params']['reason'] ?? null;
            @endphp

            <article @class([
                'brand-card border p-5',
                'border-forest-300 bg-mint-100/70 dark:border-mint-700 dark:bg-forest-900/70' => $notification->read_at === null,
                'border-sage-200 bg-cream-50/70 dark:border-white/10 dark:bg-forest-950/60' => $notification->read_at !== null,
            ])>
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0 flex-1 space-y-2">
                        <a
                            href="{{ \App\Notifications\MarketplaceNotification::safeDestination($notificationData['url'] ?? null) }}"
                            class="block text-base font-medium text-forest-900 hover:underline focus-ring dark:text-mint-100"
                        >
                            {{ $message }}
                        </a>

                        @if (is_string($reason) && $reason !== '')
                            <p class="text-base text-sage-700 dark:text-mint-100/70">{{ $reason }}</p>
                        @endif

                        <time
                            datetime="{{ $notification->created_at?->toIso8601String() }}"
                            class="block text-xs text-sage-600 dark:text-mint-100/60"
                        >
                            {{ $notification->created_at?->diffForHumans() }}
                        </time>
                    </div>

                    @if ($notification->read_at === null)
                        <form method="POST" action="{{ route('notifications.read', $notification->id) }}">
                            @csrf
                            @method('PATCH')
                            <x-button type="submit" variant="secondary" class="px-3 py-1.5 text-sm">{{ __('Mark as read') }}</x-button>
                        </form>
                    @else
                        <span class="rounded-full bg-sage-100 px-3 py-1 text-xs font-medium text-sage-800 dark:bg-forest-800 dark:text-mint-100">{{ __('Read') }}</span>
                    @endif
                </div>
            </article>
        @empty
            <x-alert type="status">{{ __('No notifications yet.') }}</x-alert>
        @endforelse

        {{ $notifications->links() }}
    </div>
@endsection
