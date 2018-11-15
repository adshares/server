<?php
/**
 * Copyright (c) 2018 Adshares sp. z o.o.
 *
 * This file is part of AdServer
 *
 * AdServer is free software: you can redistribute and/or modify it
 * under the terms of the GNU General Public License as published
 * by the Free Software Foundation, either version 3 of the License,
 * or (at your option) any later version.
 *
 * AdServer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty
 * of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with AdServer. If not, see <https://www.gnu.org/licenses/>
 */

declare(strict_types = 1);

namespace Adshares\Adserver\Utilities;

use Adshares\Common\Domain\Id;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class UniqueId implements Id
{
    /** @var UuidInterface */
    private $value;

    public function __construct(string $value)
    {
        $this->value = Uuid::fromString($value);
    }

    public static function fromUuid(UuidInterface $uuid): UniqueId
    {
        $object = new self('');
        $object->value = $uuid;

        return $object;
    }

    public function __toString(): string
    {
        return $this->value->toString();
    }

    public function equals(self $other): bool
    {
        return $this->value->equals($other->value);
    }
}
