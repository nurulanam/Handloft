<?php

use App\Mail\SmtpTestMail;
use App\Models\AppSetting;
use App\Support\MailSettings;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Settings')] class extends Component
{
    #[Url]
    public string $tab = 'loading-screen';

    public bool $show_loading_screen = true;

    public int $loading_screen_seconds = 3;

    public int $loading_screen_opacity = 10;

    public int $loading_screen_blur = 64;

    public string $mail_host = '';

    public string $mail_port = '';

    public string $mail_username = '';

    public string $mail_password = '';

    public string $mail_encryption = 'tls';

    public string $mail_from_address = '';

    public string $mail_from_name = '';

    public string $test_email = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->can('manage-settings'), 403);

        $settings = AppSetting::current();

        $this->show_loading_screen = $settings->show_loading_screen;
        $this->loading_screen_seconds = $settings->loading_screen_seconds;
        $this->loading_screen_opacity = $settings->loading_screen_opacity;
        $this->loading_screen_blur = $settings->loading_screen_blur;

        $this->mail_host = (string) $settings->mail_host;
        $this->mail_port = (string) ($settings->mail_port ?? '');
        $this->mail_username = (string) $settings->mail_username;
        // The saved password is never sent back to the browser — leave blank
        // and only change it if the admin types a new one.
        $this->mail_encryption = $settings->mail_encryption ?: 'tls';
        $this->mail_from_address = (string) $settings->mail_from_address;
        $this->mail_from_name = (string) $settings->mail_from_name;

        $this->test_email = auth()->user()->email;
    }

    public function saveLoadingScreen(): void
    {
        abort_unless(auth()->user()->can('manage-settings'), 403);

        $data = $this->validate([
            'show_loading_screen' => ['boolean'],
            'loading_screen_seconds' => ['required', 'integer', 'min:1', 'max:10'],
            'loading_screen_opacity' => ['required', 'integer', 'min:0', 'max:100'],
            'loading_screen_blur' => ['required', 'integer', 'min:0', 'max:100'],
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
};
?>

<div class="max-w-4xl space-y-6">
    <div>
        <h1 class="text-2xl font-semibold text-zinc-900">Settings</h1>
        <p class="text-sm text-zinc-500">App-wide preferences.</p>
    </div>

    <div class="flex flex-col gap-6 lg:flex-row">
        {{-- Sub-sidebar --}}
        <nav class="flex shrink-0 gap-1 overflow-x-auto lg:w-48 lg:flex-col lg:overflow-visible">
            @foreach ([
                'loading-screen' => 'Loading Screen',
                'smtp' => 'Email / SMTP',
            ] as $key => $label)
                <button
                    type="button"
                    wire:click="$set('tab', '{{ $key }}')"
                    class="shrink-0 rounded-lg px-3 py-2 text-left text-sm font-medium {{ $tab === $key ? 'bg-brand/10 text-brand' : 'text-zinc-600 hover:bg-zinc-100' }}"
                >
                    {{ $label }}
                </button>
            @endforeach
        </nav>

        {{-- Content --}}
        <div class="min-w-0 flex-1">
            @if ($tab === 'loading-screen')
                <form wire:submit="saveLoadingScreen" class="space-y-1 rounded-lg border border-zinc-200 bg-white p-5">
                    <h2 class="mb-2 text-sm font-semibold uppercase tracking-wide text-zinc-500">Login Loading Screen</h2>

                    <div class="flex items-center justify-between gap-4 py-3">
                        <div>
                            <p class="text-sm font-medium text-zinc-900">Show loading screen after login</p>
                            <p class="text-xs text-zinc-500">Briefly shows a branded loading overlay over the dashboard right after signing in.</p>
                        </div>

                        <label class="relative inline-flex shrink-0 cursor-pointer items-center">
                            <input wire:model="show_loading_screen" type="checkbox" class="peer sr-only">
                            <div class="h-6 w-11 rounded-full bg-zinc-200 transition-colors peer-checked:bg-brand"></div>
                            <div class="absolute left-1 top-1 size-4 rounded-full bg-white transition-transform peer-checked:translate-x-5"></div>
                        </label>
                    </div>

                    <div class="border-t border-zinc-100 py-3" :class="! $wire.show_loading_screen ? 'opacity-40' : ''" x-data>
                        <div class="flex items-center justify-between">
                            <label for="loading_screen_seconds" class="text-sm font-medium text-zinc-900">Loading screen duration</label>
                            <span class="text-sm font-semibold text-brand">{{ $loading_screen_seconds }}s</span>
                        </div>
                        <input
                            wire:model.live="loading_screen_seconds"
                            id="loading_screen_seconds"
                            type="range"
                            min="1"
                            max="10"
                            step="1"
                            :disabled="! $wire.show_loading_screen"
                            class="mt-2 w-full accent-brand"
                        >
                        @error('loading_screen_seconds') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="border-t border-zinc-100 py-3" :class="! $wire.show_loading_screen ? 'opacity-40' : ''" x-data>
                        <div class="flex items-center justify-between">
                            <label for="loading_screen_opacity" class="text-sm font-medium text-zinc-900">Overlay transparency</label>
                            <span class="text-sm font-semibold text-brand">{{ $loading_screen_opacity }}%</span>
                        </div>
                        <p class="text-xs text-zinc-500">How strong the white glass tint is — lower is more see-through.</p>
                        <input
                            wire:model.live="loading_screen_opacity"
                            id="loading_screen_opacity"
                            type="range"
                            min="0"
                            max="100"
                            step="5"
                            :disabled="! $wire.show_loading_screen"
                            class="mt-2 w-full accent-brand"
                        >
                        @error('loading_screen_opacity') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="border-t border-zinc-100 py-3" :class="! $wire.show_loading_screen ? 'opacity-40' : ''" x-data>
                        <div class="flex items-center justify-between">
                            <label for="loading_screen_blur" class="text-sm font-medium text-zinc-900">Overlay blur</label>
                            <span class="text-sm font-semibold text-brand">{{ $loading_screen_blur }}px</span>
                        </div>
                        <p class="text-xs text-zinc-500">How blurred the dashboard looks behind the glass.</p>
                        <input
                            wire:model.live="loading_screen_blur"
                            id="loading_screen_blur"
                            type="range"
                            min="0"
                            max="100"
                            step="4"
                            :disabled="! $wire.show_loading_screen"
                            class="mt-2 w-full accent-brand"
                        >
                        @error('loading_screen_blur') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex items-center justify-between gap-4 border-t border-zinc-100 pt-4">
                        <div
                            class="flex h-20 flex-1 items-center justify-center gap-2 overflow-hidden rounded-lg border border-zinc-200 text-xs text-zinc-400"
                            style="background-image: linear-gradient(135deg, #10512a 0%, #bfef1e 50%, #10512a 100%);"
                        >
                            <div
                                class="flex h-full w-full items-center justify-center"
                                style="background-color: rgba(255, 255, 255, {{ $loading_screen_opacity / 100 }}); backdrop-filter: blur({{ $loading_screen_blur }}px); -webkit-backdrop-filter: blur({{ $loading_screen_blur }}px);"
                            >
                                <span class="rounded bg-zinc-900/70 px-2 py-1 text-xs font-medium text-white">Preview</span>
                            </div>
                        </div>

                        <button type="submit" class="shrink-0 rounded-lg bg-brand px-4 py-2 text-sm font-semibold text-white hover:bg-brand/90" wire:loading.attr="disabled" wire:target="saveLoadingScreen">
                            Save Settings
                        </button>
                    </div>
                </form>
            @elseif ($tab === 'smtp')
                <div class="space-y-4">
                    <form wire:submit="saveSmtp" class="space-y-4 rounded-lg border border-zinc-200 bg-white p-5">
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-500">Email / SMTP</h2>
                        <p class="-mt-2 text-xs text-zinc-500">Overrides the server's default mail configuration. Leave blank to keep using the default.</p>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <label for="mail_host" class="block text-sm font-medium text-zinc-700">SMTP Host</label>
                                <input wire:model="mail_host" id="mail_host" type="text" placeholder="smtp.mailtrap.io" class="mt-1 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand-lime/40">
                                @error('mail_host') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label for="mail_port" class="block text-sm font-medium text-zinc-700">Port</label>
                                <input wire:model="mail_port" id="mail_port" type="number" placeholder="587" class="mt-1 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand-lime/40">
                                @error('mail_port') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label for="mail_encryption" class="block text-sm font-medium text-zinc-700">Encryption</label>
                                <select wire:model="mail_encryption" id="mail_encryption" class="mt-1 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand-lime/40">
                                    <option value="tls">TLS</option>
                                    <option value="ssl">SSL</option>
                                    <option value="none">None</option>
                                </select>
                            </div>

                            <div>
                                <label for="mail_username" class="block text-sm font-medium text-zinc-700">Username</label>
                                <input wire:model="mail_username" id="mail_username" type="text" autocomplete="off" class="mt-1 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand-lime/40">
                                @error('mail_username') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label for="mail_password" class="block text-sm font-medium text-zinc-700">Password</label>
                                <input wire:model="mail_password" id="mail_password" type="password" autocomplete="new-password" placeholder="{{ $mail_host ? '••••••••  (leave blank to keep)' : '' }}" class="mt-1 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand-lime/40">
                                @error('mail_password') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label for="mail_from_address" class="block text-sm font-medium text-zinc-700">From Address</label>
                                <input wire:model="mail_from_address" id="mail_from_address" type="email" placeholder="hello@example.com" class="mt-1 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand-lime/40">
                                @error('mail_from_address') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label for="mail_from_name" class="block text-sm font-medium text-zinc-700">From Name</label>
                                <input wire:model="mail_from_name" id="mail_from_name" type="text" placeholder="{{ config('app.name') }}" class="mt-1 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand-lime/40">
                                @error('mail_from_name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <div class="flex justify-end border-t border-zinc-100 pt-4">
                            <button type="submit" class="rounded-lg bg-brand px-4 py-2 text-sm font-semibold text-white hover:bg-brand/90" wire:loading.attr="disabled" wire:target="saveSmtp">
                                Save SMTP Settings
                            </button>
                        </div>
                    </form>

                    <div class="rounded-lg border border-zinc-200 bg-white p-5">
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-500">Live Test</h2>
                        <p class="mt-1 text-xs text-zinc-500">Sends a real email using the settings above — saved or not — so you can verify them before committing.</p>

                        <div class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-end">
                            <div class="flex-1">
                                <label for="test_email" class="block text-sm font-medium text-zinc-700">Send test to</label>
                                <input wire:model="test_email" id="test_email" type="email" class="mt-1 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand-lime/40">
                                @error('test_email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <button type="button" wire:click="testSmtp" wire:loading.attr="disabled" wire:target="testSmtp" class="shrink-0 rounded-lg border border-brand px-4 py-2 text-sm font-semibold text-brand hover:bg-brand/10 disabled:opacity-60">
                                <span wire:loading.remove wire:target="testSmtp">Send Test Email</span>
                                <span wire:loading wire:target="testSmtp">Sending…</span>
                            </button>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
