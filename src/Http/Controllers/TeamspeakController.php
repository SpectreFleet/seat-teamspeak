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

namespace Warlof\Seat\Connector\Teamspeak\Http\Controllers;

use Seat\Eveapi\Models\Corporation\CorporationInfo;
use Seat\Web\Http\Controllers\Controller;
use Warlof\Seat\Connector\Teamspeak\Exceptions\MissingMainCharacterException;
use Warlof\Seat\Connector\Teamspeak\Helpers\TeamspeakSetup;
use Warlof\Seat\Connector\Teamspeak\Jobs\TeamspeakUserOrchestrator;
use Warlof\Seat\Connector\Teamspeak\Models\TeamspeakUser;

class TeamspeakController extends Controller
{
    public function getUsers()
    {
        if (! request()->ajax())
            return view('teamspeak::users.list');

        $teamspeak_users = TeamspeakUser::with('group')->get();

        return app('DataTables')::of($teamspeak_users)
            ->addColumn('user_id', function ($row) {
                return $row->group->main_character_id;
            })
            ->addColumn('username', function($row) {
                return optional($row->group->main_character)->name ?: 'Unknown Character';
            })
            ->make(true);
    }

    public function postRemoveUserMapping()
    {
        $teamspeak_id = request()->input('teamspeak_id');

        if ($teamspeak_id == '')
            return redirect()->back('error', 'An error occurred while processing the request.');

        if (is_null($teamspeak_user = TeamspeakUser::where('teamspeak_id', $teamspeak_id)->first()))
            return redirect()->back()->with('error', sprintf('System cannot find any suitable mapping for Teamspeak (%s).', $teamspeak_id));

        $teamspeak_user->delete();

        return redirect()->back()->with('success',
            sprintf('System sucessfully remove the mapping between SeAT (%s) and Teamspeak (%s)',
                optional($teamspeak_user->group->main_character)->name, $teamspeak_user->teamspeak_id));
    }

    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     * @throws \Seat\Services\Exceptions\SettingException
     * @throws \Warlof\Seat\Connector\Teamspeak\Exceptions\MissingMainCharacterException
     */
    public function getRegisterUser()
    {
        $main_character = auth()->user()->group->main_character;

        if (! $main_character) {
            return redirect('home')->with('error', 'Could not find your Main Character.  Check your Profile for the correct Main.');
        }

        $corporation = CorporationInfo::find($main_character->corporation_id);

        if (! $corporation) {
            return redirect('home')->with('error', 'Could not find your Corporation.  Please have your CEO upload a Corp API key to this website.');
        }

        $teamspeak_username = $this->getTeamspeakFormattedNickname();

        return view('teamspeak::register', compact('teamspeak_username'));
    }

    /**
     * @return bool|string
     * @throws \Seat\Services\Exceptions\SettingException
     * @throws \Warlof\Seat\Connector\Teamspeak\Exceptions\MissingMainCharacterException
     */
    private function getTeamspeakFormattedNickname()
    {
        $main_character = auth()->user()->group->main_character;
        if (is_null($main_character))
            throw new MissingMainCharacterException(auth()->user()->group);

        $teamspeak_name = $main_character->name;

        if (setting('warlof.teamspeak-connector.tags', true) === true) {
            $corp = CorporationInfo::find($main_character->corporation_id);
            $teamspeak_name = sprintf('%s | %s', $corp->ticker, $main_character->name);
        }

        // Teamspeak has a 30 char limit on names. Trim it.
        return substr($teamspeak_name, 0, 30);
    }

    /**
     * @return string
     * @throws \Seat\Services\Exceptions\SettingException
     * @throws \Warlof\Seat\Connector\Teamspeak\Exceptions\MissingMainCharacterException
     * @throws \Warlof\Seat\Connector\Teamspeak\Exceptions\TeamspeakSettingException
     */
    public function postGetUserUid()
    {
        $client = new TeamspeakSetup();

        $user_list = $client->getInstance()->clientList();

        foreach ($user_list as $user) {
            $nickname = preg_replace('/’/', '\'', $user->client_nickname->toString());

            if ($nickname === $this->getTeamspeakFormattedNickname()) {
                $uid = $user->client_unique_identifier->toString();
                $found_user = [];
                $found_user['id'] = $uid;
                $found_user['nick'] = $nickname;
                $teamspeak_user = $this->postRegisterUser($uid);

                dispatch(new TeamspeakUserOrchestrator($teamspeak_user))->onQueue('high');

                return response()->json($found_user);
            }
        }

        return response()->json([
            'error' => 'Unable to retrieve you on Teamspeak. Ensure you have the proper nickname.',
        ], 404);
    }

    /**
     * @param $uid
     */
    private function postRegisterUser($uid)
    {
        $group_id = auth()->user()->group->id;

        return TeamspeakUser::updateOrCreate(
            ['group_id' => $group_id],
            ['teamspeak_id' => $uid]
        );
    }
}
