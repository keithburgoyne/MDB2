<?php
// vim: set et ts=4 sw=4 fdm=marker:
// +----------------------------------------------------------------------+
// | PHP Version 4                                                        |
// +----------------------------------------------------------------------+
// | Copyright (c) 1998-2004 Manuel Lemos, Tomas V.V.Cox,                 |
// | Stig. S. Bakken, Lukas Smith, Frank M. Kromann                       |
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
// | Author: Lukas Smith <smith@backendmedia.com>                         |
// +----------------------------------------------------------------------+
//
// $Id$
//

/**
 * MDB2 FrontBase driver
 *
 * @package MDB2
 * @category Database
 * @author  Lukas Smith <smith@backendmedia.com>
 * @author  Frank M. Kromann <frank@kromann.info>
 */
class MDB2_Driver_fbsql extends MDB2_Driver_Common
{
    // {{{ properties
    var $escape_quotes = "'";
    var $decimal_factor = 1.0;

    var $max_text_length = 32768;

    // }}}
    // {{{ constructor

    /**
    * Constructor
    */
    function MDB2_fbsql()
    {
        $this->MDB2_Driver_Common();
        $this->phptype = 'fbsql';
        $this->dbsyntax = 'fbsql';

        $this->supported['sequences'] = true;
        $this->supported['indexes'] = true;
        $this->supported['affected_rows'] = true;
        $this->supported['summary_functions'] = true;
        $this->supported['order_by_text'] = true;
        $this->supported['current_id'] = true;
        $this->supported['limit_queries'] = true;
        $this->supported['LOBs'] = true;
        $this->supported['replace'] = true;
        $this->supported['sub_selects'] = true;

        $this->decimal_factor = pow(10.0, $this->options['decimal_places']);
    }

    // }}}
    // {{{ errorInfo()

    /**
     * This method is used to collect information about an error
     *
     * @param integer $error
     * @return array
     * @access public
     */
    function errorInfo($error = null)
    {
        $native_code = @fbsql_errno($this->connection);
        $native_msg  = @fbsql_error($this->connection);
        if (is_null($error)) {
            static $ecode_map;
            if (empty($ecode_map)) {
                $ecode_map = array(
                    1004 => MDB2_ERROR_CANNOT_CREATE,
                    1005 => MDB2_ERROR_CANNOT_CREATE,
                    1006 => MDB2_ERROR_CANNOT_CREATE,
                    1007 => MDB2_ERROR_ALREADY_EXISTS,
                    1008 => MDB2_ERROR_CANNOT_DROP,
                    1046 => MDB2_ERROR_NODBSELECTED,
                    1050 => MDB2_ERROR_ALREADY_EXISTS,
                    1051 => MDB2_ERROR_NOSUCHTABLE,
                    1054 => MDB2_ERROR_NOSUCHFIELD,
                    1062 => MDB2_ERROR_ALREADY_EXISTS,
                    1064 => MDB2_ERROR_SYNTAX,
                    1100 => MDB2_ERROR_NOT_LOCKED,
                    1136 => MDB2_ERROR_VALUE_COUNT_ON_ROW,
                    1146 => MDB2_ERROR_NOSUCHTABLE,
                );
            }
            if (isset($ecode_map[$native_code])) {
                $error = $ecode_map[$native_code];
            }
        }
        return array($error, $native_code, $native_msg);
    }

    // }}}
    // {{{ autoCommit()

    /**
     * Define whether database changes done on the database be automatically
     * committed. This function may also implicitly start or end a transaction.
     *
     * @param boolean $auto_commit    flag that indicates whether the database
     *                                changes should be committed right after
     *                                executing every query statement. If this
     *                                argument is 0 a transaction implicitly
     *                                started. Otherwise, if a transaction is
     *                                in progress it is ended by committing any
     *                                database changes that were pending.
     *
     * @access public
     *
     * @return mixed MDB2_OK on success, a MDB2 error on failure
     */
    function autoCommit($auto_commit)
    {
        $this->debug(($auto_commit ? 'On' : 'Off'), 'autoCommit');
        if (!isset($this->supported['transactions'])) {
            return $this->raiseError(MDB2_ERROR_UNSUPPORTED, null, null,
                'autoCommit: transactions are not in use');
        }
        if ($this->auto_commit == $auto_commit) {
            return MDB2_OK;
        }
        if ($this->connection) {
            if ($auto_commit) {
                $result = $this->query('COMMIT');
                if (MDB2::isError($result)) {
                    return $result;
                }
                $result = $this->query('SET COMMIT TRUE');
            } else {
                $result = $this->query('SET COMMIT FALSE');
            }
            if (MDB2::isError($result)) {
                return $result;
            }
        }
        $this->auto_commit = $auto_commit;
        $this->in_transaction = !$auto_commit;
        return MDB2_OK;
    }

