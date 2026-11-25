<header {{ $attributes->merge(['class' => 'relative z-20 border-b border-sage-200 bg-cream-100/90 backdrop-blur-md dark:border-white/10 dark:bg-forest-950/90']) }}>
    <div class="mx-auto flex w-full max-w-[96rem] min-w-0 flex-wrap items-center justify-between gap-x-3 gap-y-2 px-4 py-2.5 sm:px-6 lg:px-8">
        <x-logo class="shrink-0" />

        <div class="flex w-full min-w-0 flex-wrap items-center justify-end gap-1.5 sm:gap-2 lg:w-auto lg:flex-1">
            @isset($navigation)
                <details class="group lg:hidden">
                    <summary class="inline-flex cursor-pointer list-none items-center gap-2 rounded-full border border-sage-300 bg-white/70 px-2.5 py-1.5 text-sm font-medium text-forest-800 focus-ring dark:border-white/15 dark:bg-forest-900/70 dark:text-mint-100 [&::-webkit-details-marker]:hidden sm:px-3 sm:py-2">
                        {{ __('homepage.menu') }}
                        <svg class="size-4 transition group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="m6 9 6 6 6-6" />
                        </svg>
                    </summary>

                    <div class="absolute inset-x-4 top-full z-30 mx-auto mt-1 max-h-[calc(100dvh-7rem)] w-auto max-w-sm overflow-y-auto overscroll-contain rounded-3xl border border-white/80 bg-cream-100/95 p-3 shadow-soft backdrop-blur-xl dark:border-white/10 dark:bg-forest-900/95 sm:inset-x-6 [&_a]:w-full [&_a]:justify-start [&_button]:w-full [&_button]:justify-start [&_form]:w-full">
                        <nav class="flex flex-col gap-1 text-sm font-medium" aria-label="{{ __('Primary') }}">
                            {{ $navigation }}
                        </nav>
                    </div>
                </details>

                <nav class="hidden min-w-0 flex-1 flex-wrap items-center justify-end gap-x-1 gap-y-1 text-sm lg:flex xl:gap-x-2" aria-label="{{ __('Primary') }}">
                    {{ $navigation }}
                </nav>
            @endisset

            <div class="flex shrink-0 items-center gap-1.5 sm:gap-2">
                @auth
                    <x-notification-bell
                        :unread-count="$unreadNotificationsCount ?? 0"
                        :notifications="$notificationDropdownItems ?? collect()"
                    />
                @endauth
                <x-theme-switcher />
                <div class="flex items-center gap-1">
                    <x-locale-switcher compact />
                    @auth
                        <form method="POST" action="{{ route('logout') }}" data-desktop-logout class="hidden items-center lg:flex">
                            @csrf
                            <button type="submit" class="inline-flex items-center justify-center rounded-full px-2 py-1.5 text-sm font-medium transition focus-ring text-forest-700 hover:bg-sage-100 hover:text-forest-900 dark:text-mint-100 dark:hover:bg-forest-800 xl:px-3 xl:py-2">{{ __('Logout') }}</button>
                        </form>
                    @endauth
                </div>
            </div>
        </div>
    </div>
</header>
