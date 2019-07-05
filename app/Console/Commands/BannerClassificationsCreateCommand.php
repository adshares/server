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

namespace Adshares\Adserver\Console\Commands;

use Adshares\Adserver\Console\Locker;
use Adshares\Adserver\Services\Demand\BannerClassificationCreator;

class BannerClassificationsCreateCommand extends BaseCommand
{
    protected $signature = 'ops:demand:classification:create {classifier} {--bannerIds=}';

    protected $description = 'Creates banner classification entries for specified classifier';

    /** @var BannerClassificationCreator */
    private $creator;

    public function __construct(
        BannerClassificationCreator $creator,
        Locker $locker
    ) {
        $this->creator = $creator;

        parent::__construct($locker);
    }

    public function handle(): void
    {
        if (!$this->lock()) {
            $this->info('Command '.$this->signature.' already running');

            return;
        }

        $this->info('Start command '.$this->signature);

        $classifier = $this->argument('classifier');

        if (null !== ($bannerIds = $this->option('bannerIds'))) {
            $bannerIds = explode(',', $bannerIds);
        }

        $this->creator->create($classifier, $bannerIds);

        $this->info('Finish command '.$this->signature);
    }
}
