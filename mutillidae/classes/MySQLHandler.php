<?php

/* read database configuration file and populate class parameters */
require_once 'includes/database-config.php';

class MySQLHandler {
    
	/**************************/
	/* Database Configuration */
	/**************************/
	/* If there is any problem connecting, it is almost always one of these values. */	
	
	/* ----------------------------------------------
	 * DATABASE HOST
	 * ----------------------------------------------
	 * This is the host/server which has the database.
	 * If using XAMPP, this is almost certainly localhost.
	 * 127.0.0.1 might work.
	 * */
    static public $mMySQLDatabaseHost = DB_HOST;

	/* ----------------------------------------------
	 * DATABASE USER NAME
	 * ----------------------------------------------
	 * This is the user name of the account on the database
	 * which OWASP Mutillidae II will use to connect. If this is set
	 * incorrectly, OWASP Mutillidae II is not going to be able to connect
	 * to the database.
	 * */	
	static public $mMySQLDatabaseUsername = DB_USERNAME;
	
	/* ----------------------------------------------
	 * DATABASE PASSWORD
	 * ----------------------------------------------
	 * This is the password of the account on the database
	 * which OWASP Mutillidae II will use to connect. If this is set
	 * incorrectly, OWASP Mutillidae II is not going to be able to connect
	 * to the database. On XAMPP, the password for user
	 * account root is typically blank.
	 * On Samurai, the $dbpass password is "samurai" rather 
	 * than blank.
	 * */
	static public $mMySQLDatabasePassword = DB_PASSWORD;
	
	/* ----------------------------------------------
	 * DATABASE NAME (NOT SERVER NAME)
	 * ----------------------------------------------
	 * This is the name of the database which will be created
	 * by the installation script. You can choose this name.
	 * */	
	static public $mMySQLDatabaseName = DB_NAME;
		
	/* ------------------------------------------
 	 * OBJECT PROPERTIES
 	 * ------------------------------------------ */	
	//default insecure: no output encoding.
	protected $encodeOutput = FALSE;
	protected $stopSQLInjection = FALSE;
	protected $mSecurityLevel = 0;
	protected $ESAPI = null;
	protected $Encoder = null;
	
	/* Helper Objects */
	protected $mCustomErrorHandler = null;
	protected $mLogHandler = null;
	
	/* MySQL Object */
	protected $mMySQLConnection = null;

	/* ------------------------------------------
 	 * STATIC PROPERTIES
 	 * ------------------------------------------ */
	public static $mDatabaseAvailableMessage = "";

	/* ------------------------------------------
	 * CONSTRUCTOR METHOD
	 * ------------------------------------------ */
	public function __construct($pPathToESAPI, $pSecurityLevel){
	    
	    $this->doSetSecurityLevel($pSecurityLevel);
	    
	    /* initialize OWASP ESAPI for PHP */
	    require_once $pPathToESAPI . 'ESAPI.php';
	    $this->ESAPI = new ESAPI($pPathToESAPI . 'ESAPI.xml');
	    $this->Encoder = $this->ESAPI->getEncoder();
	    
	    /* initialize custom error handler */
	    require_once 'CustomErrorHandler.php';
	    $this->mCustomErrorHandler = new CustomErrorHandler($pPathToESAPI, $pSecurityLevel);

	    $this->doOpenDatabaseConnection();
	    
	}// end function __construct()
	
	/* ------------------------------------------
 	 * PRIVATE METHODS
 	 * ------------------------------------------ */
	private function doSetSecurityLevel($pSecurityLevel){
		$this->mSecurityLevel = $pSecurityLevel;
		
		switch ($this->mSecurityLevel){
	   		case "0": // This code is insecure, we are not encoding output
			case "1": // This code is insecure, we are not encoding output
				$this->encodeOutput = FALSE;
				$this->stopSQLInjection = FALSE;
	   		break;
		    		
			case "2":
			case "3":
			case "4":
	   		case "5": // This code is fairly secure
	  			// If we are secure, then we encode all output.
	   			$this->encodeOutput = TRUE;
	   			$this->stopSQLInjection = TRUE;
	   		break;
	   	}// end switch		
	}// end function

