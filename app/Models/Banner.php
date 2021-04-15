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

namespace Adshares\Adserver\Models;

use Adshares\Adserver\Events\CreativeSha1;
use Adshares\Adserver\Events\GenerateUUID;
use Adshares\Adserver\Models\Traits\AutomateMutators;
use Adshares\Adserver\Models\Traits\BinHex;
use Adshares\Common\Domain\ValueObject\SecureUrl;
use Adshares\Common\Exception\InvalidArgumentException;
use Adshares\Supply\Domain\ValueObject\Size;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use function hex2bin;
use function in_array;

/**
 * @property int id
 * @property string uuid
 * @property string creative_contents
 * @property string creative_type
 * @property string creative_sha1
 * @property string creative_size
 * @property string name
 * @property int status
 * @property Campaign campaign
 * @property BannerClassification[] classifications
 * @property int type
 * @property array|null text_ad_source
 * @mixin Builder
 */
class Banner extends Model
{
    public const TYPE_IMAGE = 0;

    public const TYPE_HTML = 1;

    public const TYPE_DIRECT_LINK = 2;

    public const TEXT_TYPE_IMAGE = 'image';

    public const TEXT_TYPE_HTML = 'html';

    public const TEXT_TYPE_DIRECT_LINK = 'direct';

    public const STATUS_DRAFT = 0;

    public const STATUS_INACTIVE = 1;

    public const STATUS_ACTIVE = 2;

    public const STATUS_REJECTED = 3;

    public const STATUSES = [self::STATUS_DRAFT, self::STATUS_INACTIVE, self::STATUS_ACTIVE, self::STATUS_REJECTED];

    use AutomateMutators;
    use BinHex;
    use SoftDeletes;

    protected $dates = [
        'deleted_at',
    ];

    protected $dispatchesEvents = [
        'creating' => GenerateUUID::class,
        'saving' => CreativeSha1::class,
    ];

    protected $fillable = [
        'uuid',
        'campaign_id',
        'creative_contents',
        'creative_type',
        'creative_sha1',
        'creative_size',
        'name',
        'status',
    ];

    protected $hidden = [
        'campaign_id',
        'deleted_at',
    ];

    protected $casts = [
        'status' => 'int',
        'text_ad_source' => 'json',
    ];

    protected $traitAutomate = [
        'uuid' => 'BinHex',
        'creative_sha1' => 'BinHex',
    ];

    protected $touches = ['campaign'];

    public function getHidden()
    {
        $hidden = $this->hidden;
        if ($this->type !== self::TYPE_DIRECT_LINK) {
            $hidden[] = 'creative_contents';
        }

        return $hidden;
    }

    public static function isStatusAllowed(int $status): bool
    {
        return in_array($status, self::STATUSES);
    }

    public static function type(int $type): string
    {
        switch ($type) {
            case self::TYPE_IMAGE:
                return self::TEXT_TYPE_IMAGE;
            case self::TYPE_HTML:
                return self::TEXT_TYPE_HTML;
            case self::TYPE_DIRECT_LINK:
            default:
                return self::TEXT_TYPE_DIRECT_LINK;
        }
    }

    public static function typeAsInteger(string $type): int
    {
        switch ($type) {
            case self::TEXT_TYPE_IMAGE:
                return self::TYPE_IMAGE;
            case self::TEXT_TYPE_HTML:
                return self::TYPE_HTML;
            case self::TEXT_TYPE_DIRECT_LINK:
            default:
                return self::TYPE_DIRECT_LINK;
        }
    }

    public static function size(string $size): string
    {
        if (!Size::isValid($size)) {
            throw new InvalidArgumentException(sprintf('Invalid banner size (%s).', $size));
        }

        return $size;
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    protected function toArrayExtras($array)
    {
        $array['url'] = (new SecureUrl(route('banner-preview', ['id' => $this->uuid])))->toString();

        return $array;
    }

    public static function fetchBanner(string $bannerUuid): ?self
    {
        return self::where('uuid', hex2bin($bannerUuid))->first();
    }

    public static function fetchBannerByPublicIds(array $publicIds): Collection
    {
        $binPublicIds = array_map(
            function (string $item) {
                return hex2bin($item);
            },
            $publicIds
        );

        return self::whereIn('uuid', $binPublicIds)->get();
    }

    public static function fetchBannersNotClassifiedByClassifier(string $classifier, ?array $bannerIds): Collection
    {
        $builder = Banner::whereDoesntHave(
            'classifications',
            function ($query) use ($classifier) {
                $query->where('classifier', $classifier);
            }
        );

        if ($bannerIds) {
            $builder->whereIn('id', $bannerIds);
        }

        return $builder->get();
    }

    public function classifications(): HasMany
    {
        return $this->hasMany(BannerClassification::class);
    }
}
