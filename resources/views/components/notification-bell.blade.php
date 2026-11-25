@props([
    'unreadCount' => 0,
    'notifications' => null,
])

@php
    $notificationItems = $notifications ?? collect();
@endphp

<div
    class="relative z-50"
    data-notification-dropdown
    data-unread-count="{{ $unreadCount }}"
    data-read-label="{{ __('Read') }}"
    data-open="false"
>
    <button
        type="button"
        {{ $attributes->merge(['class' => 'relative inline-flex size-9 items-center justify-center rounded-full border border-sage-200 bg-cream-50/90 text-forest-700 shadow-sm transition hover:bg-white hover:shadow-md focus-ring dark:border-white/15 dark:bg-forest-900/80 dark:text-mint-100 dark:hover:bg-forest-800']) }}
        aria-label="{{ __('Notifications') }}"
        aria-haspopup="true"
        aria-expanded="false"
        aria-controls="notification-dropdown-panel"
        data-notification-toggle
    >
        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/>
            <path d="M10 21h4"/>
        </svg>

        @if ($unreadCount > 0)
            <span
                class="absolute -right-1 -top-1 grid min-w-5 place-items-center rounded-full bg-blush-600 px-1.5 py-0.5 text-xs font-semibold leading-tight text-white dark:bg-blush-500"
                data-notification-badge="true"
                data-unread-count="{{ $unreadCount }}"
                aria-hidden="true"
            >
                {{ $unreadCount > 9 ? '9+' : $unreadCount }}
            </span>
        @endif
    </button>

    <div
        id="notification-dropdown-panel"
        role="region"
        aria-label="{{ __('Recent notifications') }}"
        class="pointer-events-none absolute left-1/2 top-full z-50 mt-2 max-h-[19rem] w-[min(24rem,calc(100vw-2rem))] max-w-[calc(100vw-2rem)] -translate-x-1/2 -translate-y-2 scale-95 origin-top overflow-hidden rounded-2xl border border-white/80 bg-cream-100/95 opacity-0 shadow-lift backdrop-blur-xl transition duration-200 ease-out motion-reduce:transition-none dark:border-white/10 dark:bg-forest-900/95"
        data-notification-panel
        hidden
    >
        <div class="flex items-center justify-between gap-3 border-b border-sage-200 px-3.5 py-2.5 dark:border-white/10">
            <p class="text-xs font-semibold uppercase tracking-widest text-forest-700 dark:text-mint-200">{{ __('Recent notifications') }}</p>
        </div>

        <div
            class="max-h-[14rem] divide-y divide-sage-200/70 overflow-y-auto overscroll-contain dark:divide-white/10"
            data-notification-list
        >
            @forelse ($notificationItems as $notificationItem)
                @php
                    $notificationData = is_array($notificationItem->data) ? $notificationItem->data : [];
                    $message = __($notificationData['key'] ?? '', $notificationData['params'] ?? []);
                    $reason = $notificationData['params']['reason'] ?? null;
                    $destination = \App\Notifications\MarketplaceNotification::safeDestination($notificationData['url'] ?? null);
                @endphp

                <form
                    method="POST"
                    action="{{ route('notifications.read', $notificationItem->id) }}"
                    data-notification-form
                    data-notification-unread="{{ $notificationItem->read_at === null ? 'true' : 'false' }}"
                >
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="redirect_to" value="{{ $destination }}">

                    <button
                        type="submit"
                        data-notification-button
                        @class([
                            'group flex w-full items-start gap-2.5 px-3.5 py-2.5 text-left transition focus-ring',
                            'bg-mint-100/70 hover:bg-mint-100 dark:bg-forest-800/60 dark:hover:bg-forest-800' => $notificationItem->read_at === null,
                            'hover:bg-cream-100/80 dark:hover:bg-forest-800/50' => $notificationItem->read_at !== null,
                        ])
                    >
                        <span
                            data-notification-dot
                            @class([
                                'mt-1.5 size-2 shrink-0 rounded-full',
                                'bg-blush-600 dark:bg-blush-500' => $notificationItem->read_at === null,
                                'bg-sage-300 dark:bg-sage-600' => $notificationItem->read_at !== null,
                            ])
                            aria-hidden="true"
                        ></span>

                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-xs font-semibold text-forest-900 transition group-hover:text-forest-600 dark:text-mint-100 dark:group-hover:text-mint-300">
                                {{ $message !== '' ? $message : __('Notifications') }}
                            </span>

                            @if (is_string($reason) && $reason !== '')
                                <span class="mt-0.5 block truncate text-xs text-sage-700 dark:text-mint-100/70">{{ $reason }}</span>
                            @endif

                            <span class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-xs text-sage-600 dark:text-mint-100/60">
                                <time datetime="{{ $notificationItem->created_at?->toIso8601String() }}">
                                    {{ $notificationItem->created_at?->diffForHumans() }}
                                </time>
                                <span
                                    data-notification-status
                                    @class([
                                        'rounded-full px-1.5 py-0.5 font-medium',
                                        'bg-blush-100 text-blush-800 dark:bg-blush-900/40 dark:text-blush-200' => $notificationItem->read_at === null,
                                        'bg-sage-100 text-sage-800 dark:bg-forest-800 dark:text-mint-100' => $notificationItem->read_at !== null,
                                    ])
                                >
                                    {{ $notificationItem->read_at === null ? __('Unread') : __('Read') }}
                                </span>
                            </span>
                        </span>
                    </button>
                </form>
            @empty
                <p class="px-4 py-6 text-center text-xs text-sage-700 dark:text-mint-100/70">{{ __('No recent notifications.') }}</p>
            @endforelse
        </div>
    </div>
