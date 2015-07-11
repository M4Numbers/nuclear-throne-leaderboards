<?php

/**
 * This file represents the interface for accessing previous daily scores
 * across time. It provides a nice calender for people to look at and point
 * to their requested day, upon which they will see the daily scores for said
 * day... unless things have broken anyway
 */

/**
 * Render the archives!
 *
 * Oh, and hide the dust
 *
 * @param Twig_Environment $twig
 * @param array $sdata
 */
function render(Twig_Environment $twig, $sdata = array()) {

    //Is this the first page or some other page?
    if (isset($_GET["page"])) {
        $page = (int)$_GET["page"];
    } else {
        $page = 0;
    }

    $data = array();
    $ptr = &$data;

    //If a specific date has been set...
    if (isset($_GET["date"])) {
        try {
            //Check whether that date is actually valid
            $date = new DateTime($_GET["date"]);

            //Then construct the leaderboard for that day
            $leaderboard = new Leaderboard();
            $scores = $leaderboard->create_global($_GET["date"], "rank", "ASC", ($page - 1) * 30, 30)->to_array();
            $ptr = array(
                'location' => "archive",
                'global' => $leaderboard->global_stats,
                'year' => $date->format("Y"),
                'month' => $date->format("m"),
                'day' => $date->format("d"),
                'count' => count($scores),
                'scores' => $scores,
                'page' => $page
            );
        } catch (Exception $e) {
            //Since they provided an invalid date, default to yesterday
            $ptr = render_yesterday();
        }
    } else {
        //Default to yesterday
        $ptr = render_yesterday();
    }

    //Now render the final product
    echo $twig->render('archive.twig', array_merge($sdata, $data));

}

/**
 * Render the leaderboards for yesterday
 *
 * @return array
 */
function render_yesterday() {

    //Are we on the first page. Yea or nay?
    if (isset($_GET["page"])) {
        $page = (int)$_GET["page"];
    } else {
        $page = 1;
    }

    //Generate the leaderboard for yesterday (1 day's offset from today)
    $leaderboard = new Leaderboard();

    $scores = $leaderboard->create_global(1, "rank", "ASC", ($page - 1) * 30, 30)->to_array();

    $date = new DateTime($leaderboard->date);
    $data = array(
        'location' => "archive",
        'global' => $leaderboard->global_stats,
        'year' => $date->format("Y"),
        'month' => $date->format("m"),
        'day' => $date->format("d"),
        'count' => count($scores),
        'scores' => $scores,
        'page' => $page
    );

    //Now return the data so that Twig can render everything
    return $data;

}

/**
 * Does sweet f.a.
 *
 * @param array $sdata
 */
function json($sdata) {}
