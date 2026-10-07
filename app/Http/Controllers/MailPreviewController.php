<?php

namespace App\Http\Controllers;

use App\Notifications\MailTemplatePreview;
use App\Support\MailTheme;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/**
 * The live email preview on Settings → Email Template: the sample notification email rendered with
 * the (unsaved) layout, colours and footer note in the query string.
 *
 * It's a plain request rather than part of the Livewire render, because Livewire adds morph
 * markers (HTML comments) to Blade views rendered while a component renders, which would break the
 * email's Markdown.
 */
class MailPreviewController extends Controller
{
    public function __invoke(Request $request): Response
    {
        abort_unless($request->user()->can('manage-settings'), 403);

        $data = $request->validate([
            'layout' => ['required', Rule::in(array_keys(MailTheme::LAYOUTS))],
            'brand' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'text' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'footer' => ['nullable', 'string', 'max:255'],
        ]);

        $html = MailTheme::preview([
            'layout' => $data['layout'],
            'brand' => $data['brand'],
            'text' => $data['text'],
            'footer' => $data['footer'] ?? null,
        ], fn () => (string) (new MailTemplatePreview)->toMail($request->user())->render());

        return response($html)->header('X-Frame-Options', 'SAMEORIGIN');
    }
}