    // }}}
    // {{{ commit()

    /**
     * Commit the database changes done during a transaction that is in
     * progress. This function may only be called when auto-committing is
     * disabled, otherwise it will fail. Therefore, a new transaction is
     * implicitly started after committing the pending changes.
     *
     * @access public
     *
     * @return mixed MDB2_OK on success, a MDB2 error on failure
     */
    function commit()
    {
        $this->debug('commit transaction', 'commit');
        if (!isset($this->supported['transactions'])) {
            return $this->raiseError(MDB2_ERROR_UNSUPPORTED, null, null,
                'commit: transactions are not in use');
        }
        if ($this->auto_commit) {
            return $this->raiseError(MDB2_ERROR, null, null,
            'commit: transaction changes are being auto commited');
        }
        return $this->query('COMMIT');
    }

    // }}}
    // {{{ rollback()

    /**
     * Cancel any database changes done during a transaction that is in
     * progress. This function may only be called when auto-committing is
     * disabled, otherwise it will fail. Therefore, a new transaction is
     * implicitly started after canceling the pending changes.
     *
     * @access public
     *
     * @return mixed MDB2_OK on success, a MDB2 error on failure
     */
    function rollback()
    {
        $this->debug('rolling back transaction', 'rollback');
        if (!isset($this->supported['transactions'])) {
            return $this->raiseError(MDB2_ERROR_UNSUPPORTED, null, null,
                'rollback: transactions are not in use');
        }
        if ($this->auto_commit) {
            return $this->raiseError(MDB2_ERROR, null, null,
                'rollback: transactions can not be rolled back when changes are auto commited');
        }
        return $this->query('ROLLBACK');
    }

    // }}}
    // {{{ connect()

    /**
     * Connect to the database
     *
     * @return true on success, MDB2 Error Object on failure
     **/
    function connect()
    {
        if ($this->connection != 0) {
            if (count(array_diff($this->connected_dsn, $this->dsn)) == 0
                && $this->opened_persistent == $this->options['persistent']
            ) {
                return MDB2_OK;
            }
            @fbsql_close($this->connection);
            $this->connection = 0;
        }

        if (!PEAR::loadExtension($this->phptype)) {
            return $this->raiseError(MDB2_ERROR_NOT_FOUND, null, null,
                'connect: extension '.$this->phptype.' is not compiled into PHP');
        }

        $function = ($this->options['persistent'] ? 'fbsql_pconnect' : 'fbsql_connect');

        $dsninfo = $this->dsn;
        $dbhost = $dsninfo['hostspec'] ? $dsninfo['hostspec'] : 'localhost';
        $user = $dsninfo['username'];
        $pw = $dsninfo['password'];

        @ini_set('track_errors', true);
        if ($dbhost && $user && $pw) {
            $connection = @$function($dbhost, $user, $pw);
        } elseif ($dbhost && $user) {
            $connection = @$function($dbhost, $user);
        } elseif ($dbhost) {
            $connection = @$function($dbhost);
        } else {
            $connection = 0;
        }
        @ini_restore('track_errors');
        if ($connection <= 0) {
            return $this->raiseError(MDB2_ERROR_CONNECT_FAILED, null, null,
                $php_errormsg);
        }
        $this->connection = $connection;
        $this->connected_dsn = $this->dsn;
        $this->connected_database_name = '';
        $this->opened_persistent = $this->options['persistent'];

        if (isset($this->supported['transactions']) && !$this->auto_commit) {
            if (!@fbsql_query('SET AUTOCOMMIT FALSE;', $this->connection)) {
                @fbsql_close($this->connection);
                $this->connection = 0;
                return $this->raiseError();
            }
            $this->in_transaction = true;
        }
        return MDB2_OK;
    }

