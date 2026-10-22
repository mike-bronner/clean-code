<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Billing\Invoice;
use App\ModelsLegacy\Account;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

User::create(['name' => $name]);
\App\Models\User::create(['name' => $name]);
Invoice::create(['total' => $total]);
\App\Models\Billing\Invoice::update(['total' => $total]);
Carbon::create(2020, 1, 1);
Storage::delete($path);
File::delete($path);
\Stripe\Customer::create(['email' => $email]);
Stripe\Customer::create(['email' => $email]);
Payment::create(['amount' => $amount]);
Account::create(['name' => $name]);
\App\ModelsLegacy\Account::create(['name' => $name]);
DB::table('versions')->update(['abbreviation' => 'NRP']);
User::query()->update(['active' => false]);
static::create(['name' => $name]);
self::create(['name' => $name]);
parent::update(['name' => $name]);
$user->save();

namespace App\Models;

class Post
{
    public function publish(): void
    {
        User::create(['name' => $this->author]);
        Post::create(['title' => $this->title]);
    }
}

namespace Domain\Accounts;

class Ledger
{
    public function record(): void
    {
        Entry::create(['amount' => $this->amount]);
        \Domain\Accounts\Entry::delete();
        \App\Models\User::create(['name' => $this->name]);
    }
}
