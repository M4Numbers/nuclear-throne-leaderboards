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

    /**
     * End User Methods
     */

}