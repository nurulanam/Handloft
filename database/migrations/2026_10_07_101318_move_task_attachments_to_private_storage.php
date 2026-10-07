<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    /**
     * Attachments used to live on the public disk (anyone with the link could open them, and an
     * uploaded HTML file ran as a page on the app's domain). Move them to the private disk, where
     * they're only served through the authorising AttachmentController. Paths don't change.
     */
    public function up(): void
    {
        $public = Storage::disk('public');
        $private = Storage::disk('local');

        foreach (['task_attachments', 'task_comment_attachments'] as $table) {
            DB::table($table)->select('path')->orderBy('id')->each(function ($row) use ($public, $private) {
                if (! $public->exists($row->path) || $private->exists($row->path)) {
                    return;
                }

                $stream = $public->readStream($row->path);
                $private->writeStream($row->path, $stream);
                if (is_resource($stream)) {
                    fclose($stream);
                }

                if ($private->exists($row->path)) {
                    $public->delete($row->path);
                }
            });
        }
    }

    public function down(): void
    {
        // Deliberately not moved back to the public disk.
    }
};
