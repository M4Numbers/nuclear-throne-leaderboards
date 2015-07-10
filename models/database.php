<?php

/**
 * Class Database This is a very transparent database handler (which does absolutely
 * nothing for the most part because the original developer frequently forgets to use
 * it). So... yeah... fun.
 */
class Database {

    /**
     * @var PDO $db
     */
    public $db;

    /**
     * This is unused.
     *
     * @var $query
     */
    private $query;

    /**
     * Skip straight through and connect to the database on class instantiation
     */
    public function __construct() {
        $this->db = Application::$db;
    }

    /**
     * When presented with a query, go through and execute it with predjudice,
     * returning all the rows gathered from such endeavours. This is done in a
     * three-step process: prepare the query, execute with the variables, and
     * return the rows
     *
     * @param String $query
     * @param array $arguments
     * @return array
     */
    public function execute($query, $arguments = array()) {
        $stm = $this->db->prepare($query);
        $stm->execute($arguments);
        return $stm->fetchAll();
    }

}
