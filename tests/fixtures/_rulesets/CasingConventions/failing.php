<?php

// Local variables — camelCase required.
$fooBar = 1;
$x = 2;
$userId = 3;
$userID = 4;
$foo_bar = 5;
$FooBar = 6;
$UPPER_CASE = 7;
$_foo = 8;

// Class names — PascalCase required; acronym runs are accepted.
class HttpClient {}
class HTTPClient {}
class http_client {}
class badClassName {}
abstract class bad_abstract {}
interface UserRepository {}
interface bad_interface {}
trait HandlesEvents {}
trait bad_trait {}
enum OrderStatus {}
enum bad_enum {}

class NamingFixture
{
    // Class constants are conventionally UPPER_SNAKE_CASE and excluded here.
    public const MAX_RETRIES = 3;

    public int $fooBar = 1;
    protected string $userId = '';
    private bool $enabled = false;
    public int $snake_case = 0;
    public int $PascalProp = 0;
    public int $_hasUnderscore = 0;
    private int $_legacy = 0;

    public function __construct()
    {
        $localOk = 1;
        $_inClass = 2;
        $bad_local = 3;
    }

    public function doSomething(): void {}
    public function getUserID(): void {}
    public function do_something_bad(): void {}
    public function DoSomethingBad(): void {}
    private function _legacyHelper(): void {}
}
