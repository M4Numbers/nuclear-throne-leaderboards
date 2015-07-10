<?php

/**
 * This is the player profile page. It consists of a number of things, such
 * as that player's daily scores, that player's best moments in their Nuclear
 * Throne careers, how many things they've killed, how many runs they've had,
 * etc. etc. It's a long list of stuff which applies to that player.
 */

/**
 * Render the profile of the player who we're currently looking at
 *
 * @param Twig_Environment $twig
 * @param array $sdata
 */
function render(Twig_Environment $twig, $sdata = array()) {

    //If a player has been defined...
    if (isset($_GET["steamid"])) {

        //Find out whether we have a record of that player
        $player = new Player(array("search" => $_GET["steamid"]));

        if ($player->steamid == false) {
            echo $twig->render("404.twig", $sdata);
            return;
        }

        //And, since we do, let's start off by constructing their personal scoreboard
        // for the last 15 daily runs
        $scoreboard = new Leaderboard();
        $scores = $scoreboard->create_player($player->steamid)->to_array(0, 15);

        //And now, let's get their top-3 best ranked moments that are on the site
        $top_ranks = new Leaderboard();
        $top_ranks_list = $top_ranks->create_player($player->steamid, "rank", "ASC", 0, 2)->to_array(0, -1);

        //Along with the top-3 best scored moments on that very site.
        $top_scores = new Leaderboard();
        $top_scores_list = $top_scores->create_player($player->steamid, "score", "DESC", 0, 2)->to_array(0, -1);

        $best_moments = array();
        $dates = array();

        //Now, go through each of those items as a single array
        foreach (array_merge($top_ranks_list, $top_scores_list) as $score) {
            //And if each item doesn't already exist in the best-moment's list
            if (array_search($score["raw"]["date"], $dates) === false) {
                //Add it to the best moments along with the date that run happened on so
                // that we don't get this run duplicated in here
                $best_moments[] = $score;
                $dates[] = $score["raw"]["date"];
            }
        }

        //Add all of that gathered data into an array for twig to render
        $data = array("player" => $player,
            "scores" => $scores,
            "best_moments" => $best_moments,
            "rank" => $player->get_rank(),
            "total" => $scoreboard->get_global_stats(),
            "scores_graph" => array_reverse($scoreboard->to_array(0, 30)));

        //And render it accordingly.
        if ($data != false) {
            echo $twig->render('player.twig', array_merge($sdata, $data));
        } else {
            echo $twig->render('404.twig', $sdata);
        }

    } else {
        //If a player was not defined, we have no choice but to apologise and ask them
        // to actually choose a player this time.
        echo $twig->render('404.twig', $sdata);
    }
}

/**
 * Return a json string equivalent to 15 daily runs for someone or other
 *
 * @param $sdata
 */
function json($sdata) {

    //A page is necessary in this case
    if (!isset($_GET["page"]))
        die("{result: false}");

    // Make sure we get a number
    if (!is_int((int)$_GET["page"]))
        die("{result: false}");

    //And make sure we're actually searching for a player
    if (!isset($_GET["steamid"]))
        die("{result: false}");

    // Steam has a hard limit of 10 000 scoreboards
    // anything above 300 pages is overkill or malicious.
    if ((int)$_GET["page"] > 300)
        die("{result: false}");

    //Ask if we can find the player in our databases
    $player = new Player(array("search" => $_GET["steamid"]));
    if ($player == false)
        die("{result: false}");

    //Construct a scoreboard for that player
    $scoreboard = new Leaderboard();
    $scores = $scoreboard->create_player($player->steamid, "date", "DESC", $_GET["page"] * 15, 15)->to_array(0, -1);

    //Make ourselves an array of those scores along with the total runs for each day
    $scores = array(
        array(
            "scores" => $scores,
            "count" => $scoreboard->get_global_stats()["count"]
        )
    );

    //And echo out the resulting json
    echo json_encode($scores);

}
