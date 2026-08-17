<?php

// An array action naming a method outside the seven RESTful actions is a
// special action route pointing at a shared controller rather than at an
// invokable one. Every verb that takes its action second is exercised, so a
// verb dropped from the enumeration falls out of the report.
Route::get('/posts/archive', [PostController::class, 'archive']);
Route::post('/posts/publish', [PostController::class, 'publish']);
Route::put('/posts/restore', [PostController::class, 'restore']);
Route::patch('/posts/pin', [PostController::class, 'pin']);
Route::delete('/posts/purge', [PostController::class, 'purge']);
Route::options('/posts/probe', [PostController::class, 'probe']);
Route::any('/posts/sync', [PostController::class, 'sync']);

// The legacy 'Controller@method' string spelling of the same violation.
Route::get('/tags/archive', 'TagController@archive');
Route::post('/tags/merge', 'App\Http\Controllers\TagController@merge');

// Route::match reads its action from the *third* argument. Reading position 2
// here would inspect the URI and report nothing.
Route::match(['get', 'post'], '/posts/export', [PostController::class, 'export']);
Route::match(['get', 'head'], '/tags/export', 'TagController@export');

// A fully qualified class name is still a literal ::class constant, whichever
// way the tokenizer spells it.
Route::get('/reports/rebuild', [\App\Http\Controllers\ReportController::class, 'rebuild']);

// The long-form array literal is the same action shape.
Route::get('/reports/queue', array(ReportController::class, 'queue'));

// PHP method names are case-insensitive, so an upper-cased verb calls the
// same method and registers the same route.
Route::GET('/reports/replay', [ReportController::class, 'replay']);

// The leading separator is backfilled to its own token, so the receiver
// directly before the :: is still the literal `Route`.
\Route::get('/reports/purge', [ReportController::class, 'purge']);

// A RESTful *looking* name that is not one of the seven, and one that differs
// only in case — the list is matched exactly and case-sensitively.
Route::get('/posts/indexes', [PostController::class, 'indexAll']);
Route::get('/posts/Show', [PostController::class, 'Show']);

// A named action is the same registration written another way, so it is read
// the same. The name identifies the argument on its own: it is read where it
// follows a positional uri, where every argument is named, where the names are
// written in the other order, and in a match() call whose action would
// otherwise be found by position.
Route::get('/posts/feature', action: [PostController::class, 'feature']);
Route::post(uri: '/posts/publish', action: [PostController::class, 'publish']);
Route::get(action: 'PostController@promote', uri: '/posts/promote');
Route::match(['get', 'post'], uri: '/posts/rebuild', action: [PostController::class, 'rebuild']);

// A comment inside the argument list is skipped along with the whitespace
// around it, so it neither opens an argument nor shifts the action's position.
Route::post('/posts/note', /* the action follows */ [PostController::class, 'note']);

// `class` is a keyword there, and a keyword carries no casing.
Route::get('/posts/rewrite', [PostController::CLASS, 'rewrite']);

// A double-quoted string with nothing to interpolate is tokenized as the same
// constant string the single-quoted spelling produces, so the legacy action is
// read out of it identically. The interpolated spelling stays in passing.php:
// that one is a genuinely dynamic action, and this one is not.
Route::get('/posts/reissue', "PostController@reissue");

// The array action's method element is the same literal either way, and the
// acceptance criteria name both spellings for it too. The interpolated
// spelling of *this* element is in passing.php, beside its string-action twin.
Route::get('/posts/reindex', [PostController::class, "reindex"]);
