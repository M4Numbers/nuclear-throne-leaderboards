<?php
date_default_timezone_set("UTC");

function get_latest_daily($page = 1) {
  global $db_username, $db_password;
  $page = $page - 1;
  $db = new PDO('mysql:host=localhost;dbname=throne;charset=utf8', $db_username, $db_password, array(PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
  $players = array();

  // Get latest day ID (clumsy!)
  $result = $db->query('SELECT DISTINCT dayId FROM throne_scores ORDER BY dayId DESC LIMIT 0,1');
  foreach ($result as $dateid) {
    $today = $dateid['dayId'];
  }
  foreach($db->query('SELECT throne_scores.steamId, throne_scores.hash, throne_scores.rank, throne_scores.score, throne_players.name, throne_players.avatar FROM throne_scores LEFT JOIN throne_players ON throne_players.steamid = throne_scores.steamId WHERE throne_scores.dayId = ' . $today . ' ORDER BY rank ASC LIMIT ' . $page * 30 . ', 30') as $row) {
    if ($row['name'] === "") {
      $row['name'] = "[private]";
    }
    $players[] = $row;
  }
  return array('date' => date('Y-m-d'), 'players' => $players, 'page' => $page + 1);
} 


function get_score($hash) {
  global $db_username, $db_password;
  $db = new PDO('mysql:host=localhost;dbname=throne;charset=utf8', $db_username, $db_password, array(PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
  $stmt = $db->prepare("SELECT * FROM throne_scores LEFT JOIN throne_players on throne_scores.steamId = throne_players.steamid LEFT JOIN throne_dates ON throne_dates.dayId = throne_scores.dayId WHERE hash = :hash");
  $stmt->execute(array(':hash' => $hash));
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
  if ($stmt->rowCount() == 1)  {
    $rows[0]['avatar_medium'] = substr($rows[0]['avatar'], 0, -4) . "_medium.jpg";
    if ($rows[0]['name'] === "") {
      $rows[0]['name'] = "[private]";
    }
    return $rows[0];
  } else {
    return false;
  }
} 

function get_player($steamid) { 
  global $db_username, $db_password;
  $db = new PDO('mysql:host=localhost;dbname=throne;charset=utf8', $db_username, $db_password, array(PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
  $stmt = $db->prepare("SELECT * FROM throne_players WHERE steamid = :steamid");
  $stmt->execute(array(':steamid' => $steamid));
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
  $player = $rows[0];
  $player['avatar_medium'] = substr($player['avatar'], 0, -4) . "_medium.jpg";
  if ($player['name'] === "") {
      $player['name'] = "[private]";
    }
  $scores = array();
  $stmt = $db->prepare('SELECT * FROM throne_scores LEFT JOIN throne_dates ON throne_scores.dayId = throne_dates.dayId WHERE throne_scores.steamId = :steamid ORDER BY throne_scores.dayId ASC LIMIT 0, 100');
  $stmt->execute(array(":steamid" => $steamid));

  $allscores = [];
  $totalscore = 0;
  $allrank = [];
  $totalrank = 0;
  $count = 0;

  foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $score) {
    $scores[] = $score;
    $totalscore += $score['score'] ;
    $totalrank += $score['rank'] ;
    $count = $count + 1;
    $allscores[] = $score['score'];
    $allrank[] = $score['rank'];
  }

  $player['avgscore'] = round($totalscore / $count);
  $player['avgrank'] = round($totalrank / $count);
  $player['hiscore'] = max($allscores);
  $player['loscore'] = min($allscores);
  $player['hirank'] = max($allrank);
  $player['lorank'] = min($allrank);
  return array('player' => $player, 'scores' => $scores);
} 


// Ported from my LotusClan web admin interface thing.
function convertSteamId($steamid) { 
  $steamid = trim(strip_tags($steamid));
  // check for community id
  $xml = @file_get_contents("http://steamcommunity.com/id/".$steamid."?xml=1"); // You saw no @ here. 
    
  // Verify if the community ID exists 
  if (preg_match("/<steamID64>(\d*)<\/steamID64>/", $xml, $match)) { // based regex xml reading
    return $match[1];
  }
}
 ?>