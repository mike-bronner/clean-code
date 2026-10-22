<?php

namespace App\Http;

use App\Models\User;
use App\Models\Customer as Member;
use App\Models;

User::create();
Member::create();
Models\Invoice::create();
Account::create();
\Vendor\Client::create();