	private function doOpenDatabaseConnection(){
		
		$ACCESS_DENIED = "Access denied for user";
		$USERNAME = self::$mMySQLDatabaseUsername;
		$PASSWORD = self::$mMySQLDatabasePassword;
		$SAMURAI_WTF_PASSWORD = "samurai";
		$HOSTNAME = self::$mMySQLDatabaseHost;
		
		try{
			$this->mMySQLConnection = new mysqli($HOSTNAME, $USERNAME, $PASSWORD);
			
			if (strlen($this->mMySQLConnection->connect_error) > 0) {
				/* If error is "Access denied for user", it could just be an incorrect password. On samurai
				 * the password is "samurai". Try that password. 
				 */
				if (substr_count($this->mMySQLConnection->connect_error, $ACCESS_DENIED) > 0){				
					$this->mMySQLConnection = new mysqli($HOSTNAME, $USERNAME, $SAMURAI_WTF_PASSWORD);
					if (strlen($this->mMySQLConnection->connect_error) > 0) {
						throw (new Exception("Could not connect with password '".$SAMURAI_WTF_PASSWORD."' either."));
				    }// end if
				}else{
					throw (new Exception("Database settings might be incorect."));
				}//end if    
		    }// end if
		} catch (Exception $e) {
			throw(new Exception("CRITICAL. Error attempting to open MySQL connection. Try checking the connection settings in the MySQLHandler.php class file. If there is a problem connecting, usually one of these settings is incorrect (i.e. - username, password, database name). It is also a good idea to make sure the database is running and that the web site (Mutillidae) is allowed to connect. This error was generated by public function __construct(). Tried to connect with username " . self::$mMySQLDatabaseUsername . ", password ". self::$mMySQLDatabasePassword . ", and hostname " . self::$mMySQLDatabaseHost . ". " . $this->mCustomErrorHandler->getExceptionMessage($e)));
		}// end try		
	}// end function doOpenDatabaseConnection

	private function doCloseDatabaseConnection(){

		try{
			$lResult = $this->mMySQLConnection->close();
			if (!$lResult) {
			   	throw (new Exception("Error executing query. Connection error: ".$this->mMySQLConnection->connect_errorno." - ".$this->mMySQLConnection->connect_error." Error: ".$this->mMySQLConnection->errorno." - ".$this->mMySQLConnection->error, $this->mMySQLConnection->errorno));
			}// end if
		}catch (Exception $e){
			throw(new Exception($this->mCustomErrorHandler->getExceptionMessage($e, "Error attempting to close MySQL connection.")));
		}// end try
		
	}// end public private doCloseDatabaseConnection

	private function serializeMySQLImprovedObjectProperties(){		
		$lErrorMessage = "<br /><br />";
		if (isset($this->mMySQLConnection->connect_errno)) {
			$lErrorMessage .= "connect_errno: " . $this->mMySQLConnection->connect_errno . "<br />";
		}// end if isset()
		if (isset($this->mMySQLConnection->connect_error)) {
			$lErrorMessage .= "connect_error: " . $this->mMySQLConnection->connect_error . "<br />";
		}// end if isset()
		if (isset($this->mMySQLConnection->errno)) {
			$lErrorMessage .= "errno: " . $this->mMySQLConnection->errno . "<br />";
		}// end if isset()
		if (isset($this->mMySQLConnection->error)) {
			$lErrorMessage .= "error: " . $this->mMySQLConnection->error . "<br />";
		}// end if isset()
		if (isset($this->mMySQLConnection->client_info)) {
			$lErrorMessage .= "client_info: " . $this->mMySQLConnection->client_info . "<br />";
		}// end if isset()
		if (isset($this->mMySQLConnection->host_info)) {
			$lErrorMessage .= "host_info: " . $this->mMySQLConnection->host_info . "<br /><br />";
		}// end if isset()
		return $lErrorMessage;		
	}// end private function serializeMySQLImprovedObjectProperties()
	
	private function doExecuteQuery($pQueryString){
		try {
			$lResult = $this->mMySQLConnection->query($pQueryString);
	
			if (!$lResult) {				
		    	throw (new Exception("Error executing query: ".$this->serializeMySQLImprovedObjectProperties().")"));
		    }// end if there are no results
		    
		    return $lResult;
		} catch (Exception $e) {
			throw(new Exception($this->mCustomErrorHandler->getExceptionMessage($e, "Query: " . $this->Encoder->encodeForHTML($pQueryString))));
		}// end function

	}// end private function executeQuery
	
