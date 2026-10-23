<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\DB;

User::query()->update(['active' => false]);
User::where('expired', true)->delete();
\App\Models\User::query()->create(['name' => $name]);
DB::table('versions')->where('abbreviation', 'RP')->update(['abbreviation' => 'NRP']);
DB::table('versions')->delete();
\Illuminate\Support\Facades\DB::table('versions')->update(['abbreviation' => 'NRP']);
\DB::table('versions')->delete();
DB::connection('legacy')->table('versions')->update(['abbreviation' => 'NRP']);
$client->delete($url);
Gateway::make()->delete($url);
$save = function (User $user): void {
    $user->save();
};
$save = fn (User $user) => $user->save();

class UserController
{
    public function store(User $user): void
    {
        $user->save();
    }

    public function untyped($client): void
    {
        $client->delete($this->url);
    }

    public function local(): void
    {
        $client = $this->client;
        $client->delete($this->url);
    }

    public function nullable(?PendingRequest $client): void
    {
        $client->delete($this->url);
    }

    public function unionWithModel(PendingRequest|User $client): void
    {
        $client->delete($this->url);
    }

    public function unionWithNull(PendingRequest|null $client): void
    {
        $client->delete($this->url);
    }

    public function defaultNull(PendingRequest $client = null): void
    {
        $client->delete($this->url);
    }

    public function variadic(PendingRequest ...$clients): void
    {
        $clients->delete($this->url);
    }

    public function reassigned(PendingRequest $client, User $user): void
    {
        $client = $user;
        $client->delete($this->url);
    }

    public function passedOn(PendingRequest $client): void
    {
        $this->prepare($client);
        $client->delete($this->url);
    }

    public function property(PendingRequest $client): void
    {
        $this->client->delete($this->url);
    }

    public function staticProperty(PendingRequest $client): void
    {
        Gateway::$client->delete($this->url);
    }

    public function variableVariable(PendingRequest $client): void
    {
        $$client->delete($this->url);
    }

    public function captured(PendingRequest $client): void
    {
        $send = fn () => $client->delete($this->url);
    }

    public function selfTyped(self $client): void
    {
        $client->delete($this->url);
    }

    public function scalar(string $client): void
    {
        $client->delete($this->url);
    }

    public function owned(PendingRequest $client): void
    {
        $client->delete($this->url);
    }
}

namespace App\Models;

class Post
{
    public function archive(): void
    {
        Post::query()->update(['archived' => true]);
        User::query()->update(['archived' => true]);
    }
}

$send = fn (\Illuminate\Http\Client\PendingRequest $client) => $client->withToken($token);
$client->delete($url);

interface Remover
{
    public function remove(\Illuminate\Http\Client\PendingRequest $client): void;
}

$client->delete($url);

function removeOther(\Illuminate\Http\Client\PendingRequest $client, $other): void
{
    $other->delete($url);
}
