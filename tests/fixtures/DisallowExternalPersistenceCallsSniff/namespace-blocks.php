<?php

namespace App\Http\Controllers;

use App\Models\Customer as Member;
use App\Models\User;
use Vendor\Account;

User::create(['name' => $name]);

namespace App\Jobs;

User::create(['name' => $name]);
Member::create(['name' => $name]);

namespace App\Models;

Account::create(['name' => $name]);
