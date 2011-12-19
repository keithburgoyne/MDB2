<?php
// +----------------------------------------------------------------------+
// | PHP versions 4 and 5                                                 |
// +----------------------------------------------------------------------+
// | Copyright (c) 1998-2006 Manuel Lemos, Paul Cooper                    |
// | All rights reserved.                                                 |
// +----------------------------------------------------------------------+
// | MDB2 is a merge of PEAR DB and Metabases that provides a unified DB  |
// | API as well as database abstraction for PHP applications.            |
// | This LICENSE is in the BSD license style.                            |
// |                                                                      |
// | Redistribution and use in source and binary forms, with or without   |
// | modification, are permitted provided that the following conditions   |
// | are met:                                                             |
// |                                                                      |
// | Redistributions of source code must retain the above copyright       |
// | notice, this list of conditions and the following disclaimer.        |
// |                                                                      |
// | Redistributions in binary form must reproduce the above copyright    |
// | notice, this list of conditions and the following disclaimer in the  |
// | documentation and/or other materials provided with the distribution. |
// |                                                                      |
// | Neither the name of Manuel Lemos, Tomas V.V.Cox, Stig. S. Bakken,    |
// | Lukas Smith nor the names of his contributors may be used to endorse |
// | or promote products derived from this software without specific prior|
// | written permission.                                                  |
// |                                                                      |
// | THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS  |
// | "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT    |
// | LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS    |
// | FOR A PARTICULAR PURPOSE ARE DISCLAIMED.  IN NO EVENT SHALL THE      |
// | REGENTS OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,          |
// | INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, |
// | BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS|
// |  OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED  |
// | AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT          |
// | LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY|
// | WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE          |
// | POSSIBILITY OF SUCH DAMAGE.                                          |
// +----------------------------------------------------------------------+
// | Author: Paul Cooper <pgc@ucecom.com>                                 |
// +----------------------------------------------------------------------+
//
// $Id$

abstract class Standard_Abstract extends PHPUnit_Framework_TestCase {
    /**
     * Should the tables be cleared in the setUp() and tearDown() methods?
     * @var bool
     */
    protected $clear_tables = true;

    /**
     * The database name currently being tested
     * @var string
     */
    public $database;

    /**
     * The MDB2 object being currently tested
     * @var MDB2_Driver_Common
     */
    public $db;

    /**
     * The DSN of the database that is currently being tested
     * @var array
     */
    public $dsn;

    /**
     * The unserialized value of MDB2_TEST_SERIALIZED_DSNS
     * @var array
     */
    protected static $dsns;

    /**
     * Field names of the test table
     * @var array
     */
    public $fields = array(
            'user_name'     => 'text',
            'user_password' => 'text',
            'subscribed'    => 'boolean',
            'user_id'       => 'integer',
            'quota'         => 'decimal',
            'weight'        => 'float',
            'access_date'   => 'date',
            'access_time'   => 'time',
            'approved'      => 'timestamp',
    );

    /**
     * Options to use on the current database run
     * @var array
     */
    public $options;


    public static function setUpBeforeClass() {
        $dsns = unserialize(MDB2_TEST_SERIALIZED_DSNS);
        self::$dsns = $dsns;
    }

    /**
     * A PHPUnit dataProvider callback to supply the MDB2 objects for testing
     * @uses mdb2_test_db_object_provider()
     * @return array  the MDB2_Driver_Common objects to test against
     */
    public function provider() {
        return mdb2_test_db_object_provider();
    }

    /**
     * Establishes the class properties for each test
     *
     * Can not use setUp() because we are using a dataProvider to get multiple
     * MDB2 objects per test.
     *
     * @param MDB2_Driver_Common $db
     */
    protected function manualSetUp($db) {
        $dsn = $db->getDSN('array');
        $phptype = $dsn['phptype'];

        $this->db = $db;
        $this->dsn = self::$dsns[$phptype]['dsn'];
        $this->options = self::$dsns[$phptype]['options'];
        $this->database = $this->dsn['database'];

        $this->db->setDatabase($this->database);
        $this->db->expectError(MDB2_ERROR_UNSUPPORTED);
        $this->clearTables();
    }

    public function tearDown() {
        $this->clearTables();
        if (!$this->db || PEAR::isError($this->db)) {
            return;
        }
        $this->db->disconnect();
        $this->db->popExpect();
        unset($this->db);
    }

    public function clearTables() {
        if (!$this->clear_tables) {
            return;
        }
        if (PEAR::isError($this->db->exec('DELETE FROM users'))) {
            $this->assertTrue(false, 'Error deleting from table users');
        }
        if (PEAR::isError($this->db->exec('DELETE FROM files'))) {
            $this->assertTrue(false, 'Error deleting from table users');
        }
    }

    public function supported($feature) {
        if (!$this->db->supports($feature)) {
            $this->fail('This database does not support '.$feature);
            return false;
        }
        return true;
    }

    public function verifyFetchedValues(&$result, $rownum, $data) {
        //$row = $result->fetchRow(MDB2_FETCHMODE_DEFAULT, $rownum);
        $row = $result->fetchRow(MDB2_FETCHMODE_ASSOC, $rownum);
        if (!is_array($row)) {
            $this->fail('Error result row is not an array');
            return;
        }
        //reset($row);
        foreach ($this->fields as $field => $type) {
            //$value = current($row);
            $value = $row[$field];
            if ($type == 'float') {
                $delta = 0.0000000001;
            } else {
                $delta = 0;
            }

            $this->assertEquals($data[$field], $value, "the value retrieved for field \"$field\" doesn't match what was stored into the rownum $rownum", $delta);
            //next($row);
        }
    }

    public function getSampleData($row = 1) {
        $data = array();
        $data['user_name']     = 'user_' . $row;
        $data['user_password'] = 'somepass';
        $data['subscribed']    = $row % 2 ? true : false;
        $data['user_id']       = $row;
        $data['quota']         = strval($row/100);
        $data['weight']        = sqrt($row);
        $data['access_date']   = MDB2_Date::mdbToday();
        $data['access_time']   = MDB2_Date::mdbTime();
        $data['approved']      = MDB2_Date::mdbNow();
        return $data;
    }

    public function methodExists(&$class, $name) {
        if (is_object($class)
            && in_array(strtolower($name), array_map('strtolower', get_class_methods($class)))
        ) {
            return true;
        }
        $this->fail('method '. $name.' not implemented in '.get_class($class));
        return false;
    }

    public function tableExists($table) {
        $this->db->loadModule('Manager', null, true);
        $tables = $this->db->manager->listTables();
        if (PEAR::isError($tables)) {
            $this->fail('Cannot list tables: '. $tables->getUserInfo());
            return false;
        }
        return in_array(strtolower($table), array_map('strtolower', $tables));
    }
}