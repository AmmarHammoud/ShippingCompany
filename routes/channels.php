<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});



Broadcast::channel('driver.{id}', function ($user, $id) {
    return $user->id == (int) $id && $user->role === 'driver';
});

Broadcast::channel('client.{id}', function ($user, $id) {
    return $user->id == (int) $id && $user->role === 'client';
});
