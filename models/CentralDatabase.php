<?php

/**
 * Copyright 2014/2015 Matthew D. Ball
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

/**
 * Class CentralDatabase The central class that controls all
 * the functions which interact with the database. This includes
 * statement preparation and statement execution.
 *
 * @author M4Numbers
 */
class CentralDatabase {

    /**
     * @var PDO The database object that controls all the
     *  connections in every database we're operating here
     */
    private $pdo_base;

    /**
     * @var string The prefix of all current tables in this
     *  database
     */
    private $prefix;

    /**
     * Connect to the chosen database with the selected
     * configuration options.
     *
     * @param string $database The chosen database that we're going
     *  to perform functions and statements on
     * @param string $prefix The prefix of all tables in the chosen
     *  database
     * @throws Exception if constants were not defined.
     */
    protected function __construct( $database, $prefix ) {

        //Now we actually do need to include
        // the configuration file for reasons
        if ( !defined("DATABASE") )
            throw new Exception("No database was defined!");

        //Connect using the information we've been supplied
        // in the constructor
        $dsn = sprintf("mysql:dbname=%s;host=%s;charset=utf8;", $database, DATAHOST);

        try {

            //Create the database and set the associative fetch
            // array as default
            $pdo_base = new PDO( $dsn, DATAUSER, DATAPASS );
            $pdo_base->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $pdo_base->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            //And set the last few things in the variables
            $this->pdo_base = $pdo_base;
            $this->prefix = $prefix;

        } catch (PDOException $ex) {

            //And, just in case it failed, we need to catch said
            // failure and output it to the error log.
            error_log( "Could not connect to database: " . $ex->getMessage() );
            die;

        }

    }

    /**
     * Return the statement when we've given it the basic string template
     * to play with.
     *
     * @param String $string : The string that we're going to put into
     *  a PDO statement and the thing we're going to return
     * @return PDOStatement : Well... it was the $string
     * @throws PDOException : For when santa thinks we've been naughty
     */
    protected function makePreparedStatement( $string ) {

        try {

            //Replace all implementations of the special character with
            // the prefix of the tables that we've been provided with
            $string = sprintf(str_replace('@','%1$s',$string ),$this->prefix);
            return $this->pdo_base->prepare( $string );

        } catch (PDOException $ex) {

            error_log("Failed to make prepared statement: " . $ex->getMessage());
            throw $ex;

        }

    }

    /**
     * A function to bind all the values of a MySQLi statement to their actual
     * values as given... read into that what you will.
     *
     * @param PDOStatement $statement : MySQL PDO statement
     * @param array $arrayOfVars : An array, ordered, of all the values corresponding to the
     *  $statement from before
     * @return PDOStatement : Return the statement that has been produced, thanks
     *  to our wonderful code... or false if it fails
     * @throws PDOException : If the SQL doesn't like to be bound to anyone
     */
    protected function assignStatement( PDOStatement $statement, array $arrayOfVars ) {

        try {

            //We need to do each parameter for their own good.
            foreach ( $arrayOfVars as $key => &$val ) {
                if (is_int($val)) {
                            $statement->bindParam( $key, intval($val), PDO::PARAM_INT);
                } else {
                            $statement->bindParam( $key, $val );
                }
            }

            return $statement;

        } catch (PDOException $except) {

            error_log( "Error encountered during statement binding: " . $except->getMessage() );
            throw $except;

        }

    }

    /**
     * Execute a given status and return whatever happens to it
     *
     * @param PDOStatement $statement : The PDO statement that we're going to execute,
     *  be it query, insertion, deletion, update, or fook knows
     * @return PDOStatement : Return the rows that the statement gets
     *  that have been returned if it manages to find any... Might blow up
     * @throws PDOException : If SQL doth protest
     */
    protected function executeStatement( PDOStatement $statement ) {

        try {

            $res = $statement->execute();

            return ( !$res ) ? false : $statement;

        } catch (PDOException $ex) {

            error_log( "Failed to execute statement: " . $ex->getMessage() );
            throw $ex;

        }

    }

    /**
     * Get, set and execute a statement in this one method instead of having
     * the same lines in a dozen other statements, all doing the same thing.
     *
     * @param PDOStatement $statement : The statement that we're going to make
     *  into a star...
     * @param array $arrayOfVars : The variables that are going to go into this
     *  statement at some point or another.
     * @return PDOStatement : The result set that /should/ have been generated
     *  from the PDO execution
     * @throws PDOException : If PDO is a poor choice...
     */
    protected function executePreparedStatement( PDOStatement $statement, $arrayOfVars ) {

        try {

            $stated = $this->assignStatement( $statement, $arrayOfVars );

            return $this->executeStatement( $stated );

        } catch (PDOException $ex) {

            throw $ex;

        }

    }

    /**
     * Start up some transaction management for ourselves. This should allow
     * users to make large transactions upon the database whilst still using
     * this interface to hide all the nasty stuff
     *
     * @throws PDOException : If the transaction was unable to start
     */
    protected function beginTransaction() {
        try {

            $res = $this->pdo_base->beginTransaction();

            if (!$res)
                throw new PDOException(
                    $this->pdo_base->errorInfo(),
                    $this->pdo_base->errorCode()
                );

        } catch(PDOException $ex) {

            throw $ex;

        }

    }

    /**
     * Once all the statements have been executed successfully, it's natural to
     * believe that the user will want to commit all their statements to the
     * database (hence why this function is here)
     *
     * @throws PDOException
     */
    protected function commitTransaction() {
        try {

            if ($this->pdo_base->inTransaction()) {
                $res = $this->pdo_base->commit();

                if (!$res)
                    throw new PDOException(
                        $this->pdo_base->errorInfo(),
                        $this->pdo_base->errorCode()
                    );

            } else {
                throw new PDOException(
                    "You are not currently in the middle of a transaction",
                    25000
                );
            }

        } catch (PDOException $ex) {

            throw $ex;

        }

    }

    /**
     * Should an SQL transaction fail, the user may well want to abort it all
     * and roll back the database to a previous time.
     *
     * @throws PDOException
     */
    protected function rollbackTransaction() {
        try {

            if ($this->pdo_base->inTransaction()) {
                $res = $this->pdo_base->rollBack();

                if (!$res)
                    throw new PDOException(
                        $this->pdo_base->errorInfo(),
                        $this->pdo_base->errorCode()
                    );

            } else {
                throw new PDOException(
                    "You are not currently in the middle of a transaction",
                    25000
                );
            }

        } catch (PDOException $ex) {

            throw $ex;

        }
    }

    /**
     * @return int The last insertion ID from a statement
     */
    public function getLastInsertId() {
        return $this->pdo_base->lastInsertId();
    }

    /**
     * Destroy the PDO object
     */
    public function __destruct() {
        $this->pdo_base = null;
    }

}
