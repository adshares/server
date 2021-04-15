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

namespace Adshares\Adserver\Http\Controllers\Manager;

use Adshares\Adserver\Http\Controller;
use Adshares\Adserver\Http\Requests\GetSiteCode;
use Adshares\Adserver\Http\Response\Site\SizesResponse;
use Adshares\Adserver\Models\Site;
use Adshares\Adserver\Models\SitesRejectedDomain;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\Zone;
use Adshares\Adserver\Services\Publisher\SiteCategoriesValidator;
use Adshares\Adserver\Services\Publisher\SiteCodeGenerator;
use Adshares\Adserver\Services\Supply\SiteClassificationUpdater;
use Adshares\Adserver\Utilities\DomainReader;
use Adshares\Adserver\Utilities\SiteValidator;
use Adshares\Common\Application\Dto\PageRank;
use Adshares\Common\Exception\InvalidArgumentException;
use Adshares\Supply\Domain\ValueObject\Size;
use Closure;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class SitesController extends Controller
{
    /** @var SiteCategoriesValidator */
    private $siteCategoriesValidator;

    /** @var SiteClassificationUpdater */
    private $siteClassificationUpdater;

    public function __construct(
        SiteCategoriesValidator $siteCategoriesValidator,
        SiteClassificationUpdater $siteClassificationUpdater
    ) {
        $this->siteCategoriesValidator = $siteCategoriesValidator;
        $this->siteClassificationUpdater = $siteClassificationUpdater;
    }

    public function create(Request $request): JsonResponse
    {
        $this->validateRequestObject($request, 'site', Site::$rules);
        if (!SiteValidator::isUrlValid($request->input('site.url'))) {
            throw new UnprocessableEntityHttpException('Invalid URL');
        }
        try {
            $categories = $this->siteCategoriesValidator->processCategories($request->input('site.categories'));
        } catch (InvalidArgumentException $exception) {
            throw new UnprocessableEntityHttpException($exception->getMessage());
        }
        $url = (string)$request->input('site.url');
        $domain = DomainReader::domain($url);
        self::validateDomain($domain);

        $inputZones = $request->input('site.ad_units');
        $this->validateInputZones($inputZones);

        $input = $request->input('site');
        unset($input['categories']);
        $input['categories_by_user'] = $categories;
        $input['domain'] = $domain;

        DB::beginTransaction();

        try {
            $site = Site::create($input);
            $site->user_id = Auth::user()->id;
            $site->save();
            $this->siteClassificationUpdater->addClassificationToFiltering($site);

            if ($inputZones) {
                $site->zones()->createMany($this->processInputZones($site, $inputZones));
            }
        } catch (Exception $exception) {
            DB::rollBack();
            throw $exception;
        }

        DB::commit();

        return self::json([], Response::HTTP_CREATED)->header('Location', route('app.sites.read', ['site' => $site]));
    }

    public function read(Site $site): JsonResponse
    {
        return self::json($this->processClassificationInFiltering($site));
    }

    private function processClassificationInFiltering(Site $site): array
    {
        $siteArray = $site->toArray();
        $filtering = $siteArray['filtering'];

        $filtering['requires'] = array_filter(
            $filtering['requires'] ?: [],
            $this->filterOutHelperKeywords(),
            ARRAY_FILTER_USE_KEY
        );
        $filtering['excludes'] = array_filter(
            $filtering['excludes'] ?: [],
            $this->filterOutHelperKeywords(),
            ARRAY_FILTER_USE_KEY
        );

        $siteArray['filtering'] = $filtering;

        return $siteArray;
    }

    private function filterOutHelperKeywords(): Closure
    {
        return function ($key) {
            return SiteClassificationUpdater::INTERNAL_CLASSIFIER_NAMESPACE !== $key
                && false === strpos($key, SiteClassificationUpdater::KEYWORD_CLASSIFIED);
        };
    }

    public function update(Request $request, Site $site): JsonResponse
    {
        $input = $request->input('site');
        $this->validateRequestObject($request, 'site', array_intersect_key(Site::$rules, $input));
        $updateDomainAndUrl = false;
        if (isset($input['url'])) {
            if (!SiteValidator::isUrlValid($input['url'])) {
                throw new UnprocessableEntityHttpException('Invalid URL');
            }
            $url = (string)$input['url'];
            $domain = DomainReader::domain($url);
            self::validateDomain($domain);

            $input['domain'] = $domain;
            $updateDomainAndUrl = $site->domain !== $domain || $site->url !== $url;
        }
        $inputZones = $request->input('site.ad_units');
        $this->validateInputZones($inputZones);

        DB::beginTransaction();

        try {
            $site->fill($input);
            $site->push();
            $this->siteClassificationUpdater->addClassificationToFiltering($site);

            if ($inputZones) {
                $site->zones()->createMany($this->processInputZones($site, $inputZones));
            }

            if ($updateDomainAndUrl) {
                $site->updateWithPageRank(PageRank::default());
            }
        } catch (Exception $exception) {
            DB::rollBack();
            throw $exception;
        }

        DB::commit();

        return self::json(['message' => 'Successfully edited']);
    }

    private function processInputZones(Site $site, array $inputZones): array
    {
        $presentUniqueSizes = [];
        $keysToRemove = [];

        foreach ($inputZones as $key => &$inputZone) {
            $size = $inputZone['size'];
            $type = Size::SIZE_INFOS[$size]['type'];
            $inputZone['type'] = $type;

            if (Size::TYPE_POP !== $type) {
                continue;
            }

            if (isset($presentUniqueSizes[$size])) {
                $keysToRemove[] = $key;
            } else {
                $presentUniqueSizes[$size] = $key;
            }
        }
        unset($inputZone);

        foreach ($keysToRemove as $key) {
            unset($inputZones[$key]);
        }

        /** @var Zone $zone */
        foreach ($site->zones()->withTrashed()->get() as $zone) {
            if (Size::TYPE_POP === $zone->type) {
                $size = $zone->size;

                if (isset($presentUniqueSizes[$size])) {
                    if ($zone->trashed()) {
                        $zone->restore();
                    }

                    $key = $presentUniqueSizes[$size];
                    $zone->update($inputZones[$key]);
                    unset($inputZones[$key]);
                } else {
                    if (!$zone->trashed()) {
                        $zone->delete();
                    }
                }

                continue;
            }

            if ($zone->trashed()) {
                continue;
            }

            $zoneFromInput = null;
            foreach ($inputZones as $key => $inputZone) {
                if (($inputZone['id'] ?? null) === $zone->id) {
                    $zoneFromInput = $inputZone;
                    unset($inputZones[$key]);

                    break;
                }
            }

            if (null !== $zoneFromInput) {
                $zone->update($zoneFromInput);
            } else {
                $zone->delete();
            }
        }

        return $inputZones;
    }

    public function delete(Site $site): JsonResponse
    {
        DB::beginTransaction();

        try {
            $site->delete();
            $site->zones()->delete();
        } catch (Exception $exception) {
            DB::rollBack();
            throw $exception;
        }

        DB::commit();

        return self::json(['message' => 'Successfully deleted']);
    }

    public function list(): JsonResponse
    {
        $siteCollection = Site::get();
        $sites = [];
        foreach ($siteCollection as $site) {
            $sites[] = $this->processClassificationInFiltering($site);
        }

        return self::json($sites);
    }

    public function changeStatus(Site $site, Request $request): JsonResponse
    {
        if (!$request->has('site.status')) {
            throw new InvalidArgumentException('No status provided');
        }

        $status = (int)$request->input('site.status');

        $site->changeStatus($status);
        $site->save();

        return self::json([
            'site' => [
                'status' => $site->status,
            ],
        ]);
    }

    public function readSitesSizes(?int $siteId = null): JsonResponse
    {
        $response = new SizesResponse($siteId);

        return self::json($response);
    }

    public function readSiteRank(Site $site): JsonResponse
    {
        return self::json(new PageRank($site->rank, $site->info));
    }

    public function sitesCodes(Site $site, GetSiteCode $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        if (!$user->isEmailConfirmed) {
            return self::json(['message' => 'Confirm e-mail to get code'], JsonResponse::HTTP_FORBIDDEN);
        }

        return self::json(['codes' => SiteCodeGenerator::generate($site, $request->toConfig())]);
    }

    private function validateInputZones($inputZones): void
    {
        if (null === $inputZones) {
            return;
        }

        if (!is_array($inputZones)) {
            throw new UnprocessableEntityHttpException('Invalid ad units type.');
        }

        foreach ($inputZones as $inputZone) {
            if (!isset($inputZone['name']) || !is_string($inputZone['name'])) {
                throw new UnprocessableEntityHttpException('Invalid name.');
            }
            if (!isset($inputZone['size']) || !is_string($inputZone['size']) || !Size::isValid($inputZone['size'])) {
                throw new UnprocessableEntityHttpException('Invalid size.');
            }
        }
    }

    public function verifyDomain(Request $request): JsonResponse
    {
        $domain = $request->get('domain');
        if (null === $domain) {
            throw new BadRequestHttpException('Field `domain` is required.');
        }
        self::validateDomain($domain);

        return self::json(
            ['code' => Response::HTTP_OK, 'message' => 'Valid domain.'],
            Response::HTTP_OK
        );
    }

    private static function validateDomain(string $domain): void
    {
        if (!SiteValidator::isDomainValid($domain)) {
            throw new UnprocessableEntityHttpException('Invalid domain.');
        }
        if (SitesRejectedDomain::isDomainRejected($domain)) {
            throw new UnprocessableEntityHttpException(
                'The subdomain '.$domain.' is not supported. Please use your own domain.'
            );
        }
    }
}
