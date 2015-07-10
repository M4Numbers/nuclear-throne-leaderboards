<?php

/**
 * This is the landing page for whenever a user attempts to search for
 * anyone on thronebutt. It displays a list of all the people that were
 * found that shared the same name as what was searched
 */

/**
 * Display the results of whatever search was performed
 *
 * @param Twig_Environment $twig
 * @param array $sdata
 */
function render(Twig_Environment $twig, $sdata = array()) {

    //Let's get ourselves the term that was actually searched
    $term = html_entity_decode($_GET["q"]);

    //And perform a bit of magic in order to search for said term
    $results = Application::search_user($term);

    //If we only found one result, we may as well just re-direct the
    // user straight to that profile
    if (count($results) == 1) {
        header("Location: /player/" . $results[0]["steamid"]);
        die();
    } else {
        //Otherwise, just display all of the results that we found...
        $data = array(
            "results" => $results,
            "count" => count($results),
            "query" => $_GET["q"]
        );
    }

    //In this magical mystery rendering box right here!
    echo $twig->render('search.twig', array_merge($sdata, $data));

}

/**
 * Currently sits on its ass in front of the T.V. for most of the day,
 * however, it may be useful to include functionality here at some point
 * in the future
 *
 * @param array $sdata
 */
function json($sdata) {}
