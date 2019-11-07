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

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPageRankColumn extends Migration
{
    public function up(): void
    {
        Schema::table(
            'event_logs',
            function (Blueprint $table) {
                $table->decimal('page_rank', 3, 2)->after('human_score')->nullable();
            }
        );

        Schema::table(
            'network_impressions',
            function (Blueprint $table) {
                $table->decimal('page_rank', 3, 2)->after('human_score')->nullable();
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'network_impressions',
            function (Blueprint $table) {
                $table->dropColumn('page_rank');
            }
        );

        Schema::table(
            'event_logs',
            function (Blueprint $table) {
                $table->dropColumn('page_rank');
            }
        );
    }
}
