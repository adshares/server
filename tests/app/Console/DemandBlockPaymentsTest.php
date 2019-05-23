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

namespace Adshares\Adserver\Tests\Console;

use Adshares\Adserver\Models\AdvertiserBudget;
use Adshares\Adserver\Models\Campaign;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\UserLedgerEntry;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Common\Application\Dto\ExchangeRate;
use Adshares\Common\Infrastructure\Service\ExchangeRateReader;
use DateTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use function factory;
use function json_decode;

class DemandBlockPaymentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp()
    {
        parent::setUp();
        $this->app->bind(
            ExchangeRateReader::class,
            function () {
                $mock = $this->createMock(ExchangeRateReader::class);

                $mock->method('fetchExchangeRate')
                    ->willReturn(new ExchangeRate(new DateTime(), 1, 'XXX'));

                return $mock;
            }
        );
    }

    public function testZero(): void
    {
        $this->artisan('ops:demand:payments:block')
            ->expectsOutput('Attempt to create 0 blockades.')
            ->assertExitCode(0);
    }

    public function testHandle(): void
    {
        $user = factory(User::class)->create();

        self::createCampaigns($user);
        self::createLedgerEntries($user);

        $this->artisan('ops:demand:payments:block')
            ->expectsOutput('Attempt to create 1 blockades.')
            ->assertExitCode(0);
    }

    public function testFetchRequiredBudgetsPerUser(): void
    {
        $this->createCampaigns(factory(User::class)->create());
        $this->createCampaigns(factory(User::class)->create());
        $this->createCampaigns(factory(User::class)->create());

        $budgets = Campaign::fetchRequiredBudgetsPerUser();

        self::assertCount(3, $budgets);

        $budgets->each(static function (AdvertiserBudget $budget) {
            self::assertEquals(500 * 10 ** 11, $budget->total());
            self::assertEquals(300 * 10 * 11, $budget->bonusable());
        });
    }

    private static function createLedgerEntries(User $user): void
    {
        $entries = [
            [UserLedgerEntry::TYPE_DEPOSIT, 500 * 10 ** 11, UserLedgerEntry::STATUS_ACCEPTED],
            [UserLedgerEntry::TYPE_BONUS_INCOME, 500 * 10 ** 11, UserLedgerEntry::STATUS_ACCEPTED],
        ];

        foreach ($entries as $entry) {
            factory(UserLedgerEntry::class)->create([
                'type' => $entry[0],
                'amount' => $entry[1],
                'status' => $entry[2],
                'user_id' => $user->id,
            ]);
        }
    }

    private static function createCampaigns(User $user, int $withTargeting = 2, int $activeCount = 5): void
    {
        factory(Campaign::class)->create([
            'user_id' => $user->id,
            'status' => Campaign::STATUS_INACTIVE,
        ]);
        factory(Campaign::class)->create([
            'user_id' => $user->id,
            'status' => Campaign::STATUS_SUSPENDED,

        ]);

        factory(Campaign::class, $activeCount - $withTargeting)->create([
            'user_id' => $user->id,
            'status' => Campaign::STATUS_ACTIVE,
        ]);

        factory(Campaign::class, $withTargeting)->create([
            'user_id' => $user->id,
            'status' => Campaign::STATUS_ACTIVE,
            'targeting_requires' => json_decode('{"site": {"domain": ["www.adshares.net"]}}', true),
        ]);
    }
}