	/* ------------------------------------------
 	 * PUBLIC METHODS
 	 * ------------------------------------------ */
	public static function databaseAvailable(){
		
		self::$mDatabaseAvailableMessage = "AVAILABLE";
		$lMySQLConnection = null;
		$UNKNOWN_DATABASE = "Unknown database";
		$ACCESS_DENIED = "Access denied for user";
		$USERNAME = self::$mMySQLDatabaseUsername;
		$PASSWORD = self::$mMySQLDatabasePassword;
		$SAMURAI_WTF_PASSWORD = "samurai";
		$HOSTNAME = self::$mMySQLDatabaseHost;
		$INCORRECT_DATABASE_CONFIGURATION_MESSAGE = "Error connecting to MySQL database on host '".$HOSTNAME."' with username '".$USERNAME."' and password '".$PASSWORD."'. First, try to reset the database (ResetDB button on menu). Next, check that the database service is running and that the database username, password, database name, and database location are configured correctly. Note: File /mutillidae/classes/MySQLHandler.php contains the database configuration.";
		$INCORRECT_DATABASE_CONFIGURATION_MESSAGE_SAMURAI = "Error connecting to MySQL database on host '".$HOSTNAME."' with username '".$USERNAME."' and password '".$PASSWORD."'. Note: In addition to the configured password '".$PASSWORD."', the password 'samurai' was tried as well. First, try to reset the database (ResetDB button on menu). Next, check that the database service is running and that the database username, password, database name, and database location are configured correctly. Note: File /mutillidae/classes/MySQLHandler.php contains the database configuration."; 
		$UNKNOWN_DATABASE_MESSAGE = "Unable to select default database " . self::$mMySQLDatabaseName. ". It appears that the database to which Mutillidae is configured to connect has not been created. Try to <a href=\"set-up-database.php\">setup/reset the DB</a> to see if that helps. Next, check that the database service is running and that the database username, password, database name, and database location are configured correctly. Note: File /mutillidae/classes/MySQLHandler.php contains the database configuration.";
		
		try{
			$lMySQLConnection = new mysqli($HOSTNAME, $USERNAME, $PASSWORD);
			if (strlen($lMySQLConnection->connect_error) > 0) {
				/* If error is "Access denied for user", it could just be an incorrect password. On samurai
				 * the password is "samurai". Try that password. 
				 */
				try {
					$lMySQLConnection = new mysqli($HOSTNAME, $USERNAME, $SAMURAI_WTF_PASSWORD);
					if (strlen($lMySQLConnection->connect_error) > 0) {
						self::$mDatabaseAvailableMessage = $INCORRECT_DATABASE_CONFIGURATION_MESSAGE_SAMURAI . " Connection error: ".$lMySQLConnection->connect_error;
						throw new Exception(self::$mDatabaseAvailableMessage);
				    }// end if
				} catch (Exception $e) {
					self::$mDatabaseAvailableMessage = $INCORRECT_DATABASE_CONFIGURATION_MESSAGE . " Connection error: ".$lMySQLConnection->connect_error;
					throw new Exception(self::$mDatabaseAvailableMessage);
				}
		    }// end if there was an error right away		    

			if(!$lMySQLConnection->select_db(self::$mMySQLDatabaseName)) {
				self::$mDatabaseAvailableMessage = $UNKNOWN_DATABASE_MESSAGE . " Connection error: ".$lMySQLConnection->connect_error;
	   			throw new Exception(self::$mDatabaseAvailableMessage);	   		
			}//end if

			$lResult = $lMySQLConnection->query("SELECT 'test connection';");
			if(!$lResult){
				self::$mDatabaseAvailableMessage = "Failed to execute test query on MySQL database but we appear to be connected " . $lMySQLConnection->error."<br /><br />First, try to reset the database (ResetDB button on menu)<br /><br />Check if the database configuration is correct. If the system made it this far, the username and password are probably correct. Perhaps the database name is wrong.<br /><br />";
	   			throw new Exception(self::$mDatabaseAvailableMessage);	   		
			}// end if

			$lResult = $lMySQLConnection->query("SELECT cid FROM blogs_table;");
			if(!$lResult){
				self::$mDatabaseAvailableMessage = "Failed to execute test query on blogs_table in the MySQL database but we appear to be connected " . $lMySQLConnection->error."<br /><br />First, try to reset the database (ResetDB button on menu)<br /><br />The blogs table should exist in the ".self::$mMySQLDatabaseName." database if the database configuration is correct. If the system made it this far, the username and password are probably correct. Perhaps the database name is wrong.<br /><br />";
	   			throw new Exception(self::$mDatabaseAvailableMessage);	   		
			}// end if
			
			$lMySQLConnection->close();

		} catch (Exception $e) {
			self::$mDatabaseAvailableMessage = "Failed to connect to MySQL database. " . $e->getMessage();
   			throw new Exception(self::$mDatabaseAvailableMessage);	   	
		}// end try

		return TRUE;
		
	} //end	public function databaseAvailable(){

	public function connectToDefaultDatabase(){
		$this->mMySQLConnection->select_db(self::$mMySQLDatabaseName);
	}//end function
	
	public function setSecurityLevel($pSecurityLevel){
		$this->doSetSecurityLevel($pSecurityLevel);
	}// end function
	
	public function getSecurityLevel(){
		return $this->mSecurityLevel;
	}// end function
	
	public function openDatabaseConnection(){
		$this->doOpenDatabaseConnection();
	}// end function

	public function escapeDangerousCharacters($pString){
		return $this->mMySQLConnection->real_escape_string($pString);
	}//end function

	public function affected_rows(){
		return $this->mMySQLConnection->affected_rows;
	}//end function

	public function executeQuery($pQueryString){
		return $this->doExecuteQuery($pQueryString);
	}// end public function executeQuery
	
	public function closeDatabaseConnection(){
		$this->doCloseDatabaseConnection();
	}// end public function closeDatabaseConnection
	
}// end class