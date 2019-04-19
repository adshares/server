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

declare(strict_types = 1);

namespace Adshares\Adserver\Tests\Http;

use Adshares\Adserver\Http\Utils;
use Adshares\Adserver\Tests\TestCase;
use function hex2bin;

class UtilsTest extends TestCase
{
    public function testUserIdFromTrackingId(): void
    {
        $uidHex = 'e96438dd5a0e42a6881959886a8ebc2f';

        $tid = Utils::base64UrlEncodeWithChecksumFromBinUuidString(hex2bin($uidHex));

        self::assertSame($uidHex, Utils::hexUuidFromBase64UrlWithChecksum($tid));
    }

    public function testTrackingIdFromUserId(): void
    {
        $tid = '6WQ43VoOQqaIGVmIao68L2qb7wUbKQ';

        $uidHex = Utils::hexUuidFromBase64UrlWithChecksum($tid);

        self::assertSame($tid, Utils::base64UrlEncodeWithChecksumFromBinUuidString(hex2bin($uidHex)));
    }
}
