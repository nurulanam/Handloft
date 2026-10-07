<?php

use App\Mail\SmtpTestMail;
use App\Models\AppSetting;
use App\Notifications\MailTemplatePreview;
use App\Support\MailSettings;
use App\Support\Theme;
use App\Support\WorkSchedule;
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

    public string $ui_style = 'glass';

    public string $theme = 'forest';

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

    public string $template_preview_email = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->can('manage-settings'), 403);

        $settings = AppSetting::current();

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
        $this->template_preview_email = auth()->user()->email;

        $this->ui_style = $settings->ui_style ?: 'glass';
        $this->theme = array_key_exists((string) $settings->theme, Theme::PRESETS) ? $settings->theme : 'forest';
        $this->off_days = array_map('strval', WorkSchedule::offDays());
        $this->week_starts_on = (string) WorkSchedule::weekStartsOn();
        $this->daily_hours_target = WorkSchedule::dailyTarget() !== null ? rtrim(rtrim(number_format(WorkSchedule::dailyTarget(), 2), '0'), '.') : '';
    }

    public function saveAppearance(): void
    {
        abort_unless(auth()->user()->can('manage-settings'), 403);

        $data = $this->validate([
            'ui_style' => ['required', Rule::in(['glass', 'static'])],
            'theme' => ['required', Rule::in(array_keys(Theme::PRESETS))],
        ]);

        AppSetting::current()->update($data);

        $theme = Theme::PRESETS[$data['theme']];
        $this->dispatch('appearance-saved', look: ['brand' => $theme['brand'], 'accent' => $theme['accent'], 'static' => $data['ui_style'] === 'static']);
        $this->dispatch('notify', message: 'Appearance saved for everyone.', type: 'success');
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
            'loading_screen_style' => ['required', Rule::in(['jampe', 'bars', 'hand', 'spinner'])],
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

    public function saveMailTemplate(): void
    {
        abort_unless(auth()->user()->can('manage-settings'), 403);

        $data = $this->validate([
            'mail_brand_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'mail_button_text_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'mail_footer_note' => ['nullable', 'string', 'max:255'],
        ]);

        AppSetting::current()->update([
            'mail_brand_color' => $data['mail_brand_color'],
            'mail_button_text_color' => $data['mail_button_text_color'],
            'mail_footer_note' => $data['mail_footer_note'] ?: null,
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
            'template_preview_email' => ['required', 'email'],
        ]);

        AppSetting::current()->update([
            'mail_brand_color' => $data['mail_brand_color'],
            'mail_button_text_color' => $data['mail_button_text_color'],
            'mail_footer_note' => $data['mail_footer_note'] ?: null,
        ]);

        try {
            Notification::route('mail', $data['template_preview_email'])->notify(new MailTemplatePreview);

            $this->dispatch('notify', message: "Preview email sent to {$data['template_preview_email']}.", type: 'success');
        } catch (\Throwable $e) {
            report($e);

            $this->dispatch('notify', message: 'Could not send preview email: '.$e->getMessage(), type: 'error');
        }
    }

    public function with(): array
    {
        return [
            'smtpConfigured' => filled(AppSetting::current()->mail_host),
        ];
    }
};
?>
@php
    $input = 'block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 placeholder:text-zinc-400 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand-lime/40';
    $label = 'block text-sm font-medium text-zinc-700';
    $primary = 'inline-flex w-full items-center justify-center gap-2 rounded-lg bg-brand px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-brand/90 disabled:opacity-60 sm:w-auto sm:py-2';
    $secondary = 'inline-flex w-full shrink-0 items-center justify-center gap-2 rounded-lg border border-zinc-300 bg-white px-4 py-2.5 text-sm font-semibold text-zinc-700 hover:bg-zinc-50 disabled:opacity-60 sm:w-auto sm:py-2';
    $tabs = [
        'appearance' => ['Appearance', 'Look', 'Glass or static, theme colours', '<path d="m14.622 17.897-10.68-2.913"/><path d="M18.376 2.622a1 1 0 1 1 3.002 3.002L17.36 9.643a.5.5 0 0 0 0 .707l.944.944a2.41 2.41 0 0 1 0 3.408l-.944.944a.5.5 0 0 1-.707 0L8.354 7.348a.5.5 0 0 1 0-.707l.944-.944a2.41 2.41 0 0 1 3.408 0l.944.944a.5.5 0 0 0 .707 0z"/><path d="M9 8c-1.804 2.71-3.97 3.46-6.583 3.948a.507.507 0 0 0-.302.819l7.32 8.883a1 1 0 0 0 1.185.204C12.735 20.405 16 16.792 16 15"/>'],
        'schedule' => ['Work Schedule', 'Schedule', 'Days off, week start, hours', '<path d="M21 7.5V6a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h3.5"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h5"/><path d="M17.5 17.5 16 16.3V14"/><circle cx="16" cy="16" r="6"/>'],
        'loading-screen' => ['Loading Screen', 'Loading', 'Shown right after sign-in', '<path d="M9.937 15.5A2 2 0 0 0 8.5 14.063l-6.135-1.582a.5.5 0 0 1 0-.962L8.5 9.936A2 2 0 0 0 9.937 8.5l1.582-6.135a.5.5 0 0 1 .963 0L14.063 8.5A2 2 0 0 0 15.5 9.937l6.135 1.581a.5.5 0 0 1 0 .964L15.5 14.063a2 2 0 0 0-1.437 1.437l-1.582 6.135a.5.5 0 0 1-.963 0z"/>'],
        'smtp' => ['Email / SMTP', 'SMTP', 'Server used to send email', '<rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>'],
        'mail-template' => ['Email Template', 'Template', 'Colors and footer of emails', '<circle cx="13.5" cy="6.5" r=".5" fill="currentColor"/><circle cx="17.5" cy="10.5" r=".5" fill="currentColor"/><circle cx="8.5" cy="7.5" r=".5" fill="currentColor"/><circle cx="6.5" cy="12.5" r=".5" fill="currentColor"/><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 0 1 1.668-1.668h1.996c3.051 0 5.555-2.503 5.555-5.554C21.965 6.012 17.461 2 11.994 2z"/>'],
    ];
    $sendIcon = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="M14.536 21.686a.5.5 0 0 0 .937-.024l6.5-19a.496.496 0 0 0-.635-.635l-19 6.5a.5.5 0 0 0-.024.937l7.93 3.18a2 2 0 0 1 1.112 1.11z"/><path d="m21.854 2.147-10.94 10.939"/></svg>';
