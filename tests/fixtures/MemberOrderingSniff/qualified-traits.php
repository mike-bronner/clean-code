<?php

// PHP_CodeSniffer 4 reads each of these trait names as one name token. Every
// verdict below changes if the sniff drops a qualified name or keeps the
// `namespace` keyword of a relative one.

declare(strict_types=1);

class QualifiedTraitModel extends Model
{
    use \Zeta;
    use Alpha;
    use Mike\Omega;
    use Beta;
    use namespace\Charlie;
    use Delta;
    use namespace\Theta;
    use Gamma;
}
