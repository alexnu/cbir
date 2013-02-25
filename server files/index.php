<html>
 <head>
  <style>
   a {text-decoration:none;}
   td.sample {width:440px;}
   td.add {width:330px;}
   td.results {width:790px;}
   td.right {text-align:right;}
   img.sample {
    width:70px;
    padding:2px;
    opacity:0.4;
    filter:alpha(opacity=40); /* For IE8 and earlier */
   }
   .sample img:hover
   {
    opacity:1.0;
    filter:alpha(opacity=100); /* For IE8 and earlier */
    cursor:pointer;
    padding:1px;
    border:1px solid #021a40;
   }
   img.selected
   {
    width:70px;
    padding:2px;
    opacity:1.0;
    filter:alpha(opacity=100); /* For IE8 and earlier */
   }
  </style>
 </head>
<body>

<table cellpadding="10">
 <tr>
  <th>Query Image</th>
  <th>Add Image to Database</th>
 </tr>
 <tr>
  <td>Select one of the sample images:</td>
<?
include ('functions.php');

$connection = mysql_connect($db_server["host"], $db_server["username"], $db_server["password"]);
mysql_select_db($db_server["database"], $connection);

$result = mysql_query("SELECT count(*) FROM images",$connection);
while($row = mysql_fetch_array($result))
    $imagesnum = $row[0];
    
echo "  <td>Number of images in database: ".$imagesnum."</td>\n";
?>
 </tr>
 <tr>
  <td class="sample">
<?
foreach (new DirectoryIterator('query') as $fileInfo) {
  if($fileInfo->isDot()) continue;
  if (isset($_GET["file"]) && $_GET["file"] == $fileInfo->getFilename()) $class = "selected";
  else $class = "sample";
    echo "<a href='index.php?file=".$fileInfo->getFilename()."&page=1'><img class='".$class."' src='query/".$fileInfo->getFilename()."'></a>\n";
}
?> 
  </td>
  <td>
   <input type="button" value="Load from folder"  onclick="window.open(&quot;addfolder.php&quot;,&quot;_self&quot;); window.open(&quot;addfolder.php&quot;,&quot;_self&quot;);" />
   <input type="button" value="Clear Database"  onclick="window.open(&quot;clear.php&quot;,&quot;_self&quot;); window.open(&quot;clear.php&quot;,&quot;_self&quot;);" />
  </td>
 </tr>
 <tr>
  <td>
   <form action="addsample.php" method="post" enctype="multipart/form-data">
    <label for="file">Or add new image:</label><br>
    <input type="file" name="file" id="file"><br>
    <input type="submit" name="submit" value="Submit">
   </form>
  </td>
  <td class="add">
   <form action="addimage.php" method="post" enctype="multipart/form-data">
    <label for="file">Upload:</label><br>
    <input type="file" name="file" id="file"><br>
    <input type="submit" name="submit" value="Submit">
   </form>
  </td>
 </tr>
<?
if (isset($_GET["file"]) && strlen($_GET["file"]) > 0)
{
  //start timer
  $start = microtime(true);
    
  // Parse image to get y,i,q values
  list($y_values, $i_values, $q_values) = ParseImage("query/".$_GET["file"]);

  // Dempose and trunctate
  DecomposeImage($y_values);
  $y_trunc = TruncateCoeffs($y_values, $COEFFNUM);
  DecomposeImage($i_values);
  $i_trunc = TruncateCoeffs($i_values, $COEFFNUM);
  DecomposeImage($q_values);
  $q_trunc = TruncateCoeffs($q_values, $COEFFNUM);

  // Calculate score for every image in database
  $connection = mysql_connect($db_server["host"], $db_server["username"], $db_server["password"]);
  mysql_select_db($db_server["database"], $connection);

  // Initialize scores and filenames
  $result = mysql_query("SELECT * FROM images",$connection);
  while($image = mysql_fetch_array($result)){
    $scores[$image['image_id']] = $w['Y'][0]*ABS($y_values[0][0] - $image['Y_average'])
                  + $w['I'][0]*ABS($i_values[0][0] - $image['I_average']) 
                  + $w['Q'][0]*ABS($q_values[0][0] - $image['Q_average']);
    $filenames[$image['image_id']] = $image['filename'];
  }

  // compare query coefficients with database
  for ($i = 0; $i < $COEFFNUM; $i++) {

    $query = "SELECT * FROM coeffs_y WHERE X = ".$y_trunc['x'][$i]." AND Y = ".$y_trunc['y'][$i]." AND SIGN = '".$y_trunc['sign'][$i]."'";
    $result = mysql_query($query,$connection);  
    while($coeff_y = mysql_fetch_array($result)){
      $scores[$coeff_y['image']] -= $w['Y'][bin($coeff_y['X'],$coeff_y['Y'])];
    }
  
    $query = "SELECT * FROM coeffs_i WHERE X = ".$i_trunc['x'][$i]." AND Y = ".$i_trunc['y'][$i]." AND SIGN = '".$i_trunc['sign'][$i]."'";
    $result = mysql_query($query,$connection);  
    while($coeff_i = mysql_fetch_array($result)){
      $scores[$coeff_i['image']] -= $w['I'][bin($coeff_i['X'],$coeff_i['Y'])];
    }
  
    $query = "SELECT * FROM coeffs_q WHERE X = ".$q_trunc['x'][$i]." AND Y = ".$q_trunc['y'][$i]." AND SIGN = '".$q_trunc['sign'][$i]."'";
    $result = mysql_query($query,$connection);  
    while($coeff_q = mysql_fetch_array($result)){
      $scores[$coeff_q['image']] -= $w['Q'][bin($coeff_q['X'],$coeff_q['Y'])];
    }
  }

  mysql_close($connection);
  asort($scores,SORT_NUMERIC);
  
  // paging
  if ($_GET["page"] == 1)
	$prev_page = 1;
  else
    $prev_page = $_GET["page"] - 1;
  $next_page = $_GET["page"] + 1;
  
  echo" <tr><th colspan = '2'>";
  echo "<a href='index.php?file=".$_GET["file"]."&page=".$prev_page."'><</a> ";
  echo "Results (18 first)";
  echo " <a href='index.php?file=".$_GET["file"]."&page=".$next_page."'>></a></th></tr>\n";
  echo " <tr><td colspan = '2' class='results'>\n";
  
  // show results
  $i = 0;
  foreach($scores as $key => $value){
    if ($i >= 18*($_GET["page"]-1) && $i <= (18*$_GET["page"])-1){
		echo "  <img src='images/".$filenames[$key]."'>\n";
	}
    $i++;
  }
  echo " </td></tr>\n";
  echo " <tr><td>Execution time: ".number_format(microtime(true) - $start,2). "</td>\n";
  echo " <td class='right'>Images ".(18*($_GET["page"]-1)+1)." to ".(18*$_GET["page"])."</tr>\n";
}

?>
</table>

</body>
</html>