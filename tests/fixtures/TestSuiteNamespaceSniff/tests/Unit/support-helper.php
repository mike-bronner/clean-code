<?php

// A shared support helper under a suite directory. Its namespace names a
// *different* suite, so it would be reported the moment it counted as a test
// class — it does not, carrying neither the `Test` suffix nor a base class.

namespace Tests\Feature;

class SupportHelper
{
}
