<?php
// +----------------------------------------------------------------------+
// | PHP versions 4 and 5                                                 |
// +----------------------------------------------------------------------+
// | Copyright (c) 1998-2005 Manuel Lemos, Paul Cooper, Lorenzo Alberton  |
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
// | Authors: Paul Cooper <pgc@ucecom.com>                                |
// |          Lorenzo Alberton <l dot alberton at quipo dot it>           |
// +----------------------------------------------------------------------+
//
// $Id$

require_once 'MDB2/Schema.php';

class MDB2_Manager_TestCase extends PHPUnit_TestCase {
    //contains the dsn of the database we are testing
    var $dsn;
    //contains the options that should be used during testing
    var $options;
    //contains the name of the database we are testing
    var $database;
    //contains the MDB2 object of the db once we have connected
    var $db;
    //contains the name of the driver_test schema
    var $driver_input_file = 'driver_test.schema';
    //contains the name of the lob_test schema
    var $lob_input_file = 'lob_test.schema';
    //contains the name of the extension to use for backup schemas
    var $backup_extension = '.before';
    //test table name (it is dynamically created/dropped)
    var $table = 'newtable';
    //test table fields
    var $fields;

    function MDB2_Manager_Test($name) {
        $this->PHPUnit_TestCase($name);
    }

    function setUp() {
        $this->dsn = $GLOBALS['dsn'];
        $this->options = $GLOBALS['options'];
        $this->database = $GLOBALS['database'];
        $backup_file = $this->driver_input_file.$this->backup_extension;
        if (file_exists($backup_file)) {
            unlink($backup_file);
        }
        $backup_file = $this->lob_input_file.$this->backup_extension;
        if (file_exists($backup_file)) {
            unlink($backup_file);
        }
        $this->db =& MDB2::connect($this->dsn, $this->options);
        $this->db->loadModule('Manager');
        if (PEAR::isError($this->db) || PEAR::isError($this->db->manager) ) {
            $this->assertTrue(false, 'Could not connect to manager in setUp');
            exit;
        }
        $this->fields = array(
            'id' => array(
                'type'     => 'integer',
                'unsigned' => 1,
                'notnull'  => 1,
                'default'  => 0,
            ),
            'name' => array(
                'type'   => 'text',
                'length' => 12,
            ),
            'description' => array(
                'type'   => 'text',
                'length' => 12,
            ),
            'sex' => array(
                'type' => 'text',
                'length' => 1,
                'default' => 'M',
            ),
        );
    }

    function tearDown() {
        unset($this->dsn);
        if (!PEAR::isError($this->db->manager)) {
            $this->db->disconnect();
        }
        unset($this->db);
    }

    function methodExists(&$class, $name) {
        if (is_object($class)
            && array_key_exists(strtolower($name), array_change_key_case(array_flip(get_class_methods($class)), CASE_LOWER))
        ) {
            return true;
        }
        $this->assertTrue(false, 'method '. $name.' not implemented in '.get_class($class));
        return false;
    }

    function tableExists() {
        $tables = $this->db->manager->listTables();
        return in_array($this->table, $tables);
    }

/*
    function testCreateDatabase() {
        if (!$this->methodExists($this->manager->db->manager, 'dropDatabase')) {
            return;
        }
        $this->db->manager->db->expectError('*');
        $result = $this->db->manager->db->manager->dropDatabase($this->database);
        $this->db->manager->db->popExpect();
        if (PEAR::isError($result)) {
            $this->assertTrue(false, 'Database dropping failed: please manually delete the database if needed');
        }
        if (!$this->methodExists($this->db->manager, 'updateDatabase')) {
            return;
        }
        $result = $this->db->manager->updateDatabase(
            $this->driver_input_file,
            false,
            array('create' =>'1', 'name' => $this->database)
        );
        if (!PEAR::isError($result)) {
            $result = $this->db->manager->updateDatabase(
                $this->lob_input_file,
                false,
                array('create' =>'0', 'name' => $this->database)
            );
        }
        $this->assertFalse(PEAR::isError($result), 'Error creating database');
    }

    function testUpdateDatabase() {
        if (!$this->methodExists($this->db->manager, 'updateDatabase')) {
            return;
        }
        $backup_file = $this->driver_input_file.$this->backup_extension;
        if (!file_exists($backup_file)) {
            copy($this->driver_input_file, $backup_file);
        }
        $result = $this->db->manager->updateDatabase(
            $this->driver_input_file,
            $backup_file,
            array('create' =>'0', 'name' =>$this->database)
        );
        if (!PEAR::isError($result)) {
            $backup_file = $this->lob_input_file.$this->backup_extension;
            if (!file_exists($backup_file)) {
                copy($this->lob_input_file, $backup_file);
            }
            $result = $this->db->manager->updateDatabase(
                $this->lob_input_file,
                $backup_file,
                array('create' =>'0', 'name' => $this->database)
            );
        }
        $this->assertFalse(PEAR::isError($result), 'Error updating database');
    }
*/

