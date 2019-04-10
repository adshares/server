<?php
/**
 * Copyright (c) 2018-2019 Adshares sp. z o.o.
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

namespace Adshares\Adserver\Models;

use Adshares\Common\Application\Dto\ExchangeRate as DomainExchangeRate;
use Illuminate\Database\Eloquent\Model;

class ExchangeRate extends Model
{
    public static function create(DomainExchangeRate $fetchedExchangeRate): self
    {
        $exchangeRate = new self();
        $exchangeRate->valid_at = $fetchedExchangeRate->getDateTime();
        $exchangeRate->value = $fetchedExchangeRate->getValue();
        $exchangeRate->currency = $fetchedExchangeRate->getCurrency();

        return $exchangeRate;
    }
}
