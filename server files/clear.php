<?
include('functions.php');

// connect to database
$connection = mysql_connect($db_server["host"], $db_server["username"], $db_server["password"]);
mysql_select_db($db_server["database"], $connection);

$query = "DELETE FROM images";
$result = mysql_query($query);
if (!$result) die('Invalid query: ' . mysql_error());

$query = "DELETE FROM coeffs_y";
$result = mysql_query($query);
if (!$result) die('Invalid query: ' . mysql_error());

$query = "DELETE FROM coeffs_i";
$result = mysql_query($query);
if (!$result) die('Invalid query: ' . mysql_error());

$query = "DELETE FROM coeffs_q";
$result = mysql_query($query);
if (!$result) die('Invalid query: ' . mysql_error());

mysql_close($connection);

header( 'Location: index.php' ) ;

?>

