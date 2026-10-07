@php
    // The look of every notification email, from Settings → Email Template (App\Support\MailTheme):
    // a layout (modern, classic or minimal), the brand colour and the button text colour. They're
    // applied as a <style> block on top of the static vendor/mail/html/themes/default.css, which is
    // never Blade-parsed; CssToInlineStyles inlines every <style> block it finds, and the !important
    // flags make these win regardless of order.
    $mailTheme = \App\Support\MailTheme::current();
    $layout = $mailTheme['layout'];
    $brand = $mailTheme['brand'];
    $buttonText = $mailTheme['text'];
    $appName = config('app.name');
    $logo = rtrim((string) config('app.url'), '/').'/apple-touch-icon.png';
@endphp
<x-mail::layout>
{{-- Head --}}
<x-slot:head>
<style>
.button-primary {
background-color: {{ $brand }} !important;
border-top: 10px solid {{ $brand }} !important;
border-right: 22px solid {{ $brand }} !important;
border-bottom: 10px solid {{ $brand }} !important;
border-left: 22px solid {{ $brand }} !important;
color: {{ $buttonText }} !important;
font-weight: 600 !important;
}
a { color: {{ $brand }}; }
@if ($layout === 'modern')
body, .wrapper, .body { background-color: #f4f4f5 !important; }
.header { padding: 32px 0 20px !important; }
.header a { color: #18181b !important; font-size: 18px !important; font-weight: 700 !important; text-decoration: none !important; }
.inner-body { background-color: #ffffff !important; border: 1px solid #e4e4e7 !important; border-top: 4px solid {{ $brand }} !important; border-radius: 16px !important; box-shadow: 0 1px 3px rgba(24, 24, 27, 0.06) !important; }
.content-cell { padding: 36px !important; }
h1 { color: #18181b !important; font-size: 20px !important; }
p { color: #3f3f46 !important; }
.button { border-radius: 10px !important; }
.subcopy { border-top: 1px solid #f4f4f5 !important; }
.footer p { color: #a1a1aa !important; }
@elseif ($layout === 'minimal')
body, .wrapper, .body { background-color: #ffffff !important; }
.header { padding: 28px 0 0 !important; }
.inner-body { background-color: #ffffff !important; border: none !important; border-radius: 0 !important; box-shadow: none !important; }
.content-cell { padding: 20px 0 28px !important; }
h1 { color: #18181b !important; font-size: 18px !important; text-align: left !important; }
p { color: #3f3f46 !important; text-align: left !important; }
.action, .action td { text-align: left !important; }
.button { border-radius: 6px !important; }
.footer { border-top: 1px solid #e4e4e7 !important; }
.footer p { color: #a1a1aa !important; text-align: left !important; }
.footer .content-cell { padding: 20px 0 32px !important; }
@else
.header a { color: {{ $brand }} !important; }
.button { border-radius: 4px !important; }
@endif
</style>
</x-slot:head>

{{-- Header --}}
<x-slot:header>
@if ($layout === 'modern')
<tr>
<td class="header" align="center">
<a href="{{ config('app.url') }}" style="display: inline-block; text-decoration: none;">
<img src="{{ $logo }}" width="36" height="36" alt="" style="width: 36px; height: 36px; border-radius: 9px; vertical-align: middle; margin-right: 10px;">
<span style="vertical-align: middle;">{{ $appName }}</span>
</a>
</td>
</tr>
@elseif ($layout === 'minimal')
<tr>
<td class="header" align="center">
<table width="570" cellpadding="0" cellspacing="0" role="presentation" align="center" class="inner-body">
<tr>
<td align="left" style="text-align: left;">
<a href="{{ config('app.url') }}" style="font-size: 15px; font-weight: 700; color: #18181b; text-decoration: none;">
<span style="display: inline-block; width: 10px; height: 10px; border-radius: 3px; background-color: {{ $brand }}; margin-right: 8px;"></span>{{ $appName }}
</a>
</td>
</tr>
</table>
</td>
</tr>
@else
<x-mail::header :url="config('app.url')">
{{ $appName }}
</x-mail::header>
@endif
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
@if ($note = $mailTheme['footer'])
{{ $note }}<br>
@endif
© {{ date('Y') }} {{ $appName }}. {{ __('All rights reserved.') }}
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
