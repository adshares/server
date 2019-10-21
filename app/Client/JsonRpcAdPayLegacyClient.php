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

use Adshares\Adserver\HttpClient\JsonRpc;
use Adshares\Adserver\HttpClient\JsonRpc\Procedure;
use Adshares\Demand\Application\Service\AdPayLegacy;

final class JsonRpcAdPayLegacyClient implements AdPayLegacy
{
    private const METHOD_CAMPAIGN_UPDATE = 'campaign_update';

    private const METHOD_CAMPAIGN_DELETE = 'campaign_delete';

    private const METHOD_ADD_EVENTS = 'add_events';

    private const METHOD_GET_PAYMENTS = 'get_payments';

    private const METHOD_FORCE_RECALCULATION = 'debug_force_payment_recalculation';

    /** @var JsonRpc */
    private $rpcClient;

    public function __construct(JsonRpc $rpcClient)
    {
        $this->rpcClient = $rpcClient;
    }

    public function updateCampaign(array $campaigns): void
    {
        $procedure = new Procedure(
            self::METHOD_CAMPAIGN_UPDATE,
            $campaigns
        );

        $this->rpcClient->callAndFailIfUnsuccessful($procedure);
    }

    public function deleteCampaign(array $campaignIds): void
    {
        $procedure = new Procedure(
            self::METHOD_CAMPAIGN_DELETE,
            $campaignIds
        );

        $this->rpcClient->callAndFailIfUnsuccessful($procedure);
    }

    public function addEvents(array $events): void
    {
        $procedure = new Procedure(
            self::METHOD_ADD_EVENTS,
            $events
        );

        $this->rpcClient->callAndFailIfUnsuccessful($procedure);
    }

    public function getPayments(int $timestamp, bool $force): array
    {
        if ($force) {
            $debugProcedure = new Procedure(
                self::METHOD_FORCE_RECALCULATION,
                [['timestamp' => $timestamp]]
            );

            $this->rpcClient->callAndFailIfUnsuccessful($debugProcedure);
        }

        $procedure = new Procedure(
            self::METHOD_GET_PAYMENTS,
            [['timestamp' => $timestamp]]
        );

        $responseArray = $this->rpcClient->callAndAlwaysGetResult($procedure)->toArray();

        return $responseArray['payments'];
    }
}