<?php

use App\Models\AppSetting;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Settings')] class extends Component
{
    public bool $show_loading_screen = true;

    public int $loading_screen_seconds = 3;

    public int $loading_screen_opacity = 10;

    public int $loading_screen_blur = 64;

    public function mount(): void
    {
        abort_unless(auth()->user()->can('manage-settings'), 403);

        $settings = AppSetting::current();

        $this->show_loading_screen = $settings->show_loading_screen;
        $this->loading_screen_seconds = $settings->loading_screen_seconds;
        $this->loading_screen_opacity = $settings->loading_screen_opacity;
        $this->loading_screen_blur = $settings->loading_screen_blur;
    }

    public function save(): void
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
};
?>

<div class="max-w-2xl space-y-6">
    <div>
        <h1 class="text-2xl font-semibold text-zinc-900">Settings</h1>
        <p class="text-sm text-zinc-500">App-wide preferences.</p>
    </div>

    <form wire:submit="save" class="space-y-1 rounded-lg border border-zinc-200 bg-white p-5">
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

            <button type="submit" class="shrink-0 rounded-lg bg-brand px-4 py-2 text-sm font-semibold text-white hover:bg-brand/90" wire:loading.attr="disabled">
                Save Settings
            </button>
        </div>
    </form>
</div>
