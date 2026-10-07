<?php

use App\Mail\SmtpTestMail;
use App\Models\AppSetting;
use App\Notifications\MailTemplatePreview;
use App\Support\MailSettings;
use App\Support\LiveUpdates;
use App\Support\PermissionCatalog;
use Illuminate\Support\Facades\Broadcast;
use App\Support\WorkSchedule;
use Spatie\Permission\Models\Role as RoleModel;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Settings')] class extends Component
{
    #[Url]
    public string $tab = 'appearance';

    /** @var array<int, string> Off weekdays as strings ("0" = Sunday … "6" = Saturday), for checkbox binding. */
    public array $off_days = [];

    public string $week_starts_on = '1';

    public string $daily_hours_target = '8';

    public bool $show_loading_screen = true;

    public int $loading_screen_seconds = 3;

    public int $loading_screen_opacity = 10;

    public int $loading_screen_blur = 64;

    public string $loading_screen_style = 'jampe';

    public string $mail_host = '';

    public string $mail_port = '';

    public string $mail_username = '';

    public string $mail_password = '';

    public string $mail_encryption = 'tls';

    public string $mail_from_address = '';

    public string $mail_from_name = '';

    public string $test_email = '';

    public string $mail_brand_color = '#10512a';

    public string $mail_button_text_color = '#ffffff';

    public string $mail_footer_note = '';

    public string $mail_layout = 'modern';

    public string $template_preview_email = '';

    // Live notifications
    public bool $live_enabled = false;

    public int $live_poll_seconds = 30;

    /** Use a Reverb app configured here instead of the one in .env. */
    public bool $live_custom = false;

    public string $reverb_app_id = '';

    public string $reverb_app_key = '';

    /** Write-only: never sent back to the browser; blank keeps the saved secret. */
    public string $reverb_app_secret = '';

    public bool $reverb_secret_saved = false;

    public string $reverb_host = '';

    public string $reverb_port = '';

    public string $reverb_scheme = 'https';

    public string $reverb_client_host = '';

    public string $reverb_client_port = '';

    public string $reverb_client_scheme = '';

    /** @var array{ok: bool, message: string}|null The last "Test connection" result. */
    public ?array $liveTest = null;

    /** @var array<string, list<string>> Roles & permissions: role name => granted (editable) permissions. */
    public array $grants = [];

    /**
     * Everyone can open Settings for the Look tab (their own, per-browser appearance); the other tabs
     * are app-wide and for Super Admins (manage-settings) only. Their values are only loaded for them,
     * so nothing like the SMTP setup ever reaches anyone else's browser.
     */
    public function canManage(): bool
    {
        return auth()->user()->can('manage-settings');
    }

    public function mount(): void
    {
        if (! $this->canManage()) {
            $this->tab = 'appearance';

            return;
        }

        $settings = AppSetting::current();

        $this->grants = $this->savedGrants();

        $this->live_enabled = LiveUpdates::enabled();
        $this->live_poll_seconds = LiveUpdates::pollSeconds();
        $this->live_custom = $settings->hasCustomReverbSettings();
        $this->reverb_app_id = (string) $settings->reverb_app_id;
        $this->reverb_app_key = (string) $settings->reverb_app_key;
        $this->reverb_secret_saved = filled($settings->reverb_app_secret);
        $this->reverb_host = (string) $settings->reverb_host;
        $this->reverb_port = (string) ($settings->reverb_port ?? '');
        $this->reverb_scheme = $settings->reverb_scheme ?: 'https';
        $this->reverb_client_host = (string) $settings->reverb_client_host;
        $this->reverb_client_port = (string) ($settings->reverb_client_port ?? '');
        $this->reverb_client_scheme = (string) $settings->reverb_client_scheme;

        $this->show_loading_screen = $settings->show_loading_screen;
        $this->loading_screen_seconds = $settings->loading_screen_seconds;
        $this->loading_screen_opacity = $settings->loading_screen_opacity;
        $this->loading_screen_blur = $settings->loading_screen_blur;
        $this->loading_screen_style = $settings->loading_screen_style ?: 'jampe';

        $this->mail_host = (string) $settings->mail_host;
        $this->mail_port = (string) ($settings->mail_port ?? '');
        $this->mail_username = (string) $settings->mail_username;
        // The saved password is never sent back to the browser — leave blank
        // and only change it if the admin types a new one.
        $this->mail_encryption = $settings->mail_encryption ?: 'tls';
        $this->mail_from_address = (string) $settings->mail_from_address;
        $this->mail_from_name = (string) $settings->mail_from_name;

        $this->test_email = auth()->user()->email;

        $this->mail_brand_color = $settings->mailBrandColor();
        $this->mail_button_text_color = $settings->mailButtonTextColor();
        $this->mail_footer_note = (string) $settings->mail_footer_note;
        $this->mail_layout = \App\Support\MailTheme::current()['layout'];
        $this->template_preview_email = auth()->user()->email;

        $this->off_days = array_map('strval', WorkSchedule::offDays());
        $this->week_starts_on = (string) WorkSchedule::weekStartsOn();
        $this->daily_hours_target = WorkSchedule::dailyTarget() !== null ? rtrim(rtrim(number_format(WorkSchedule::dailyTarget(), 2), '0'), '.') : '';
    }

    /**
     * Quick presets for the off-day picker.
     */
    public function setOffDays(string $preset): void
    {
        $this->off_days = match ($preset) {
            'sat-sun' => ['6', '0'],
            'fri' => ['5'],
            'fri-sat' => ['5', '6'],
            'sun' => ['0'],
            default => [],
        };
        $this->resetErrorBag('off_days');
    }

    public function saveSchedule(): void
    {
        abort_unless(auth()->user()->can('manage-settings'), 403);

        $data = $this->validate([
            'off_days' => ['array', 'max:6'],
            'off_days.*' => ['integer', 'between:0,6', 'distinct'],
            'week_starts_on' => ['required', Rule::in(['0', '1', '6'])],
            'daily_hours_target' => ['nullable', 'numeric', 'between:0.5,24'],
        ], [
            'off_days.max' => 'Keep at least one working day in the week.',
        ]);

        AppSetting::current()->update([
            'off_days' => collect($data['off_days'] ?? [])->map(fn ($day) => (int) $day)->unique()->sort()->values()->all(),
            'week_starts_on' => (int) $data['week_starts_on'],
            'daily_hours_target' => $data['daily_hours_target'] !== null && $data['daily_hours_target'] !== '' ? (float) $data['daily_hours_target'] : null,
        ]);

        $this->dispatch('notify', message: 'Work schedule saved.', type: 'success');
    }

    public function saveLoadingScreen(): void
    {
        abort_unless(auth()->user()->can('manage-settings'), 403);

        $data = $this->validate([
            'show_loading_screen' => ['boolean'],
            'loading_screen_seconds' => ['required', 'integer', 'min:1', 'max:10'],
            'loading_screen_opacity' => ['required', 'integer', 'min:0', 'max:100'],
            'loading_screen_blur' => ['required', 'integer', 'min:0', 'max:100'],
            'loading_screen_style' => ['required', Rule::in(['jampe', 'bars', 'handoff', 'spinner'])],
        ]);

        AppSetting::current()->update($data);

        $this->dispatch('notify', message: 'Settings saved.', type: 'success');
    }

    public function saveSmtp(): void
    {
        abort_unless(auth()->user()->can('manage-settings'), 403);

        $data = $this->validate([
            'mail_host' => ['nullable', 'string', 'max:255'],
            'mail_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'mail_username' => ['nullable', 'string', 'max:255'],
            'mail_password' => ['nullable', 'string', 'max:255'],
            'mail_encryption' => ['nullable', Rule::in(['tls', 'ssl', 'none'])],
            'mail_from_address' => ['nullable', 'email', 'max:255'],
            'mail_from_name' => ['nullable', 'string', 'max:255'],
        ]);

        $settings = AppSetting::current();

        $settings->update([
            'mail_host' => $data['mail_host'] ?: null,
            'mail_port' => $data['mail_port'] ?: null,
            'mail_username' => $data['mail_username'] ?: null,
            // Blank means "leave the saved password as-is" — only overwrite
            // it when the admin actually typed a new one.
            'mail_password' => $data['mail_password'] !== '' ? $data['mail_password'] : $settings->mail_password,
            'mail_encryption' => $data['mail_encryption'] !== 'none' ? $data['mail_encryption'] : null,
            'mail_from_address' => $data['mail_from_address'] ?: null,
            'mail_from_name' => $data['mail_from_name'] ?: null,
        ]);

        $this->mail_password = '';

        $this->dispatch('notify', message: 'SMTP settings saved.', type: 'success');
    }

    /**
     * Sends a real test email using whatever is currently in the form —
     * saved or not — so the admin can verify new credentials before
     * committing to them.
     */
    public function testSmtp(): void
    {
        abort_unless(auth()->user()->can('manage-settings'), 403);

        $data = $this->validate([
            'mail_host' => ['required', 'string', 'max:255'],
            'mail_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'mail_username' => ['nullable', 'string', 'max:255'],
            'mail_encryption' => ['nullable', Rule::in(['tls', 'ssl', 'none'])],
            'mail_from_address' => ['nullable', 'email', 'max:255'],
            'mail_from_name' => ['nullable', 'string', 'max:255'],
            'test_email' => ['required', 'email'],
        ]);

        $password = $this->mail_password !== '' ? $this->mail_password : AppSetting::current()->mail_password;

        MailSettings::applyFrom([
            'mail_host' => $data['mail_host'],
            'mail_port' => $data['mail_port'],
            'mail_username' => $data['mail_username'] ?: null,
            'mail_password' => $password,
            'mail_encryption' => $data['mail_encryption'] !== 'none' ? $data['mail_encryption'] : null,
            'mail_from_address' => $data['mail_from_address'] ?: null,
            'mail_from_name' => $data['mail_from_name'] ?: null,
        ]);

        try {
            Mail::to($data['test_email'])->send(new SmtpTestMail);

            $this->dispatch('notify', message: "Test email sent to {$data['test_email']}.", type: 'success');
        } catch (\Throwable $e) {
            report($e);

            $this->dispatch('notify', message: 'Could not send test email: '.$e->getMessage(), type: 'error');
        }
    }

    /**
     * A ready-made brand + button text pair (Settings → Email Template).
     */
    public function applyMailPreset(string $preset): void
    {
        abort_unless($this->canManage(), 403);

        if ($colors = \App\Support\MailTheme::PRESETS[$preset] ?? null) {
            $this->mail_brand_color = $colors['brand'];
            $this->mail_button_text_color = $colors['text'];
            $this->resetErrorBag(['mail_brand_color', 'mail_button_text_color']);
        }
    }

    public function saveMailTemplate(): void
    {
        abort_unless(auth()->user()->can('manage-settings'), 403);

        $data = $this->validate([
            'mail_brand_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'mail_button_text_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'mail_footer_note' => ['nullable', 'string', 'max:255'],
            'mail_layout' => ['required', Rule::in(array_keys(\App\Support\MailTheme::LAYOUTS))],
        ]);

        AppSetting::current()->update([
            'mail_brand_color' => $data['mail_brand_color'],
            'mail_button_text_color' => $data['mail_button_text_color'],
            'mail_footer_note' => $data['mail_footer_note'] ?: null,
            'mail_layout' => $data['mail_layout'],
        ]);

        $this->dispatch('notify', message: 'Email template saved.', type: 'success');
    }

    /**
     * Saves the form's colors (the shared mail template reads them straight
     * from the database at send time, not from this form) and then sends a
     * real notification email built from that same template, so an admin
     * can see exactly what recipients will see.
     */
    public function sendTemplatePreview(): void
    {
        abort_unless(auth()->user()->can('manage-settings'), 403);

        $data = $this->validate([
            'mail_brand_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'mail_button_text_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'mail_footer_note' => ['nullable', 'string', 'max:255'],
            'mail_layout' => ['required', Rule::in(array_keys(\App\Support\MailTheme::LAYOUTS))],
            'template_preview_email' => ['required', 'email'],
        ]);

        AppSetting::current()->update([
            'mail_brand_color' => $data['mail_brand_color'],
            'mail_button_text_color' => $data['mail_button_text_color'],
            'mail_footer_note' => $data['mail_footer_note'] ?: null,
            'mail_layout' => $data['mail_layout'],
        ]);

        try {
            Notification::route('mail', $data['template_preview_email'])->notify(new MailTemplatePreview);

            $this->dispatch('notify', message: "Preview email sent to {$data['template_preview_email']}.", type: 'success');
        } catch (\Throwable $e) {
            report($e);

            $this->dispatch('notify', message: 'Could not send preview email: '.$e->getMessage(), type: 'error');
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function liveRules(): array
    {
        $host = ['regex:/^(?!-)[A-Za-z0-9.-]{1,253}$/'];

        return [
            'live_enabled' => ['boolean'],
            'live_poll_seconds' => ['required', 'integer', Rule::in([15, 30, 60, 120])],
            'live_custom' => ['boolean'],
            'reverb_app_id' => [Rule::requiredIf($this->live_custom), 'nullable', 'string', 'max:100', 'alpha_dash'],
            'reverb_app_key' => [Rule::requiredIf($this->live_custom), 'nullable', 'string', 'max:100', 'alpha_dash'],
            'reverb_app_secret' => [Rule::requiredIf($this->live_custom && ! $this->reverb_secret_saved), 'nullable', 'string', 'max:255'],
            'reverb_host' => [Rule::requiredIf($this->live_custom), 'nullable', 'string', ...$host],
            'reverb_port' => [Rule::requiredIf($this->live_custom), 'nullable', 'integer', 'between:1,65535'],
            'reverb_scheme' => ['required', Rule::in(['http', 'https'])],
            'reverb_client_host' => ['nullable', 'string', ...$host],
            'reverb_client_port' => ['nullable', 'integer', 'between:1,65535'],
            'reverb_client_scheme' => ['nullable', Rule::in(['', 'http', 'https'])],
        ];
    }

    /** @var array<string, string> */
    private const LIVE_MESSAGES = [
        'reverb_host.regex' => 'Enter a host name or IP address only, without http:// or a path.',
        'reverb_client_host.regex' => 'Enter a host name or IP address only, without http:// or a path.',
        'reverb_app_secret.required' => 'Enter the app secret.',
    ];

    /**
     * The custom Reverb server from the form (the saved secret when none was typed).
     *
     * @return array{app_id: string, key: string, secret: ?string, host: string, port: int, scheme: string}
     */
    private function liveServerFromForm(): array
    {
        return [
            'app_id' => $this->reverb_app_id,
            'key' => $this->reverb_app_key,
            'secret' => $this->reverb_app_secret !== '' ? $this->reverb_app_secret : AppSetting::current()->reverb_app_secret,
            'host' => $this->reverb_host,
            'port' => (int) $this->reverb_port,
            'scheme' => $this->reverb_scheme,
        ];
    }

    /** A test result is about the server that was tested; switching between .env and custom clears it. */
    public function updatedLiveCustom(): void
    {
        $this->liveTest = null;
        $this->resetErrorBag();
    }

    public function saveLive(): void
    {
        abort_unless($this->canManage(), 403);

        $this->validate($this->liveRules(), self::LIVE_MESSAGES);

        if ($this->live_enabled && ! $this->live_custom && ! LiveUpdates::envHasCredentials()) {
            $this->addError('live_custom', 'There are no Reverb credentials in .env. Enter a custom server, or add REVERB_APP_ID, REVERB_APP_KEY and REVERB_APP_SECRET to .env.');

            return;
        }

        $settings = AppSetting::current();
        $previousServer = [$settings->reverb_app_id, $settings->reverb_app_key, $settings->reverb_app_secret];

        $settings->update([
            'live_updates_enabled' => $this->live_enabled,
            'live_poll_seconds' => $this->live_poll_seconds,
        ] + ($this->live_custom ? [
            'reverb_app_id' => $this->reverb_app_id,
            'reverb_app_key' => $this->reverb_app_key,
            'reverb_host' => $this->reverb_host,
            'reverb_port' => (int) $this->reverb_port,
            'reverb_scheme' => $this->reverb_scheme,
            'reverb_client_host' => $this->reverb_client_host ?: null,
            'reverb_client_port' => $this->reverb_client_port !== '' ? (int) $this->reverb_client_port : null,
            'reverb_client_scheme' => $this->reverb_client_scheme ?: null,
        ] + ($this->reverb_app_secret !== '' ? ['reverb_app_secret' => $this->reverb_app_secret] : []) : [
            'reverb_app_id' => null, 'reverb_app_key' => null, 'reverb_app_secret' => null,
            'reverb_host' => null, 'reverb_port' => null, 'reverb_scheme' => null,
            'reverb_client_host' => null, 'reverb_client_port' => null, 'reverb_client_scheme' => null,
        ]));

        $serverChanged = $previousServer !== [$settings->reverb_app_id, $settings->reverb_app_key, $settings->reverb_app_secret];

        $this->reverb_app_secret = '';
        $this->reverb_secret_saved = filled($settings->reverb_app_secret);

        LiveUpdates::forget();
        LiveUpdates::applyFromDatabase();

        // Hand the open page its new connection details, so it connects (or disconnects) right away.
        $this->dispatch('live-config-changed', config: LiveUpdates::clientConfig());
        $this->dispatch('notify', message: $serverChanged
            ? 'Live notifications saved. Restart the Reverb server so it uses the new app credentials.'
            : 'Live notifications saved.', type: 'success');
    }

    public function testLive(): void
    {
        abort_unless($this->canManage(), 403);

        $this->validate($this->liveRules(), self::LIVE_MESSAGES);

        if ($this->live_custom) {
            LiveUpdates::applyServer($this->liveServerFromForm());
        } else {
            LiveUpdates::useEnvServer();
        }

        if (blank(config('broadcasting.connections.reverb.key'))) {
            $this->liveTest = ['ok' => false, 'message' => 'No Reverb credentials to test. Enter a custom server or add them to .env.'];

            return;
        }

        // A short timeout, so an unreachable server answers in seconds rather than hanging the page.
        config(['broadcasting.connections.reverb.options.timeout' => 5]);
        app(\Illuminate\Broadcasting\BroadcastManager::class)->purge('reverb');

        $started = microtime(true);

        try {
            Broadcast::connection('reverb')->getPusher()->getChannels();
            $this->liveTest = ['ok' => true, 'message' => 'Reverb answered in '.(int) round((microtime(true) - $started) * 1000).' ms. Credentials accepted.'];
        } catch (\Throwable $e) {
            report($e);
            $reason = str($e->getMessage())->squish()->limit(160)->toString();
            $this->liveTest = ['ok' => false, 'message' => 'Couldn\'t reach Reverb: '.($reason ?: class_basename($e)).'. Check that the server is running and the host, port and credentials are right.'];
        }
    }

    /**
     * What Manager and Team Member are granted right now, limited to the editable permissions.
     *
     * @return array<string, list<string>>
     */
    private function savedGrants(): array
    {
        return collect(PermissionCatalog::editableRoles())->mapWithKeys(fn ($role) => [
            $role->value => array_values(array_intersect(
                PermissionCatalog::editable(),
                RoleModel::findOrCreate($role->value)->permissions->pluck('name')->all(),
            )),
        ])->all();
    }

    /**
     * Turning a permission off also turns off the ones that depend on it (e.g. exporting needs reports).
     */
    public function updatedGrants(): void
    {
        foreach (PermissionCatalog::editableRoles() as $role) {
            $this->grants[$role->value] = PermissionCatalog::normalize((array) ($this->grants[$role->value] ?? []));
        }
    }

    public function resetPermissionDefaults(): void
    {
        abort_unless($this->canManage(), 403);

        foreach (PermissionCatalog::editableRoles() as $role) {
            $this->grants[$role->value] = PermissionCatalog::normalize(PermissionCatalog::DEFAULTS[$role->value]);
        }
    }

    public function savePermissions(): void
    {
        abort_unless($this->canManage(), 403);

        $this->validate([
            'grants' => ['array'],
            'grants.*' => ['array'],
            'grants.*.*' => ['string', Rule::in(PermissionCatalog::editable())],
        ]);

        foreach (PermissionCatalog::editableRoles() as $role) {
            $model = RoleModel::findOrCreate($role->value);
            $granted = PermissionCatalog::normalize((array) ($this->grants[$role->value] ?? []));

            // Only the editable permissions change; anything else the role holds (legacy ones) is kept.
            $kept = $model->permissions->pluck('name')->diff(PermissionCatalog::editable())->all();
            $model->syncPermissions([...$kept, ...$granted]);
        }

        $this->grants = $this->savedGrants();
        $this->dispatch('notify', message: 'Permissions saved. They apply right away.', type: 'success');
    }

    public function with(): array
    {
        $saved = $this->canManage() ? $this->savedGrants() : [];
        $changes = 0;
        foreach ($saved as $role => $permissions) {
            $current = (array) ($this->grants[$role] ?? []);
            $changes += count(array_diff($current, $permissions)) + count(array_diff($permissions, $current));
        }

        return [
            'smtpConfigured' => filled(AppSetting::current()->mail_host),
            'permissionChanges' => $changes,
            'mailPreviewUrl' => $this->canManage() && $this->tab === 'mail-template' && preg_match('/^#[0-9A-Fa-f]{6}$/', $this->mail_brand_color) && preg_match('/^#[0-9A-Fa-f]{6}$/', $this->mail_button_text_color)
                ? route('settings.mail-preview', [
                    'layout' => array_key_exists($this->mail_layout, \App\Support\MailTheme::LAYOUTS) ? $this->mail_layout : \App\Support\MailTheme::DEFAULT_LAYOUT,
                    'brand' => $this->mail_brand_color,
                    'text' => $this->mail_button_text_color,
                    'footer' => mb_substr($this->mail_footer_note, 0, 255),
                ])
                : null,
            'liveEnvConfigured' => LiveUpdates::envHasCredentials(),
        ];
    }
};
?>
@php
    $input = 'block w-full rounded-lg border border-zinc-300 bg-surface px-3 py-2 text-sm text-zinc-900 placeholder:text-zinc-400 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand-lime/40';
    $label = 'block text-sm font-medium text-zinc-700';
    $primary = 'inline-flex w-full items-center justify-center gap-2 rounded-lg bg-brand px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-brand/90 disabled:opacity-60 sm:w-auto sm:py-2';
    $secondary = 'inline-flex w-full shrink-0 items-center justify-center gap-2 rounded-lg border border-zinc-300 bg-surface px-4 py-2.5 text-sm font-semibold text-zinc-700 hover:bg-zinc-50 disabled:opacity-60 sm:w-auto sm:py-2';
    $tabs = [
        'appearance' => ['Appearance', 'Look', 'Glass or static, theme colours', '<path d="m14.622 17.897-10.68-2.913"/><path d="M18.376 2.622a1 1 0 1 1 3.002 3.002L17.36 9.643a.5.5 0 0 0 0 .707l.944.944a2.41 2.41 0 0 1 0 3.408l-.944.944a.5.5 0 0 1-.707 0L8.354 7.348a.5.5 0 0 1 0-.707l.944-.944a2.41 2.41 0 0 1 3.408 0l.944.944a.5.5 0 0 0 .707 0z"/><path d="M9 8c-1.804 2.71-3.97 3.46-6.583 3.948a.507.507 0 0 0-.302.819l7.32 8.883a1 1 0 0 0 1.185.204C12.735 20.405 16 16.792 16 15"/>'],
        'schedule' => ['Work Schedule', 'Schedule', 'Days off, week start, hours', '<path d="M21 7.5V6a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h3.5"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h5"/><path d="M17.5 17.5 16 16.3V14"/><circle cx="16" cy="16" r="6"/>'],
        'permissions' => ['Roles & Permissions', 'Access', 'What managers and members can do', '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/>'],
        'live' => ['Live Notifications', 'Live', 'Instant push with Reverb', '<path d="M4.9 19.1C1 15.2 1 8.8 4.9 4.9"/><path d="M7.8 16.2c-2.3-2.3-2.3-6.1 0-8.5"/><circle cx="12" cy="12" r="2"/><path d="M16.2 7.8c2.3 2.3 2.3 6.1 0 8.5"/><path d="M19.1 4.9C23 8.8 23 15.1 19.1 19"/>'],
        'loading-screen' => ['Loading Screen', 'Loading', 'Shown right after sign-in', '<path d="M9.937 15.5A2 2 0 0 0 8.5 14.063l-6.135-1.582a.5.5 0 0 1 0-.962L8.5 9.936A2 2 0 0 0 9.937 8.5l1.582-6.135a.5.5 0 0 1 .963 0L14.063 8.5A2 2 0 0 0 15.5 9.937l6.135 1.581a.5.5 0 0 1 0 .964L15.5 14.063a2 2 0 0 0-1.437 1.437l-1.582 6.135a.5.5 0 0 1-.963 0z"/>'],
        'smtp' => ['Email / SMTP', 'SMTP', 'Server used to send email', '<rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>'],
        'mail-template' => ['Email Template', 'Template', 'Layout, colours and footer', '<circle cx="13.5" cy="6.5" r=".5" fill="currentColor"/><circle cx="17.5" cy="10.5" r=".5" fill="currentColor"/><circle cx="8.5" cy="7.5" r=".5" fill="currentColor"/><circle cx="6.5" cy="12.5" r=".5" fill="currentColor"/><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 0 1 1.668-1.668h1.996c3.051 0 5.555-2.503 5.555-5.554C21.965 6.012 17.461 2 11.994 2z"/>'],
    ];
    $canManage = $this->canManage();
    $activeTab = $canManage ? $tab : 'appearance';
    if (! $canManage) {
        $tabs = array_intersect_key($tabs, ['appearance' => true]);
    }
    $sendIcon = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="M14.536 21.686a.5.5 0 0 0 .937-.024l6.5-19a.496.496 0 0 0-.635-.635l-19 6.5a.5.5 0 0 0-.024.937l7.93 3.18a2 2 0 0 1 1.112 1.11z"/><path d="m21.854 2.147-10.94 10.939"/></svg>';
@endphp

<div class="space-y-4 sm:space-y-6">
    <div>
        <h1 class="text-xl font-semibold text-zinc-900 sm:text-2xl">Settings</h1>
        <p class="hidden text-sm text-zinc-500 sm:block">{{ $canManage ? 'Your own look, plus how '.config('app.name').' works and sends email for everyone.' : 'How '.config('app.name').' looks for you, in this browser.' }}</p>
    </div>

    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:gap-8">
        {{-- Section nav (Super Admins, who see every tab): an icon tab bar on phones, a described side menu from lg up. --}}
        @if (count($tabs) > 1)
        <nav class="grid shrink-0 grid-cols-4 gap-1 sm:grid-cols-7 rounded-xl border border-zinc-200 bg-surface p-1 lg:sticky lg:top-20 lg:flex lg:w-64 lg:flex-col lg:gap-1 lg:p-2" aria-label="Settings sections">
            @foreach ($tabs as $key => [$name, $short, $hint, $icon])
                <button
                    type="button"
                    wire:click="$set('tab', '{{ $key }}')"
                    @class([
                        'flex flex-col items-center gap-1 rounded-lg px-2 py-2 text-xs font-medium transition-colors lg:flex-row lg:items-center lg:gap-3 lg:px-3 lg:py-2.5 lg:text-left',
                        'bg-brand text-white shadow-sm lg:bg-brand/10 lg:text-brand lg:shadow-none' => $activeTab === $key,
                        'text-zinc-600 hover:bg-zinc-100' => $activeTab !== $key,
                    ])
                    @if ($activeTab === $key) aria-current="page" @endif
                >
                    <span @class([
                        'flex size-5 shrink-0 items-center justify-center lg:size-9 lg:rounded-lg',
                        'lg:bg-brand lg:text-white' => $activeTab === $key,
                        'lg:bg-zinc-100 lg:text-zinc-500' => $activeTab !== $key,
                    ])>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4.5">{!! $icon !!}</svg>
                    </span>
                    <span class="min-w-0">
                        <span class="lg:hidden">{{ $short }}</span>
                        <span class="hidden text-sm font-semibold lg:block">{{ $name }}</span>
                        <span class="hidden text-xs font-normal lg:block {{ $activeTab === $key ? 'text-brand/70' : 'text-zinc-400' }}">{{ $hint }}</span>
                    </span>
                </button>
            @endforeach
        </nav>
        @endif

        <div class="min-w-0 flex-1 space-y-4 sm:space-y-6">
            @if ($activeTab === 'appearance')
                {{-- Per person, per browser: kept in localStorage by window.appearance (layouts/partials/appearance),
                     applied the moment it's picked; nothing is sent to the server. One panel, a row per setting,
                     and the options themselves unboxed: the choice is shown by a ring, not another card. --}}
                <div
                    class="rounded-2xl border border-zinc-200 bg-surface"
                    x-data="{ look: appearance.get() }"
                    @appearance-changed.window="look = $event.detail"
                >
                    <div class="px-4 pt-5 sm:px-6">
                        <h2 class="text-base font-semibold text-zinc-900">Appearance</h2>
                        <p class="text-sm text-zinc-500">Applies instantly and is saved in this browser only, so it doesn't change how {{ config('app.name') }} looks for anyone else.</p>
                    </div>

                    <div class="divide-y divide-zinc-100 px-4 sm:px-6">
                        {{-- Visual style --}}
                        <section class="grid gap-4 py-6 md:grid-cols-[12rem_1fr] md:gap-8">
                            <div>
                                <h3 class="text-sm font-semibold text-zinc-900">Visual style</h3>
                                <p class="text-xs text-zinc-500">Frosted glass, or calm solid panels that are lighter on older devices.</p>
                            </div>
                            <div class="grid max-w-md grid-cols-2 gap-4" role="radiogroup" aria-label="Visual style">
                                @foreach (['glass' => 'Liquid glass', 'static' => 'Static'] as $key => $styleName)
                                    <label class="group cursor-pointer">
                                        <input type="radio" name="look-style" value="{{ $key }}" :checked="look.style === '{{ $key }}'" @change="appearance.set('style', '{{ $key }}')" class="peer sr-only">
                                        <span class="style-demo relative block h-20 overflow-hidden rounded-xl ring-1 ring-zinc-200 ring-offset-2 ring-offset-surface transition group-hover:ring-zinc-300 peer-checked:ring-2 peer-checked:ring-brand! peer-focus-visible:ring-2 peer-focus-visible:ring-brand/40 {{ $key === 'glass' ? 'bg-brand' : 'bg-zinc-100' }}">
                                            @if ($key === 'glass')
                                                <span class="absolute -left-4 top-1 size-16 rounded-full bg-brand-lime/70 blur-xl"></span>
                                                <span class="absolute right-2 top-6 size-14 rounded-full bg-emerald-300/70 blur-xl"></span>
                                                <span class="absolute inset-x-3.5 bottom-2.5 top-5 rounded-lg border border-white/50 bg-surface/40 backdrop-blur-md"></span>
                                            @else
                                                <span class="absolute inset-x-3.5 bottom-2.5 top-5 rounded-lg bg-surface shadow-sm"></span>
                                            @endif
                                            <span class="absolute left-6 top-8 h-1.5 w-12 rounded-full {{ $key === 'glass' ? 'bg-surface/80' : 'bg-zinc-200' }}"></span>
                                            <span class="absolute left-6 top-11 h-1.5 w-20 rounded-full {{ $key === 'glass' ? 'bg-surface/60' : 'bg-zinc-100' }}"></span>
                                        </span>
                                        <span class="mt-2.5 flex items-center gap-1.5 text-sm text-zinc-500 group-has-[:checked]:font-medium group-has-[:checked]:text-zinc-900">
                                            {{ $styleName }}
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="hidden size-4 text-brand group-has-[:checked]:block"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" /></svg>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </section>

                        {{-- Theme colour --}}
                        <section class="grid gap-4 py-6 md:grid-cols-[12rem_1fr] md:gap-8">
                            <div>
                                <h3 class="text-sm font-semibold text-zinc-900">Theme colour</h3>
                                <p class="text-xs text-zinc-500">Recolours the logo, buttons, highlights and active menu items.</p>
                            </div>
                            <div class="flex flex-wrap gap-x-4 gap-y-4 sm:gap-x-6" role="radiogroup" aria-label="Theme colour">
                                @foreach (\App\Support\Theme::PRESETS as $key => $preset)
                                    <label class="group flex w-14 cursor-pointer flex-col items-center gap-2" title="{{ $preset['name'] }}">
                                        <input type="radio" name="look-theme" value="{{ $key }}" :checked="look.theme === '{{ $key }}'" @change="appearance.set('theme', '{{ $key }}')" class="peer sr-only">
                                        {{-- Split swatch: the main colour with a wedge of the accent. --}}
                                        <span
                                            class="flex size-10 items-center justify-center rounded-full ring-offset-2 ring-offset-surface transition group-hover:scale-105 peer-checked:ring-2 peer-checked:ring-(--swatch) peer-focus-visible:ring-2 peer-focus-visible:ring-brand/40"
                                            style="--swatch: {{ $preset['brand'] }}; background: linear-gradient(135deg, {{ $preset['brand'] }} 0 62%, {{ $preset['accent'] }} 62% 100%)"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-4 text-white opacity-0 drop-shadow transition-opacity group-has-[:checked]:opacity-100"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" /></svg>
                                        </span>
                                        <span class="text-xs text-zinc-500 group-has-[:checked]:font-medium group-has-[:checked]:text-zinc-900">{{ $preset['name'] }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </section>

                        {{-- Mode --}}
                        <section class="grid gap-4 py-6 md:grid-cols-[12rem_1fr] md:gap-8">
                            <div>
                                <h3 class="text-sm font-semibold text-zinc-900">Mode</h3>
                                <p class="text-xs text-zinc-500">System follows your device's light or dark setting.</p>
                            </div>
                            <div class="self-start">
                                <div class="inline-grid grid-cols-3 gap-1 rounded-xl bg-zinc-100 p-1" role="radiogroup" aria-label="Mode">
                                    @foreach ([
                                        'light' => ['Light', '<circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/>'],
                                        'dark' => ['Dark', '<path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/>'],
                                        'system' => ['System', '<rect width="20" height="14" x="2" y="3" rx="2"/><path d="M8 21h8"/><path d="M12 17v4"/>'],
                                    ] as $key => [$modeName, $modeIcon])
                                        <label class="cursor-pointer">
                                            <input type="radio" name="look-mode" value="{{ $key }}" :checked="look.mode === '{{ $key }}'" @change="appearance.set('mode', '{{ $key }}')" class="peer sr-only">
                                            <span class="flex items-center justify-center gap-1.5 rounded-lg px-3.5 py-1.5 text-sm font-medium text-zinc-500 transition hover:text-zinc-900 peer-checked:bg-surface peer-checked:text-zinc-900 peer-checked:shadow-sm peer-focus-visible:ring-2 peer-focus-visible:ring-brand/30">
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4">{!! $modeIcon !!}</svg>
                                                {{ $modeName }}
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        </section>
                    </div>
                </div>
            @elseif ($activeTab === 'schedule')
                @php
                    $offSelected = collect($off_days)->map(fn ($d) => (int) $d);
                    $workingPerWeek = 7 - $offSelected->unique()->count();
                    $targetValue = is_numeric($daily_hours_target) ? (float) $daily_hours_target : null;
                @endphp
                <form wire:submit="saveSchedule" class="overflow-hidden rounded-xl border border-zinc-200 bg-surface">
                    <div class="border-b border-zinc-100 px-4 py-4 sm:px-6">
                        <h2 class="text-base font-semibold text-zinc-900">Work schedule</h2>
                        <p class="text-sm text-zinc-500">Used by the calendar, dashboard, work history and reports.</p>
                    </div>

                    <div class="divide-y divide-zinc-100">
                        <div class="grid gap-4 px-4 py-5 sm:px-6 lg:grid-cols-3 lg:gap-6">
                            <div>
                                <h3 class="text-sm font-semibold text-zinc-900">Weekly days off</h3>
                                <p class="text-xs text-zinc-500">Shaded in the calendar and reports, and left out of hour targets.</p>
                            </div>
                            <div class="lg:col-span-2">
                                <div class="grid grid-cols-7 gap-1.5">
                                    @foreach (\App\Support\WorkSchedule::DAY_NAMES as $dayNumber => $dayName)
                                        <label class="cursor-pointer">
                                            <input type="checkbox" wire:model.live="off_days" value="{{ $dayNumber }}" class="peer sr-only">
                                            <span class="flex flex-col items-center rounded-xl border-2 border-zinc-200 py-2 text-center transition peer-checked:border-brand peer-checked:bg-brand peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-brand-lime/50">
                                                <span class="text-sm font-semibold">{{ substr($dayName, 0, 3) }}</span>
                                                <span class="text-[10px] opacity-70">{{ $offSelected->contains($dayNumber) ? 'Off' : 'Work' }}</span>
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                                <div class="mt-3 flex flex-wrap items-center gap-1.5 text-xs">
                                    <span class="text-zinc-500">Quick pick:</span>
                                    @foreach (['sat-sun' => 'Sat + Sun', 'fri' => 'Friday only', 'fri-sat' => 'Fri + Sat', 'sun' => 'Sunday only', 'none' => 'No days off'] as $preset => $presetLabel)
                                        <button type="button" wire:click="setOffDays('{{ $preset }}')" class="rounded-full border border-zinc-300 px-2.5 py-1 font-medium text-zinc-600 hover:border-brand hover:text-brand">{{ $presetLabel }}</button>
                                    @endforeach
                                </div>
                                @error('off_days') <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <div class="grid gap-4 px-4 py-5 sm:px-6 lg:grid-cols-3 lg:gap-6">
                            <div>
                                <h3 class="text-sm font-semibold text-zinc-900">Week starts on</h3>
                                <p class="text-xs text-zinc-500">The first column of the calendar, and where "this week" begins.</p>
                            </div>
                            <div class="lg:col-span-2">
                                <div class="grid grid-cols-3 rounded-lg border border-zinc-300 bg-surface p-0.5 sm:inline-grid sm:w-80">
                                    @foreach (['6' => 'Saturday', '0' => 'Sunday', '1' => 'Monday'] as $value => $startLabel)
                                        <label class="cursor-pointer">
                                            <input type="radio" wire:model.live="week_starts_on" value="{{ $value }}" class="peer sr-only">
                                            <span class="block rounded-md py-1.5 text-center text-sm font-medium text-zinc-600 peer-checked:bg-brand peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-brand-lime/50">{{ $startLabel }}</span>
                                        </label>
                                    @endforeach
                                </div>
                                @error('week_starts_on') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <div class="grid gap-4 px-4 py-5 sm:px-6 lg:grid-cols-3 lg:gap-6">
                            <div>
                                <h3 class="text-sm font-semibold text-zinc-900">Daily hours target</h3>
                                <p class="text-xs text-zinc-500">Expected hours per working day. Leave empty for no target.</p>
                            </div>
                            <div class="lg:col-span-2">
                                <div class="flex items-center gap-3">
                                    <div class="relative w-32">
                                        <input wire:model.live.debounce.400ms="daily_hours_target" type="number" min="0.5" max="24" step="0.5" inputmode="decimal" placeholder="—" class="{{ $input }} pr-8">
                                        <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-sm text-zinc-400">h</span>
                                    </div>
                                    <p class="text-sm text-zinc-600">
                                        @if ($targetValue)
                                            {{ $workingPerWeek }} working {{ \Illuminate\Support\Str::plural('day', $workingPerWeek) }} × {{ rtrim(rtrim(number_format($targetValue, 2), '0'), '.') }}h = <b class="text-zinc-900">{{ \App\Support\Duration::forHumans($workingPerWeek * $targetValue) }}</b> a week
                                        @else
                                            {{ $workingPerWeek }} working {{ \Illuminate\Support\Str::plural('day', $workingPerWeek) }} a week, no hours target
                                        @endif
                                    </p>
                                </div>
                                @error('daily_hours_target') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end border-t border-zinc-100 bg-zinc-50/70 px-4 py-3 sm:px-6">
                        <button type="submit" class="{{ $primary }}" wire:loading.attr="disabled" wire:target="saveSchedule">Save work schedule</button>
                    </div>
                </form>
            @elseif ($activeTab === 'permissions')
                @php
                    $roleColumns = \App\Support\PermissionCatalog::editableRoles();
                    $requires = \App\Support\PermissionCatalog::REQUIRES;
                @endphp
                <form wire:submit="savePermissions" class="rounded-2xl border border-zinc-200 bg-surface">
                    <div class="px-4 pt-5 sm:px-6">
                        <h2 class="text-base font-semibold text-zinc-900">Roles &amp; permissions</h2>
                        <p class="text-sm text-zinc-500">Choose what Managers and Team Members can do. Super Admins can always do everything. Changes apply as soon as you save.</p>
                    </div>

                    <div class="mt-4 overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-y border-zinc-100 text-[11px] font-semibold uppercase tracking-wide text-zinc-400">
                                    <th class="px-4 py-2.5 text-left font-semibold sm:px-6">Capability</th>
                                    <th class="hidden w-28 px-2 py-2.5 text-center font-semibold md:table-cell">Super Admin</th>
                                    @foreach ($roleColumns as $role)
                                        <th class="w-24 px-2 py-2.5 text-center font-semibold sm:w-28 {{ $loop->last ? 'pr-4 sm:pr-6' : '' }}">{{ $role->label() }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            @foreach (\App\Support\PermissionCatalog::GROUPS as $group => $permissions)
                                <tbody class="divide-y divide-zinc-100 border-b border-zinc-100">
                                    <tr>
                                        <th colspan="{{ count($roleColumns) + 2 }}" class="bg-zinc-50/70 px-4 py-2 text-left text-xs font-semibold text-zinc-600 sm:px-6">{{ $group }}</th>
                                    </tr>
                                    @foreach ($permissions as $permission => [$permissionLabel, $permissionHint])
                                        <tr wire:key="perm-{{ $permission }}">
                                            <td class="px-4 py-3 sm:px-6">
                                                <p class="font-medium text-zinc-900">{{ $permissionLabel }}</p>
                                                <p class="text-xs text-zinc-500">{{ $permissionHint }}</p>
                                                @isset($requires[$permission])
                                                    <p class="mt-0.5 text-[11px] text-zinc-400">Needs “{{ \App\Support\PermissionCatalog::label($requires[$permission]) }}”.</p>
                                                @endisset
                                            </td>
                                            <td class="hidden px-2 py-3 text-center md:table-cell">
                                                <span class="inline-flex size-6 items-center justify-center rounded-full bg-brand/10 text-brand" title="Super Admins always have this">
                                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-3.5"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" /></svg>
                                                </span>
                                            </td>
                                            @foreach ($roleColumns as $role)
                                                @php $blocked = isset($requires[$permission]) && ! in_array($requires[$permission], $grants[$role->value] ?? [], true); @endphp
                                                <td class="px-2 py-3 text-center {{ $loop->last ? 'pr-4 sm:pr-6' : '' }}">
                                                    <label class="relative inline-flex {{ $blocked ? 'cursor-not-allowed opacity-40' : 'cursor-pointer' }}" title="{{ $blocked ? 'Turn on “'.\App\Support\PermissionCatalog::label($requires[$permission]).'” first' : $role->label().': '.$permissionLabel }}">
                                                        <input type="checkbox" wire:model.live="grants.{{ $role->value }}" value="{{ $permission }}" @disabled($blocked) class="peer sr-only">
                                                        <span class="h-6 w-11 rounded-full bg-zinc-300 transition-colors peer-checked:bg-brand peer-focus-visible:ring-4 peer-focus-visible:ring-brand/20"></span>
                                                        <span class="absolute left-0.5 top-0.5 size-5 rounded-full bg-white shadow-sm transition-transform peer-checked:translate-x-5"></span>
                                                        <span class="sr-only">{{ $role->label() }}: {{ $permissionLabel }}</span>
                                                    </label>
                                                </td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            @endforeach
                            <tbody>
                                @foreach (\App\Support\PermissionCatalog::LOCKED as $permission => [$permissionLabel, $permissionHint])
                                    <tr>
                                        <td class="px-4 py-3 sm:px-6">
                                            <p class="flex items-center gap-1.5 font-medium text-zinc-900">
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-3.5 text-zinc-400"><path fill-rule="evenodd" d="M10 1a4.5 4.5 0 0 0-4.5 4.5V9H5a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-6a2 2 0 0 0-2-2h-.5V5.5A4.5 4.5 0 0 0 10 1Zm3 8V5.5a3 3 0 1 0-6 0V9h6Z" clip-rule="evenodd" /></svg>
                                                {{ $permissionLabel }}
                                            </p>
                                            <p class="text-xs text-zinc-500">{{ $permissionHint }} Always Super Admin only.</p>
                                        </td>
                                        <td class="hidden px-2 py-3 text-center md:table-cell">
                                            <span class="inline-flex size-6 items-center justify-center rounded-full bg-brand/10 text-brand"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-3.5"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" /></svg></span>
                                        </td>
                                        @foreach ($roleColumns as $role)
                                            <td class="px-2 py-3 text-center text-zinc-300 {{ $loop->last ? 'pr-4 sm:pr-6' : '' }}">—</td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- Reset only fills in the defaults; nothing changes until Save. --}}
                    <div class="flex flex-wrap items-center justify-end gap-x-3 gap-y-2 border-t border-zinc-100 px-4 py-3.5 sm:px-6">
                        @if ($permissionChanges > 0)
                            <span class="mr-auto text-xs font-medium text-amber-700 sm:mr-0">{{ $permissionChanges }} unsaved {{ \Illuminate\Support\Str::plural('change', $permissionChanges) }}</span>
                        @endif
                        <button type="button" wire:click="resetPermissionDefaults" class="inline-flex items-center justify-center gap-2 rounded-xl border border-red-200 bg-surface px-4 py-2.5 text-sm font-semibold text-red-600 shadow-xs transition hover:border-red-300 hover:bg-red-50 focus:outline-none focus-visible:ring-4 focus-visible:ring-red-500/15 active:scale-[0.98]">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-4"><path fill-rule="evenodd" d="M7.793 2.232a.75.75 0 0 1-.025 1.06L3.622 7.25h10.003a5.375 5.375 0 0 1 0 10.75H10.75a.75.75 0 0 1 0-1.5h2.875a3.875 3.875 0 0 0 0-7.75H3.622l4.146 3.957a.75.75 0 0 1-1.036 1.085l-5.5-5.25a.75.75 0 0 1 0-1.085l5.5-5.25a.75.75 0 0 1 1.06.025Z" clip-rule="evenodd" /></svg>
                            Reset to defaults
                        </button>
                        <button type="submit" class="btn-primary" @disabled($permissionChanges === 0) wire:loading.attr="disabled" wire:target="savePermissions">Save permissions</button>
                    </div>
                </form>
            @elseif ($activeTab === 'live')
                @php
                    $hostInput = 'field-input';
                    $err = fn (string $field) => $errors->has($field) ? ' field-input-error' : '';
                @endphp
                <form wire:submit="saveLive" class="rounded-2xl border border-zinc-200 bg-surface">
                    <div class="px-4 pt-5 sm:px-6">
                        <h2 class="text-base font-semibold text-zinc-900">Live notifications</h2>
                        <p class="text-sm text-zinc-500">New notifications appear the moment they're sent, pushed by a Laravel Reverb WebSocket server. Stored notifications and emails go out either way.</p>
                    </div>

                    <div class="divide-y divide-zinc-100 px-4 sm:px-6">
                        {{-- On / off + status --}}
                        <section class="flex flex-col gap-4 py-6 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0">
                                <h3 class="text-sm font-semibold text-zinc-900">Push notifications instantly</h3>
                                <p class="text-xs text-zinc-500">When off, nothing is sent to Reverb, browsers don't open a connection, and the bell checks for new notifications on a timer instead.</p>

                                {{-- This browser's connection, read live from Echo. --}}
                                <div
                                    class="mt-3 flex flex-wrap items-center gap-2"
                                    x-data="{ state: 'off', tick() { this.state = window.liveState ? window.liveState() : 'off' } }"
                                    x-init="tick(); setInterval(() => tick(), 1000)"
                                >
                                    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset"
                                        :class="{
                                            'bg-emerald-50 text-emerald-700 ring-emerald-200': state === 'connected',
                                            'bg-amber-50 text-amber-700 ring-amber-200': ['connecting', 'initialized'].includes(state),
                                            'bg-red-50 text-red-700 ring-red-200': ['unavailable', 'failed'].includes(state),
                                            'bg-zinc-100 text-zinc-600 ring-zinc-200': ['off', 'disconnected'].includes(state),
                                        }">
                                        <span class="size-1.5 rounded-full bg-current" :class="state === 'connected' && 'animate-pulse'"></span>
                                        <span x-text="{ connected: 'This browser: connected', connecting: 'This browser: connecting…', initialized: 'This browser: connecting…', unavailable: 'This browser: can\'t reach Reverb', failed: 'This browser: not supported', disconnected: 'This browser: disconnected', off: 'This browser: not connected' }[state] ?? state"></span>
                                    </span>
                                    @if ($liveTest)
                                        <span @class(['inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset', 'bg-emerald-50 text-emerald-700 ring-emerald-200' => $liveTest['ok'], 'bg-red-50 text-red-700 ring-red-200' => ! $liveTest['ok']])>
                                            <span class="size-1.5 rounded-full bg-current"></span>
                                            Server: {{ $liveTest['ok'] ? 'reachable' : 'not reachable' }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                            <label class="relative inline-flex shrink-0 cursor-pointer self-start">
                                <span class="sr-only">Push notifications instantly</span>
                                <input type="checkbox" wire:model.live="live_enabled" class="peer sr-only">
                                <span class="h-7 w-12 rounded-full bg-zinc-300 transition-colors peer-checked:bg-brand peer-focus-visible:ring-4 peer-focus-visible:ring-brand/20"></span>
                                <span class="absolute left-1 top-1 size-5 rounded-full bg-white shadow-sm transition-transform peer-checked:translate-x-5"></span>
                            </label>
                        </section>

                        {{-- Fallback interval --}}
                        <section class="grid gap-3 py-6 md:grid-cols-[14rem_1fr] md:gap-8">
                            <div>
                                <h3 class="text-sm font-semibold text-zinc-900">Fallback check</h3>
                                <p class="text-xs text-zinc-500">How often the bell checks for new notifications {{ $live_enabled ? 'if the live connection drops' : 'while live push is off' }}. Only for open, visible tabs.</p>
                            </div>
                            <div class="self-start">
                                <div class="inline-grid grid-cols-4 gap-1 rounded-xl bg-zinc-100 p-1" role="radiogroup" aria-label="Fallback check">
                                    @foreach ([15 => '15s', 30 => '30s', 60 => '1 min', 120 => '2 min'] as $seconds => $pollLabel)
                                        <label class="cursor-pointer">
                                            <input type="radio" wire:model.live="live_poll_seconds" value="{{ $seconds }}" class="peer sr-only">
                                            <span class="flex items-center justify-center rounded-lg px-3 py-1.5 text-sm font-medium text-zinc-500 transition hover:text-zinc-900 peer-checked:bg-surface peer-checked:text-zinc-900 peer-checked:shadow-sm">{{ $pollLabel }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        </section>

                        {{-- Connection --}}
                        <section class="grid gap-4 py-6 md:grid-cols-[14rem_1fr] md:gap-8">
                            <div>
                                <h3 class="text-sm font-semibold text-zinc-900">Reverb server</h3>
                                <p class="text-xs text-zinc-500">Use the server set in .env, or enter one here. Values saved here take priority.</p>
                            </div>
                            <div class="min-w-0 space-y-5">
                                <div class="inline-grid grid-cols-2 gap-1 rounded-xl bg-zinc-100 p-1" role="radiogroup" aria-label="Reverb server">
                                    @foreach ([0 => 'From .env', 1 => 'Custom'] as $value => $modeLabel)
                                        <label class="cursor-pointer">
                                            <input type="radio" wire:model.live="live_custom" value="{{ $value }}" class="peer sr-only">
                                            <span class="flex items-center justify-center rounded-lg px-4 py-1.5 text-sm font-medium text-zinc-500 transition hover:text-zinc-900 peer-checked:bg-surface peer-checked:text-zinc-900 peer-checked:shadow-sm">{{ $modeLabel }}</span>
                                        </label>
                                    @endforeach
                                </div>
                                @error('live_custom') <p class="text-xs font-medium text-red-600">{{ $message }}</p> @enderror

                                @if (! $live_custom)
                                    <p @class(['flex items-start gap-2 rounded-xl px-3.5 py-3 text-xs', 'bg-emerald-50 text-emerald-800' => $liveEnvConfigured, 'bg-amber-50 text-amber-800' => ! $liveEnvConfigured])>
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="mt-0.5 size-4 shrink-0"><path fill-rule="evenodd" d="M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0Zm-7-4a1 1 0 1 1-2 0 1 1 0 0 1 2 0ZM9 9a.75.75 0 0 0 0 1.5h.253a.25.25 0 0 1 .244.304l-.459 2.066A1.75 1.75 0 0 0 10.747 15H11a.75.75 0 0 0 0-1.5h-.253a.25.25 0 0 1-.244-.304l.459-2.066A1.75 1.75 0 0 0 9.253 9H9Z" clip-rule="evenodd" /></svg>
                                        <span>{{ $liveEnvConfigured ? 'Using REVERB_APP_ID, REVERB_APP_KEY, REVERB_APP_SECRET, REVERB_HOST/PORT/SCHEME from .env (and VITE_REVERB_HOST/PORT/SCHEME for browsers).' : 'No Reverb app in .env yet. Add REVERB_APP_ID, REVERB_APP_KEY and REVERB_APP_SECRET, or choose Custom.' }}</span>
                                    </p>
                                @else
                                    <div>
                                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-zinc-400">App credentials</p>
                                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                            <x-form.field label="App ID" for="reverb_app_id" error="reverb_app_id" required>
                                                <input id="reverb_app_id" wire:model="reverb_app_id" type="text" autocomplete="off" class="{{ $hostInput.$err('reverb_app_id') }} font-mono">
                                            </x-form.field>
                                            <x-form.field label="App key" for="reverb_app_key" error="reverb_app_key" required>
                                                <input id="reverb_app_key" wire:model="reverb_app_key" type="text" autocomplete="off" class="{{ $hostInput.$err('reverb_app_key') }} font-mono">
                                            </x-form.field>
                                            <x-form.field label="App secret" for="reverb_app_secret" error="reverb_app_secret" :required="! $reverb_secret_saved" :hint="$reverb_secret_saved ? 'Saved (encrypted). Leave blank to keep it.' : 'Stored encrypted, never shown again.'">
                                                <input id="reverb_app_secret" wire:model="reverb_app_secret" type="password" autocomplete="new-password" placeholder="{{ $reverb_secret_saved ? '••••••••••••' : '' }}" class="{{ $hostInput.$err('reverb_app_secret') }} font-mono">
                                            </x-form.field>
                                        </div>
                                    </div>

                                    <div>
                                        <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-zinc-400">Server address</p>
                                        <p class="mb-2 text-xs text-zinc-500">Where this app sends notifications to Reverb, often <span class="font-mono">127.0.0.1:8080</span> on the same machine.</p>
                                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-[1fr_8rem_8rem]">
                                            <x-form.field label="Host" for="reverb_host" error="reverb_host" required>
                                                <input id="reverb_host" wire:model="reverb_host" type="text" placeholder="127.0.0.1" autocomplete="off" class="{{ $hostInput.$err('reverb_host') }} font-mono">
                                            </x-form.field>
                                            <x-form.field label="Port" for="reverb_port" error="reverb_port" required>
                                                <input id="reverb_port" wire:model="reverb_port" type="number" min="1" max="65535" placeholder="8080" class="{{ $hostInput.$err('reverb_port') }} tabular-nums">
                                            </x-form.field>
                                            <x-form.field label="Scheme" for="reverb_scheme" error="reverb_scheme">
                                                <select id="reverb_scheme" wire:model="reverb_scheme" class="field-input">
                                                    <option value="http">http</option>
                                                    <option value="https">https</option>
                                                </select>
                                            </x-form.field>
                                        </div>
                                    </div>

                                    <div>
                                        <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-zinc-400">Browser address</p>
                                        <p class="mb-2 text-xs text-zinc-500">Where browsers open the WebSocket, usually your public domain behind a proxy. Leave blank to use this site's own address.</p>
                                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-[1fr_8rem_8rem]">
                                            <x-form.field label="Host" for="reverb_client_host" error="reverb_client_host" optional>
                                                <input id="reverb_client_host" wire:model="reverb_client_host" type="text" placeholder="{{ request()->getHost() }}" autocomplete="off" class="{{ $hostInput.$err('reverb_client_host') }} font-mono">
                                            </x-form.field>
                                            <x-form.field label="Port" for="reverb_client_port" error="reverb_client_port" optional>
                                                <input id="reverb_client_port" wire:model="reverb_client_port" type="number" min="1" max="65535" placeholder="443" class="{{ $hostInput.$err('reverb_client_port') }} tabular-nums">
                                            </x-form.field>
                                            <x-form.field label="Scheme" for="reverb_client_scheme" error="reverb_client_scheme">
                                                <select id="reverb_client_scheme" wire:model="reverb_client_scheme" class="field-input">
                                                    <option value="">Match page</option>
                                                    <option value="http">http (ws)</option>
                                                    <option value="https">https (wss)</option>
                                                </select>
                                            </x-form.field>
                                        </div>
                                    </div>

                                    <p class="flex items-start gap-2 rounded-xl bg-zinc-50 px-3.5 py-3 text-xs text-zinc-600">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="mt-0.5 size-4 shrink-0 text-zinc-400"><path fill-rule="evenodd" d="M15.312 11.424a5.5 5.5 0 0 1-9.201 2.466l-.312-.311h2.433a.75.75 0 0 0 0-1.5H3.989a.75.75 0 0 0-.75.75v4.242a.75.75 0 0 0 1.5 0v-2.43l.31.31a7 7 0 0 0 11.712-3.138.75.75 0 0 0-1.449-.39Zm1.23-3.723a.75.75 0 0 0 .219-.53V2.929a.75.75 0 0 0-1.5 0V5.36l-.31-.31A7 7 0 0 0 3.239 8.188a.75.75 0 1 0 1.448.389A5.5 5.5 0 0 1 13.89 6.11l.311.31h-2.432a.75.75 0 0 0 0 1.5h4.243a.75.75 0 0 0 .53-.219Z" clip-rule="evenodd" /></svg>
                                        <span>The Reverb server reads its app credentials when it starts. After changing the app ID, key or secret, restart it (<span class="font-mono">php artisan reverb:restart</span>, or your process manager).</span>
                                    </p>
                                @endif
                            </div>
                        </section>
                    </div>

                    @if ($liveTest)
                        <div @class(['mx-4 mb-1 flex items-start gap-2 rounded-xl px-3.5 py-3 text-sm sm:mx-6', 'bg-emerald-50 text-emerald-800' => $liveTest['ok'], 'bg-red-50 text-red-800' => ! $liveTest['ok']])>
                            <span class="mt-1.5 size-1.5 shrink-0 rounded-full bg-current"></span>
                            <span>{{ $liveTest['message'] }}</span>
                        </div>
                    @endif

                    <div class="flex flex-wrap items-center justify-end gap-x-3 gap-y-2 border-t border-zinc-100 px-4 py-3.5 sm:px-6">
                        <button type="button" wire:click="testLive" wire:loading.attr="disabled" wire:target="testLive" class="btn-secondary">
                            <svg wire:loading wire:target="testLive" class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity=".3" stroke-width="3"/><path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>
                            Test connection
                        </button>
                        <x-form.submit target="saveLive">Save</x-form.submit>
                    </div>
                </form>
            @elseif ($activeTab === 'loading-screen')
                <form wire:submit="saveLoadingScreen" x-data class="overflow-hidden rounded-xl border border-zinc-200 bg-surface">
                    <div class="flex items-start justify-between gap-4 border-b border-zinc-100 px-4 py-4 sm:px-6">
                        <div>
                            <h2 class="text-base font-semibold text-zinc-900">Loading screen</h2>
                            <p class="text-sm text-zinc-500">A branded overlay shown for a moment right after signing in.</p>
                        </div>
                        <label class="relative mt-0.5 inline-flex shrink-0 cursor-pointer items-center" title="Show loading screen after login">
                            <span class="sr-only">Show loading screen after login</span>
                            <input wire:model="show_loading_screen" type="checkbox" class="peer sr-only">
                            <span class="h-7 w-12 rounded-full bg-zinc-200 transition-colors peer-checked:bg-brand peer-focus-visible:ring-2 peer-focus-visible:ring-brand-lime/50"></span>
                            <span class="absolute left-1 top-1 size-5 rounded-full bg-surface shadow-sm transition-transform peer-checked:translate-x-5"></span>
                        </label>
                    </div>

                    <div class="grid grid-cols-1 gap-6 px-4 py-5 transition-opacity sm:px-6 lg:grid-cols-5" :class="! $wire.show_loading_screen && 'pointer-events-none opacity-40'">
                        {{-- Live preview: a mock dashboard behind the overlay, so transparency and blur are visible. --}}
                        <div class="lg:order-2 lg:col-span-2">
                            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-zinc-400">Preview</p>
                            <div class="relative aspect-[4/3] overflow-hidden rounded-xl border border-zinc-200 bg-zinc-50">
                                <div class="absolute inset-0 flex" aria-hidden="true">
                                    <div class="w-1/5 bg-ink-900 p-2"><div class="size-4 rounded bg-brand-lime"></div><div class="mt-3 space-y-1.5"><div class="h-1.5 rounded bg-brand"></div><div class="h-1.5 w-3/4 rounded bg-ink-700"></div><div class="h-1.5 w-2/3 rounded bg-ink-700"></div><div class="h-1.5 w-3/4 rounded bg-ink-700"></div></div></div>
                                    <div class="flex-1 space-y-2 p-3">
                                        <div class="grid grid-cols-3 gap-2"><div class="h-8 rounded-md bg-surface shadow-sm"></div><div class="h-8 rounded-md bg-surface shadow-sm"></div><div class="h-8 rounded-md bg-brand-lime/60"></div></div>
                                        <div class="flex h-[55%] items-end gap-1.5 rounded-md bg-surface p-2 shadow-sm">
                                            @foreach ([40, 70, 30, 90, 55, 80, 45, 65] as $bar)
                                                <div class="flex-1 rounded-t-sm bg-brand" style="height: {{ $bar }}%"></div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                                <div
                                    class="absolute inset-0 flex flex-col items-center justify-center gap-3"
                                    :style="`background-color: rgba(255, 255, 255, ${$wire.loading_screen_opacity / 100}); backdrop-filter: blur(${$wire.loading_screen_blur / 3}px); -webkit-backdrop-filter: blur(${$wire.loading_screen_blur / 3}px);`"
                                >
                                    <div class="flex h-16 items-center justify-center">
                                        <x-loading-animation :name="$loading_screen_style" size="md" />
                                    </div>
                                    <div class="flex items-center gap-2 text-sm font-semibold text-zinc-900">
                                        <x-logo-mark class="size-6 shrink-0" />
                                        {{ config('app.name') }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-3 lg:order-1 lg:col-span-3">
                            @foreach ([
                                'loading_screen_seconds' => ['Duration', 'How long it stays on screen.', 1, 10, 1, 's', '<line x1="10" x2="14" y1="2" y2="2"/><line x1="12" x2="15" y1="14" y2="11"/><circle cx="12" cy="14" r="8"/>'],
                                'loading_screen_opacity' => ['Transparency', 'Lower is more see-through.', 0, 100, 5, '%', '<circle cx="12" cy="12" r="10"/><path d="M12 18a6 6 0 0 0 0-12v12z"/>'],
                                'loading_screen_blur' => ['Blur', 'How much the page behind is blurred.', 0, 100, 4, 'px', '<path d="M12 22a7 7 0 0 0 7-7c0-2-1-3.9-3-5.5s-3.5-4-4-6.5c-.5 2.5-2 4.9-4 6.5C6 11.1 5 13 5 15a7 7 0 0 0 7 7z"/>'],
                            ] as $field => [$fieldLabel, $fieldHint, $min, $max, $step, $unit, $fieldIcon])
                                <div class="rounded-xl border border-zinc-200 bg-zinc-50/60 px-4 pb-2 pt-3.5">
                                    <div class="flex items-center gap-3">
                                        <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-surface text-brand shadow-sm ring-1 ring-zinc-900/5">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4.5">{!! $fieldIcon !!}</svg>
                                        </span>
                                        <div class="min-w-0 flex-1">
                                            <label for="{{ $field }}" class="block text-sm font-semibold text-zinc-900">{{ $fieldLabel }}</label>
                                            <p class="truncate text-xs text-zinc-500">{{ $fieldHint }}</p>
                                        </div>
                                        <span class="min-w-14 shrink-0 rounded-lg bg-brand px-2.5 py-1 text-center text-sm font-semibold tabular-nums text-white shadow-sm" x-text="$wire.{{ $field }} + '{{ $unit }}'">{{ $this->{$field} }}{{ $unit }}</span>
                                    </div>
                                    <input
                                        wire:model="{{ $field }}"
                                        id="{{ $field }}"
                                        type="range"
                                        min="{{ $min }}"
                                        max="{{ $max }}"
                                        step="{{ $step }}"
                                        class="range-modern mt-3"
                                        style="--fill: {{ ($this->{$field} - $min) / ($max - $min) * 100 }}%"
                                        :style="{ '--fill': (($wire.{{ $field }} - {{ $min }}) / {{ $max - $min }} * 100) + '%' }"
                                    >
                                    <div class="flex justify-between text-[11px] font-medium tabular-nums text-zinc-400">
                                        <span>{{ $min }}{{ $unit }}</span>
                                        <span>{{ $max }}{{ $unit }}</span>
                                    </div>
                                    @error($field) <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                </div>
                            @endforeach
                        </div>

                        <div class="lg:order-3 lg:col-span-5">
                            <p class="text-sm font-medium text-zinc-900">Animation</p>
                            <div class="mt-2 grid grid-cols-2 gap-3 sm:grid-cols-4">
                                @foreach (['jampe' => 'Jumping Boxes', 'bars' => 'Equalizer', 'handoff' => 'Handoff', 'spinner' => 'Spinner'] as $key => $styleLabel)
                                    <button
                                        type="button"
                                        wire:click="$set('loading_screen_style', '{{ $key }}')"
                                        @class([
                                            'relative flex flex-col items-center gap-3 rounded-xl border p-4 transition',
                                            'border-brand bg-brand/5 ring-1 ring-brand' => $loading_screen_style === $key,
                                            'border-zinc-200 hover:border-zinc-300 hover:bg-zinc-50' => $loading_screen_style !== $key,
                                        ])
                                    >
                                        @if ($loading_screen_style === $key)
                                            <span class="absolute right-2 top-2 flex size-5 items-center justify-center rounded-full bg-brand text-white">
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" class="size-3"><path d="M20 6 9 17l-5-5"/></svg>
                                            </span>
                                        @endif
                                        <span class="flex h-10 items-center justify-center">
                                            <x-loading-animation :name="$key" size="sm" />
                                        </span>
                                        <span class="text-xs font-medium {{ $loading_screen_style === $key ? 'text-brand' : 'text-zinc-600' }}">{{ $styleLabel }}</span>
                                    </button>
                                @endforeach
                            </div>
                            @error('loading_screen_style') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="flex justify-end border-t border-zinc-100 bg-zinc-50/70 px-4 py-3 sm:px-6">
                        <button type="submit" class="{{ $primary }}" wire:loading.attr="disabled" wire:target="saveLoadingScreen">Save changes</button>
                    </div>
                </form>
            @elseif ($activeTab === 'smtp')
                <form wire:submit="saveSmtp" x-data="{ reveal: false }" class="overflow-hidden rounded-xl border border-zinc-200 bg-surface">
                    <div class="flex items-start justify-between gap-4 border-b border-zinc-100 px-4 py-4 sm:px-6">
                        <div>
                            <h2 class="text-base font-semibold text-zinc-900">SMTP server</h2>
                            <p class="text-sm text-zinc-500">Leave it empty to use the server's default mailer.</p>
                        </div>
                        <span @class([
                            'inline-flex shrink-0 items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium',
                            'bg-brand/10 text-brand' => $smtpConfigured,
                            'bg-zinc-100 text-zinc-500' => ! $smtpConfigured,
                        ])>
                            <span class="size-1.5 rounded-full {{ $smtpConfigured ? 'bg-brand' : 'bg-zinc-400' }}"></span>
                            {{ $smtpConfigured ? 'Custom' : 'Default' }}
                        </span>
                    </div>

                    <div class="divide-y divide-zinc-100">
                        {{-- Server --}}
                        <div class="grid gap-4 px-4 py-5 sm:px-6 lg:grid-cols-3 lg:gap-6">
                            <div>
                                <h3 class="text-sm font-semibold text-zinc-900">Server</h3>
                                <p class="text-xs text-zinc-500">Where outgoing mail is handed off.</p>
                            </div>
                            <div class="grid grid-cols-3 gap-4 lg:col-span-2">
                                <div class="col-span-2">
                                    <label for="mail_host" class="{{ $label }}">Host</label>
                                    <input wire:model="mail_host" id="mail_host" type="text" placeholder="smtp.mailtrap.io" class="mt-1 {{ $input }}">
                                    @error('mail_host') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label for="mail_port" class="{{ $label }}">Port</label>
                                    <input wire:model="mail_port" id="mail_port" type="number" inputmode="numeric" placeholder="587" class="mt-1 {{ $input }}">
                                    @error('mail_port') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                </div>
                                <div class="col-span-3">
                                    <span class="{{ $label }}">Encryption</span>
                                    <div class="mt-1 grid grid-cols-3 rounded-lg border border-zinc-300 bg-surface p-0.5 sm:inline-grid sm:w-72">
                                        @foreach (['tls' => 'TLS', 'ssl' => 'SSL', 'none' => 'None'] as $value => $encryptionLabel)
                                            <label class="cursor-pointer">
                                                <input wire:model="mail_encryption" type="radio" name="mail_encryption" value="{{ $value }}" class="peer sr-only">
                                                <span class="block rounded-md py-1.5 text-center text-sm font-medium text-zinc-600 peer-checked:bg-brand peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-brand-lime/50">{{ $encryptionLabel }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Sign-in --}}
                        <div class="grid gap-4 px-4 py-5 sm:px-6 lg:grid-cols-3 lg:gap-6">
                            <div>
                                <h3 class="text-sm font-semibold text-zinc-900">Sign-in</h3>
                                <p class="text-xs text-zinc-500">Credentials from your mail provider.</p>
                            </div>
                            <div class="grid gap-4 sm:grid-cols-2 lg:col-span-2">
                                <div>
                                    <label for="mail_username" class="{{ $label }}">Username</label>
                                    <input wire:model="mail_username" id="mail_username" type="text" autocomplete="off" class="mt-1 {{ $input }}">
                                    @error('mail_username') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label for="mail_password" class="{{ $label }}">Password</label>
                                    <div class="relative mt-1">
                                        <input wire:model="mail_password" id="mail_password" :type="reveal ? 'text' : 'password'" type="password" autocomplete="new-password" placeholder="{{ $smtpConfigured ? 'Saved — leave blank to keep' : '' }}" class="{{ $input }} pr-10">
                                        <button type="button" @click="reveal = ! reveal" class="absolute inset-y-0 right-0 flex items-center px-3 text-zinc-400 hover:text-zinc-600" :title="reveal ? 'Hide password' : 'Show password'">
                                            <svg x-show="! reveal" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/></svg>
                                            <svg x-show="reveal" x-cloak xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="M10.733 5.076a10.744 10.744 0 0 1 11.205 6.575 1 1 0 0 1 0 .696 10.747 10.747 0 0 1-1.444 2.49"/><path d="M14.084 14.158a3 3 0 0 1-4.242-4.242"/><path d="M17.479 17.499a10.75 10.75 0 0 1-15.417-5.151 1 1 0 0 1 0-.696 10.75 10.75 0 0 1 4.446-5.143"/><path d="m2 2 20 20"/></svg>
                                        </button>
                                    </div>
                                    @error('mail_password') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                </div>
                            </div>
                        </div>

                        {{-- Sender --}}
                        <div class="grid gap-4 px-4 py-5 sm:px-6 lg:grid-cols-3 lg:gap-6">
                            <div>
                                <h3 class="text-sm font-semibold text-zinc-900">Sender</h3>
                                <p class="text-xs text-zinc-500">Who recipients see emails from.</p>
                            </div>
                            <div class="grid gap-4 sm:grid-cols-2 lg:col-span-2">
                                <div>
                                    <label for="mail_from_name" class="{{ $label }}">From name</label>
                                    <input wire:model="mail_from_name" id="mail_from_name" type="text" placeholder="{{ config('app.name') }}" class="mt-1 {{ $input }}">
                                    @error('mail_from_name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label for="mail_from_address" class="{{ $label }}">From address</label>
                                    <input wire:model="mail_from_address" id="mail_from_address" type="email" placeholder="hello@example.com" class="mt-1 {{ $input }}">
                                    @error('mail_from_address') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end border-t border-zinc-100 bg-zinc-50/70 px-4 py-3 sm:px-6">
                        <button type="submit" class="{{ $primary }}" wire:loading.attr="disabled" wire:target="saveSmtp">Save SMTP settings</button>
                    </div>
                </form>

                <div class="rounded-xl border border-zinc-200 bg-surface px-4 py-5 sm:px-6">
                    <h2 class="text-base font-semibold text-zinc-900">Send a test email</h2>
                    <p class="text-sm text-zinc-500">Uses the settings above, saved or not.</p>
                    <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-start">
                        <div class="flex-1">
                            <label for="test_email" class="sr-only">Send test to</label>
                            <input wire:model="test_email" id="test_email" type="email" placeholder="you@example.com" class="{{ $input }}">
                            @error('test_email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <button type="button" wire:click="testSmtp" wire:loading.attr="disabled" wire:target="testSmtp" class="{{ $secondary }}">
                            {!! $sendIcon !!}
                            <span wire:loading.remove wire:target="testSmtp">Send test</span>
                            <span wire:loading wire:target="testSmtp">Sending…</span>
                        </button>
                    </div>
                </div>
            @elseif ($activeTab === 'mail-template')
                @php
                    $validHex = fn ($value) => (bool) preg_match('/^#[0-9A-Fa-f]{6}$/', $value);
                    $colorsValid = $validHex($mail_brand_color) && $validHex($mail_button_text_color);
                    $ratio = $colorsValid ? \App\Support\MailTheme::contrast($mail_brand_color, $mail_button_text_color) : null;
                    $suggested = $validHex($mail_brand_color) ? \App\Support\MailTheme::suggestedText($mail_brand_color) : null;
                    $swatchBrand = $validHex($mail_brand_color) ? $mail_brand_color : '#10512a';
                @endphp
                <div class="grid items-start gap-4 sm:gap-6 2xl:grid-cols-2">
                    <form wire:submit="saveMailTemplate" class="rounded-2xl border border-zinc-200 bg-surface">
                        <div class="px-4 pt-5 sm:px-6">
                            <h2 class="text-base font-semibold text-zinc-900">Email template</h2>
                            <p class="text-sm text-zinc-500">The look of every notification email. The preview updates as you change things.</p>
                        </div>

                        <div class="divide-y divide-zinc-100 px-4 sm:px-6">
                            {{-- Layout --}}
                            <section class="space-y-3 py-6">
                                <h3 class="text-sm font-semibold text-zinc-900">Layout</h3>
                                <div class="grid grid-cols-3 gap-3 sm:gap-4" role="radiogroup" aria-label="Email layout">
                                    @foreach (\App\Support\MailTheme::LAYOUTS as $key => [$layoutName, $layoutHint])
                                        <label class="group cursor-pointer" title="{{ $layoutHint }}">
                                            <input type="radio" wire:model.live="mail_layout" value="{{ $key }}" class="peer sr-only">
                                            {{-- A tiny drawing of the layout, in the current brand colour. --}}
                                            <span class="block overflow-hidden rounded-xl p-2.5 ring-1 ring-zinc-200 ring-offset-2 ring-offset-surface transition group-hover:ring-zinc-300 peer-checked:ring-2 peer-checked:ring-brand! peer-focus-visible:ring-2 {{ $key === 'minimal' ? 'bg-white' : ($key === 'modern' ? 'bg-zinc-100' : 'bg-slate-100') }}" style="--b: {{ $swatchBrand }}">
                                                @if ($key === 'modern')
                                                    <span class="mx-auto mb-1.5 flex w-fit items-center gap-1"><span class="size-2.5 rounded-[3px] bg-(--b)"></span><span class="h-1 w-6 rounded-full bg-zinc-400"></span></span>
                                                    <span class="block rounded-md border-t-2 border-(--b) bg-white p-2 shadow-sm">
                                                        <span class="block h-1 w-8 rounded-full bg-zinc-300"></span>
                                                        <span class="mt-1 block h-1 w-12 rounded-full bg-zinc-200"></span>
                                                        <span class="mx-auto mt-2 block h-2.5 w-8 rounded bg-(--b)"></span>
                                                    </span>
                                                @elseif ($key === 'classic')
                                                    <span class="mx-auto mb-1.5 block h-1.5 w-8 rounded-full bg-(--b)"></span>
                                                    <span class="block rounded-sm bg-white p-2 shadow-sm">
                                                        <span class="block h-1 w-8 rounded-full bg-zinc-300"></span>
                                                        <span class="mt-1 block h-1 w-12 rounded-full bg-zinc-200"></span>
                                                        <span class="mx-auto mt-2 block h-2.5 w-8 rounded-sm bg-(--b)"></span>
                                                    </span>
                                                @else
                                                    <span class="mb-1.5 flex items-center gap-1"><span class="size-2 rounded-[2px] bg-(--b)"></span><span class="h-1 w-6 rounded-full bg-zinc-400"></span></span>
                                                    <span class="block py-2">
                                                        <span class="block h-1 w-8 rounded-full bg-zinc-300"></span>
                                                        <span class="mt-1 block h-1 w-12 rounded-full bg-zinc-200"></span>
                                                        <span class="mt-2 block h-2.5 w-8 rounded-sm bg-(--b)"></span>
                                                    </span>
                                                @endif
                                            </span>
                                            <span class="mt-2 flex items-center gap-1 text-sm text-zinc-500 group-has-[:checked]:font-medium group-has-[:checked]:text-zinc-900">
                                                {{ $layoutName }}
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="hidden size-4 text-brand group-has-[:checked]:block"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" /></svg>
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                                <p class="text-xs text-zinc-500">{{ \App\Support\MailTheme::LAYOUTS[$mail_layout][1] ?? '' }}</p>
                            </section>

                            {{-- Colours --}}
                            <section class="space-y-4 py-6">
                                <div>
                                    <h3 class="text-sm font-semibold text-zinc-900">Colours</h3>
                                    <p class="text-xs text-zinc-500">Pick a ready-made pair, or set your own. The button text is chosen to stay readable.</p>
                                </div>

                                <div class="flex flex-wrap gap-x-4 gap-y-3" role="radiogroup" aria-label="Colour presets">
                                    @foreach (\App\Support\MailTheme::PRESETS as $key => $preset)
                                        @php $active = strtolower($mail_brand_color) === $preset['brand'] && strtolower($mail_button_text_color) === $preset['text']; @endphp
                                        <button type="button" wire:click="applyMailPreset('{{ $key }}')" class="group flex w-14 flex-col items-center gap-1.5" title="{{ $preset['name'] }}" aria-pressed="{{ $active ? 'true' : 'false' }}">
                                            <span @class(['flex size-10 items-center justify-center rounded-full text-[11px] font-bold ring-offset-2 ring-offset-surface transition group-hover:scale-105', 'ring-2' => $active]) style="background-color: {{ $preset['brand'] }}; color: {{ $preset['text'] }}; --tw-ring-color: {{ $preset['brand'] }}">Aa</span>
                                            <span class="text-xs {{ $active ? 'font-medium text-zinc-900' : 'text-zinc-500' }}">{{ $preset['name'] }}</span>
                                        </button>
                                    @endforeach
                                </div>

                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    @foreach (['mail_brand_color' => 'Brand colour', 'mail_button_text_color' => 'Button text'] as $field => $fieldLabel)
                                        <div>
                                            <label for="{{ $field }}" class="mb-1.5 block text-sm font-medium text-zinc-700">{{ $fieldLabel }}</label>
                                            <div class="flex items-center gap-2">
                                                <label class="relative size-10 shrink-0 cursor-pointer overflow-hidden rounded-xl ring-1 ring-zinc-900/10 shadow-xs" style="background-color: {{ $validHex($this->{$field}) ? $this->{$field} : '#ffffff' }}" title="Pick a colour">
                                                    <input wire:model.live="{{ $field }}" type="color" class="absolute inset-0 size-full cursor-pointer opacity-0">
                                                </label>
                                                <input wire:model.live.debounce.400ms="{{ $field }}" id="{{ $field }}" type="text" maxlength="7" spellcheck="false" @class(['field-input font-mono uppercase', 'field-input-error' => $errors->has($field)])>
                                            </div>
                                            @error($field) <p class="mt-1.5 text-xs font-medium text-red-600">Use a hex colour like #10512A.</p> @enderror
                                        </div>
                                    @endforeach
                                </div>

                                @if ($ratio !== null)
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span @class(['inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset', 'bg-emerald-50 text-emerald-700 ring-emerald-200' => $ratio >= 4.5, 'bg-amber-50 text-amber-700 ring-amber-200' => $ratio < 4.5])>
                                            <span class="size-1.5 rounded-full bg-current"></span>
                                            {{ $ratio >= 4.5 ? 'Easy to read' : 'Hard to read' }} · {{ $ratio }}:1
                                        </span>
                                        @if ($suggested && strtolower($mail_button_text_color) !== $suggested && $ratio < 7)
                                            <button type="button" wire:click="$set('mail_button_text_color', '{{ $suggested }}')" class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold text-brand transition-colors hover:bg-brand/5">
                                                <span class="size-3 rounded-full ring-1 ring-zinc-900/15" style="background-color: {{ $suggested }}"></span>
                                                Use {{ $suggested === '#ffffff' ? 'white' : 'dark' }} text ({{ \App\Support\MailTheme::contrast($mail_brand_color, $suggested) }}:1)
                                            </button>
                                        @endif
                                    </div>
                                @endif
                            </section>

                            {{-- Footer note --}}
                            <section class="space-y-2 py-6">
                                <label for="mail_footer_note" class="flex items-center gap-1.5 text-sm font-semibold text-zinc-900">Footer note <span class="text-xs font-normal text-zinc-400">Optional</span></label>
                                <textarea wire:model.live.debounce.500ms="mail_footer_note" id="mail_footer_note" rows="2" maxlength="255" placeholder="e.g. Questions? Reply to this email." class="field-input resize-none"></textarea>
                                <p class="text-xs text-zinc-500">Shown above the copyright line.</p>
                                @error('mail_footer_note') <p class="text-xs font-medium text-red-600">{{ $message }}</p> @enderror
                            </section>
                        </div>

                        <div class="flex flex-col gap-3 border-t border-zinc-100 px-4 py-3.5 sm:flex-row sm:items-start sm:justify-between sm:px-6">
                            <div class="flex min-w-0 flex-1 gap-2 sm:max-w-sm">
                                <label for="template_preview_email" class="sr-only">Send a preview to</label>
                                <div class="min-w-0 flex-1">
                                    <input wire:model="template_preview_email" id="template_preview_email" type="email" placeholder="you@example.com" @class(['field-input py-2', 'field-input-error' => $errors->has('template_preview_email')])>
                                    @error('template_preview_email') <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p> @enderror
                                </div>
                                <button type="button" wire:click="sendTemplatePreview" wire:loading.attr="disabled" wire:target="sendTemplatePreview" class="btn-secondary shrink-0 py-2" title="Saves the template, then sends a real sample">
                                    {!! $sendIcon !!}
                                    <span wire:loading.remove wire:target="sendTemplatePreview">Send test</span>
                                    <span wire:loading wire:target="sendTemplatePreview">Sending…</span>
                                </button>
                            </div>
                            <x-form.submit target="saveMailTemplate" class="sm:self-start">Save template</x-form.submit>
                        </div>
                    </form>

                    {{-- Live preview: the real email, rendered from the unsaved choices. --}}
                    <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-surface 2xl:sticky 2xl:top-20">
                        <div class="flex items-center gap-3 border-b border-zinc-100 px-4 py-3">
                            <img src="{{ asset('apple-touch-icon.png') }}" alt="" class="size-9 shrink-0 rounded-full">
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold text-zinc-900">{{ $mail_from_name ?: config('app.name') }}</p>
                                <p class="truncate text-xs text-zinc-500">Rahim assigned you "Redesign the onboarding emails"</p>
                            </div>
                            <span class="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-zinc-100 px-2.5 py-1 text-xs font-medium text-zinc-600" wire:loading.class="opacity-50">
                                <span class="size-1.5 rounded-full bg-emerald-500"></span>Live preview
                            </span>
                        </div>
                        @if ($mailPreviewUrl)
                            <iframe src="{{ $mailPreviewUrl }}" sandbox title="Email preview" class="block h-[620px] w-full bg-white" wire:loading.class="opacity-60"></iframe>
                        @else
                            <p class="px-6 py-24 text-center text-sm text-zinc-500">Enter valid colours to see the preview.</p>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
