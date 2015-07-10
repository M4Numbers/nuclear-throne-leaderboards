<?php

/**
 * Ho-dear-lord-christ... This file is a slight clusterfck of if statements;
 * but from what I can gather, this file represents an administrator's various
 * options around the site
 */

/**
 * Render the page according to what the admin wishes to do
 *
 * @param Twig_Environment $twig
 * @param array $sdata
 */
function render(Twig_Environment $twig, $sdata = array()) {

    //If this person wishes to perform an action...
    if (isset($_GET["act"]) && isset($_SESSION["admin"])) {

        //If the admin would like to hide a score...
        if ($_GET["act"] == "hide" && isset($_GET["hash"])) {
            if($_SESSION["admin"] > 0) {
                hide_score($_GET["hash"]);
                header("Location: /player/" . $_GET["player"]);
            } else {
                echo $twig->render("404.twig", $sdata);
            }
        }

        //If the admin would like to show a score again...
        if ($_GET["act"] == "show" && isset($_GET["hash"])) {
            if($_SESSION["admin"] > 0) {
                hide_score($_GET["hash"], 0);
                header("Location: /player/" . $_GET["player"]);
            } else {
                echo $twig->render("404.twig", $sdata);
            }
        }

        //If the admin would like to mark a user as suspicious...
        if ($_GET["act"] == "mark" && isset($_GET["player"])) {
            if($_SESSION["admin"] > 0) {
                mark_hacker($_GET["player"]);
                header("Location: /player/" . $_GET["player"]);
            } else {
                echo $twig->render("404.twig", $sdata);
            }
        }

        //If the admin would like to unmark a user...
        if ($_GET["act"] == "unmark" && isset($_GET["player"])) {
            if($_SESSION["admin"] > 0) {
                mark_hacker($_GET["player"], 0);
                header("Location: /player/" . $_GET["player"]);
            } else {
                echo $twig->render("404.twig", $sdata);
            }
        }

        //If the admin would like to force an update to a player's profile...
        if ($_GET["act"] == "update" && isset($_GET["player"])) {
            if($_SESSION["admin"] > 0) {
                update_profile($_GET["player"]);
                header("Location: /player/" . $_GET["player"]);
            } else {
                echo $twig->render("404.twig", $sdata);
            }
        }

    } else {
        //If the admin is not going to act, feh! Throw 'em out!
        echo $twig->render("404.twig", $sdata);
    }

}

/**
 * Currently, the only functionality here is to change someone's twitch
 * account details.
 *
 * @param array $sdata Unused.
 */
function json($sdata) {

    //Are they performing an action?
    if (isset($_GET["act"])) {

        //Is that action updating twitch?
        if ($_GET["act"] == "update-twitch") {

            //Are they actually an admin (or, at least, the person in control of that account)?
            if ((isset($_SESSION["admin"]) && $_SESSION["admin"] >= 1) ||
                    ($_POST["twitch_steamid"] == $_SESSION["steamid"])) {

                //Find the player who we're updating the twitch account for
                $player = new Player(array("search" => $_POST["twitch_steamid"]));

                //And attempt to change that twitch account, returning a json boolean value of
                // the result when we get it.
                if ($player->set_twitch($_POST["twitch_user"])) {
                    echo '{"result": "success"}';
                } else {
                    echo '{"result": "error"}';
                }
            } else {
                //Otherwise, return an erroneous value to represent that the user does not
                // have sufficient thronebutt privilege.
                echo '{"result": "permission"}';

            }
        }

    }
}
