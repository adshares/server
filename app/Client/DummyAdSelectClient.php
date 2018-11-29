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

namespace Adshares\Adserver\Client;

use Adshares\Supply\Application\Dto\FoundBanners;
use Adshares\Supply\Application\Dto\ImpressionContext;
use Adshares\Supply\Application\Service\BannerFinder;

final class DummyAdSelectClient implements BannerFinder
{
    public function findBanners(ImpressionContext $context): FoundBanners
    {
        return new FoundBanners();
    }

    public static function getBestBanners(array $zones, array $keywords)
    {
        $typeDefault = [
            'image',
        ];

        $bannerIds = [];
        foreach ($zones as $zoneInfo) {
            $zone = Zone::find($zoneInfo['zone']);

            if ($zone) {
                try {
                    $pluck =
                        NetworkBanner::where('creative_width', $zone->width)
                            ->where('creative_height', $zone->height)
                            ->whereIn('creative_type', $typeDefault)
                            ->get()
                            ->pluck('uuid');
                    $bannerIds[] = $pluck->random();
                } catch (\InvalidArgumentException $e) {
                    $bannerIds[] = '';
                }
            } else {
                $bannerIds[] = md5(rand());
            }
        }

        $banners = [];
        foreach ($bannerIds as $bannerId) {
            $banner = $bannerId ? NetworkBanner::where('uuid', hex2bin($bannerId))->first() : NetworkBanner::first();

            if (!empty($banner)) {
                $campaign = NetworkCampaign::find($banner->network_campaign_id);
                $banners[] = [
                    'serve_url' => $banner->serve_url,
                    'creative_sha1' => $banner->creative_sha1,
                    'pay_from' => $campaign->adshares_address, // send this info to log
                    'click_url' => route(
                        'log-network-click',
                        [
                            'id' => $banner->uuid,
                            'r' => Utils::urlSafeBase64Encode($banner->click_url),
                        ]
                    ),
                    'view_url' => route(
                        'log-network-view',
                        [
                            'id' => $banner->uuid,
                            'r' => Utils::urlSafeBase64Encode($banner->view_url),
                        ]
                    ),
                ];
            }
        }

        return $banners;
    }

}
