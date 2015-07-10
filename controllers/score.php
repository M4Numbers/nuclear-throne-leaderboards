<?php

/**
 * This page is largely redundant at the moment (though there are a number of
 * features that will be available in the future to allow for this page to give
 * options such as adding videos to runs and comments too). It shows the stats
 * for a single run of the user's choosing
 */

/**
 * Render the page that gives the details about an individual score on
 * the daily
 *
 * @param Twig_Environment $twig
 * @param array $sdata
 */
function render(Twig_Environment $twig, $sdata = array()) {

    //Ask if a run has actually been chosen to display the score of
    if (isset($_GET["hash"])) {

        //Generate the data around that score
        $score = new Score(array("hash" => $_GET["hash"]));

        //And publish it as required
        if ($score != false) {
            echo $twig->render('score.twig', array_merge($score->to_array(), $sdata));
        } else {
            echo $twig->render('404.twig', $sdata);
        }

    } else {
        //If no run was selected, we just have to return a 404 in defiance,
        // who knows, maybe the user will learn.
        echo $twig->render('404.twig', $sdata);
    }
}

function json($sdata) {}
