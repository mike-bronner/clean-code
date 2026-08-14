<?php

// This fixture's own path carries no API segment, so every API-namespaced
// controller below is declared somewhere its location does not agree with.

namespace App\Http\Controllers\API {
    // Negative: an API namespace, but the file does not live under an API path
    // segment.
    class ReportController
    {
        public function index(): string
        {
            // `namespace\` is the relative-name operator, not a declaration.
            // It sits above the next class, which is what makes the following
            // one a real test of the sniff telling the two apart.
            return namespace\formatted();
        }
    }

    // Negative: same verdict, resolved past the operator above it.
    class InvoiceController
    {
    }
}

// Negative: the API grouping is case-insensitive, so a lowercase segment is
// the same violation.
namespace App\Http\Controllers\api\Reports {
    class ArchiveController
    {
    }
}

// Negative: the *controller root* is matched case-insensitively too, on the
// same terms as the API grouping above. Without that normalization this
// namespace resolves to no controller root at all, and a misplaced API
// controller goes unreported.
namespace App\Http\CONTROLLERS\API {
    class LedgerController
    {
    }
}
