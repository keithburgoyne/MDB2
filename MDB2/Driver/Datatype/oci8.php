<?php
// +----------------------------------------------------------------------+
// | PHP versions 4 and 5                                                 |
// +----------------------------------------------------------------------+
// | Copyright (c) 1998-2006 Manuel Lemos, Tomas V.V.Cox,                 |
// | Stig. S. Bakken, Lukas Smith                                         |
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
// | Author: Lukas Smith <smith@pooteeweet.org>                           |
// +----------------------------------------------------------------------+

// $Id$

require_once 'MDB2/Driver/Datatype/Common.php';

/**
 * MDB2 OCI8 driver
 *
 * @package MDB2
 * @category Database
 * @author Lukas Smith <smith@pooteeweet.org>
 */
class MDB2_Driver_Datatype_oci8 extends MDB2_Driver_Datatype_Common
{
    // {{{ convertResult()

    /**
     * convert a value to a RDBMS indepdenant MDB2 type
     *
     * @param mixed $value value to be converted
     * @param int $type constant that specifies which type to convert to
     * @return mixed converted value
     * @access public
     */
    function convertResult($value, $type)
    {
        if (is_null($value)) {
            return null;
        }
        switch ($type) {
        case 'date':
            return substr($value, 0, strlen('YYYY-MM-DD'));
        case 'time':
            return substr($value, strlen('YYYY-MM-DD '), strlen('HH:MI:SS'));
        default:
            return $this->_baseConvertResult($value, $type);
        }
    }

    // }}}
    // {{{ getTypeDeclaration()

    /**
     * Obtain DBMS specific SQL code portion needed to declare an text type
     * field to be used in statements like CREATE TABLE.
     *
     * @param array $field  associative array with the name of the properties
     *      of the field being declared as array indexes. Currently, the types
     *      of supported field properties are as follows:
     *
     *      length
     *          Integer value that determines the maximum length of the text
     *          field. If this argument is missing the field should be
     *          declared to have the longest length allowed by the DBMS.
     *
     *      default
     *          Text value to be used as default for this field.
     *
     *      notnull
     *          Boolean flag that indicates whether this field is constrained
     *          to not be set to null.
     * @return string  DBMS specific SQL code portion that should be used to
     *      declare the specified field.
     * @access public
     */
    function getTypeDeclaration($field)
    {
        $db =& $this->getDBInstance();
        if (PEAR::isError($db)) {
            return $db;
        }

        switch ($field['type']) {
        case 'text':
            $length = !empty($field['length'])
                ? $field['length'] : $db->options['default_text_field_length'];
            $fixed = !empty($field['fixed']) ? $field['fixed'] : false;
            return $fixed ? 'CHAR('.$length.')' : 'VARCHAR2('.$length.')';
        case 'clob':
            return 'CLOB';
        case 'blob':
            return 'BLOB';
        case 'integer':
            if (!empty($field['length'])) {
                return 'NUMBER('.$field['length'].')';
            }
            return 'INT';
        case 'boolean':
            return 'NUMBER(1)';
        case 'date':
        case 'time':
        case 'timestamp':
            return 'DATE';
        case 'float':
            return 'NUMBER';
        case 'decimal':
            return 'NUMBER(*,'.$db->options['decimal_places'].')';
        }
    }

    // }}}
    // {{{ _quoteCLOB()

    /**
     * Convert a text value into a DBMS specific format that is suitable to
     * compose query statements.
     *
     * @param string $value text string value that is intended to be converted.
     * @param bool $quote determines if the value should be quoted and escaped
     * @return string text string that represents the given argument value in
     *        a DBMS specific format.
     * @access protected
     */
    function _quoteCLOB($value, $quote)
    {
        return 'EMPTY_CLOB()';
    }

    // }}}
    // {{{ _quoteBLOB()

    /**
     * Convert a text value into a DBMS specific format that is suitable to
     * compose query statements.
     *
     * @param string $value text string value that is intended to be converted.
     * @param bool $quote determines if the value should be quoted and escaped
     * @return string text string that represents the given argument value in
     *        a DBMS specific format.
     * @access protected
     */
    function _quoteBLOB($value, $quote)
    {
        return 'EMPTY_BLOB()';
    }

    // }}}
    // {{{ _quoteDate()

    /**
     * Convert a text value into a DBMS specific format that is suitable to
     * compose query statements.
     *
     * @param string $value text string value that is intended to be converted.
     * @param bool $quote determines if the value should be quoted and escaped
     * @return string text string that represents the given argument value in
     *        a DBMS specific format.
     * @access protected
     */
    function _quoteDate($value, $quote)
    {
       return $this->_quoteText("$value 00:00:00", $quote);
    }

    // }}}
    // {{{ _quoteTimestamp()

    /**
     * Convert a text value into a DBMS specific format that is suitable to
     * compose query statements.
     *
     * @param string $value text string value that is intended to be converted.
     * @param bool $quote determines if the value should be quoted and escaped
     * @return string text string that represents the given argument value in
     *        a DBMS specific format.
     * @access protected
     */
    function _quoteTimestamp($value, $quote)
    {
       return $this->_quoteText($value, $quote);
    }

    // }}}
    // {{{ _quoteTime()

    /**
     * Convert a text value into a DBMS specific format that is suitable to
     *        compose query statements.
     *
     * @param string $value text string value that is intended to be converted.
     * @param bool $quote determines if the value should be quoted and escaped
     * @return string text string that represents the given argument value in
     *        a DBMS specific format.
     * @access protected
     */
    function _quoteTime($value, $quote)
    {
       return $this->_quoteText("0001-01-01 $value", $quote);
    }