    // }}}
    // {{{ _close()
    /**
     * all the RDBMS specific things needed close a DB connection
     *
     * @return boolean
     * @access private
     **/
    function _close()
    {
        if ($this->connection != 0) {
            if (isset($this->supported['transactions']) && !$this->auto_commit) {
                $result = $this->autoCommit(true);
            }
            @fbsql_close($this->connection);
            $this->connection = 0;
            unset($GLOBALS['_MDB2_databases'][$this->db_index]);

            if (isset($result) && MDB2::isError($result)) {
                return $result;
            }
        }
        return MDB2_OK;
    }

    // }}}
    // {{{ _modifyQuery()

    /**
     * This method is used by backends to alter queries for various
     * reasons.
     *
     * @param string $query  query to modify
     * @return the new (modified) query
     * @access private
     */
    function _modifyQuery($query)
    {
        // "DELETE FROM table" gives 0 affected rows in fbsql.
        // This little hack lets you know how many rows were deleted.
        if (preg_match('/^\s*DELETE\s+FROM\s+(\S+)\s*$/i', $query)) {
            $query = preg_replace('/^\s*DELETE\s+FROM\s+(\S+)\s*$/',
                                  'DELETE FROM \1 WHERE 1=1', $query);
        }
        return $query;
    }

    // }}}
    // {{{ query()

    /**
     * Send a query to the database and return any results
     *
     * @param string  $query  the SQL query
     * @param mixed   $types  string or array that contains the types of the
     *                        columns in the result set
     * @param mixed $result_class string which specifies which result class to use
     * @return mixed a result handle or MDB2_OK on success, a MDB2 error on failure
     *
     * @access public
     */
    function &query($query, $types = null, $result_class = false)
    {
        $ismanip = MDB2::isManip($query);
        $offset = $this->row_offset;
        $limit = $this->row_limit;
        $this->row_offset = $this->row_limit = 0;
        if ($limit > 0) {
            if (!$ismanip) {
                $query = str_replace('SELECT', "SELECT TOP($offset,$limit)", $query);
            }
        }
        if ($this->options['optimize'] == 'portability') {
            $query = $this->_modifyQuery($query);
        }
        $this->last_query = $query;
        $this->debug($query, 'query');

        $connected = $this->connect();
        if (MDB2::isError($connected)) {
            return $connected;
        }

        if ($this->database_name
            && $this->database_name != $this->connected_database_name
        ) {
            if (!@fbsql_select_db($this->database_name, $this->connection)) {
                $error =& $this->raiseError();
                return $error;
            }
            $this->connected_database_name = $this->database_name;
        }

        // Add ; to the end of the query. This is required by FrontBase
        $query .= ';';
        if ($result = @fbsql_query($query, $this->connection)) {
            if ($ismanip) {
                return MDB2_OK;
            } else {
                if (!$result_class) {
                    $result_class = $this->options['result_buffering']
                        ? $this->options['buffered_result_class'] : $this->options['result_class'];
                }
                $class_name = sprintf($result_class, $this->phptype);
                $result =& new $class_name($this, $result);
                if ($types) {
                    $err = $result->setResultTypes($types);
                    if (MDB2::isError($err)) {
                        $result->free();
                        return $err;
                    }
                }
                return $result;
            }
        }
        $error =& $this->raiseError();
        return $error;
    }

    // }}}
    // {{{ affectedRows()

    /**
     * returns the affected rows of a query
     *
     * @return mixed MDB2 Error Object or number of rows
     * @access public
     */
    function affectedRows()
    {
        $affected_rows = @fbsql_affected_rows($this->connection);
        if ($affected_rows === false) {
            return $this->raiseError(MDB2_ERROR_NEED_MORE_DATA);
        }
        return $affected_rows;
    }

    // }}}
    // {{{ nextID()

