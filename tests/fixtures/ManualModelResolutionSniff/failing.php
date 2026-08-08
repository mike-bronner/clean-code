<?php

use App\Models;
use App\Models\User;

class UserController
{
    public function show(int $id): ?User
    {
        return User::find($id);
    }

    public function edit(int $id): User
    {
        return User::findOrFail($id);
    }

    public function shout(int $id): ?User
    {
        return User::FIND($id);
    }

    function implicitlyPublic(int $id): ?User
    {
        return User::find($id);
    }

    public function qualified(int $id): ?User
    {
        return Models\User::find($id);
    }

    public function columns(int $id): ?User
    {
        return User::find($id, ['name']);
    }

    public function secondParameter(string $slug, int $id): ?User
    {
        return User::findOrFail($id);
    }

    public function nested(int $id): array
    {
        return ['user' => User::find($id)];
    }

    public function inheritedByClosure(int $id): mixed
    {
        return DB::transaction(function () use ($id) {
            return User::findOrFail($id);
        });
    }
}
