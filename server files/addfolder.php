<?

include('functions.php');

$folder = new DirectoryIterator('images');
$total = 0;
foreach ($folder as $fileInfo) {
  if($fileInfo->isDot()) continue;
  if(isInDatabase($fileInfo->getFilename())) continue;
  if (!isset($nextfile)) $nextfile = $fileInfo->getFilename();
  $total++;
}

if ($total > 0){
  echo "<p>Found ".$total." unprocessed images</p>\n";
  echo "<p>Processing image ".$nextfile."..</p>\n";

  //start timer
  $start = microtime(true);

  ProcessImage($nextfile);
  $end = microtime(true) - $start;

  echo "<p>Done!</p>\n";
  echo "<p>Process time: ".number_format($end,2). " secs</p>";
}
else{
  echo "<p>No unprocessed images found</p>\n";
}

function isInDatabase($filename) {
    global $db_server;

    $connection = mysql_connect($db_server["host"], $db_server["username"], $db_server["password"]);
    mysql_select_db($db_server["database"], $connection);

    $result = mysql_query("SELECT count(*) FROM images WHERE filename='".$filename."'",$connection);
    while($row = mysql_fetch_array($result))
        $imagesnum = $row[0];

    if ($imagesnum > 0) return true;
    else return false;
}

?>
<input type="button" value="Next"  onclick="window.open(&quot;addfolder.php&quot;,&quot;_self&quot;); window.open(&quot;addfolder.php&quot;,&quot;_self&quot;);" />
<input type="button" value="Back"  onclick="window.open(&quot;index.php&quot;,&quot;_self&quot;); window.open(&quot;index.php&quot;,&quot;_self&quot;);" />