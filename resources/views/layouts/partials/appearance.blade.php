{{-- The look, per person and per browser (Settings → Look and the account menu), kept in localStorage:
     visual style (Liquid glass / Static), theme colour and colour mode (Light / Dark / System). Defaults:
     Liquid glass, Forest, System. Runs in <head>, before the first paint, so nothing flickers in; it
     sets classes and colours on <html>: .ui-static, .dark, .sidebar-collapsed and the theme's
     --brand-base / --brand-accent (app.css derives --color-brand from them, lifted for dark mode).
     wire:navigate copies the new page's <html> attributes over the current ones, which would drop all
     of that, so it's re-applied in the same frame as every page swap (links and back/forward alike).
     With $themeOnly (the printed report) only the theme colours are applied: print stays light. --}}
@php($themeOnly ??= false)
<script>
    (() => {
        const root = document.documentElement;
        const presets = @js(\App\Support\Theme::PRESETS);
        const read = (key) => { try { return localStorage.getItem(key); } catch { return null; } };
        const write = (key, value) => { try { localStorage.setItem(key, value); } catch {} };
        const system = matchMedia('(prefers-color-scheme: dark)');

        window.appearance = {
            presets,
            get() {
                const theme = read('appearance.theme');
                const mode = read('colorMode');
                return {
                    style: read('appearance.style') === 'static' ? 'static' : 'glass',
                    theme: presets[theme] ? theme : @js(\App\Support\Theme::DEFAULT),
                    mode: mode === 'light' || mode === 'dark' ? mode : 'system',
                };
            },
            {{-- key: "style", "theme" or "mode" --}}
            set(key, value) {
                write(key === 'mode' ? 'colorMode' : 'appearance.' + key, value);
                this.apply();
                window.dispatchEvent(new CustomEvent('appearance-changed', { detail: this.get() }));
            },
            apply() {
                const look = this.get();
                const colours = presets[look.theme];
                root.style.setProperty('--brand-base', colours.brand);
                root.style.setProperty('--brand-accent', colours.accent);
                root.style.setProperty('--color-brand', colours.brand);
                root.style.setProperty('--color-brand-lime', colours.accent);
                @unless ($themeOnly)
                    root.classList.toggle('ui-static', look.style === 'static');
                    root.classList.toggle('dark', look.mode === 'dark' || (look.mode === 'system' && system.matches));
                    root.classList.toggle('sidebar-collapsed', read('sidebarCollapsed') === 'true');
                @endunless
            },
        };

        window.appearance.apply();
        system.addEventListener('change', () => window.appearance.apply());
        document.addEventListener('livewire:navigating', (e) => e.detail?.onSwap?.(() => window.appearance.apply()));
        document.addEventListener('livewire:navigated', () => window.appearance.apply());
    })();
</script>
