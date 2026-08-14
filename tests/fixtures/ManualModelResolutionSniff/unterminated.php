<?php

use App\Models\User;

class UserController
{
    public function show(int $id): ?User
    {
        return User::find($id);
    }

    User::find($id);
}

User::find($id
