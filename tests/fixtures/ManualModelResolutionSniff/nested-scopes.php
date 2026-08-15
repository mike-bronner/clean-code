<?php

use App\Models\User;

class UserController
{
    public function shadowedByClosure(int $id): mixed
    {
        return collect([1, 2])->map(function (int $id): ?User {
            return User::find($id);
        });
    }

    public function shadowedByArrowFunction(int $id): mixed
    {
        return collect([1, 2])->map(fn (int $id): ?User => User::find($id));
    }

    public function insideAnonymousClass(int $id): object
    {
        return new class {
            public function resolve(int $id): ?User
            {
                return User::find($id);
            }
        };
    }

    public function insideNestedFunction(int $id): mixed
    {
        function resolveUser(int $unrelated): ?User
        {
            return User::find($unrelated);
        }

        return resolveUser($id);
    }

    public function insideNestedFunctionInClosure(int $id): mixed
    {
        return collect([1, 2])->each(function (): void {
            function resolveOtherUser(int $unrelated): ?User
            {
                return User::find($unrelated);
            }
        });
    }
}
