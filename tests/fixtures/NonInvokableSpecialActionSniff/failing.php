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
