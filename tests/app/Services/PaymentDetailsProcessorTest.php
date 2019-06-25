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

namespace Adshares\Adserver\Tests\Utilities;

use Adshares\Adserver\Models\AdsPayment;
use Adshares\Adserver\Models\NetworkEventLog;
use Adshares\Adserver\Models\NetworkPayment;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\UserLedgerEntry;
use Adshares\Adserver\Services\PaymentDetailsProcessor;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Common\Application\Dto\ExchangeRate;
use Adshares\Common\Domain\ValueObject\AccountId;
use Adshares\Common\Infrastructure\Service\ExchangeRateReader;
use Adshares\Common\Infrastructure\Service\LicenseReader;
use DateTime;
use Illuminate\Foundation\Testing\RefreshDatabase;

final class PaymentDetailsProcessorTest extends TestCase
{
    use RefreshDatabase;

    private const LICENSE_FEE = 0.01;

    private const OPERATOR_FEE = 0.01;

    public function testProcessingEmptyDetails(): void
    {
        $paymentDetailsProcessor = $this->getPaymentDetailsProcessor();

        $adsPayment = $this->createAdsPayment(10000);

        $paymentDetailsProcessor->processPaymentDetails($adsPayment, [], 0);

        $this->assertCount(0, NetworkPayment::all());
    }

    public function testProcessingDetails(): void
    {
        $totalPayment = 10000;
        $paidEventsCount = 2;

        $paymentDetailsProcessor = $this->getPaymentDetailsProcessor();

        $user = factory(User::class)->create();
        $userUuid = $user->uuid;

        $networkEvents = factory(NetworkEventLog::class)->times($paidEventsCount)->create(
            ['event_value' => null, 'publisher_id' => $userUuid]
        );

        $adsPayment = $this->createAdsPayment($totalPayment);

        $paymentDetails = [];
        foreach ($networkEvents as $networkEvent) {
            $paymentDetails[] = [
                'case_id' => $networkEvent->case_id,
                'event_id' => $networkEvent->event_id,
                'event_type' => $networkEvent->event_type,
                'banner_id' => $networkEvent->banner_id,
                'zone_id' => $networkEvent->zone_id,
                'publisher_id' => $userUuid,
                'event_value' => $totalPayment / $paidEventsCount,
            ];
        }

        $result = $paymentDetailsProcessor->processPaymentDetails($adsPayment, $paymentDetails, 0);

        $expectedLicenseAmount = 0;
        $expectedOperatorAmount = 0;
        foreach ($paymentDetails as $paymentDetail) {
            $eventValue = $paymentDetail['event_value'];
            $eventLicenseAmount = (int)floor(self::LICENSE_FEE * $eventValue);
            $expectedLicenseAmount += $eventLicenseAmount;
            $expectedOperatorAmount += (int)floor(self::OPERATOR_FEE * ($eventValue - $eventLicenseAmount));
        }
        $expectedAdIncome = $totalPayment - $expectedLicenseAmount - $expectedOperatorAmount;

        $this->assertEquals($expectedLicenseAmount, $result->licenseFeePartialSum());

        $this->assertCount(1, UserLedgerEntry::all());
        $userLedgerEntry = UserLedgerEntry::first();
        $this->assertEquals($expectedAdIncome, $userLedgerEntry->amount);
    }

    private function getExchangeRateReader(): ExchangeRateReader
    {
        $value = 1;

        $exchangeRateReader = $this->createMock(ExchangeRateReader::class);
        $exchangeRateReader->method('fetchExchangeRate')->willReturn(new ExchangeRate(new DateTime(), $value, 'USD'));

        /** @var ExchangeRateReader $exchangeRateReader */
        return $exchangeRateReader;
    }

    private function getLicenseReader(): LicenseReader
    {
        $licenseReader = $this->createMock(LicenseReader::class);
        $licenseReader->method('getAddress')->willReturn(new AccountId('0001-00000000-9B6F'));
        $licenseReader->method('getFee')->willReturn(self::LICENSE_FEE);

        /** @var LicenseReader $licenseReader */
        return $licenseReader;
    }

    private function getPaymentDetailsProcessor(): PaymentDetailsProcessor
    {
        return new PaymentDetailsProcessor(
            $this->getExchangeRateReader(),
            $this->getLicenseReader()
        );
    }

    private function createAdsPayment(int $amount): AdsPayment
    {
        $adsPayment = new AdsPayment();
        $adsPayment->txid = '0002:000017C3:0001';
        $adsPayment->amount = $amount;
        $adsPayment->address = '0002-00000007-055A';
        $adsPayment->save();

        return $adsPayment;
    }
}
