<?php

// A suite this package does not ship a name for. Under the default segments the
// location names no suite, so only the namespace speaks and the violation is
// DirectoryMismatch; add `Contract` to the configured segments and the location
// speaks too, contradicting the namespace, and the violation becomes
// NamespaceMismatch. The same file reports differently under each, which is
// what makes the property observable rather than merely set.

namespace Tests\Unit;

class ManifestTest
{
}