    // }}}
    // {{{ writeLOBToFile()

    /**
     * retrieve LOB from the database
     *
     * @param array $lob array
     * @param string $file name of the file into which the LOb should be fetched
     * @return mixed MDB2_OK on success, a MDB2 error on failure
     * @access protected
     */
    function writeLOBToFile($lob, $file)
    {
        if (preg_match('/^(\w+:\/\/)(.*)$/', $file, $match)) {
            if ($match[1] == 'file://') {
                $file = $match[2];
            }
        }
        $lob_data = stream_get_meta_data($lob);
        $lob_index = $lob_data['wrapper_data']->lob_index;
        $result = $this->lobs[$lob_index]['resource']->writetofile($file);
        $this->lobs[$lob_index]['resource']->seek(0);
        if (!$result) {
            $db =& $this->getDBInstance();
            if (PEAR::isError($db)) {
                return $db;
            }

            return $db->raiseError(null, null, null,
                'writeLOBToFile: Unable to write LOB to file');
        }
        return MDB2_OK;
    }

    // }}}
    // {{{ _retrieveLOB()

    /**
     * retrieve LOB from the database
     *
     * @param array $lob array
     * @return mixed MDB2_OK on success, a MDB2 error on failure
     * @access protected
     */
    function _retrieveLOB(&$lob)
    {
        if (!is_object($lob['resource'])) {
            $db =& $this->getDBInstance();
            if (PEAR::isError($db)) {
                return $db;
            }

           return $db->raiseError(MDB2_ERROR_NOT_FOUND, null, null,
               'attemped to retrieve LOB from non existing or NULL column');
        }

        if (!$lob['loaded']
#            && !method_exists($lob['resource'], 'read')
        ) {
            $lob['value'] = $lob['resource']->load();
            $lob['resource']->seek(0);
        }
        $lob['loaded'] = true;
        return MDB2_OK;
    }

    // }}}
    // {{{ _readLOB()

    /**
     * Read data from large object input stream.
     *
     * @param array $lob array
     * @param blob $data reference to a variable that will hold data to be
     *      read from the large object input stream
     * @param int $length integer value that indicates the largest ammount of
     *      data to be read from the large object input stream.
     * @return mixed length on success, a MDB2 error on failure
     * @access protected
     */
    function _readLOB($lob, $length)
    {
        if ($lob['loaded']) {
            return parent::_readLOB($lob, $length);
        }

        if (!is_object($lob['resource'])) {
            $db =& $this->getDBInstance();
            if (PEAR::isError($db)) {
                return $db;
            }

           return $db->raiseError(MDB2_ERROR_NOT_FOUND, null, null,
               'attemped to retrieve LOB from non existing or NULL column');
        }

        $data = $lob['resource']->read($length);
        if (!is_string($data)) {
            $db =& $this->getDBInstance();
            if (PEAR::isError($db)) {
                return $db;
            }

            return $db->raiseError(null, null, null,
                    '_readLOB: Unable to read LOB');
        }
        return $data;
    }

    // }}}
    // {{{ mapNativeDatatype()

    /**
     * Maps a native array description of a field to a MDB2 datatype and length
     *
     * @param array  $field native field description
     * @return array containing the various possible types, length, sign, fixed
     * @access public
     */
    function mapNativeDatatype($field)
    {
        $db_type = strtolower($field['type']);
        $type = array();
        $length = $unsigned = $fixed = null;
        if (!empty($field['length'])) {
            $length = $field['length'];
        }
        switch ($db_type) {
        case 'integer':
        case 'pls_integer':
        case 'binary_integer':
            $type[] = 'integer';
            if ($length == '1') {
                $type[] = 'boolean';
                if (preg_match('/^[is|has]/', $field['name'])) {
                    $type = array_reverse($type);
                }
            }
            break;
        case 'varchar':
        case 'varchar2':
        case 'nvarchar2':
            $fixed = false;
        case 'char':
        case 'nchar':
            $type[] = 'text';
            if ($length == '1') {
                $type[] = 'boolean';
                if (preg_match('/^[is|has]/', $field['name'])) {
                    $type = array_reverse($type);
                }
            }
            if ($fixed !== false) {
                $fixed = true;
            }
            break;
        case 'date':
        case 'timestamp':
            $type[] = 'timestamp';
            $length = null;
            break;
        case 'float':
            $type[] = 'float';
            break;
        case 'number':
            if (!empty($field['scale'])) {
                $type[] = 'decimal';
            } else {
                $type[] = 'integer';
                if ($length == '1') {
                    $type[] = 'boolean';
                    if (preg_match('/^[is|has]/', $field['name'])) {
                        $type = array_reverse($type);
                    }
                }
            }
            break;
        case 'long':
            $type[] = 'clob';
            $type[] = 'text';
            break;
        case 'blob':
        case 'clob':
        case 'nclob':
        case 'raw':
        case 'long raw':
        case 'bfile':
            $type[] = 'blob';
            $length = null;
            break;
        case 'rowid':
        case 'urowid':
        default:
            $db =& $this->getDBInstance();
            if (PEAR::isError($db)) {
                return $db;
            }

            return $db->raiseError(MDB2_ERROR_UNSUPPORTED, null, null,
                'mapNativeDatatype: unknown database attribute type: '.$db_type);
        }

        return array($type, $length, $unsigned, $fixed);
    }
}

?>