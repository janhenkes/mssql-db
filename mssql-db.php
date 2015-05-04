<?php
define( 'OBJECT', 'OBJECT', true );
define( 'OBJECT_K', 'OBJECT_K' );
define( 'ARRAY_A', 'ARRAY_A' );
define( 'ARRAY_N', 'ARRAY_N' );

class mssqldb {
	/**
	 * The PHP extension which will be used for the MSSQL connection
	 *
	 * @var string
	 */
	var $php_mssql_extension = '';

	/**
	 * The last error during query.
	 *
	 * @var string
	 */
	var $last_error = '';

	/**
	 * Amount of queries made
	 *
	 * @access private
	 * @var int
	 */
	var $num_queries = 0;

	/**
	 * Database Username
	 *
	 * @access protected
	 * @var string
	 */
	protected $dbuser;

	/**
	 * Database Password
	 *
	 * @access protected
	 * @var string
	 */
	protected $dbpassword;

	/**
	 * Database Name
	 *
	 * @access protected
	 * @var string
	 */
	protected $dbname;

	/**
	 * Database Host
	 *
	 * @access protected
	 * @var string
	 */
	protected $dbhost;

	/**
	 * Database Port
	 *
	 * @access protected
	 * @var string
	 */
	protected $dbport;

	/**
	 * Database Handle
	 *
	 * @access protected
	 * @var string
	 */
	protected $dbh;

	/**
	 * Whether the database queries are ready to start executing.
	 *
	 * @access private
	 * @var bool
	 */
	var $ready = false;

	/**
	 * Last query made
	 *
	 * @access private
	 * @var array
	 */
	var $last_query;

	/**
	 * Results of the last query made
	 *
	 * @access private
	 * @var array|null
	 */
	var $last_result;

	/**
	 * MySQL result, which is either a resource or boolean.
	 *
	 * @access protected
	 * @var mixed
	 */
	protected $result;

	/**
	 * Saved info on the table column
	 *
	 * @access protected
	 * @var array
	 */
	protected $col_info;

	/**
	 * Count of rows returned by previous query
	 *
	 * @access private
	 * @var int
	 */
	var $num_rows = 0;

	/**
	 * Count of affected rows by previous query
	 *
	 * @access private
	 * @var int
	 */
	var $rows_affected = 0;

	/**
	 * The ID generated for an AUTO_INCREMENT column by the previous query (usually INSERT).
	 *
	 * @access public
	 * @var int
	 */
	var $insert_id = 0;

	const EXT_SQLSRV = 'sqlsrv';
	const EXT_MSSQL = 'mssql';

	/**
	 * Connects to the database server and selects a database
	 *
	 * PHP5 style constructor for compatibility with PHP5. Does
	 * the actual setting up of the class properties and connection
	 * to the database.
	 *
	 * @param string $dbuser MySQL database user
	 * @param string $dbpassword MySQL database password
	 * @param string $dbname MySQL database name
	 * @param string $dbhost MySQL database host
	 * @param string $dbport MySQL database port
	 */
	function __construct( $dbuser, $dbpassword, $dbname, $dbhost, $dbport ) {
		register_shutdown_function( array( $this, '__destruct' ) );

		$this->dbuser = $dbuser;
		$this->dbpassword = $dbpassword;
		$this->dbname = $dbname;
		$this->dbhost = $dbhost;
		$this->dbport = $dbport;

		$this->set_extension();
		$this->db_connect();
	}

	/**
	 * PHP5 style destructor and will run when database object is destroyed.
	 *
	 * @see mssqldb::__construct()
	 * @return bool true
	 */
	function __destruct() {
		return true;
	}

	function set_extension() {
		if ( strtoupper( substr( PHP_OS, 0, 3 ) ) === 'WIN' && function_exists( "sqlsrv_connect" ) ) {
			$this->php_mssql_extension = self::EXT_SQLSRV;
		} else if ( function_exists( "mssql_connect" ) ) {
			$this->php_mssql_extension = self::EXT_MSSQL;
		}
	}

	/**
	 * Connect to and select database
	 */
	function db_connect() {
		if ( $this->php_mssql_extension == self::EXT_SQLSRV ) {
			$this->dbh = sqlsrv_connect( $this->dbhost . ( $this->dbport ? ', ' . $this->dbport : "" ), array( "Database" => $this->dbname, "UID" => $this->dbuser, "PWD" => $this->dbpassword ) );

			if ( !$this->dbh ) {
				// TODO proper log error
				var_dump( sqlsrv_errors() );
				exit;
			}
		} else if ( $this->php_mssql_extension == self::EXT_MSSQL ) {
			$this->dbh = mssql_connect( $this->dbhost . ( $this->dbport ? ':' . $this->dbport : "" ), $this->dbuser, $this->dbpassword );

			if ( !$this->dbh ) {
				// TODO proper log error
				die( 'Unable to connect!' );
			}

			if ( !mssql_select_db( $this->dbname, $this->dbh ) ) {
				// TODO proper log error
				die( 'Unable to select database!' );
			}
		}

		$this->ready = true;
	}

	/**
	 * Kill cached query results.
	 *
	 * @return void
	 */
	function flush() {
		$this->last_result = array();
		$this->col_info    = null;
		$this->last_query  = null;
		$this->rows_affected = $this->num_rows = 0;
		$this->last_error  = '';

		/*if ( is_resource( $this->result ) )
			mysql_free_result( $this->result );*/
	}

