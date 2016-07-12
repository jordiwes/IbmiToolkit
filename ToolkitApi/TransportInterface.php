<?php
namespace ToolkitApi;

/**
 * Transport interface to be implemented by all transports\
 *
 * @package ToolkitApi
 */
interface TransportInterface
{
    /**
     * Transport type.
     *
     * @var string
     * public static $transportType;
     */

    /**
     * 
     * Establish a transport connection
     *
     * @param $database
     * @param $user
     * @param $password
     * @param null $options
     * @return bool
     */
    public function connect($database, $user, $password, $options = null);

    /**
     * @param $conn
     */
    public function disconnect();

    public function getConnection();

    /**
     * @return string
     */
    public function getErrorCode();

    /**
     * @return string
     */
    public function getErrorMsg();

    /**
     * set error code and message based on last db2 prepare or execute error.
     * 
     * @todo: consider using GET DIAGNOSTICS for even more message text:
     * http://publib.boulder.ibm.com/infocenter/iseries/v5r4/index.jsp?topic=%2Frzala%2Frzalafinder.htm
     * 
     * @param null $stmt
     */

    public function execXMLStoredProcedure($conn, $sql, $bindArray);


    /**
     * returns a first column from sql stmt result set
     *
     * used in one place: iToolkitService's ReadSPLFData().
     *
     * @todo eliminate this method if possible.
     *
     * @param $conn
     * @param $sql
     * @throws \Exception
     * @return array
     */
    public function executeQuery($conn, $sql);
}
