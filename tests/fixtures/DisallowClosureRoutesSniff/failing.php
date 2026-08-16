<?php

declare(strict_types=1);

use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// Every registration verb, with a closure action.
Route::get('/a', function () {
    return 'a';
});
Route::post('/b', fn () => 'b');
Route::put('/c', static function () {
    return 'c';
});
Route::patch('/d', static fn () => 'd');
Route::delete('/e', function () {
    return 'e';
});
Route::options('/f', function () {
    return 'f';
});
Route::any('/g', function () {
    return 'g';
});

// The action is the third argument here, and the first argument is an array of
// verbs whose commas must not be read as this call's argument boundaries.
Route::match(['get', 'post'], '/h', function () {
    return 'h';
});

// The action is the only argument.
Route::fallback(function () {
    return 'i';
});

// A fully qualified facade reference resolves to the same class.
\Illuminate\Support\Facades\Route::get('/j', function () {
    return 'j';
});

// A verb reached through a chain of builder calls.
Route::middleware('auth')->get('/k', fn () => 'k');
Route::middleware('auth')->prefix('admin')->post('/l', function () {
    return 'l';
});

// A named action argument.
Route::put('/m', action: function () {
    return 'm';
});

// The group callback itself is compliant; the route registered inside it is
// not. Exactly one violation belongs to this statement, on the inner closure.
Route::group(['prefix' => 'admin'], function () {
    Route::get('/n', function () {
        return 'n';
    });
});

// A closure inside the action closure's own body is part of that action, not a
// second route action. Exactly one violation belongs to this statement.
Route::get('/o', function () {
    $inner = fn () => 'o';

    return $inner();
});

// A compliant call in the same file, so the failing fixture also proves the
// sniff is not simply reporting every route.
Route::get('/p', [UserController::class, 'index']);

// `match` is a PHP keyword, and the verb list can only carry it because PHPCS
// hands it back as a plain T_STRING when it names a method. The static
// spelling is line 29; this is the chained one.
Route::middleware('auth')->match(['get', 'post'], '/q', function () {
    return 'q';
});
