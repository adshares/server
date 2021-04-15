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

namespace Adshares\Adserver\Services\Advertiser\Dto;

use Adshares\Common\Exception\InvalidArgumentException;
use Illuminate\Contracts\Support\Arrayable;

class TextAdSource implements Arrayable
{
    /** @var string */
    private $title;

    /** @var string|null */
    private $text;

    /** @var string */
    private $url;

    public function __construct($data)
    {
        if (!is_array($data)) {
            throw new InvalidArgumentException('Text ad source must be an array.');
        }
        if (!isset($data['title'])) {
            throw new InvalidArgumentException('Text ad title is required.');
        }
        if (!is_string($data['title'])) {
            throw new InvalidArgumentException('Text ad title must be a string.');
        }
        if (!isset($data['url'])) {
            throw new InvalidArgumentException('Text ad url is required.');
        }
        if (!is_string($data['url'])) {
            throw new InvalidArgumentException('Text ad url must be a string.');
        }

        $this->title = $data['title'];
        $this->text = $data['text'] ?? null;
        $this->url = $data['url'];
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getText(): string
    {
        return $this->text ?? '';
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function toArray(): array
    {
        return [
            'title' => $this->getTitle(),
            'text' => $this->getText(),
            'url' => $this->getUrl(),
        ];
    }
}