    /**
     * returns the next free id of a sequence
     *
     * @param string  $seq_name name of the sequence
     * @param boolean $ondemand when true the seqence is
     *                          automatic created, if it
     *                          not exists
     *
     * @return mixed MDB2 Error Object or id
     * @access public
     */
    function nextID($seq_name, $ondemand = true)
    {
        $sequence_name = $this->getSequenceName($seq_name);
        $this->expectError(MDB2_ERROR_NOSUCHTABLE);
        $result = $this->query("INSERT INTO $sequence_name (dummy) VALUES (1)");
        $this->popExpect();
        if (MDB2::isError($result)) {
            if ($ondemand && $result->getCode() == MDB2_ERROR_NOSUCHTABLE) {
                $this->loadModule('manager');
                // Since we are creating the sequence on demand
                // we know the first id = 1 so initialize the
                // sequence at 2
                $result = $this->manager->createSequence($seq_name, 2);
                if (MDB2::isError($result)) {
                    return $this->raiseError(MDB2_ERROR, null, null,
                        'nextID: on demand sequence could not be created');
                } else {
                    // First ID of a newly created sequence is 1
                    return 1;
                }
            }
            return $result;
        }
        $value = $this->queryOne("SELECT UNIQUE FROM $sequence_name", 'integer');
        $result = $this->query("DELETE FROM $sequence_name WHERE sequence < $value");
        if (MDB2::isError($result)) {
            $this->warnings[] = 'nextID: could not delete previous sequence table values from '.$seq_name;
        }
        return $value;
    }

    // }}}
    // {{{ currID()

    /**
     * returns the current id of a sequence
     *
     * @param string  $seq_name name of the sequence
     * @return mixed MDB2 Error Object or id
     * @access public
     */
    function currID($seq_name)
    {
        $sequence_name = $this->getSequenceName($seq_name);
        return $this->queryOne("SELECT MAX(sequence) FROM $sequence_name", 'integer');
    }
}

class MDB2_Result_mysql extends MDB2_Result_Common
{
    // }}}
    // {{{ constructor

    /**
     * Constructor
     */
    function MDB2_Result_mysql(&$mdb, &$result)
    {
        parent::MDB2_Result_Common($mdb, $result);
    }

    // }}}
    // {{{ fetch()

    /**
    * fetch value from a result set
    *
    * @param int    $rownum    number of the row where the data can be found
    * @param int    $field    field number where the data can be found
    * @return mixed string on success, a MDB2 error on failure
    * @access public
    */
    function fetch($rownum = 0, $field = 0)
    {
        $value = @fbsql_result($this->result, $rownum, $field);
        if (!$value) {
            if (is_null($this->result)) {
                return $this->mdb->raiseError(MDB2_ERROR_NEED_MORE_DATA, null, null,
                    'fetch: resultset has already been freed');
            }
        } elseif (isset($this->types[$field])) {
            $value = $this->mdb->datatype->convertResult($value, $this->types[$field]);
        }
        return $value;
    }

    // }}}
    // {{{ fetchRow()

    /**
     * Fetch a row and insert the data into an existing array.
     *
     * @param int       $fetchmode  how the array data should be indexed
     * @return int data array on success, a MDB2 error on failure
     * @access public
     */
    function fetchRow($fetchmode = MDB2_FETCHMODE_DEFAULT)
    {
        if ($fetchmode == MDB2_FETCHMODE_DEFAULT) {
            $fetchmode = $this->mdb->fetchmode;
        }
        if ($fetchmode & MDB2_FETCHMODE_ASSOC) {
            $row = @fbsql_fetch_assoc($this->result);
            if (is_array($row) && $this->mdb->options['optimize'] == 'portability') {
                $row = array_change_key_case($row, CASE_LOWER);
            }
        } else {
           $row = @fbsql_fetch_row($this->result);
        }
        if (!$row) {
            if (is_null($this->result)) {
                return $this->mdb->raiseError(MDB2_ERROR_NEED_MORE_DATA, null, null,
                    'fetchRow: resultset has already been freed');
            }
            return null;
        }
        if (isset($this->types)) {
            $row = $this->mdb->datatype->convertResultRow($this->types, $row);
        }
        ++$this->rownum;
        return $row;
    }

    // }}}
    // {{{ getColumnNames()

