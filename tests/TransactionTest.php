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

require_once "../models/CentralDatabase.php";

class TransactionTest extends CentralDatabase {

    public function __construct() {
        if (!defined("DATABASE"))
            throw new PDOException("No database was defined");
        parent::__construct(DATABASE, "");
    }

    public function resetDatabase() {
        parent::executeStatement(
            parent::makePreparedStatement("TRUNCATE TABLE `throne_test`")
        );
    }

    public function runTest() {
        $this->resetDatabase();
        $c = $this->testNormal();
        $d = $this->testRollback($c);
    }

    public function testNormal() {

        try {
            printf("Beginning Normal Usage Test...\n");

            printf("Preparing Statement...\n");

            $sql = "INSERT INTO `throne_test` (`foo`, `bar`) VALUES (:foo, :bar)";
            $stmt = parent::makePreparedStatement($sql);

            printf("Beginning Transaction...\n");

            parent::beginTransaction();

            printf("Inserting 20 rows...\n");

            parent::executePreparedStatement($stmt, ["foo" => 1,  "bar" => "a"]);
            parent::executePreparedStatement($stmt, ["foo" => 2,  "bar" => "b"]);
            parent::executePreparedStatement($stmt, ["foo" => 3,  "bar" => "c"]);
            parent::executePreparedStatement($stmt, ["foo" => 4,  "bar" => "d"]);
            parent::executePreparedStatement($stmt, ["foo" => 5,  "bar" => "e"]);
            parent::executePreparedStatement($stmt, ["foo" => 6,  "bar" => "f"]);
            parent::executePreparedStatement($stmt, ["foo" => 7,  "bar" => "g"]);
            parent::executePreparedStatement($stmt, ["foo" => 8,  "bar" => "h"]);
            parent::executePreparedStatement($stmt, ["foo" => 9,  "bar" => "i"]);
            parent::executePreparedStatement($stmt, ["foo" => 10, "bar" => "j"]);
            parent::executePreparedStatement($stmt, ["foo" => 11, "bar" => "k"]);
            parent::executePreparedStatement($stmt, ["foo" => 12, "bar" => "l"]);
            parent::executePreparedStatement($stmt, ["foo" => 13, "bar" => "m"]);
            parent::executePreparedStatement($stmt, ["foo" => 14, "bar" => "n"]);
            parent::executePreparedStatement($stmt, ["foo" => 15, "bar" => "o"]);
            parent::executePreparedStatement($stmt, ["foo" => 16, "bar" => "p"]);
            parent::executePreparedStatement($stmt, ["foo" => 17, "bar" => "q"]);
            parent::executePreparedStatement($stmt, ["foo" => 18, "bar" => "r"]);
            parent::executePreparedStatement($stmt, ["foo" => 19, "bar" => "s"]);
            parent::executePreparedStatement($stmt, ["foo" => 20, "bar" => "t"]);

            printf("Committing 20 rows...\n");

            parent::commitTransaction();

            printf("Commit complete, testing...\n");

            $res = parent::executeStatement(
                parent::makePreparedStatement(
                    "SELECT * FROM `throne_test`"
                )
            );

            if ($res->rowCount() == 20) {
                printf("All rows present...\n");
            } else {
                printf("Err... Not all rows present...\n");
            }

            return $res->rowCount();

        } catch (PDOException $e) {
            printf("Error... (%d) %s\n", $e->getCode(), $e->getMessage());
            return -1;
        }

    }

    public function testRollback($count) {
        try {
            printf("Beginning Rollback Usage Test...\n");

            printf("Preparing Statement...\n");

            $sql = "INSERT INTO `throne_test` (`foo`, `bar`) VALUES (:foo, :bar)";
            $stmt = parent::makePreparedStatement($sql);

            printf("Beginning Transaction...\n");

            parent::beginTransaction();

            printf("Inserting 20 rows...\n");

            parent::executePreparedStatement($stmt, ["foo" => 21, "bar" => "aa"]);
            parent::executePreparedStatement($stmt, ["foo" => 22, "bar" => "ba"]);
            parent::executePreparedStatement($stmt, ["foo" => 23, "bar" => "ca"]);
            parent::executePreparedStatement($stmt, ["foo" => 24, "bar" => "da"]);
            parent::executePreparedStatement($stmt, ["foo" => 25, "bar" => "ea"]);
            parent::executePreparedStatement($stmt, ["foo" => 26, "bar" => "fa"]);
            parent::executePreparedStatement($stmt, ["foo" => 27, "bar" => "ga"]);
            parent::executePreparedStatement($stmt, ["foo" => 28, "bar" => "ha"]);
            parent::executePreparedStatement($stmt, ["foo" => 29, "bar" => "ia"]);
            parent::executePreparedStatement($stmt, ["foo" => 30, "bar" => "ja"]);
            parent::executePreparedStatement($stmt, ["foo" => 31, "bar" => "ka"]);
            parent::executePreparedStatement($stmt, ["foo" => 32, "bar" => "la"]);
            parent::executePreparedStatement($stmt, ["foo" => 33, "bar" => "ma"]);
            parent::executePreparedStatement($stmt, ["foo" => 34, "bar" => "na"]);
            parent::executePreparedStatement($stmt, ["foo" => 35, "bar" => "oa"]);
            parent::executePreparedStatement($stmt, ["foo" => 36, "bar" => "pa"]);
            parent::executePreparedStatement($stmt, ["foo" => 37, "bar" => "qa"]);
            parent::executePreparedStatement($stmt, ["foo" => 38, "bar" => "ra"]);
            parent::executePreparedStatement($stmt, ["foo" => 39, "bar" => "sa"]);
            parent::executePreparedStatement($stmt, ["foo" => 40, "bar" => "ta"]);

            printf("Rolling back 20 rows...\n");

            parent::rollbackTransaction();

            printf("Rollback complete, testing...\n");

            $res = parent::executeStatement(
                parent::makePreparedStatement(
                    "SELECT * FROM `throne_test`"
                )
            );

            if ($res->rowCount() == $count) {
                printf("Rollback successful...\n");
            } else {
                printf("Err... Incorrect number of rows...\n");
            }

            return $res->rowCount();

        } catch (PDOException $e) {
            printf("Error... (%d) %s\n", $e->getCode(), $e->getMessage());
            return -1;
        }
    }

}