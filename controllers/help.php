<?php

/**
 * Help is a non-accessible page from the site which displays a plea for
 * donations from the current owner of the server. It is non-central and
 * contains a few links to various payment methods.
 */

/**
 * This function very simply echoes out the twig file to render
 *
 * @param Twig_Environment $twig
 * @param array $sdata
 */
function render(Twig_Environment $twig, $sdata = array()) {
    echo $twig->render('help.twig', $sdata);
}

/**
 * Magic (-ly does nothing)
 *
 * @param array $sdata
 */
function json($sdata) {}
