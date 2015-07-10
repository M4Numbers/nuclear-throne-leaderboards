<?php

/**
 * This file represents what happens when the user goes to the 'about' page.
 * That's it... that's literally all there is to say about this page that
 * isn't unbelievably obvious.
 */

/**
 * Render the page (special effects optional)
 * @param Twig_Environment $twig
 * @param array $sdata
 */
function render(Twig_Environment $twig, $sdata = array()) {
    echo $twig->render('about.twig', $sdata);
}

function json($sdata) {}
