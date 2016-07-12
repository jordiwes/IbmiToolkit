<?php
namespace ToolkitApi;

use ToolkitApi\TransportInterface;

/**
 * Class PDOTransport
 *
 * @package ToolkitApi
 */
class PDOTransport implements TransportInterface
{
    private $last_errorcode;
    private $last_errormsg;
    public static $transportType = 'PDO';



    public function __construct($databaseNameOrResource, $i5NamingFlag = '0', $user = '', $password = '', $isPersistent = false)
    {
        if (is_resource($databaseNameOrResource)) {
            $conn = $databaseNameOrResource;

        } else {
            $databaseName = $databaseNameOrResource;

            if ($this->isDebug()) {
                $this->debugLog("Creating a new db connection at " . date("Y-m-d H:i:s") . ".\n");
                $this->execStartTime = microtime(true);
            }

            $conn = $this->connect($databaseName, $user, $password, array('persistent'=>$this->getIsPersistent()));

            if ($this->isDebug()) {
                $durationCreate = sprintf('%f', microtime(true) - $this->execStartTime);
                $this->debugLog("Created a new db connection in $durationCreate seconds.");
            }

            if (!$conn) {
                // Note: SQLState 08001 (with or without SQLCODE=-30082) usually means invalid user or password. This is true for DB2 and ODBC.
                $sqlState = $this->getErrorCode();
                $this->error = $this->getErrorMsg();

                $this->debugLog("\nFailed to connect. sqlState: $sqlState. error: $this->error");
                throw new \Exception($this->error, (int)$sqlState);
            }
        }
        return $conn;
    }


    /**
     * 
     * @todo should perhaps handle this method differently if $options are not passed
     *
     * @param $database
     * @param $user
     * @param $password
     * @param null $options
     * @return bool|resource
     */
    public function connect($database, $user, $password, $options = null)
    {
        $host = 'ibm:' . $database;

        $options = [

            PDO::ATTR_PERSISTENT => FALSE,

            PDO::ATTR_ERRMODE    => PDO::ERRMODE_EXCEPTION,

            PDO::ATTR_AUTOCOMMIT => 1,

        ];


        try {
            $this->conn = new \PDO($host, $user, $password, $options);
        } catch (PDOException $e) {
            print "Error!: " . $e->getMessage() . "<br/>";
        }

    }

    /**
     * @param $conn
     */
    public function disconnect()
    {
        $this->conn = null;
    }

    public function getConnection()
    {
        return $this->conn;
    }

    /**
     * set error code and message based on last odbc connection/prepare/execute error.
     * 
     * @todo: consider using GET DIAGNOSTICS for even more message text:
     * http://publib.boulder.ibm.com/infocenter/iseries/v5r4/index.jsp?topic=%2Frzala%2Frzalafinder.htm
     * 
     * @param null $conn
     */
    protected function setError($conn = null)
    {
        // is conn resource provided, or do we get last error?
        if ($conn) {
            $this->setErrorCode(odbc_error($conn));
            $this->setErrorMsg(odbc_errormsg($conn));
        } else {
            $this->setErrorCode(odbc_error());
            $this->setErrorMsg(odbc_errormsg());
        }
    }

    /**
     * @param $errorCode
     */
    protected function setErrorCode($errorCode)
    {
        $this->last_errorcode = $errorCode;
    }

    /**
     * @return string
     */
    public function getErrorCode()
    {
        return $this->last_errorcode;
    }

    /**
     * @param $errorMsg
     */
    protected function setErrorMsg($errorMsg)
    {
        $this->last_errormsg = $errorMsg;
    }

    /**
     * @return string
     */
    public function getErrorMsg()
    {
        return $this->last_errormsg;
    }
    
    /**
     * this function used for special stored procedure call only
     * 
     * @param $conn
     * @param $stmt
     * @param $bindArray
     * @return string
     */
    public function execXMLStoredProcedure($conn, $stmt, $bindArray)
    {
        $crsr = $conn->prepare($conn, $stmt);
        
        if (!$crsr) { 
            $this->setError($conn);
            return false;
        }




        // stored procedure takes four parameters. Each 'name' will be bound to a real PHP variable
        $params = array(
            array('position' => 1, 'name' => "internalKey", 'inout' => PDO::PARAM_INPUT),
            array('position' => 2, 'name' => "controlKey",  'inout' => PDO::PARAM_INPUT),
            array('position' => 3, 'name' => "inputXml",    'inout' => PDO::PARAM_INPUT),
            array('position' => 4, 'name' => "outputXml",   'inout' => PDO::PARAM_OUPUT),
        );

        // bind the four parameters
        foreach ($params as $param) {
            $ret = $crsr->bindParam ($param['position'], $$param['name'], $param['inout']);
            if (!$ret) {
                // unable to bind a param. Set error and exit
                $this->setStmtError($crsr);
                return false;
            }
        }

        // extension problem: sends warning message into the php_log or stdout
        // about number of result sets. (switch on return code of SQLExecute() 
        // SQL_SUCCESS_WITH_INFO
        if (!@$crsr->execute()) {
            $this->setError($conn);
            return "PDO Error code: " . $this->getErrorCode() . ' msg: ' . $this->getErrorMsg();
        }
        

        
        return $outputXML;
    }

    /**
     * @param $conn
     * @param $stmt
     * @return array
     */
    public function executeQuery($conn, $stmt)
    {
        $txt = array();
        $crsr = $conn->exec($conn, $stmt);
        
        if (is_resource($crsr)) {      
            while ($row = $stmt->fetch($crsr)) {
                $txt[]=  $row;
            }
        } else {
            $this->setError($conn);
        }
        
        return $txt;
    }

    
}
