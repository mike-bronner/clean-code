<?php

use App\Models\User;

class UserController
{
    public function show(User $user): User
    {
        return $user;
    }

    public function withLiteral(): ?User
    {
        return User::find(1);
    }

    public function withProperty(): ?User
    {
        return User::find($this->userId);
    }

    public function withParameterProperty(Request $request): ?User
    {
        return User::find($request->id);
    }

    public function withLocal(int $id): ?User
    {
        $key = $id + 1;

        return User::find($key);
    }

    public function withQueryBuilder(int $id): ?User
    {
        return User::where('id', $id)->first();
    }

    public function withInstanceFinder(int $id): ?User
    {
        return User::query()->find($id);
    }

    public function withRepository(int $id): ?User
    {
        return $this->repository->find($id);
    }

    public function withNearMissNames(int $id, array $ids): mixed
    {
        User::findMany($ids);
        User::findOr($id);
        User::first($id);
        User::$connection;
        User::{$this->finder}($id);
        resolveWith(User::Find, $id);

        return User::Find;
    }

    public function withVariableClassName(string $model, int $id): mixed
    {
        return $model::find($id);
    }

    public function withRelativeScope(int $id): mixed
    {
        self::find($id);
        static::find($id);
        parent::find($id);

        return User::find(...);
    }

    protected function protectedAction(int $id): ?User
    {
        return User::find($id);
    }

    private function privateAction(int $id): ?User
    {
        return User::find($id);
    }
}

class UserService
{
    public function show(int $id): ?User
    {
        return User::find($id);
    }
}

function showUser(int $id): ?User
{
    return User::find($id);
}

$resolve = static function (int $id): ?User {
    return User::find($id);
};
