<?php
/**
 * Copyright (c) 2018 Adshares sp. z o.o.
 *
 * This file is part of AdServer
 *
 * AdServer is free software: you can redistribute and/or modify it
 * under the terms of the GNU General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AdServer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty
 * of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with AdServer. If not, see <https://www.gnu.org/licenses/>
 */

namespace Adshares\Adserver\Http\Controllers\Rest;

use Adshares\Adserver\Http\Controllers\Controller;
use Adshares\Adserver\Jobs\ClassifyCampaign;
use Adshares\Adserver\Models\Banner;
use Adshares\Adserver\Models\Campaign;
use Adshares\Adserver\Models\Notification;
use Adshares\Adserver\Repository\CampaignRepository;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class CampaignsController extends Controller
{
    private const FILESYSTEM_DISK = 'public';

    /**
     * @var CampaignRepository
     */
    private $campaignRepository;

    public function __construct(CampaignRepository $campaignRepository)
    {
        $this->campaignRepository = $campaignRepository;
    }

    public function upload(Request $request)
    {
        $file = $request->file('file');
        $path = $file->store('banners', self::FILESYSTEM_DISK);

        $name = $file->getClientOriginalName();
        $imageSize = getimagesize($file->getRealPath());
        $size = '';

        if (isset($imageSize[0]) && isset($imageSize[1])) {
            $size = sprintf('%sx%s', $imageSize[0], $imageSize[1]);
        }

        return self::json(
            [
                'imageUrl' => config('app.url').'/storage/'.$path,
                'name' => $name,
                'size' => $size,
            ],
            Response::HTTP_OK
        );
    }

    public function add(Request $request): JsonResponse
    {
        $this->validateRequestObject($request, 'campaign', Campaign::$rules);
        $input = $request->input('campaign');
        $input['user_id'] = Auth::user()->id;
        $input['targeting_requires'] = $request->input('campaign.targeting.requires');
        $input['targeting_excludes'] = $request->input('campaign.targeting.excludes');

        $banners = [];
        $temporaryFileToRemove = [];

        if (isset($input['ads']) && count($input['ads']) > 0) {
            $temporaryFileToRemove = $this->temporaryBannersToRemove($input['ads']);
            $banners = $this->prepareBannersFromInput($input['ads']);
        }

        $campaign = new Campaign($input);
        $this->campaignRepository->save($campaign, $banners);

        if ($temporaryFileToRemove) {
            $this->removeLocalBannerImages($temporaryFileToRemove);
        }

        return self::json($campaign->toArray(), Response::HTTP_CREATED)->header(
            'Location',
            route('app.campaigns.read', ['campaign' => $campaign])
        );
    }

    private function temporaryBannersToRemove(array $input): array
    {
        $banners = [];

        foreach ($input as $banner) {
            if ($banner['type'] === Banner::HTML_TYPE) {
                continue;
            }

            $banners[] = $this->getBannerLocalPublicPath($banner['image_url']);
        }

        return $banners;
    }

    private function getBannerLocalPublicPath(string $imageUrl): string
    {
        return str_replace(config('app.url').'/storage/', '', $imageUrl);
    }

    private function prepareBannersFromInput(array $input): array
    {
        $banners = [];

        foreach ($input as $banner) {
            $size = explode('x', Banner::size($banner['size']));

            if (!isset($size[0]) || !isset($size[1])) {
                throw new \RuntimeException('Banner size is required.');
            }

            $bannerModel = new Banner();
            $bannerModel->name = $banner['name'];
            $bannerModel->creative_width = $size[0];
            $bannerModel->creative_height = $size[1];
            $bannerModel->creative_type = Banner::type($banner['type']);

            if ($banner['type'] === Banner::HTML_TYPE) {
                $bannerModel->creative_contents = $banner['html'];
            }
            else {
                $path = $this->getBannerLocalPublicPath($banner['image_url']);
                $content = Storage::disk(self::FILESYSTEM_DISK)->get($path);

                $bannerModel->creative_contents = $content;
            }

            $banners[] = $bannerModel;
        }

        return $banners;
    }

    private function removeLocalBannerImages(array $files): void
    {
        foreach ($files as $file) {
            try {
                Storage::disk(self::FILESYSTEM_DISK)->delete($file);
            } catch (FileNotFoundException $ex) {
                // do nothing
            }
        }
    }

    public function browse()
    {
        $campaigns = $this->campaignRepository->find();

        return self::json($campaigns);
    }

    public function count()
    {
        //@TODO: create function data
        $siteCount = [
            'totalBudget' => 0,
            'totalClicks' => 0,
            'totalImpressions' => 0,
            'averageCTR' => 0,
            'averageCPC' => 0,
            'totalCost' => 0,
        ];

        return self::json($siteCount);
    }

    public function edit(Request $request, $campaignId)
    {
        $this->validateRequestObject(
            $request,
            'campaign',
            array_intersect_key(
                Campaign::$rules,
                $request->input('campaign')
            )
        );

        $input = $request->input('campaign');
        $input['targeting_requires'] = $request->input('campaign.targeting.requires');
        $input['targeting_excludes'] = $request->input('campaign.targeting.excludes');

        $ads = $request->input('campaign.ads');
        $banners = Collection::make($ads);

        $campaign = $this->campaignRepository->fetchCampaignById($campaignId);
        $campaign->fill($input);

        $bannersToUpdate = [];
        $bannersToDelete = [];
        $bannersToInsert = [];
        $temporaryFileToRemove = [];

        foreach ($campaign->banners as $banner) {
            $bannerFromInput = $banners->firstWhere('uuid', $banner->uuid);

            if ($bannerFromInput) {
                $banner->name = $bannerFromInput['name'];
                $bannersToUpdate[] = $banner;

                $banners = $banners->reject(
                    function($value) use ($banner) {
                        return (string)($value['uuid'] ?? "") === $banner->uuid;
                    }
                );

                continue;
            }

            $bannersToDelete[] = $banner;
        }

        if ($banners) {
            $bannersToInsert = $this->prepareBannersFromInput($banners->toArray());
        }

        if ($ads) {
            $this->temporaryBannersToRemove($ads);
        }

        $this->campaignRepository->update($campaign, $bannersToInsert, $bannersToUpdate, $bannersToDelete);

        if ($temporaryFileToRemove) {
            $this->removeLocalBannerImages($temporaryFileToRemove);
        }

        return self::json([], Response::HTTP_NO_CONTENT);
    }

    public function changeStatus(Request $request, $campaignId)
    {
        $this->validateRequestObject(
            $request,
            'campaign',
            array_intersect_key(
                Campaign::$rules,
                $request->input('campaign')
            )
        );

        $status = (int)$request->input('campaign.status');

        if (!Campaign::isStatusAllowed($status)) {
            $status = Campaign::STATUS_INACTIVE;
        }

        $campaign = $this->campaignRepository->fetchCampaignById($campaignId);
        $campaign->status = $status;

        $this->campaignRepository->update($campaign);

        return self::json([], Response::HTTP_NO_CONTENT);
    }

    public function delete(int $campaignId): JsonResponse
    {
        $campaign = $this->campaignRepository->fetchCampaignById($campaignId);

        if ($campaign->status !== Campaign::STATUS_INACTIVE) {
            $campaign->status = Campaign::STATUS_INACTIVE;
            $this->campaignRepository->save($campaign);
        }
        $this->campaignRepository->delete($campaign);

        return self::json([], Response::HTTP_NO_CONTENT);
    }

    public function read(Request $request, $campaignId)
    {
        // TODO check privileges
        $campaign = $this->campaignRepository->fetchCampaignById($campaignId);

        return self::json(['campaign' => $campaign->toArray()]);
    }

    public function classify($campaignId)
    {
        $campaign = $this->campaignRepository->fetchCampaignById($campaignId);

        $targetingRequires = ($campaign->targeting_requires) ? json_decode($campaign->targeting_requires, true) : null;
        $targetingExcludes = ($campaign->targeting_excludes) ? json_decode($campaign->targeting_excludes, true) : null;
        $banners = $campaign->getBannersUrls();

        ClassifyCampaign::dispatch($campaignId, $targetingRequires, $targetingExcludes, $banners);

        $campaign->classification_status = 1;
        $campaign->update();

        Notification::add(
            $campaign->user_id,
            Notification::CLASSIFICATION_TYPE,
            'Classify queued',
            sprintf('Campaign %s has been queued to classify', $campaign->id)
        );

        return self::json([], Response::HTTP_NO_CONTENT);
    }

    public function disableClassify($campaignId)
    {
        $campaign = $this->campaignRepository->fetchCampaignById($campaignId);
        $campaign->classification_status = 0;
        $campaign->classification_tags = null;

        $campaign->update();
    }
}
