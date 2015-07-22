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

}