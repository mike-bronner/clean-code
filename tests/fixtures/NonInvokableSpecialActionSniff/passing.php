<?php

// Compliant: a special action route pointing at an invokable controller. The
// bare ::class action is the shape the standard asks for.
Route::get('/reports/archive', ArchiveReportController::class);
Route::post('/reports/publish', \App\Http\Controllers\PublishReportController::class);

// An array action naming one of the seven RESTful methods is a resource route
// written longhand. That is CleanCode.Routes.NonResourceRegistration's (#248)
// diagnostic about the verb call itself; reporting it here too would
// double-report one line.
Route::get('/posts', [PostController::class, 'index']);
Route::get('/posts/create', [PostController::class, 'create']);
Route::post('/posts', [PostController::class, 'store']);
Route::get('/posts/{post}', [PostController::class, 'show']);
Route::get('/posts/{post}/edit', [PostController::class, 'edit']);
Route::put('/posts/{post}', [PostController::class, 'update']);
Route::delete('/posts/{post}', [PostController::class, 'destroy']);

// The legacy string spelling of the same RESTful shape, silent for the same
// reason.
Route::get('/tags', 'TagController@index');
Route::patch('/tags/{tag}', 'TagController@update');

// Dynamic actions are skipped rather than guessed at: a non-literal first
// element hides which class is targeted.
$controller = PostController::class;
Route::get('/dynamic-class', [$controller, 'archive']);
Route::get('/dynamic-expression', [resolveController(), 'archive']);

// A non-literal second element hides the method name itself — the very thing
// being classified.
$method = 'archive';
Route::get('/dynamic-method', [PostController::class, $method]);
Route::get('/constant-method', [PostController::class, self::ARCHIVE]);
Route::get('/const-method', [PostController::class, PostController::ARCHIVE]);

// An interpolated string action is unreadable at token level too.
Route::get('/interpolated', "PostController@{$method}");
Route::get('/interpolated-simple', "PostController@$method");

// The associative ['uses' => ...] action is a third shape entirely. It is
// skipped rather than read by index, which is what stops the sniff reporting
// 'uses' as the target method.
Route::get('/uses-string', ['uses' => 'PostController@archive']);
Route::get('/uses-array', ['uses' => [PostController::class, 'archive']]);
Route::get('/uses-with-name', ['uses' => 'PostController@archive', 'as' => 'posts.archive']);

// Closures and arrow functions belong to #174, not here.
Route::get('/closure', function () {
    return view('welcome');
});
Route::get('/arrow', fn () => view('welcome'));

// Route::match reads its action from the *third* argument, because the HTTP
// methods come first. Both the compliant invokable action and the RESTful
// longhand stay silent through that offset.
Route::match(['get', 'post'], '/reports/export', ExportReportController::class);
Route::match(['get', 'head'], '/posts/{post}', [PostController::class, 'show']);

// Fewer arguments than the action's position: there is nothing to read, which
// is not the same as a violation.
Route::get('/no-action');
Route::match(['get', 'post'], '/no-action-either');

// A verb call on any other receiver is not a route registration. Matching the
// literal `Route` token is what keeps these out.
Http::get('/remote', [ApiClient::class, 'fetch']);
Cache::get('/cached', [CacheStore::class, 'fetch']);
Router::get('/aliased', [PostController::class, 'archive']);

// The receiver is matched case-sensitively, unlike the verb after it: a class
// name is a name, and the facade is spelled `Route`. A differently cased
// spelling is another name as far as this sniff reads it, so it is left alone
// the same way `Router` above is.
route::get('/lower-cased-receiver', [PostController::class, 'archive']);
ROUTE::get('/upper-cased-receiver', [PostController::class, 'archive']);
RoUtE::post('/mixed-case-receiver', 'PostController@archive');

// Registrations that are not verb calls at all carry no action argument in
// the position this sniff reads.
Route::resource('posts', PostController::class);
Route::apiResource('tags', TagController::class);
Route::group(['prefix' => 'admin'], function () {
    Route::get('/dashboard', DashboardController::class);
});

// A named action is read by its name, so the compliant and RESTful shapes are
// as silent written that way as they are written positionally. The violating
// spellings are in failing.php.
Route::get(uri: '/named-invokable', action: ArchiveReportController::class);
Route::get(uri: '/named-restful', action: [PostController::class, 'index']);
Route::get(action: 'TagController@update', uri: '/named-restful-string');
Route::get(uri: '/named-dynamic', action: [PostController::class, $method]);
Route::match(methods: ['get', 'post'], uri: '/named-match', action: ExportReportController::class);

// PHP resolves a named argument against the parameter's own spelling, so
// `Action:` names no parameter of the call. The sniff reads no action out of
// it, and the remaining argument is named too, so nothing is read positionally
// either.
Route::get(uri: '/mis-cased-name', Action: [PostController::class, 'archive']);

// A name cannot be read positionally either: the call below names its only
// argument, so there is no second positional argument for the action.
Route::get(uri: '/named-uri-only');

// A name with nothing after it is not a call PHP accepts, but it still
// tokenizes, so the sniff has to read it without reaching past the argument.
Route::get(uri: '/named-without-a-value', action:);

// A nested comma cannot shift the action's position — the array and the call
// below are both jumped whole.
Route::get('/nested', ExportReportController::class)->middleware(['auth', 'verified']);
Route::get(route_uri('reports', 'archive'), ArchiveReportController::class);

// A string action with no method segment names nothing to classify, and one
// carrying several @ is not a spelling Laravel resolves.
Route::get('/bare-string', 'PostController');
Route::get('/double-at', 'PostController@archive@extra');

// A ::class-like first element that is not a resolvable class name is skipped
// with the rest of the dynamic shapes.
Route::get('/static-class', [static::class, 'archive']);
Route::get('/variable-class', [$this->controller::class, 'archive']);

// An array action that is not a two-element list is not the recognised shape.
Route::get('/one-element', [PostController::class]);
Route::get('/three-elements', [PostController::class, 'archive', 'extra']);

// Both halves of a string action have to be real identifiers, so an ordinary
// string that happens to hold an @ is not read as a controller action.
Route::get('/mailto', 'support@example.com');
Route::get('/empty-method', 'PostController@');
Route::get('/empty-controller', '@archive');

// A verb reached through a chained builder is a documented false negative:
// the verb is called on the object `Route::middleware()` returned, with `->`
// and not `::`, so the receiver check this sniff is scoped to never sees it.
// The sniff stays silent rather than guessing at the head of a chain, which a
// single-file token scan cannot tell from any other fluent builder.
Route::middleware('auth')->get('/chained', [PostController::class, 'archive']);
Route::prefix('admin')->name('admin.')->post('/chained-deep', 'PostController@publish');
