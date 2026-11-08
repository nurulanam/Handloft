<?php

namespace App\Models;

use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A customer the team does work for. Projects belong to a client; a task's client is its project's.
 */
#[Fillable(['name'])]
class Client extends Model
{
    /** @use HasFactory<ClientFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The client with this name, created if it doesn't exist yet. Names match regardless of case and
     * stray spaces, so "acme " and "Acme" are one client.
     */
    public static function named(string $name): self
    {
        $name = trim(preg_replace('/\s+/', ' ', $name));

        return self::withTrashed()->whereRaw('lower(name) = ?', [mb_strtolower($name)])->first()?->restoreIfTrashed()
            ?? self::create(['name' => $name]);
    }

    private function restoreIfTrashed(): self
    {
        if ($this->trashed()) {
            $this->restore();
        }

        return $this;
    }

    /**
     * Every task of the client's projects.
     *
     * @return HasManyThrough<Task, Project, $this>
     */
    public function tasks(): HasManyThrough
    {
        return $this->hasManyThrough(Task::class, Project::class);
    }

    /**
     * @return HasMany<Project, $this>
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }
}
