<?php

namespace Rakit\Validation\Tests;

use ArrayObject;
use Rakit\Validation\Rule;

/**
 * Records which sibling keys each expanded attribute can see.
 *
 * The log lives in an ArrayObject because Validator::resolveRules() clones the
 * registered rule per attribute — a plain array property would be copied with it.
 */
class SiblingSpyRule extends Rule
{
    protected $message = "spy";

    /** @var ArrayObject<string, string[]> */
    public $seen;

    public function __construct()
    {
        $this->seen = new ArrayObject();
    }

    public function check($value): bool
    {
        $attribute = $this->getAttribute();
        $keys = [];
        foreach ($attribute->getOtherAttributes() as $i => $other) {
            // Indexing by $i asserts list-ness: keys must be a 0..n-1 sequence.
            $keys[$i] = $other->getKey();
        }
        $this->seen[$attribute->getKey()] = $keys;

        return true;
    }
}
