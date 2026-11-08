<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Every signed-in user may follow the task board.
Broadcast::channel('tasks', fn ($user) => $user !== null);
