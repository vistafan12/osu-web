<?php

/**
 *    Copyright 2015-2017 ppy Pty. Ltd.
 *
 *    This file is part of osu!web. osu!web is distributed with the hope of
 *    attracting more community contributions to the core ecosystem of osu!.
 *
 *    osu!web is free software: you can redistribute it and/or modify
 *    it under the terms of the Affero GNU General Public License version 3
 *    as published by the Free Software Foundation.
 *
 *    osu!web is distributed WITHOUT ANY WARRANTY; without even the implied
 *    warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *    See the GNU Affero General Public License for more details.
 *
 *    You should have received a copy of the GNU Affero General Public License
 *    along with osu!web.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace App\Http\Controllers;

use App\Models\Achievement;
use App\Models\User;
use App\Transformers\AchievementTransformer;
use App\Transformers\UserTransformer;
use Auth;
use Request;

class UsersController extends Controller
{
    protected $section = 'user';

    public function __construct()
    {
        $this->middleware('auth', ['only' => [
            'checkUsernameAvailability',
            'checkUsernameExists',
        ]]);

        return parent::__construct();
    }

    public function disabled()
    {
        return view('users.disabled');
    }

    public function checkUsernameAvailability()
    {
        $username = Request::input('username');

        $errors = Auth::user()->validateUsernameChangeTo($username);

        $available = count($errors) === 0;
        $message = $available ? "Username '".e($username)."' is available!" : implode(' ', $errors);
        $cost = $available ? Auth::user()->usernameChangeCost() : 0;

        return [
            'username' => Request::input('username'),
            'available' => $available,
            'message' => $message,
            'cost' => $cost,
            'costString' => currency($cost),
        ];
    }

    public function checkUsernameExists()
    {
        $username = Request::input('username');
        $user = User::default()->where('username', $username)->first();
        if ($user === null) {
            abort(404);
        }

        return [
            'user_id' => $user->user_id,
            'username' => $user->username,
            'avatar_url' => $user->user_avatar,
        ];
    }

    public function card($id)
    {
        $id = get_int($id);

        $user = User::lookup($id, 'id');
        $mutual = false;

        if (Auth::user()) {
            $friend = Auth::user()
                ->friends()
                ->withMutual()
                ->where('zebra_id', $id)
                ->first();

            if ($friend) {
                $mutual = $friend->mutual;
            }
        }

        // render usercard as popup (i.e. pretty fade-in elements on load)
        $popup = true;

        return view('objects._usercard', compact('user', 'friend', 'mutual', 'popup'));
    }

    public function show($id)
    {
        $user = User::lookup($id, null, true);

        if ($user === null || !priv_check('UserShow', $user)->can()) {
            abort(404);
        }

        if ((string) $user->user_id !== $id) {
            return ujs_redirect(route('users.show', $user));
        }

        $achievements = json_collection(
            Achievement::achievable()
                ->orderBy('grouping')
                ->orderBy('ordering')
                ->orderBy('progression')
                ->get(),
            new AchievementTransformer()
        );

        $userArray = json_item(
            $user,
            new UserTransformer(), [
                'userAchievements',
                'allRankHistories',
                'allScores',
                'allScoresBest',
                'allScoresFirst',
                'allStatistics',
                'beatmapPlaycounts',
                'followerCount',
                'page',
                'recentActivities',
                'recentlyReceivedKudosu',
                'rankedAndApprovedBeatmapsets.beatmaps',
                'favouriteBeatmapsets.beatmaps',
            ]
        );

        return view('users.show', compact('user', 'userArray', 'achievements'));
    }
}
