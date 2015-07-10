<?php

/**
 * The alltime functionality of the site tracks universal statistics across the
 * breadth of time, tracking and ranking statistics for everyone before tabulating
 * it.
 */

/**
 * Render the page with the all-time scores
 *
 * @param Twig_Environment $twig
 * @param array $sdata
 */
function render(Twig_Environment $twig, $sdata = array()) {

    /**
     * Ask the friendly getters whether we are on a specified page
     */
    if (isset($_GET["page"])) {
        //And if we are, get either that page, or the 0th page, whichever's higher
        $page = max((int)$_GET["page"], 0);
    } else {
        //Otherwise, the default page is 0
        $page = 0;
    }

    //Boot up a new leaderboard
    $leaderboard = new Leaderboard();

    //And ask what we're sorting by
    if (!isset($_GET["sort"])) {
        //By default, we sort by score
        $sort = "score";
    } elseif ($_GET["sort"] == "total") {
        $sort = "score";
    } elseif ($_GET["sort"] == "avg") {
        $sort = "average";
    } elseif ($_GET["sort"] == "runs") {
        $sort = "runs";
    } elseif ($_GET["sort"] == "wins") {
        $sort = "wins";
    } else {
        //We also sort by score on errors too!
        $sort = "score";
    }

    //Construct the data for the page
    $data = array(
        "location" => "alltime",
        "scores" => $leaderboard->create_alltime($page * 30, 30, $sort, "DESC"),
        "sort_by" => $_GET["sort"],
        "page" => $page
    );

    //And if that data was somehow false (aka, failed in creation), we throw out a 404
    if ($data != false) {
        echo $twig->render('alltime.twig', array_merge($sdata, $data));
    } else {
        echo $twig->render('404.twig', $sdata);
    }
}

/**
 * Do absolutely nothing.
 *
 * @param array $sdata unused
 */
function json($sdata) {}
