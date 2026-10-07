<?php

namespace App\Http\Controllers;

use App\Models\TaskAttachment;
use App\Models\TaskCommentAttachment;
use App\Support\Attachments;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves a task or comment attachment to someone allowed to see the task. Safe types (images, PDF,
 * plain text) open in the browser; everything else downloads. ?download=1 always downloads.
 */
class AttachmentController extends Controller
{
    public function __invoke(Request $request, string $kind, int $id): StreamedResponse
    {
        $attachment = $kind === 'comment' ? TaskCommentAttachment::findOrFail($id) : TaskAttachment::findOrFail($id);

        Gate::authorize('view', Attachments::taskOf($attachment));

        $disk = Attachments::diskFor($attachment->path);
        abort_if($disk === null, 404);

        $inlineType = $request->boolean('download') ? null : Attachments::inlineType($attachment->original_name);

        $headers = [
            'Content-Type' => $inlineType ?? 'application/octet-stream',
            // Never let the browser guess a type (e.g. treat a .txt as HTML).
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
        ];

        // Even what's shown inline can't run scripts. (Chrome's PDF viewer refuses to render under a
        // sandbox, and doesn't run page scripts anyway, so PDFs only get nosniff.)
        if ($inlineType && ! str_starts_with($inlineType, 'application/pdf')) {
            $headers['Content-Security-Policy'] = "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox";
        }

        return Storage::disk($disk)->response(
            $attachment->path,
            $attachment->original_name,
            $headers,
            $inlineType ? 'inline' : 'attachment',
        );
    }
}
