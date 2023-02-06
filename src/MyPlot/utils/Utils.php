<?php
declare(strict_types=1);

namespace MyPlot\utils;

class Utils
{
    /**
     * @param int $a
     * @param int $b
     * @param bool[][] $plots
     *
     * @return int[]|null
     */
    public static function findEmptyPlotSquared(int $a, int $b, array $plots): ?array
    {
        if (!isset($plots[$a][$b]))
            return [$a, $b];
        if (!isset($plots[$b][$a]))
            return [$b, $a];
        if ($a !== 0) {
            if (!isset($plots[-$a][$b]))
                return [-$a, $b];
            if (!isset($plots[$b][-$a]))
                return [$b, -$a];
        }
        if ($b !== 0) {
            if (!isset($plots[-$b][$a]))
                return [-$b, $a];
            if (!isset($plots[$a][-$b]))
                return [$a, -$b];
        }
        if (($a | $b) === 0) {
            if (!isset($plots[-$a][-$b]))
                return [-$a, -$b];
            if (!isset($plots[-$b][-$a]))
                return [-$b, -$a];
        }
        return null;
    }
}