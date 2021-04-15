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

namespace Adshares\Adserver\Services\Advertiser;

use Adshares\Adserver\Services\Advertiser\Dto\TextAdSource;
use Adshares\Adserver\Utilities\DomainReader;
use Adshares\Supply\Domain\ValueObject\Size;

class TextAdsGenerator
{
    private const TEXT_AD_VIEW = 'advertiser.text-ad';

    /** @var TextAdSource */
    private $source;

    public function __construct(TextAdSource $source)
    {
        $this->source = $source;
    }

    public function generate(string $size): string
    {
        [$width, $height] = Size::toDimensions($size);

        return view(
            $this->getView($size),
            [
                'domain' => DomainReader::domain($this->source->getUrl()),
                'height' => $height,
                'loopCount' => max(1, (int)floor($height / ($width >= 700 ? 70 : ($width > 160 ? 101 : 130)))),
                'text' => $this->source->getText(),
                'title' => $this->source->getTitle(),
                'width' => $width,
            ]
        )->render();
    }

    private function getView(string $size): string
    {
        $viewForSize = self::TEXT_AD_VIEW.'-'.$size;

        if (view()->exists($viewForSize)) {
            return $viewForSize;
        }

        return self::TEXT_AD_VIEW;
    }
}
