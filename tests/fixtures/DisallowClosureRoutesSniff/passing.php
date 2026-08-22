<?php

declare(strict_types=1);

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

// Every compliant action shape the standard allows, on the verbs the sniff
// registers on: an array action, the legacy string action, and an invokable
// controller's class reference.
Route::get('/users', [UserController::class, 'index']);
Route::post('/users', 'UserController@store');
Route::put('/users/{user}', [UserController::class, 'update']);
Route::patch('/users/{user}', [UserController::class, 'update']);
Route::delete('/users/{user}', [UserController::class, 'destroy']);
Route::options('/users', [UserController::class, 'options']);
Route::any('/legacy', [UserController::class, 'legacy']);
Route::match(['get', 'post'], '/search', [UserController::class, 'search']);
Route::fallback([UserController::class, 'missing']);
Route::get('/dashboard', DashboardController::class);

// A group callback is not a route action and caches fine, both when called
// statically and when reached through a chain.
Route::group(['prefix' => 'admin'], function () {
    Route::get('/reports', [UserController::class, 'reports']);
});
Route::middleware('auth')->group(static function () {
    Route::get('/profile', [UserController::class, 'profile']);
});
Route::prefix('v1')->group(fn () => Route::get('/ping', [UserController::class, 'ping']));

// A chained verb with a compliant action: the chain resolves to the facade, so
// the call is examined and found clean rather than skipped.
Route::middleware('auth')->get('/account', [UserController::class, 'account']);

// A closure that is not a *direct* argument of the route call. Each of these
// sits one level down — inside a nested call, and inside an array literal — and
// each is preceded by a comma of its own, so a scan that did not step over the
// nested construct would read it as this call's next argument.
Route::get('/deferred', app('registrar')->resolve('routes', fn () => [UserController::class, 'deferred']));
Route::post('/import', array_values([[UserController::class, 'import'], fn () => 1])[0]);

// A chained method that is not a registration verb. `missing` takes a genuine
// callback, and the sniff never looks at it.
Route::get('/reports/{report}', [UserController::class, 'report'])
    ->missing(function () {
        return null;
    });

// A same-named method on a class that is not the Route facade. The sniff
// matches on the class segment before `::`, so `Cache::get` stays silent even
// though its second argument really is a closure.
Cache::get('users', fn () => []);

// A router held in a variable is invisible to a token-level sniff: there is no
// `Route::` to resolve, so `$router->get` is never examined.
$router = app('router');
$router->get('/health', function () {
    return 'ok';
});

// An object operator whose left side is not a call at all. The chain walk needs
// a closing parenthesis to hop over, and there is none here.
$this->get('/probe', function () {
    return null;
});

// A declaration rather than a call. The token before the name is `function`,
// so nothing resolves to the facade.
class RouteRegistrar
{
    public function get(string $uri, callable $action): void
    {
        //
    }

    public function match(array $verbs, string $uri, callable $action): void
    {
        //
    }
}

// Three real facade calls on methods outside the nine registration verbs.
// `Route::resource` does take a controller, so this is not merely a list of
// argument-less helpers: none of the three is examined because none of them is
// one of the nine, not because there is nothing to look at.
Route::redirect('/old', '/new');
Route::view('/welcome', 'welcome');
Route::resource('/photos', UserController::class);
