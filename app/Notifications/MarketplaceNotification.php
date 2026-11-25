<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Schema;

abstract class MarketplaceNotification extends Notification
{
    /**
     * @param  array<string, int|string|null>  $params
     */
    public function __construct(
        public readonly string $type,
        public readonly string $translationKey,
        public readonly array $params,
        public readonly string $url,
    ) {}

    public static function databaseTableExists(): bool
    {
        return Schema::hasTable('notifications');
    }

    public static function safeDestination(mixed $url): string
    {
        $fallback = route('notifications.index');

        if (! is_string($url) || trim($url) === '') {
            return $fallback;
        }

        $candidate = trim($url);

        if (str_contains($candidate, '\\') || preg_match('/[\x00-\x1F\x7F]/', $candidate) === 1) {
            return $fallback;
        }

        $parts = parse_url($candidate);

        if ($parts === false || isset($parts['user']) || isset($parts['pass'])) {
            return $fallback;
        }

        if (! isset($parts['host'])) {
            if (isset($parts['scheme']) || str_starts_with($candidate, '//')) {
                return $fallback;
            }

            $path = $parts['path'] ?? '';

            if (! str_starts_with($path, '/') || str_starts_with($path, '//')) {
                return $fallback;
            }

            return self::absoluteInternalDestination($parts);
        }

        $scheme = strtolower($parts['scheme'] ?? '');

        if (! in_array($scheme, ['http', 'https'], true)) {
            return $fallback;
        }

        $candidateOrigin = strtolower($scheme.'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : ''));
        $allowedOrigins = [];

        foreach ([config('app.url'), request()->getSchemeAndHttpHost()] as $origin) {
            if (! is_string($origin) || $origin === '') {
                continue;
            }

            $parsedOrigin = parse_url($origin);

            if ($parsedOrigin === false || ! isset($parsedOrigin['scheme'], $parsedOrigin['host'])) {
                continue;
            }

            $allowedOrigins[] = strtolower($parsedOrigin['scheme'].'://'.$parsedOrigin['host'].(isset($parsedOrigin['port']) ? ':'.$parsedOrigin['port'] : ''));
        }

        if (! in_array($candidateOrigin, $allowedOrigins, true)) {
            return $fallback;
        }

        return self::absoluteInternalDestination($parts);
    }

    /**
     * @param  array<string, string>  $parts
     */
    private static function absoluteInternalDestination(array $parts): string
    {
        $path = $parts['path'] ?? '/';

        if ($path === '') {
            $path = '/';
        }

        $destination = $path;

        if (isset($parts['query'])) {
            $destination .= '?'.$parts['query'];
        }

        if (isset($parts['fragment'])) {
            $destination .= '#'.$parts['fragment'];
        }

        return url($destination);
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function shouldSend(object $notifiable, string $channel): bool
    {
        return self::databaseTableExists();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => $this->type,
            'key' => $this->translationKey,
            'params' => $this->params,
            'url' => $this->url,
        ];
    }
}
