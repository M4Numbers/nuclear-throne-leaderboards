<?php

/**
 * This file should be in a crontab to run every hour, depending on service load.
 * It represents how the alltime database is updated with all the new scores for
 * the last hour of the day
 */

//We require ourselves a config file... Men!
//TODO: Link in other files that are not currently linked due to file structure
require_once "../config/config.php";
require_once "../models/CentralDatabase.php";
require_once "../models/ThroneBase.php";
require_once "../models/application.php";

// I... um... no.
if (!isset($db_username)) {

    //Look at it this way, if the $db_username is not set, it's unlikely that the
    // password is set either (and this is completely ignoring the bucket of worms
    // that including root as a username is)
    $db_username = "root";

    //As an alternative, I would say that if this check fails, the script should
    // die; there are no-longer any certainties and we should end everything asap

} // My dev box sucks.

function update_alltime() {

    try {

        $db = Application::getDatabase();
        $db->update_alltime_leaderboard();

    } catch (PDOException $ex) {
        echo $ex->getMessage();
    }

    //Then report a successful update
    echo "All time database update successful. \n";

}

update_alltime();
