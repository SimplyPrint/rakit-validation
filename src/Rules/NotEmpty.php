<?php

namespace Rakit\Validation\Rules;

use Rakit\Validation\Rule;

class NotEmpty extends Rule
{
    /** @var string */
    protected $message = "The :attribute must not be empty";

    /**
     * Check the value is valid
     *
     * @param mixed $value
     * @return bool
     * @throws \Exception
     */
    public function check($value): bool
    {
        return !empty($value);
    }
}