	/**
	 * Perform a MSSQL database query, using current database connection.
	 *
	 * More information can be found on the codex page.
	 *
	 * @param string $query Database query
	 * @return int|false Number of rows affected/selected or false on error
	 */
	function query( $query ) {
		if ( ! $this->ready )
			return false;

		$return_val = 0;
		$this->flush();

		if ( preg_match( '/^\s*(insert|replace)\s/i', $query ) && $this->php_mssql_extension == self::EXT_SQLSRV ) {
			$query .= "; SELECT SCOPE_IDENTITY() AS IDENTITY_COLUMN_NAME";
		}

		// Keep track of the last query for debug..
		$this->last_query = $query;

		switch ( $this->php_mssql_extension ) {
			case self::EXT_MSSQL :

				$this->result = @mssql_query( $query, $this->dbh );

				$this->num_queries++;

				// If there is an error then take note of it..
				// TODO set last error to mssql_error here
				if ( $this->last_error = false ) {
					// Clear insert_id on a subsequent failed insert.
					if ( $this->insert_id && preg_match( '/^\s*(insert|replace)\s/i', $query ) )
						$this->insert_id = 0;

					// TODO log error
					return false;
				}

				break;
			case self::EXT_SQLSRV :

				$this->result = @sqlsrv_query( $this->dbh, $query );

				$this->num_queries++;

				// If there is an error then take note of it..
				if( $this->result === false ) {
					if ( ( $this->last_error = sqlsrv_errors() ) != null ) {
						// Clear insert_id on a subsequent failed insert.
						if ( $this->insert_id && preg_match( '/^\s*(insert|replace)\s/i', $query ) )
							$this->insert_id = 0;

						// TODO log error
						return false;
					}
				}

				break;
		}

		if ( preg_match( '/^\s*(create|alter|truncate|drop)\s/i', $query ) ) {
			$return_val = $this->result;
		} elseif ( preg_match( '/^\s*(insert|delete|update|replace)\s/i', $query ) ) {
			switch ( $this->php_mssql_extension ) {
				case self::EXT_MSSQL :
					$this->rows_affected = mssql_rows_affected ( $this->dbh );
					break;
				case self::EXT_SQLSRV :
					$this->rows_affected = sqlsrv_rows_affected ( $this->result );
					break;
			}
			// Take note of the insert_id
			if ( preg_match( '/^\s*(insert|replace)\s/i', $query ) ) {
				switch ( $this->php_mssql_extension ) {
					case self::EXT_MSSQL :
						$this->insert_id = $this->mssql_insert_id();
						break;
					case self::EXT_SQLSRV :
						$this->rows_affected = $this->sqlsrv_last_inserted_id( $this->result );
						break;
				}
			}
			// Return number of rows affected
			$return_val = $this->rows_affected;
		} else {
			$num_rows = 0;
			while ( $row = $this->fetch_object( $this->result ) ) {
				$this->last_result[$num_rows] = $row;
				$num_rows++;
			}

			// Log number of rows the query returned
			// and return number of rows selected
			$this->num_rows = $num_rows;
			$return_val     = $num_rows;
		}

		return $return_val;
	}

	/**
	 * Retrieve an entire SQL result set from the database (i.e., many rows)
	 *
	 * Executes a SQL query and returns the entire SQL result.
	 *
	 *
	 * @param string $query SQL query.
	 * @param string $output Optional. Any of ARRAY_A | ARRAY_N | OBJECT | OBJECT_K constants. With one of the first three, return an array of rows indexed from 0 by SQL result row number.
	 * 	Each row is an associative array (column => value, ...), a numerically indexed array (0 => value, ...), or an object. ( ->column = value ), respectively.
	 * 	With OBJECT_K, return an associative array of row objects keyed by the value of each row's first column's value. Duplicate keys are discarded.
	 * @return mixed Database query results
	 */
	function get_results( $query = null, $output = OBJECT ) {
		if ( $query )
			$this->query( $query );
		else
			return null;

		$new_array = array();
		if ( $output == OBJECT ) {
			// Return an integer-keyed array of row objects
			return $this->last_result;
		} elseif ( $output == OBJECT_K ) {
			// Return an array of row objects with keys from column 1
			// (Duplicates are discarded)
			foreach ( $this->last_result as $row ) {
				$var_by_ref = get_object_vars( $row );
				$key = array_shift( $var_by_ref );
				if ( ! isset( $new_array[ $key ] ) )
					$new_array[ $key ] = $row;
			}
			return $new_array;
		} elseif ( $output == ARRAY_A || $output == ARRAY_N ) {
			// Return an integer-keyed array of...
			if ( $this->last_result ) {
				foreach( (array) $this->last_result as $row ) {
					if ( $output == ARRAY_N ) {
						// ...integer-keyed row arrays
						$new_array[] = array_values( get_object_vars( $row ) );
					} else {
						// ...column name-keyed row arrays
						$new_array[] = get_object_vars( $row );
					}
				}
			}
			return $new_array;
		}
		return null;
	}

	/**
	 * @resource $result
	 */
	function fetch_object ( $result ) {
		if ( !is_resource( $result ) )
			return false;

		switch ( $this->php_mssql_extension ) {
			case self::EXT_MSSQL :

				return mssql_fetch_object( $result );

				break;
			case self::EXT_SQLSRV :

				return sqlsrv_fetch_object( $result );

				break;
		}
	}

	function mssql_insert_id() {
		$id = 0;
		$res = mssql_query( "SELECT @@identity AS id" );
		if ( $row = mssql_fetch_array( $res, MSSQL_ASSOC ) ) {
			$id = $row["id"];
		}
		return $id;
	}

	function sqlsrv_last_inserted_id( $query_id ) {
		sqlsrv_next_result( $query_id );
		sqlsrv_fetch( $query_id );
		return sqlsrv_get_field( $query_id, 0 );
	}
}