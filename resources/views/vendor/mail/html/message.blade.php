@php
    // Lets an admin reskin every outgoing notification email from the
    // Settings page without redeploying — these two colors are the only
    // "theme" the static vendor/mail/html/themes/default.css can't express,
    // since that file is served as a plain static asset (never Blade-parsed)
    // by Illuminate\Mail\Markdown::render(). Overriding them here instead,
    // wrapped in an actual <style> tag, works because CssToInlineStyles
    // inlines whatever <style> blocks it finds in the rendered HTML
    // alongside the static theme — the !important flags make sure this
    // block wins regardless of inlining order.
    $brand = \App\Models\AppSetting::current()->mailBrandColor();
    $buttonText = \App\Models\AppSetting::current()->mailButtonTextColor();
@endphp
<x-mail::layout>
{{-- Head --}}
<x-slot:head>
<style>
.header a { color: {{ $brand }} !important; }
.button-primary {
background-color: {{ $brand }} !important;
border-top: 8px solid {{ $brand }} !important;
border-right: 18px solid {{ $brand }} !important;
border-bottom: 8px solid {{ $brand }} !important;
border-left: 18px solid {{ $brand }} !important;
color: {{ $buttonText }} !important;
}
</style>
</x-slot:head>

{{-- Header --}}
<x-slot:header>
<x-mail::header :url="config('app.url')">
{{ config('app.name') }}
</x-mail::header>
</x-slot:header>

{{-- Body --}}
{!! $slot !!}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer --}}
<x-slot:footer>
<x-mail::footer>
@if ($note = \App\Models\AppSetting::current()->mail_footer_note)
{{ $note }}<br>
@endif
© {{ date('Y') }} {{ config('app.name') }}. {{ __('All rights reserved.') }}
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
