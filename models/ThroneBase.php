<?php

/**
 * Copyright 2015 M. D. Ball (m.d.ball2@ncl.ac.uk)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
class ThroneBase extends CentralDatabase {

    public function __construct() {

        //Check whether we have the config file with us
        if (!defined("DATABASE"))
            throw new PDOException("No database was defined");

        //Connect to the database
        parent::__construct(DATABASE, "",
            array(
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            )
        );

    }

    /**
     * Start token methods
     */

    public function insert_token($steamId, $token) {
        $sql = "INSERT INTO `throne_tokens` (`token`, `user_id`, `last_accessed`)
                VALUES(:token, :steamid, CURRENT_TIMESTAMP())
                 ON DUPLICATE KEY UPDATE `last_accessed`=CURRENT_TIMESTAMP()";

        try {
            parent::executePreparedStatement(
                parent::makePreparedStatement($sql),
                array(":steamid" => $steamId, ":token" => $token)
            );
        } catch (PDOException $e) {
            throw $e;
        }
    }

    public function check_token($token) {
        $sql = "SELECT `user_id` FROM `throne_tokens` WHERE `token` = :token";

        try {
            $res = parent::executePreparedStatement(
                parent::makePreparedStatement($sql),
                array(":token" => $token)
            );

            if ($res->rowCount() > 0) {
                $row = $res->fetch();
                return $row['user_id'];
            }

            return 0;
        } catch (PDOException $e) {
            throw $e;
        }
    }

    public function remove_token($token) {
        $sql = "DELETE FROM `throne_tokens` WHERE `token` = :token";

        try {
            parent::executePreparedStatement(
                parent::makePreparedStatement($sql),
                array(":token" => $token)
            );
        } catch (PDOException $e) {
            throw $e;
        }
    }

    public function remove_all_user_tokens($userId) {
        $sql = "DELETE FROM `throne_tokens` WHERE `user_id` = :userid";

        try {
            parent::executePreparedStatement(
                parent::makePreparedStatement($sql),
                array(":userid" => $userId)
            );
        } catch (PDOException $e) {
            throw $e;
        }
    }

    /**
     * End Token Methods
     */

    /**
     * Start Leaderboard Methods
     */

    public function clear_magic_variables() {
        $sql = "SET @prev_value = NULL";
        $rql = "SET @rank_count = 0";

        try {
            parent::executeStatement(
                parent::makePreparedStatement($sql)
            );
            parent::executeStatement(
                parent::makePreparedStatement($rql)
            );
        } catch (PDOException $e) {
            throw $e;
        }
    }

    public function generate_alltime_leaderboard($order_by = "score", $start = 0, $size = 30, $direction = "ASC") {
        $sql = "SELECT  d.*, p.*, c.ranks, w.* FROM (
                  SELECT {$order_by}, @rank:=@rank+1 ranks FROM (
                    SELECT  DISTINCT {$order_by} FROM throne_alltime a
                    ORDER BY {$order_by} DESC
                  ) t, (SELECT @rank:= 0) r
                ) c INNER JOIN throne_alltime d ON c.{$order_by} = d.{$order_by}
                LEFT JOIN throne_players p ON p.steamid = d.steamid
                LEFT JOIN (
                  (SELECT COUNT(*) as wins, steamid FROM throne_scores
                    WHERE rank = 1 GROUP BY steamid) AS w
                  ) ON w.steamid = d.steamid
                ORDER BY c.ranks {$direction} LIMIT :start, :size";

        try {

            $res = parent::executePreparedStatement(
                parent::makePreparedStatement($sql),
                array(":start" => $start, ":size" => $size)
            );

            return $res->fetchAll();

        } catch (PDOException $e) {
            throw $e;
        }
    }

    public function get_day_id_from_date($date) {

        $aov = array(":chosen" => $date);

        $sql = "SELECT `dayId` FROM `throne_dates`
                WHERE `date`=:chosen";

        try {
            $res = parent::executePreparedStatement(
                parent::makePreparedStatement($sql), $aov
            );

            if ($res->rowCount() > 0) {
                $row = $res->fetch();
                return $row['dayId'];
            }
            return 0;
        } catch (PDOException $e) {
            throw $e;
        }

    }

    public function get_day_id_from_offset($offset) {
        $offset++;

        $sql = "SELECT `dayId` FROM `throne_dates`
                ORDER BY `dayId` DESC LIMIT {$offset}";

        try {

            $res = parent::executeStatement(
                parent::makePreparedStatement($sql)
            );

            $row = $res->fetchAll();

            return $row[$offset-1]['dayId'];

        } catch (PDOException $e) {
            throw $e;
        }
    }

    public function get_global_statistics($dateId) {
        $aov = array(":dayId" => $dateId);

        $sql = "SELECT COUNT(*) AS runcount, AVG(score) AS avgscore
                FROM throne_scores WHERE `dayId` = :dayId";

        try {

            $res = parent::executePreparedStatement(
                parent::makePreparedStatement($sql), $aov
            );

            $row = $res->fetch();
            return $row;

        } catch (PDOException $e) {
            throw $e;
        }
    }

    public function generate_leaderboard(
            $where, $condition, $order_by, $direction,
            $start = 0, $len = 0, $dateId = -1) {

        //Work out if we need a limit on this thing
        if ($len > 0) {
            $limit = "LIMIT $start, $len";
        } else {
            $limit = "";
        }

        //Then ask if we're being asked to make a narrow or wide search (with regards to
        // time anyway)
        if ($dateId > -1) {
            $date_query = "AND throne_scores.dayId = '" . $dateId . "'";
        } else {
            $date_query = "";
        }

        //This query is in possession of several orders of sketch
        $sql = "SELECT * FROM `throne_scores`
                LEFT JOIN throne_dates ON throne_scores.dayId = throne_dates.dayId
                LEFT JOIN throne_players ON throne_scores.steamId = throne_players.steamid
                LEFT JOIN (
                  (SELECT COUNT(*) AS wins, steamId FROM throne_scores
                    WHERE rank = 1 GROUP BY steamId) AS w) ON w.steamId = throne_scores.steamId
                LEFT JOIN (
                    SELECT dayId AS d, COUNT(*) AS runs FROM throne_scores
                    GROUP BY dayId) x ON x.d = throne_scores.dayId
                WHERE `{$where}` = :cnd
                $date_query
                ORDER BY `{$order_by}` {$direction} {$limit}";

        $aov = array(":cnd" => $condition);

        try {

            //Still... apparently we're going to try it and hope that nothing breaks
            $entries = parent::executePreparedStatement(parent::makePreparedStatement($sql), $aov);
            return $entries->fetchAll();

        } catch (Exception $e) {
            //Or die trying... that's an acceptable path too
            die ("Error fetching leaderboard: " . $e->getMessage());
        }
    }
    /**
     * End Leaderboard Methods
     */

    /**
     * Start User Methods
     */

    public function search_user($user) {
        $sql = "SELECT * FROM `throne_players` WHERE MATCH (name) AGAINST (:q)";

        try {
            $res = parent::executePreparedStatement(
                parent::makePreparedStatement($sql),
                array(":q" => $user)
            );
            return $res->fetchAll();
        } catch (PDOException $e) {
            throw $e;
        }
    }

    public function get_user($user) {

        $sql = "SELECT * FROM `throne_players`
                  LEFT JOIN (
                    (SELECT COUNT(*) AS wins, steamId FROM throne_scores
                    WHERE rank = 1 GROUP BY steamId)
                  AS w) ON w.steamId = throne_players.steamid
                WHERE throne_players.steamid = :steamid";

        $aov = array(":steamid" => $user);

        try {

            $res = parent::executePreparedStatement(
                parent::makePreparedStatement($sql), $aov
            );

            return $res->fetch();

        } catch (PDOException $e) {
            throw $e;
        }

    }

    public function get_alltime_rank($user) {

        $sql = "SELECT d.*, c.ranks FROM (
                  SELECT score, @rank:=@rank+1 ranks FROM (
                    SELECT DISTINCT score FROM throne_alltime a
                    ORDER BY score DESC
                  ) t, (SELECT @rank:= 0) r
                ) c INNER JOIN throne_alltime d ON c.score = d.score
                WHERE d.steamid = :steamid";

        $aov = array(":steamid" => $user);

        try {

            $res = parent::executePreparedStatement(
                parent::makePreparedStatement($sql), $aov
            );

            while ($row = $res->fetch()) {
                return $row['ranks'];
            }

            return -1;

        } catch (PDOException $e) {
            throw $e;
        }

    }

    public function update_twitch($user, $twitch) {

        $sql = "UPDATE throne_players SET twitch = :twitch WHERE steamid = :steamid";

        $aov = array(":twitch" => $twitch, ":steamid" => $user);

        try {

            $res = parent::executePreparedStatement(
                parent::makePreparedStatement($sql), $aov
            );

            return $res->rowCount() > 0;

        } catch (PDOException $e) {
            throw $e;
        }

    }

    public function check_user_is_admin($user) {

        $sql = "SELECT `admin` FROM throne_players WHERE steamid = :steamid";

        $aov = array(":steamid" => $user);

        try {

            $res = parent::executePreparedStatement(
                parent::makePreparedStatement($sql), $aov
            );

            while ($row = $res->fetch()) {
                return $row['admin'];
            }

            return 0;

        } catch (PDOException $e) {
            throw $e;
        }
    }

    public function mark_user_hacker($user, $state) {

        $sql = "UPDATE throne_players SET suspected_hacker = :state WHERE steamid = :user";

        $aov = array(
            ":user" => $user,
            ":state" => $state,
        );

        try {

            parent::executePreparedStatement(
                parent::makePreparedStatement($sql), $aov
            );

        } catch (PDOException $e) {
            throw $e;
        }
    }

    public function update_user($user, $name, $avatar) {

        $sql = "UPDATE throne_players SET
                  name = :name,
                  avatar = :avatar,
                  last_updated = NOW()
                WHERE steamid = :steamid";

        $aov = array(
            ":steamid" => $user,
            ":name" => $name,
            ":avatar" => $avatar,
        );

        try {

            parent::executePreparedStatement(
                parent::makePreparedStatement($sql), $aov
            );

        } catch(PDOException $e) {
            throw $e;
        }

    }

    /**
     * End User Methods
     */

    /**
     * Start Score Methods
     */

    public function set_score_hidden($hash, $state) {

        $sql = "UPDATE throne_scores SET hidden = :state WHERE hash = :hash";

        $aov = array(
            ":hash" => $hash,
            ":state" => $state,
        );

        try {

            parent::executePreparedStatement(
                parent::makePreparedStatement($sql), $aov
            );

        } catch (PDOException $e) {
            throw $e;
        }

    }

    public function get_single_score($hash) {

        $sql = "SELECT * FROM `throne_scores`
                  LEFT JOIN `throne_dates` ON `throne_dates`.dayId = `throne_scores`.dayId
                WHERE `hash` = :hash";

        $aov = array(":hash" => $hash);

        try {

            $res = parent::executePreparedStatement(
                parent::makePreparedStatement($sql), $aov
            );

            return $res->fetch();

        } catch(PDOException $e) {
            throw $e;
        }
    }

    public function update_score($hash, $video, $comment) {

        $sql = "UPDATE `throne_scores` SET
                  `video` = :video,
                  `comment` = :comment
                WHERE `hash` = :hash";

        $aov = array(
            ":hash" => $hash,
            ":video" => $video,
            ":comment" => $comment,
        );

        try {

            $res = parent::executePreparedStatement(
                parent::makePreparedStatement($sql), $aov
            );

            return $res->rowCount() > 0;

        } catch (PDOException $e) {
            throw $e;
        }
    }

    /**
     * End Score Methods
     */

    /**
     * Start Twitch Methods
     */

    public function get_streams() {

        $sql = "SELECT * FROM throne_streams ORDER BY viewers DESC LIMIT 0,3";

        try {

            $res = parent::executeStatement(parent::makePreparedStatement($sql));

            return $res->fetchAll();

        } catch (PDOException $e) {
            throw $e;
        }

    }

    /**
     * End Twitch Methods
     */

    /**
     * Start Twitter Methods
     */

    public function get_latest_day_id() {

        $sql = "SELECT * FROM throne_dates ORDER BY dayId DESC LIMIT 0,1";

        try {

            $res = parent::executeStatement(parent::makePreparedStatement($sql));

            return $res->fetch();

        } catch (PDOException $e) {
            throw $e;
        }

    }

    public function find_top_player_for_day($dayId) {
        $sql = "SELECT throne_scores.score, throne_players.name FROM throne_scores
                LEFT JOIN throne_players ON throne_players.steamid = throne_scores.steamId
                WHERE throne_scores.dayId = ':dayId' ORDER BY rank ASC LIMIT 0,1";

        $aov = array(":dayId" => $dayId);

        try {

            $res = parent::executePreparedStatement(
                parent::makePreparedStatement($sql), $aov
            );

            return $res->fetch();

        } catch (PDOException $e) {
            throw $e;
        }
    }

    /**
     * End Twitter Methods
     */

    /**
     * Start Crontab Methods
     */

    public function update_profiles($profiles, $steam_apikey, callable $curler = "get_data") {

        //TODO: Cut the logging from this so it's purely functional

        $t = count($profiles);
        // Logging.
        echo($t . " profiles to update. \n");

        //A counting variable which is necessary to make sure that we don't overstay our welcome on Steam
        $c = 0;
        try {

            // Prepare for a major alteration
            parent::beginTransaction();

            $insert = parent::makePreparedStatement(
                "INSERT INTO throne_players(steamid, name, avatar)
                VALUES(:steamid, :name, :avatar)
                ON DUPLICATE KEY UPDATE name=VALUES(name), avatar=VALUES(avatar), last_updated=NOW();"
            );

            foreach ($profiles as $row) {

                $jsonUserData = "";

                //For each player, we get their profile page and save their name and a link
                // to their avatar.
                try {

                    $jsonUserData = call_user_func($curler,
                        sprintf(
                            "http://api.steampowered.com/ISteamUser/GetPlayerSummaries/v0002/?key=%s&steamids=%s",
                            $steam_apikey, $row['steamId']
                        )
                    );

                    //Decode the data
                    $user = json_decode($jsonUserData, true);

                    //And insert the person straight in
                    parent::executePreparedStatement($insert,
                        array(
                            ":steamid" => $row['steamId'],
                            ":name" => $user["response"]["players"][0]["personaname"],
                            ":avatar" => $user["response"]["players"][0]["avatar"]
                        )
                    );

                    // Log the update.
                    printf(
                        "[%d/%d] Updated %s as %s\n",
                        $c, $t, $row['steamId'], $user["response"]["players"][0]["personaname"]
                    );

                } catch (Exception $e) {

                    //Print an amount of debug

                    printf("----- Profile Update Failed -----\n");

                    printf(
                        "[%d/%d]   Failed to update %s due to %s\n",
                        $c, $t, $row['steamId'], $e->getMessage()
                    );
                    printf(
                        "[%d/%d]   Pulled from: http://api.steampowered.com/ISteamUser/GetPlayerSummaries".
                        "/v0002/?key=%s&steamids=%s\n", $c, $t, $steam_apikey, $row['steamId']
                    );
                    printf(
                        "[%d/%d]   Result: ", $c, $t
                    );
                    var_dump($jsonUserData);

                    printf("\n----- Profile Debug Completed -----\n");

                }

                //Wait for 0.2 seconds so that we don't piss off Lord GabeN and mistakenly
                // DDoS Steam.
                usleep(200000);
                $c = $c + 1;

                // I have to do this.
                //I assume that this is done because of the time that it must be taking by this
                // point and the number of requests that have been fired off to Steam. We need
                // to stop before we go too far
                if ($c === 500) {
                    break;
                }

            }

            //Commit all those changes we've made
            parent::commitTransaction();

        } catch (PDOException $e) {
            parent::rollbackTransaction();
            throw $e;

        }
    }

    public function get_updating_profiles() {

        $sql = "SELECT DISTINCT throne_scores.steamId FROM throne_scores
                  LEFT JOIN throne_players ON throne_scores.steamId = throne_players.steamid
                WHERE DATEDIFF(NOW(), throne_scores.last_updated) < 5
                  AND
                    (DATEDIFF(NOW(), throne_players.last_updated) > 1
                      OR throne_players.last_updated IS NULL)";

        try {

            $res = parent::executeStatement(parent::makePreparedStatement($sql));
            return $res->fetchAll();

        } catch (PDOException $e) {
            throw $e;
        }

    }

    public function update_all_scores($scores, $banned) {

        //Mark the number one rank
        $rank = 1;
        //And the lowest rank currently possible
        $rank_hax = count($scores) + 1;

        $ret = array();

        try {
            //Start some transaction management for a minute so we can roll out everything
            // as a single big change as opposed to many little ones
            parent::beginTransaction();

            $insert = parent::makePreparedStatement(
                "INSERT INTO throne_scores(hash, dayId, steamId, score, rank, hidden, first_created)
                VALUES(:hash, :dayId,:steamID,:score,:rank,:hidden,NOW())
                ON DUPLICATE KEY UPDATE rank=VALUES(rank), score=VALUES(score), hidden=VALUES(hidden);"
            );

            foreach ($scores as $score) {

                $b = (array_search($score['steamID'], $banned) === false);

                if ($b && ($rank == 1)) {
                    $ret = $score;
                }

                // Insert data into the database
                parent::executePreparedStatement($insert,
                    array(
                        ":hash" => $score['hash'],
                        ":dayId" => $score['dayId'],
                        ":steamID" => $score['steamID'],
                        ":score" => $score['score'],
                        ":rank" => ($b) ? $rank : $rank_hax,
                        ":hidden" => ($b) ? 0 : 1,
                    )
                );

                if ($b) {
                    //And prop the rank up one more for the next person
                    $rank += 1;
                } else {
                    //And prop the rank up one more for the next hacker
                    $rank_hax += 1;
                }

            }

            // Commit our efforts.
            parent::commitTransaction();

            return $ret;

        } catch (PDOException $e) {
            parent::rollbackTransaction();
            throw $e;
        }
    }

    public function refresh_twitch_streams($streams) {

        $sql = "INSERT INTO throne_streams(name, status, viewers, preview)
                VALUES(:name, :status, :viewers, :preview)";

        $reset = "TRUNCATE TABLE throne_streams";

        try {

            parent::beginTransaction();

            parent::executeStatement(parent::makePreparedStatement($reset));

            //For each stream we have found, add it into the streaming table
            foreach ($streams as $stream) {

                parent::executePreparedStatement(
                    parent::makePreparedStatement($sql),
                    array(
                        ":name" => $stream['channel']['name'],
                        ":status" => $stream['channel']['status'],
                        ":viewers" => $stream['viewers'],
                        ":preview" => str_replace("http://","https://", $stream['preview']['small'])
                    )
                );

            }

            parent::commitTransaction();

        } catch (PDOException $e) {
            parent::rollbackTransaction();
            throw $e;
        }
    }

    public function insert_new_day($dayId, $date) {

        $sql = "INSERT IGNORE INTO throne_dates(`dayId`, `date`) VALUES(:dayId, :day)";

        $aov = array(
            ":dayId" => $dayId,
            ":day" => $date,
        );

        try {

            parent::executePreparedStatement(
                parent::makePreparedStatement($sql), $aov
            );

        } catch (PDOException $e) {
            throw $e;
        }

    }

    public function find_hackers() {

        $sql = "SELECT `steamid` FROM throne_players WHERE suspected_hacker = 1";

        try {

            $res = parent::executeStatement(parent::makePreparedStatement($sql));

            return $res->fetchAll();

        } catch (PDOException $e) {
            throw $e;
        }

    }

    public function find_hidden_players($dayId) {

        $sql = "SELECT `steamId` FROM throne_scores WHERE hidden = 1 AND dayId = :dayId";

        $aov = array(":dayId" => $dayId);

        try {

            $res = parent::executePreparedStatement(
                parent::makePreparedStatement($sql), $aov
            );

            return $res->fetchAll();

        } catch (PDOException $e) {
            throw $e;
        }

    }

    public function update_alltime_leaderboard() {
        try {

            parent::beginTransaction();

            $this->burn_alltime_leaderboards();
            $this->add_new_alltime_scores();

            parent::commitTransaction();

        } catch (PDOException $e) {
            parent::rollbackTransaction();
            throw $e;
        }
    }

    public function burn_alltime_leaderboards() {

        $sql = "TRUNCATE TABLE `throne_alltime`";

        try {

            parent::executeStatement(parent::makePreparedStatement($sql));

        } catch (PDOException $e) {
            throw $e;
        }

    }

    public function add_new_alltime_scores() {

        $sql = "INSERT INTO throne_alltime(steamid, score, average, runs)
                  SELECT throne_scores.steamId, SUM(score) as score,
                    AVG(score) as average, COUNT(score) AS runs
                  FROM `throne_scores`
                    LEFT JOIN throne_players ON throne_scores.steamId = throne_players.steamid
                  WHERE suspected_hacker = 0
                  GROUP BY throne_scores.steamId
                  ORDER BY score DESC";

        try {

            parent::executeStatement(parent::makePreparedStatement($sql));

        } catch (PDOException $e) {
            throw $e;
        }

    }

    /**
     * End Crontab Methods
     */

}