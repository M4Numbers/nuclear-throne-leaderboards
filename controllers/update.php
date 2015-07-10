<?php

/**
 * A page which allows for a user to update their various daily runs with
 * additional details such as the video which shows their run, and a comment
 * which goes along with it, describing such useful information as:
 *
 * 'Suck it, Throne!'
 *
 * Please note: This page is functionally useless at the moment as there is no
 * front-end interface that allows the user to change these details about their
 * videos
 */

/**
 * In a slight break with tradition, rendering the page will do absolutely
 * nothing and will simply return a 404 error. Consider this text filler to
 * describe precisely that.
 *
 * @param Twig_Environment $twig
 * @param $sdata
 */
function render(Twig_Environment $twig, $sdata) {
    echo $twig->render('404.twig', $sdata);
}

/**
 * Update a daily run somewhere with some additional details to help people
 * get better acquainted as to just how 'good' they're going to have to get
 *
 * @param array $sdata Functionally useless
 */
function json($sdata) {

    //Let's first ask what the user would like to do...
    switch ($_GET["act"]) {

        //They can update their score to provide a video (even with such a
        // misleading case statement)
        case "scoreupdate":
            //Note: There is currently no verification to check whether the
            // run belongs to the user which is currently logged in

            //Are they logged in?
            if (!isset($_SESSION["steamid"])) {
                echo json_encode(array("error" => "You are not logged in."));
                break;
            }

            //Is their provided video a valid video?
            $verify = "/^(https?\:\/\/)?w{3}?\.?(youtu|youtube|twitch)\.(com|be|tv)\/.*$/";
            if (preg_match($verify, $_POST["video"]) === false) {
                echo json_encode(array("error" => "Bad link."));
                break;
            }

            /**
             * Let's get those details into the database!
             *
             * @var PDO $db
             */
            $db = Application::$db;
            $stmt = $db->prepare("UPDATE `throne_scores` SET `video` = :video, `comment` = :comment WHERE `hash` = :hash");
            $stmt->execute(array(":video" => $_POST["video"], "comment" => $_POST["comment"], ":hash" => $_POST["hash"]));

            //If something went wrong, the rows altered will be 0, ergo, the update failed
            if ($stmt->rowCount() < 1) {
                echo json_encode(array("error" => "Update failed."));
                break;
            }

            //Otherwise, let's assume that everything worked
            break;

        //Since they have rolled on down to default, they haven't provided a valid
        // operation to act upon, so we have to fire them... sorry.
        default:
            echo json_encode(array("error" => "Wrong method."));

    }

}
