<?php

namespace Adshares\Adserver\Rules;

use Adshares\Supply\Domain\ValueObject\Size;
use Illuminate\Contracts\Validation\Rule;

class SizeRule implements Rule
{
    public function passes($attribute, $value): bool
    {
        if (null === $value) {
            return false;
        }

        return Size::isValid($value);
    }

    public function message(): ?string
    {
        return "The ':input' is not a valid size.";
    }
}
