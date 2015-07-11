<?php

/**
 * This is the index page of the site. It shows the current daily scores along with
 * a few extra bits of information pertaining to the site (including the top 5 for
 * yesterday's daily, a few details about the currently logged in user, and current
 * Twitch streams that are online)
 */

/**
 * Render the index of the site
 *
 * @param Twig_Environment $twig
 * @param array $sdata
 */
function render(Twig_Environment $twig, $sdata = array()) {

    // Get current page (whatever that may be)
    if (isset($_GET["page"]) && (int)$_GET["page"] > 0) {
        $page = $_GET["page"];
    } else {
        $page = 0;
    }

    // Get currently active livestreams
    $oStreams = new Streams(4);
    $oStreams->streams;

    // Get today's leaderboards
    $leaderboards_today = new Leaderboard();
    $today = $leaderboards_today->create_global(0, "rank", "ASC", $page * 30, 30)->to_array();

    // Get yesterday's leaderboards
    $leaderboards_yesterday = new Leaderboard();
    $yesterday = $leaderboards_yesterday->create_global(1, "rank", "ASC", 0, 5)->to_array();

    $global = $leaderboards_today->global_stats;

    // Get logged in user's data
    if (isset($_SESSION["steamid"])) {

        $user = new Player(array("search" => $_SESSION["steamid"]));
        $userdata = $user->to_array();
        $userdata["today_rank"] = $user->get_rank_today();
        if ($userdata["today_rank"] == -1)
            $userdata["today_rank"] = "N/A";
        $userdata["rank"] = $user->get_rank();
        $userdata["percentile"] = round($userdata["today_rank"] / $global["runcount"] * 100, 2);

    } else {

        //Since the current user isn't logged in, we have no idea what they have
        // and/or haven't done on the daily
        $userdata = array();

    }

    //And finally run out the data to the renderer
    $data = array(
        'location' => "daily",
        'date' => $leaderboards_today->date,
        'scores' => $today,
        'scores_yesterday' => $yesterday,
        'userdata' => $userdata,
        'streams' => $oStreams->streams,
        'streamcount' => count($oStreams->streams),
        'global' => $global,
        'page' => $page + 1
    );

    echo $twig->render('index.twig', array_merge($sdata, $data));

}

/**
 * Solves a rubiks cube in infinity seconds.
 *
 * @param array $sdata
 */
function json($sdata) {}