    /**
     * Create a sample table, test the new fields, and drop it.
     */
    function testCreateTable() {
        $this->db->setDatabase($this->database);
        if (!$this->methodExists($this->db->manager, 'createTable')) {
            return;
        }
        if ($this->tableExists()) {
            $this->db->manager->dropTable($this->table);
        }

        $result = $this->db->manager->createTable($this->table, $this->fields);
        $this->assertFalse(PEAR::isError($result), 'Error creating table');
    }

    /**
     *
     */
    function testListTableFields() {
        $this->db->setDatabase($this->database);
        if (!$this->methodExists($this->db->manager, 'listTableFields')) {
            return;
        }
        if (!$this->tableExists()) {
            $this->assertTrue(false, 'Table does not exists');
        } else {
            $this->assertEquals(
                array_keys($this->fields),
                $this->db->manager->listTableFields($this->table),
                'Error creating table: incorrect fields'
            );
        }
    }

    /**
     *
     */
    function testListTables() {
        $this->db->setDatabase($this->database);
        if (!$this->methodExists($this->db->manager, 'listTables')) {
            return;
        }
        $this->assertTrue($this->tableExists(), 'Error listing tables');
    }

    /**
     *
     */
    function testAlterTable() {
        $this->db->setDatabase($this->database);
        if (!$this->methodExists($this->db->manager, 'alterTable')) {
            return;
        }
        $changes = array(
            'add' => array(
                'quota' => array(
                    'type' => 'integer',
                    'unsigned' => 1,
                ),
            ),
            'remove' => array(
                'description' => array(),
            ),
            /*
            'change' => array(
                'gender' => array(
                    'default' => 'F',
                ),
            ),
            */
            'rename' => array(
                'sex' => array(
                    'name' => 'gender',
                ),
            ),
        );
        if (!$this->tableExists()) {
            $this->db->manager->createTable($this->table, $this->fields);
        }

        $result = $this->db->manager->alterTable($this->table, $changes, false);
        $this->assertFalse(PEAR::isError($result), 'Error altering table');

        $altered_table_fields = $this->db->manager->listTableFields($this->table);
        foreach ($changes['add'] as $newfield => $dummy) {
            $this->assertTrue(in_array($newfield, $altered_table_fields), 'Error: new field "'.$newfield.'"not added');
        }
        foreach ($changes['remove'] as $newfield => $dummy) {
            $this->assertFalse(in_array($newfield, $altered_table_fields), 'Error: field "'.$newfield.'"not removed');
        }
        foreach ($changes['rename'] as $oldfield => $newfield) {
            $this->assertFalse(in_array($oldfield, $altered_table_fields), 'Error: field "'.$oldfield.'"not renamed');
            $this->assertTrue(in_array($newfield['name'], $altered_table_fields), 'Error: field "'.$oldfield.'"not renamed correctly');
        }

        /*
        //ideally, get the new table definition, and check all field properties
        $this->db->loadModule('Reverse');
        $altered_table = $this->db->reverse->tableInfo($this->table);
        $this->assertFalse(PEAR::isError($altered_table), 'Error getting table info');
        //echo '<pre>';var_dump($altered_table);echo '</pre>';
        //check field properties
        */
    }

    /**
     *
     */
    function testDropTable() {
        $this->db->setDatabase($this->database);
        if (!$this->methodExists($this->db->manager, 'dropTable')) {
            return;
        }
        if ($this->tableExists()) {
            $result = $this->db->manager->dropTable($this->table);
            $this->assertFalse(PEAR::isError($result), 'Error dropping table');
        }
    }

    /**
     *
     */
    function testListTablesNoTable() {
        $this->db->setDatabase($this->database);
        if (!$this->methodExists($this->db->manager, 'listTables')) {
            return;
        }
        $this->assertFalse($this->tableExists(), 'Error listing tables');
    }
}

?>