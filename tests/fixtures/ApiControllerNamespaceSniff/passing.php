<?php

// This fixture's own path carries neither a Controllers nor an API segment, so
// every namespace below is judged against a non-API location. Braced blocks let
// one file carry several namespaces; the sniff resolves each class against the
// block enclosing it.

// Positive: the compliant view controller — no API namespace segment, and no
// API path segment either. The sniff registers on it, compares both sides and
// finds them in agreement.
namespace App\Http\Controllers {
    class ReportController
    {
    }

    // Positive: only namespace segments are compared, never the class name. A
    // controller *named* for the API still carries no API namespace segment.
    class ApiTokenController
    {
    }
}

// Positive: a nested view-controller namespace. Segments below the controller
// root are allowed; what matters is that none of them is API.
namespace App\Http\Controllers\Reports {
    class IndexController
    {
    }
}

// Positive: not a controller at all. The API segment here sits outside any
// controller root, on both the namespace and the path side, so the standard
// has nothing to say about it — without the controller-root check this class
// would read as an API-namespaced class at a non-API path.
namespace App\Services\API {
    class Client
    {
    }
}

// Positive: `Api` in the *leading* segments is not the API grouping. Only the
// segments below the controller root count, so a vendor package rooted at Api
// is left alone.
namespace Api\Http\Controllers {
    class WebhookController
    {
    }
}
