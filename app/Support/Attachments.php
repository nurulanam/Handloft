<?php

namespace App\Support;

use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\TaskCommentAttachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Task and comment attachments: stored on the private "local" disk (never under public/storage) and
 * served only through AttachmentController, which checks the viewer may see the task.
 *
 * Only a short list of safe types is ever shown in the browser (images, PDF, plain text). Everything
 * else, including HTML and SVG, is always downloaded, so an uploaded file can't run as a page on the
 * app's own domain.
 */
final class Attachments
{
    public const DISK = 'local';

    /** Shown in the browser (lightbox / viewer), with the content type we send for them. */
    public const INLINE_TYPES = [
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'bmp' => 'image/bmp',
        'pdf' => 'application/pdf',
        'txt' => 'text/plain; charset=UTF-8',
    ];

    public static function store(UploadedFile $file, string $directory): string
    {
        return $file->store($directory, self::DISK);
    }

    /**
     * The disk a stored path lives on: the private disk, or the public one for files uploaded before
     * attachments were made private (the migration moves them, this is the fallback).
     */
    public static function diskFor(string $path): ?string
    {
        foreach ([self::DISK, 'public'] as $disk) {
            if (Storage::disk($disk)->exists($path)) {
                return $disk;
            }
        }

        return null;
    }

    public static function delete(string $path): void
    {
        foreach ([self::DISK, 'public'] as $disk) {
            Storage::disk($disk)->delete($path);
        }
    }

    public static function extension(string $name): string
    {
        return strtolower(pathinfo($name, PATHINFO_EXTENSION));
    }

    /** 'image', 'pdf' or 'file': how the page previews it. */
    public static function kindOf(string $name): string
    {
        return match (true) {
            in_array(self::extension($name), ['png', 'jpg', 'jpeg', 'gif', 'webp', 'bmp'], true) => 'image',
            self::extension($name) === 'pdf' => 'pdf',
            default => 'file',
        };
    }

    public static function inlineType(string $name): ?string
    {
        return self::INLINE_TYPES[self::extension($name)] ?? null;
    }

    public static function taskOf(TaskAttachment|TaskCommentAttachment $attachment): Task
    {
        return $attachment instanceof TaskAttachment ? $attachment->task : $attachment->comment->task;
    }
}
