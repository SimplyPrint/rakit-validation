<?php

namespace Rakit\Validation\Tests;

use PHPUnit\Framework\TestCase;
use Rakit\Validation\Validator;

/**
 * A wildcard rule expands to one Attribute per matched leaf. Every one of those must
 * be able to see its siblings, but the set is SHARED rather than copied per attribute:
 * a copy each made a wildcard quadratic in memory and time, and a 6,000-point
 * `outline.*.*` cost 3 GB and 31 s before this was fixed.
 */
class WildcardExpansionTest extends TestCase
{
    public function testSiblingsAreVisibleToEveryExpandedAttribute()
    {
        $spy = new SiblingSpyRule();
        $validator = new Validator();
        $validator->addValidator('spy', $spy);

        $validation = $validator->validate(
            ['points' => [['a'], ['b'], ['c']]],
            ['points.*' => 'spy']
        );

        $this->assertFalse($validation->fails());
        // Each leaf sees the other two, reindexed from zero, and never itself.
        $this->assertSame([
            'points.0' => ['points.1', 'points.2'],
            'points.1' => ['points.0', 'points.2'],
            'points.2' => ['points.0', 'points.1'],
        ], $spy->seen->getArrayCopy());
    }

    /**
     * On a regression this either blows the bound below or exhausts memory outright.
     * Both are failures; the bound is ~8x the patched cost and ~8x under the old one.
     */
    public function testNestedWildcardDoesNotCopyTheSiblingSetPerLeaf()
    {
        $grid = array_fill(0, 1500, [1.5, 2.5]); // 3,000 leaves

        $before = memory_get_peak_usage(true);
        $validation = (new Validator())->validate(
            ['grid' => $grid],
            ['grid.*' => 'array', 'grid.*.*' => 'numeric']
        );
        $peak = memory_get_peak_usage(true) - $before;

        $this->assertFalse($validation->fails());
        $this->assertLessThan(
            32 * 1024 * 1024,
            $peak,
            sprintf('3,000 leaves peaked at %.1f MB — the sibling set is being copied per leaf', $peak / 1048576)
        );
    }
}
