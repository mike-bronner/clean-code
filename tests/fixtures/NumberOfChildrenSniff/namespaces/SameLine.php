<?php

/**
 * The same collision squeezed onto one physical line, which is the case the
 * declaration's line alone cannot separate: both classes called Twin are
 * declared on line 13, so the pair is told apart by the order they are written
 * in and by nothing else.
 *
 * Three children for the first Twin and two for the second, again, so a test
 * reads which one each report is about.
 */

declare(strict_types=1); namespace Fixture\Namespaces\Line\First { class Twin {} class LineOne extends Twin {} class LineTwo extends Twin {} class LineThree extends Twin {} } namespace Fixture\Namespaces\Line\Second { class Twin {} class LineFour extends Twin {} class LineFive extends Twin {} }