</div>

<script>
    (() => {
        const root = document.querySelector('[data-notification-dropdown]');
        if (! root) return;

        const toggle = root.querySelector('[data-notification-toggle]');
        const panel = root.querySelector('[data-notification-panel]');
        if (! toggle || ! panel) return;

        const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
        const openClasses = ['opacity-100', 'scale-100', 'translate-y-0'];
        const closedClasses = ['opacity-0', 'scale-95', '-translate-y-2', 'pointer-events-none'];
        let closeTimer = null;

        const setOpen = (open) => {
            if (closeTimer !== null) {
                window.clearTimeout(closeTimer);
                closeTimer = null;
            }

            root.dataset.open = open ? 'true' : 'false';
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');

            if (open) {
                panel.hidden = false;
                window.requestAnimationFrame(() => {
                    panel.classList.remove(...closedClasses);
                    panel.classList.add(...openClasses);
                });

                return;
            }

            panel.classList.remove(...openClasses);
            panel.classList.add(...closedClasses);

            if (reduceMotion.matches) {
                panel.hidden = true;

                return;
            }

            closeTimer = window.setTimeout(() => {
                if (root.dataset.open !== 'true') {
                    panel.hidden = true;
                }

                closeTimer = null;
            }, 200);
        };

        const refreshUnreadBadge = (form) => {
            if (form.dataset.notificationUnread !== 'true') {
                return;
            }

            const currentCount = Number(root.dataset.unreadCount ?? 0);
            const nextCount = Math.max(0, currentCount - 1);
            const badge = root.querySelector('[data-notification-badge]');
            const dot = form.querySelector('[data-notification-dot]');
            const status = form.querySelector('[data-notification-status]');
            const button = form.querySelector('[data-notification-button]');

            root.dataset.unreadCount = String(nextCount);
            form.dataset.notificationUnread = 'false';

            if (badge) {
                badge.dataset.unreadCount = String(nextCount);
                badge.textContent = nextCount > 9 ? '9+' : String(nextCount);

                if (nextCount === 0) {
                    badge.remove();
                }
            }

            if (dot) {
                dot.classList.remove('bg-blush-600', 'dark:bg-blush-500');
                dot.classList.add('bg-sage-300', 'dark:bg-sage-600');
            }

            if (status) {
                status.textContent = root.dataset.readLabel;
                status.classList.remove('bg-blush-100', 'text-blush-800', 'dark:bg-blush-900/40', 'dark:text-blush-200');
                status.classList.add('bg-sage-100', 'text-sage-800', 'dark:bg-forest-800', 'dark:text-mint-100');
            }

            if (button) {
                button.classList.remove('bg-mint-100/70', 'dark:bg-forest-800/60');
            }
        };

        if (root.dataset.open === 'true') {
            setOpen(true);
        }

        toggle.addEventListener('click', () => {
            setOpen(toggle.getAttribute('aria-expanded') !== 'true');
        });

        document.addEventListener('click', (event) => {
            if (! root.contains(event.target)) {
                setOpen(false);
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && toggle.getAttribute('aria-expanded') === 'true') {
                setOpen(false);
                toggle.focus();
            }
        });

        root.querySelectorAll('[data-notification-form]').forEach((form) => {
            form.addEventListener('submit', (event) => {
                if (form.dataset.submitted === 'true') {
                    return;
                }

                event.preventDefault();
                form.dataset.submitted = 'true';

                refreshUnreadBadge(form);
                setOpen(false);

                const destination = form.querySelector('input[name="redirect_to"]')?.value ?? '';
                const token = form.querySelector('input[name="_token"]')?.value ?? '';

                fetch(form.action, {
                    method: 'PATCH',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'text/html',
                        'X-CSRF-TOKEN': token,
                    },
                    body: new FormData(form),
                }).then((response) => {
                    window.location.assign(response.url || destination);
                }).catch(() => {
                    window.location.assign(destination);
                });
            });
        });
    })();
</script>
