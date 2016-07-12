<?php
namespace ToolkitApi;

use ToolkitApi\TransportInterface;

/**
 * Class odbcsupp
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
        $crsr = odbc_prepare($conn, $stmt);
        
        if (!$crsr) { 
            $this->setError($conn);
            return false;
        }
        
        // extension problem: sends warning message into the php_log or stdout 
        // about number of result sets. (switch on return code of SQLExecute() 
        // SQL_SUCCESS_WITH_INFO
        if (!@odbc_execute($crsr , array($bindArray['internalKey'], $bindArray['controlKey'], $bindArray['inputXml']))) {
            $this->setError($conn);
            return "ODBC error code: " . $this->getErrorCode() . ' msg: ' . $this->getErrorMsg();
        }
        
        // disconnect operation cause crush in fetch, nothing appears as sql script.
        $row='';
        $outputXML = '';
        if (!$bindArray['disconnect']) {
            while (odbc_fetch_row($crsr)) {
                $tmp = odbc_result($crsr, 1);
                
                if ($tmp) {
                    // because of ODBC problem blob transferring should execute some "clean" on returned data
                    if (strstr($tmp , "</script>")) {
                        $pos = strpos($tmp, "</script>");
                        $pos += strlen("</script>"); // @todo why append this value?
                        $row .= substr($tmp, 0, $pos);
                        break;
                    } else {
                        $row .= $tmp;
                    }
                }
            }
            $outputXML = $row;
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
        $crsr = odbc_exec($conn, $stmt);
        
        if (is_resource($crsr)) {      
            while (odbc_fetch_row($crsr)) {  
                $row = odbc_result($crsr, 1);
                
                if (!$row) {
                    break;
                }
                
                $txt[]=  $row;
            }
        } else {
            $this->setError($conn);
        }
        
        return $txt;
    }

    
}
