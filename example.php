<?php
require dirname( __FILE__ ) . "/mssql-db.php";
require dirname( __FILE__ ) . "/config.inc.php";

$mssqldb = new mssqldb( $dbuser, $dbpassword, $dbname, $dbhost, $dbport );
$res = $mssqldb->get_results( "SELECT * FROM " . $table );

var_dump( $res );