@endphp

<div class="space-y-4 sm:space-y-6">
    <div>
        <h1 class="text-xl font-semibold text-zinc-900 sm:text-2xl">Settings</h1>
        <p class="hidden text-sm text-zinc-500 sm:block">Manage how {{ config('app.name') }} greets people and sends email.</p>
    </div>

    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:gap-8">
        {{-- Section nav: a 3-up icon tab bar on phones, a described side menu from lg up. --}}
        <nav class="grid shrink-0 grid-cols-5 gap-1 rounded-xl border border-zinc-200 bg-white p-1 lg:sticky lg:top-20 lg:flex lg:w-64 lg:flex-col lg:gap-1 lg:p-2" aria-label="Settings sections">
            @foreach ($tabs as $key => [$name, $short, $hint, $icon])
                <button
                    type="button"
                    wire:click="$set('tab', '{{ $key }}')"
                    @class([
                        'flex flex-col items-center gap-1 rounded-lg px-2 py-2 text-xs font-medium transition-colors lg:flex-row lg:items-center lg:gap-3 lg:px-3 lg:py-2.5 lg:text-left',
                        'bg-brand text-white shadow-sm lg:bg-brand/10 lg:text-brand lg:shadow-none' => $tab === $key,
                        'text-zinc-600 hover:bg-zinc-100' => $tab !== $key,
                    ])
                    @if ($tab === $key) aria-current="page" @endif
                >
                    <span @class([
                        'flex size-5 shrink-0 items-center justify-center lg:size-9 lg:rounded-lg',
                        'lg:bg-brand lg:text-white' => $tab === $key,
                        'lg:bg-zinc-100 lg:text-zinc-500' => $tab !== $key,
                    ])>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4.5">{!! $icon !!}</svg>
                    </span>
                    <span class="min-w-0">
                        <span class="lg:hidden">{{ $short }}</span>
                        <span class="hidden text-sm font-semibold lg:block">{{ $name }}</span>
                        <span class="hidden text-xs font-normal lg:block {{ $tab === $key ? 'text-brand/70' : 'text-zinc-400' }}">{{ $hint }}</span>
                    </span>
                </button>
            @endforeach
        </nav>

        <div class="min-w-0 flex-1 space-y-4 sm:space-y-6">
            @if ($tab === 'appearance')
                <form
                    wire:submit="saveAppearance"
                    class="overflow-hidden rounded-xl border border-zinc-200 bg-white"
                    x-data="{ presets: @js(\App\Support\Theme::PRESETS) }"
                    x-effect="
                        const t = presets[$wire.theme] ?? presets.forest;
                        applyAppearance({ brand: t.brand, accent: t.accent, static: $wire.ui_style === 'static' });
                    "
                >
                    <div class="border-b border-zinc-100 px-4 py-4 sm:px-6">
                        <h2 class="text-base font-semibold text-zinc-900">Appearance</h2>
                        <p class="text-sm text-zinc-500">Changes preview instantly on this page; save to apply them for everyone.</p>
                    </div>

                    <div class="space-y-6 px-4 py-5 sm:px-6">
                        <div>
                            <p class="text-sm font-semibold text-zinc-900">Visual style</p>
                            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                @foreach ([
                                    'glass' => ['Liquid glass', 'Frosted, translucent panels with soft colour behind them.'],
                                    'static' => ['Static', 'Solid panels, no blur or drifting — calmer and lighter on older devices.'],
                                ] as $key => [$styleName, $styleHint])
                                    <label class="group relative cursor-pointer">
                                        <input type="radio" wire:model.live="ui_style" value="{{ $key }}" class="peer sr-only">
                                        <span class="block rounded-2xl border-2 border-zinc-200 p-3 transition peer-checked:border-brand peer-focus-visible:ring-2 peer-focus-visible:ring-brand-lime/50">
                                            {{-- Mini preview --}}
                                            <span class="relative block h-24 overflow-hidden rounded-xl {{ $key === 'glass' ? 'bg-brand' : 'bg-zinc-100' }}">
                                                @if ($key === 'glass')
                                                    <span class="absolute -left-4 top-2 size-20 rounded-full bg-brand-lime/70 blur-xl"></span>
                                                    <span class="absolute right-2 top-8 size-16 rounded-full bg-emerald-300/70 blur-xl"></span>
                                                    <span class="absolute inset-x-4 bottom-3 top-6 rounded-xl border border-white/50 bg-white/40 backdrop-blur-md"></span>
                                                @else
                                                    <span class="absolute inset-x-4 bottom-3 top-6 rounded-xl border border-zinc-200 bg-white shadow-sm"></span>
                                                @endif
                                                <span class="absolute left-7 top-9 h-2 w-16 rounded-full {{ $key === 'glass' ? 'bg-white/80' : 'bg-zinc-200' }}"></span>
                                                <span class="absolute left-7 top-13 h-2 w-24 rounded-full {{ $key === 'glass' ? 'bg-white/60' : 'bg-zinc-100' }}"></span>
                                            </span>
                                            <span class="mt-3 flex items-center justify-between gap-2">
                                                <span class="text-sm font-semibold text-zinc-900">{{ $styleName }}</span>
                                                <span class="flex size-5 items-center justify-center rounded-full border-2 border-zinc-300 group-has-[:checked]:border-brand group-has-[:checked]:bg-brand">
                                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="hidden size-3 text-white group-has-[:checked]:block"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" /></svg>
                                                </span>
                                            </span>
                                            <span class="mt-0.5 block text-xs text-zinc-500">{{ $styleHint }}</span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <div>
                            <p class="text-sm font-semibold text-zinc-900">Theme colour</p>
                            <p class="text-xs text-zinc-500">Recolours the logo, buttons, highlights, active menu items and focus rings.</p>
                            <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3">
                                @foreach (\App\Support\Theme::PRESETS as $key => $preset)
                                    <label class="group cursor-pointer">
                                        <input type="radio" wire:model.live="theme" value="{{ $key }}" class="peer sr-only">
                                        <span class="flex items-center gap-3 rounded-2xl border-2 border-zinc-200 p-3 transition peer-checked:border-[var(--swatch)] peer-focus-visible:ring-2 peer-focus-visible:ring-brand-lime/50" style="--swatch: {{ $preset['brand'] }}">
                                            <span class="relative size-10 shrink-0 rounded-xl" style="background-color: {{ $preset['brand'] }}">
                                                <span class="absolute -bottom-1 -right-1 size-4 rounded-full ring-2 ring-white" style="background-color: {{ $preset['accent'] }}"></span>
                                            </span>
                                            <span class="min-w-0 flex-1">
                                                <span class="block text-sm font-semibold text-zinc-900">{{ $preset['name'] }}</span>
                                                <span class="mt-1 inline-block rounded-md px-2 py-0.5 text-[10px] font-semibold text-white" style="background-color: {{ $preset['brand'] }}">Button</span>
                                            </span>
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="hidden size-5 shrink-0 group-has-[:checked]:block" style="color: {{ $preset['brand'] }}"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" /></svg>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end border-t border-zinc-100 bg-zinc-50/70 px-4 py-3 sm:px-6">
                        <button type="submit" class="{{ $primary }}" wire:loading.attr="disabled" wire:target="saveAppearance">Save appearance</button>
                    </div>
                </form>
            @elseif ($tab === 'schedule')
                @php
                    $offSelected = collect($off_days)->map(fn ($d) => (int) $d);
                    $workingPerWeek = 7 - $offSelected->unique()->count();
                    $targetValue = is_numeric($daily_hours_target) ? (float) $daily_hours_target : null;
                @endphp
                <form wire:submit="saveSchedule" class="overflow-hidden rounded-xl border border-zinc-200 bg-white">
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
                                <div class="grid grid-cols-3 rounded-lg border border-zinc-300 bg-white p-0.5 sm:inline-grid sm:w-80">
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
            @elseif ($tab === 'loading-screen')
                <form wire:submit="saveLoadingScreen" x-data class="overflow-hidden rounded-xl border border-zinc-200 bg-white">
                    <div class="flex items-start justify-between gap-4 border-b border-zinc-100 px-4 py-4 sm:px-6">
                        <div>
                            <h2 class="text-base font-semibold text-zinc-900">Loading screen</h2>
                            <p class="text-sm text-zinc-500">A branded overlay shown for a moment right after signing in.</p>
                        </div>
                        <label class="relative mt-0.5 inline-flex shrink-0 cursor-pointer items-center" title="Show loading screen after login">
                            <span class="sr-only">Show loading screen after login</span>
                            <input wire:model="show_loading_screen" type="checkbox" class="peer sr-only">
                            <span class="h-7 w-12 rounded-full bg-zinc-200 transition-colors peer-checked:bg-brand peer-focus-visible:ring-2 peer-focus-visible:ring-brand-lime/50"></span>
                            <span class="absolute left-1 top-1 size-5 rounded-full bg-white shadow-sm transition-transform peer-checked:translate-x-5"></span>
                        </label>
                    </div>

                    <div class="grid grid-cols-1 gap-6 px-4 py-5 transition-opacity sm:px-6 lg:grid-cols-5" :class="! $wire.show_loading_screen && 'pointer-events-none opacity-40'">
                        {{-- Live preview: a mock dashboard behind the overlay, so transparency and blur are visible. --}}
                        <div class="lg:order-2 lg:col-span-2">
                            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-zinc-400">Preview</p>
                            <div class="relative aspect-[4/3] overflow-hidden rounded-xl border border-zinc-200 bg-zinc-50">
                                <div class="absolute inset-0 flex" aria-hidden="true">
                                    <div class="w-1/5 bg-zinc-900 p-2"><div class="size-4 rounded bg-brand-lime"></div><div class="mt-3 space-y-1.5"><div class="h-1.5 rounded bg-brand"></div><div class="h-1.5 w-3/4 rounded bg-zinc-700"></div><div class="h-1.5 w-2/3 rounded bg-zinc-700"></div><div class="h-1.5 w-3/4 rounded bg-zinc-700"></div></div></div>
                                    <div class="flex-1 space-y-2 p-3">
                                        <div class="grid grid-cols-3 gap-2"><div class="h-8 rounded-md bg-white shadow-sm"></div><div class="h-8 rounded-md bg-white shadow-sm"></div><div class="h-8 rounded-md bg-brand-lime/60"></div></div>
                                        <div class="flex h-[55%] items-end gap-1.5 rounded-md bg-white p-2 shadow-sm">
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
                                        <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-white text-brand shadow-sm ring-1 ring-zinc-900/5">
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
                                @foreach (['jampe' => 'Jumping Boxes', 'bars' => 'Equalizer', 'hand' => 'Typing Hand', 'spinner' => 'Spinner'] as $key => $styleLabel)
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
            @elseif ($tab === 'smtp')
                <form wire:submit="saveSmtp" x-data="{ reveal: false }" class="overflow-hidden rounded-xl border border-zinc-200 bg-white">
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
                                    <div class="mt-1 grid grid-cols-3 rounded-lg border border-zinc-300 bg-white p-0.5 sm:inline-grid sm:w-72">
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

                <div class="rounded-xl border border-zinc-200 bg-white px-4 py-5 sm:px-6">
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
            @elseif ($tab === 'mail-template')
                <div class="grid gap-4 sm:gap-6 xl:grid-cols-2 xl:items-start">
                    <form wire:submit="saveMailTemplate" class="overflow-hidden rounded-xl border border-zinc-200 bg-white">
                        <div class="border-b border-zinc-100 px-4 py-4 sm:px-6">
                            <h2 class="text-base font-semibold text-zinc-900">Email template</h2>
                            <p class="text-sm text-zinc-500">Shared by every notification email.</p>
                        </div>

                        <div class="space-y-5 px-4 py-5 sm:px-6">
                            @foreach ([
                                'mail_brand_color' => ['Brand color', 'App name and button background.', ['#10512a', '#0f172a', '#1d4ed8', '#7c3aed', '#be185d', '#c2410c']],
                                'mail_button_text_color' => ['Button text', 'Text on the button.', ['#ffffff', '#0f172a', '#bfef1e']],
                            ] as $field => [$fieldLabel, $fieldHint, $presets])
                                <div>
                                    <label for="{{ $field }}" class="{{ $label }}">{{ $fieldLabel }}</label>
                                    <p class="text-xs text-zinc-500">{{ $fieldHint }}</p>
                                    <div class="mt-2 flex flex-wrap items-center gap-3">
                                        <div class="flex items-center gap-2">
                                            <label class="relative size-10 shrink-0 cursor-pointer overflow-hidden rounded-full border border-zinc-300 shadow-sm" style="background-color: {{ $this->{$field} }}" title="Pick a color">
                                                <input wire:model.live="{{ $field }}" type="color" class="absolute inset-0 size-full cursor-pointer opacity-0">
                                            </label>
                                            <div class="w-28"><input wire:model.live.debounce.400ms="{{ $field }}" id="{{ $field }}" type="text" maxlength="7" class="{{ $input }} font-mono uppercase"></div>
                                        </div>
                                        <div class="flex items-center gap-1.5">
                                            @foreach ($presets as $preset)
                                                <button
                                                    type="button"
                                                    wire:click="$set('{{ $field }}', '{{ $preset }}')"
                                                    @class([
                                                        'size-7 rounded-full border border-zinc-900/10 transition hover:scale-110',
                                                        'ring-2 ring-brand ring-offset-2' => strtolower($this->{$field}) === $preset,
                                                    ])
                                                    style="background-color: {{ $preset }}"
                                                    title="{{ $preset }}"
                                                ></button>
                                            @endforeach
                                        </div>
                                    </div>
                                    @error($field) <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                </div>
                            @endforeach

                            <div>
                                <label for="mail_footer_note" class="{{ $label }}">Footer note <span class="font-normal text-zinc-400">(optional)</span></label>
                                <textarea wire:model.live.debounce.400ms="mail_footer_note" id="mail_footer_note" rows="2" maxlength="255" placeholder="e.g. Questions? Reply to this email." class="mt-1 {{ $input }} resize-none"></textarea>
                                <p class="mt-1 text-xs text-zinc-500">Shown above the copyright line.</p>
                                @error('mail_footer_note') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <div class="flex justify-end border-t border-zinc-100 bg-zinc-50/70 px-4 py-3 sm:px-6">
                            <button type="submit" class="{{ $primary }}" wire:loading.attr="disabled" wire:target="saveMailTemplate">Save template</button>
                        </div>
                    </form>

                    {{-- Live preview: a rough mock of the email in an inbox, not a full render, so it updates instantly. --}}
                    <div class="space-y-4 sm:space-y-6 xl:sticky xl:top-20">
                        <div class="overflow-hidden rounded-xl border border-zinc-200 bg-zinc-100">
                            <div class="flex items-center gap-3 border-b border-zinc-200 bg-white px-4 py-3">
                                <span class="flex size-9 shrink-0 items-center justify-center rounded-full text-xs font-semibold" style="background-color: {{ $mail_brand_color }}; color: {{ $mail_button_text_color }}">{{ \App\Support\Avatar::initials($mail_from_name ?: config('app.name')) }}</span>
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-zinc-900">{{ $mail_from_name ?: config('app.name') }}</p>
                                    <p class="truncate text-xs text-zinc-500">You've been assigned a new task</p>
                                </div>
                                <span class="ml-auto shrink-0 text-xs text-zinc-400">Preview</span>
                            </div>
                            <div class="p-4 sm:p-6">
                                <div class="mx-auto max-w-sm rounded-lg bg-white shadow-sm">
                                    <div class="px-6 py-5 text-center">
                                        <span class="text-base font-bold" style="color: {{ $mail_brand_color }}">{{ config('app.name') }}</span>
                                    </div>
                                    <div class="border-t border-zinc-100 px-6 py-6 text-center">
                                        <p class="text-sm font-semibold text-zinc-900">Hi there,</p>
                                        <p class="mt-1 text-sm text-zinc-600">This is how your notification emails will look.</p>
                                        <span class="mt-5 inline-block rounded-md px-5 py-2.5 text-sm font-semibold" style="background-color: {{ $mail_brand_color }}; color: {{ $mail_button_text_color }}">View task</span>
                                    </div>
                                    <div class="border-t border-zinc-100 px-6 py-4 text-center text-xs text-zinc-400">
                                        @if ($mail_footer_note)
                                            <p class="mb-1 text-zinc-500">{{ $mail_footer_note }}</p>
                                        @endif
                                        © {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-xl border border-zinc-200 bg-white px-4 py-5 sm:px-6">
                            <h2 class="text-base font-semibold text-zinc-900">Send a real preview</h2>
                            <p class="text-sm text-zinc-500">Saves the template, then emails you a real sample.</p>
                            <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-start">
                                <div class="flex-1">
                                    <label for="template_preview_email" class="sr-only">Send preview to</label>
                                    <input wire:model="template_preview_email" id="template_preview_email" type="email" placeholder="you@example.com" class="{{ $input }}">
                                    @error('template_preview_email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                </div>
                                <button type="button" wire:click="sendTemplatePreview" wire:loading.attr="disabled" wire:target="sendTemplatePreview" class="{{ $secondary }}">
                                    {!! $sendIcon !!}
                                    <span wire:loading.remove wire:target="sendTemplatePreview">Send preview</span>
                                    <span wire:loading wire:target="sendTemplatePreview">Sending…</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
