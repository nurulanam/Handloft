<?php

namespace App\Support;

use App\Models\AppSetting;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Support\Facades\Schema;

/**
 * Live notifications (Settings → Live notifications): whether new notifications are pushed to
 * browsers over Reverb, and which Reverb app to use.
 *
 * - Off: nothing is sent to Reverb, browsers don't open a socket, and the bell checks for new
 *   notifications every few seconds instead (live_poll_seconds). Database and email notifications
 *   are unaffected either way.
 * - On: the .env Reverb app is used unless a custom one is saved here. Saved values are applied to
 *   config at boot, for the web app and for the Reverb server process itself (`reverb:start` boots
 *   the app too), so the server picks up a changed app id / key / secret on its next restart.
 * - The browser gets its connection details from the page (clientConfig()), never the secret, so no
 *   asset rebuild is needed when they change.
 */
final class LiveUpdates
{
    private static ?AppSetting $settings = null;

    private static bool $loaded = false;

    /** The broadcaster from .env, captured before any override. */
    private static ?string $envDefault = null;

    /** @var array<string, mixed>|null The .env "reverb" connection, captured before any override. */
    private static ?array $envServer = null;

    private static function captureEnv(): void
    {
        self::$envDefault ??= (string) config('broadcasting.default');
        self::$envServer ??= (array) config('broadcasting.connections.reverb');
    }

    /**
     * Whether .env has a Reverb app (id and key) to fall back on.
     */
    public static function envHasCredentials(): bool
    {
        self::captureEnv();

        return filled(self::$envServer['key'] ?? null) && filled(self::$envServer['app_id'] ?? null);
    }

    /**
     * Point the "reverb" connection back at the .env app, for this process (used to test it).
     */
    public static function useEnvServer(): void
    {
        self::captureEnv();
        config(['broadcasting.connections.reverb' => self::$envServer]);
        app(BroadcastManager::class)->purge('reverb');
    }

    private static function settings(): ?AppSetting
    {
        if (! self::$loaded) {
            self::$loaded = true;

            try {
                self::$settings = Schema::hasTable('app_settings') ? AppSetting::query()->first() : null;
            } catch (\Throwable) {
                self::$settings = null;
            }
        }

        return self::$settings;
    }

    /**
     * Forget the cached settings (after they're saved, and between tests).
     */
    public static function forget(): void
    {
        self::$settings = null;
        self::$loaded = false;
    }

    /**
     * Forget everything, including the captured .env values (between tests, which boot a fresh config).
     */
    public static function reset(): void
    {
        self::forget();
        self::$envDefault = null;
        self::$envServer = null;
    }

    public static function enabled(): bool
    {
        self::captureEnv();
        $settings = self::settings();

        if ($settings?->live_updates_enabled !== null) {
            return $settings->live_updates_enabled && self::hasCredentials();
        }

        return self::$envDefault === 'reverb' && self::hasCredentials();
    }

    private static function hasCredentials(): bool
    {
        return self::usesCustomServer() || self::envHasCredentials();
    }

    public static function usesCustomServer(): bool
    {
        return (bool) self::settings()?->hasCustomReverbSettings();
    }

    public static function pollSeconds(): int
    {
        return max(10, (int) (self::settings()?->live_poll_seconds ?: 30));
    }

    public static function applyFromDatabase(): void
    {
        self::captureEnv();
        $settings = self::settings();

        if (! $settings?->hasCustomReverbSettings()) {
            self::useEnvServer();
        } else {
            self::applyServer([
                'app_id' => $settings->reverb_app_id,
                'key' => $settings->reverb_app_key,
                'secret' => $settings->reverb_app_secret,
                'host' => $settings->reverb_host,
                'port' => $settings->reverb_port,
                'scheme' => $settings->reverb_scheme,
            ]);
        }

        config(['broadcasting.default' => self::enabled() ? 'reverb' : 'null']);
        app(BroadcastManager::class)->purge();
    }

    /**
     * Point the "reverb" connection (and the Reverb server's app) at these credentials, for this
     * process. Used at boot for saved settings, and by the Settings page to test unsaved ones.
     *
     * @param  array{app_id: ?string, key: ?string, secret: ?string, host: ?string, port: ?int, scheme: ?string}  $server
     */
    public static function applyServer(array $server): void
    {
        $scheme = $server['scheme'] ?: 'https';
        $options = [
            'host' => $server['host'] ?: '127.0.0.1',
            'port' => (int) ($server['port'] ?: ($scheme === 'https' ? 443 : 8080)),
            'scheme' => $scheme,
            'useTLS' => $scheme === 'https',
        ];

        config([
            'broadcasting.connections.reverb.app_id' => $server['app_id'],
            'broadcasting.connections.reverb.key' => $server['key'],
            'broadcasting.connections.reverb.secret' => $server['secret'],
            'broadcasting.connections.reverb.options' => array_merge((array) config('broadcasting.connections.reverb.options'), $options),
            'reverb.apps.apps.0.app_id' => $server['app_id'],
            'reverb.apps.apps.0.key' => $server['key'],
            'reverb.apps.apps.0.secret' => $server['secret'],
        ]);

        app(BroadcastManager::class)->purge('reverb');
    }

    /**
     * What the browser needs to connect: public values only (the app key is public by design;
     * the secret never leaves the server).
     *
     * @return array{enabled: bool, key: ?string, host: ?string, port: ?int, scheme: ?string, poll: int}
     */
    public static function clientConfig(): array
    {
        $settings = self::settings();
        $custom = $settings?->hasCustomReverbSettings();
        $client = (array) config('broadcasting.connections.reverb.client');

        return [
            'enabled' => self::enabled(),
            'key' => $custom ? $settings->reverb_app_key : (self::$envServer['key'] ?? config('broadcasting.connections.reverb.key')),
            'host' => ($custom ? $settings->reverb_client_host : null) ?: ($client['host'] ?? null) ?: null,
            'port' => (int) (($custom ? $settings->reverb_client_port : null) ?: ($client['port'] ?? null)) ?: null,
            'scheme' => ($custom ? $settings->reverb_client_scheme : null) ?: ($client['scheme'] ?? null) ?: null,
            'poll' => self::pollSeconds(),
        ];
    }
}