    /**
     * Retrieve the names of columns returned by the DBMS in a query result.
     *
     * @return mixed                an associative array variable
     *                              that will hold the names of columns. The
     *                              indexes of the array are the column names
     *                              mapped to lower case and the values are the
     *                              respective numbers of the columns starting
     *                              from 0. Some DBMS may not return any
     *                              columns when the result set does not
     *                              contain any rows.
     *
     *                              a MDB2 error on failure
     * @access public
     */
    function getColumnNames()
    {
        $columns = array();
        $numcols = $this->numCols();
        if (MDB2::isError($numcols)) {
            return $numcols;
        }
        for ($column = 0; $column < $numcols; $column++) {
            $column_name = @fbsql_field_name($this->result, $column);
            if ($this->mdb->options['optimize'] == 'portability') {
                $column_name = strtolower($column_name);
            }
            $columns[$column_name] = $column;
        }
        return $columns;
    }

    // }}}
    // {{{ numCols()

    /**
     * Count the number of columns returned by the DBMS in a query result.
     *
     * @access public
     * @return mixed integer value with the number of columns, a MDB2 error
     *                       on failure
     */
    function numCols()
    {
        $cols = @fbsql_num_fields($this->result);
        if (is_null($cols)) {
            if (is_null($this->result)) {
                return $this->mdb->raiseError(MDB2_ERROR_NEED_MORE_DATA, null, null,
                    'numCols: resultset has already been freed');
            }
            return $this->mdb->raiseError();
        }
        return $cols;
    }

    // }}}
    // {{{ nextResult()

    /**
     * Move the internal result pointer to the next available result
     * Currently not supported
     *
     * @return true if a result is available otherwise return false
     * @access public
     */
    function nextResult()
    {
        if (is_null($this->result)) {
            return $this->mdb->raiseError(MDB2_ERROR_NEED_MORE_DATA, null, null,
                'nextResult: resultset has already been freed');
        }
        return @fbsql_next_result($this->result);
    }

    // }}}
    // {{{ free()

    /**
     * Free the internal resources associated with result.
     *
     * @return boolean true on success, false if result is invalid
     * @access public
     */
    function free()
    {
        $free = @fbsql_free_result($this->result);
        if (!$free) {
            if (is_null($this->result)) {
                return MDB2_OK;
            }
            return $this->mdb->raiseError();
        }
        $this->result = null;
        return MDB2_OK;
    }
}

class MDB2_BufferedResult_mysql extends MDB2_Result_mysql
{
    // }}}
    // {{{ constructor

    /**
     * Constructor
     */
    function MDB2_BufferedResult_mysql(&$mdb, &$result)
    {
        parent::MDB2_Result_mysql($mdb, $result);
    }

    // }}}
    // {{{ seek()

    /**
    * seek to a specific row in a result set
    *
    * @param int    $rownum    number of the row where the data can be found
    * @return mixed MDB2_OK on success, a MDB2 error on failure
    * @access public
    */
    function seek($rownum = 0)
    {
        if (!@fbsql_data_seek($this->result, $rownum)) {
            if (is_null($this->result)) {
                return $this->mdb->raiseError(MDB2_ERROR_NEED_MORE_DATA, null, null,
                    'seek: resultset has already been freed');
            }
            return $this->mdb->raiseError(MDB2_ERROR_INVALID, null, null,
                'seek: tried to seek to an invalid row number ('.$rownum.')');
        }
        $this->rownum = $rownum - 1;
        return MDB2_OK;
    }

    // }}}
    // {{{ hasMore()

    /**
    * check if the end of the result set has been reached
    *
    * @return mixed true or false on sucess, a MDB2 error on failure
    * @access public
    */
    function hasMore()
    {
        $numrows = $this->numRows();
        if (MDB2::isError($numrows)) {
            return $numrows;
        }
        return $this->rownum < $numrows - 1;
    }

    // }}}
    // {{{ numRows()

    /**
    * returns the number of rows in a result object
    *
    * @return mixed MDB2 Error Object or the number of rows
    * @access public
    */
    function numRows()
    {
        $rows = @fbsql_num_rows($this->result);
        if (is_null($rows)) {
            if (is_null($this->result)) {
                return $this->mdb->raiseError(MDB2_ERROR_NEED_MORE_DATA, null, null,
                    'numRows: resultset has already been freed');
            }
            return $this->raiseError();
        }
        return $rows;
    }
}

?>