<?php

/**
 * This file is part of SeAT Teamspeak Connector.
 *
 * Copyright (C) 2018  Warlof Tutsimo <loic.leuilliot@gmail.com>
 *
 * SeAT Teamspeak Connector  is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * SeAT Teamspeak Connector is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTeamspeakGroupTitlesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('teamspeak_group_titles', function (Blueprint $table) {
            $table->bigInteger('corporation_id');
            $table->integer('title_id');
            $table->string('teamspeak_sgid');
            $table->boolean('enable')->default(true);
            $table->timestamps();

            $table->primary(['corporation_id', 'title_id', 'teamspeak_sgid'], 'teamspeak_group_titles_pk');

            $table->foreign(['corporation_id', 'title_id'])
                ->references(['corporation_id', 'title_id'])
                ->on('corporation_titles')
                ->onDelete('cascade');

            $table->foreign('teamspeak_sgid')
                ->references('id')
                ->on('teamspeak_groups')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('teamspeak_group_titles');
    }
}
