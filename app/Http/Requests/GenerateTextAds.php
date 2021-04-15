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

namespace Adshares\Adserver\Http\Requests;

use Adshares\Adserver\Rules\SizeRule;
use Adshares\Adserver\Services\Advertiser\Dto\TextAdSource;

class GenerateTextAds extends FormRequest
{
    private const TITLE_MAX_LENGTH = 30;

    private const TEXT_MAX_LENGTH = 90;

    public function rules(): array
    {
        return [
            'text_ad_source.title' => 'required|string|max:'.self::TITLE_MAX_LENGTH,
            'text_ad_source.text' => 'nullable|string|max:'.self::TEXT_MAX_LENGTH,
            'text_ad_source.url' => 'required|url',
            'sizes' => 'required|array',
            'sizes.*' => new SizeRule(),
        ];
    }

    public function getSource(): TextAdSource
    {
        return new TextAdSource($this->get('text_ad_source'));
    }

    public function getSizes(): array
    {
        return $this->get('sizes');
    }
}
