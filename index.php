<?php


/**
 * Due to how this site is structured, the index is actually the home for everything.
 * Or rather... the index acts as an information office. A customer comes up to it and
 * gives it a few details (such as the mode, a steamid, and what they want to do), upon
 * which, the index looks up the controllers that it has access to, and passes on the
 * information to that specific controller so that it can deal with the request
 * accordingly.
 */

$time = microtime();
$time = explode(' ', $time);
$time = $time[1] + $time[0];
$start = $time;

// Darwinian 100% Professional PHP Controller Page Router Thing(tm)(r)
// Safety of using in production: 1%
// Bugbears found living under the mattress?  Unknown.

require "config.php";
require_once "config/config.php";

//So, it occurs to me that sometimes, having these errors is never wanted, even
// in a development setting (i.e. when this page is being polled for json stats)
// so we have an override getter in case we need that functionality.
if ($config_development == true && !isset($_GET['supress'])) {
    // enable development options
    error_reporting(E_ALL);
    ini_set('display_errors', 'On');
    ini_set("xdebug.var_display_max_depth", "-1");
}

// include and register Twig auto-loader
require_once 'vendor/autoload.php';
$loader = new Twig_Loader_Filesystem('templates');
$twig   = new Twig_Environment($loader /*, array('cache' => 'cache', 'debug' => false)*/ );


// include all models
foreach (glob("models/*.php") as $filename) {
    include $filename;
}

$db = Application::getDatabase();

session_start();

//Note: $steam_callback seems to be an unnecessary variable that is set somewhere that I
// do not know. Kept it in and everything seems to be working from a devel environment.
$openid = new LightOpenID($steam_callback);

//Login magic for which I'm not sure of the exact functionality.
if (!$openid->mode) {
    if (isset($_GET['login'])) {
        if ($_POST["remember-me"] == "remember-me")
            $_SESSION["persist_login"] = true;

        $openid->identity = 'http://steamcommunity.com/openid/?l=english';
        header('Location: ' . $openid->authUrl());
    }
} else {
    if ($openid->validate()) {
        $id  = $openid->identity;
        $ptn = "/^http:\/\/steamcommunity\.com\/openid\/id\/(7[0-9]{15,25}+)$/";
        preg_match($ptn, $id, $matches);
        $_SESSION["steamid"] = $matches[1];
        $url = sprintf(
            "http://api.steampowered.com/ISteamUser/GetPlayerSummaries/v0002/?key=%s&steamids=%s",
            STEAMAPI, $matches[1]
        );

        //Set up our curl request to Steam
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_VERBOSE, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_AUTOREFERER, false);
        curl_setopt($ch, CURLOPT_REFERER, "http://www.thronebutt.com");
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        $result = curl_exec($ch);
        curl_close($ch);

        //And let's ask if the login was actually successful
        $json_decoded = json_decode($result);

        foreach ($json_decoded->response->players as $player) {
          $_SESSION["steamname"] = $player->personaname;
        }

        if (isset ($_SESSION["persist_login"])) {
            Application::generate_token($_SESSION["steamid"]);
        }
    }
}

if (isset($_SESSION["steamid"])) {
    //If the person currently logged in is an admin, we need to set their rights accordingly
    $_SESSION["admin"] = check_your_privilege($_SESSION["steamid"]);
}

//If the person using the site still has an authentic cookie for themselves, but have somehow
// lost their associated steamid from the session, we need to re-connect them to their long-
// -lost gamer tag.
//
// Remember to look suitably tearful over the reunion.
if (isset($_COOKIE["authtoken"]) && !isset($_SESSION["steamid"])) {
    $steamid_login = $db->check_token($_COOKIE["authtoken"]);

    //Then again, let's just make sure that they're not sneaking an attempted login
    // beforehand.
    if ($steamid_login != "") {
        $_SESSION["steamid"] = $steamid_login;
        $url = sprintf(
            "http://api.steampowered.com/ISteamUser/GetPlayerSummaries/v0002/?key=%s&steamids=%s",
            STEAMAPI, $steamid_login
        );
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_VERBOSE, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        $result = curl_exec($ch);
        curl_close($ch);

        $json_decoded = json_decode($result);

        foreach ($json_decoded->response->players as $player) {
          $_SESSION["steamname"] = $player->personaname;
        }
    }
}

//If the user is attempting to logout, let them do so at their own risk.
// We do so by removing their token and nuking the session before sending
// them to Steam's logout location (I think; don't quote me on that)
if (isset($_GET["logout"])) {
    $db->remove_token($_COOKIE["authtoken"]);
    session_destroy();
    session_unset();
    header('Location: ' . $steam_callback);
}

// List legal controllers - everything else will go to 404.
$controller_list = array();

//Get the list of all controllers available to us and stuff them in the above array
foreach (glob("controllers/*.php") as $filename) {
    preg_match("/controllers\/(\w*)\.php/xi", $filename, $match);
    $controller_list[] = $match[1];
}


// Route all requests to their respective places
if (isset($_GET['do'])) {

    // see if the page requested is in the controllers list
    if (array_search($_GET['do'], $controller_list) === false) {
        // if not, the respective place is in a 404 (a.k.a. the rubbish heap)
        echo $twig->render('404.twig');
    } else {
        // Include the controller for the requested file
        include "controllers/" . $_GET["do"] . ".php";
    }

} else {

    //If that variable was not included, we're just going to be loading the index
    include "controllers/index.php";

}

//Gather all the common data that we have available to us at the current time,
// along with a few special announcements if applicable
$data = array('session' => $_SESSION,
            'weekday' => date("w") + 1,
            'get' => $_GET,
            'notice' => @file_get_contents("announcement.txt"));

//Occasionally, we'll be asked to return a page as json instead of as HTML
//Note: The two methods below: 'json' and 'render' each have endpoints in the controller
// files that were called earlier, each of which may have been included at some point
// or another.
if (isset($_GET['json'])) {
    //Which is what this bit 'ere will do, returning the required page as a json string
    // that the end user can interpret accordingly.
    json($data);
} else {
    //Otherwise, it's a standard order of business to just render the page as HTML
    // according the required functionality in each controller.
    render($twig, $data);

    //Right, that's everything, show the nice audience how fast we did a thing!
    $time = microtime();
    $time = explode(' ', $time);
    $time = $time[1] + $time[0];
    $finish = $time;
    $total_time = round(($finish - $start), 4);

    echo '<!-- Page generated in '.$total_time.' seconds. -->';
}
