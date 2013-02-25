<?

//------------------//
// Function Library //
//------------------//

// Process Image
function ProcessImage($imgFile)
{
	global $db_server,$COEFFNUM;
	
	// Parse image
	list ($y_values, $i_values, $q_values) = ParseImage("images/".$imgFile);

	// Dempose and trunctate
	DecomposeImage($y_values);
	$y_trunc = TruncateCoeffs($y_values, $COEFFNUM);
	DecomposeImage($i_values);
	$i_trunc = TruncateCoeffs($i_values, $COEFFNUM);
	DecomposeImage($q_values);
	$q_trunc = TruncateCoeffs($q_values, $COEFFNUM);

	// connect to database
	$connection = mysql_connect($db_server["host"], $db_server["username"], $db_server["password"]);
	mysql_select_db($db_server["database"], $connection);

	// save image file
	$imageid = InsertFileToDB($imgFile,$y_values[0][0],$i_values[0][0],$q_values[0][0],$connection);
	
	// save y-coeffs
	InsertCoeffsToDB("coeffs_y",$y_trunc,$imageid);

	// save i-coeffs
	InsertCoeffsToDB("coeffs_i",$i_trunc,$imageid);

	// save q-coeffs
	InsertCoeffsToDB("coeffs_q",$q_trunc,$imageid);

	mysql_close($connection);
}

// Get uploaded image
function UploadImage($location)
{

	//echo "Upload: " . $_FILES["file"]["name"] . "<br>";
    //echo "Type: " . $_FILES["file"]["type"] . "<br>";
    //echo "Size: " . ($_FILES["file"]["size"] / 1024) . " kB<br>";
    //echo "Temp file: " . $_FILES["file"]["tmp_name"] . "<br>";

	// check for errors
	if ($_FILES["file"]["error"] > 0)
    {
      exit("Return Code: " . $_FILES["file"]["error"] . "<br>");
    }
	
	// get uploaded image
	$filepart = implode(explode(".",$_FILES["file"]["name"],-1));
	$explosion = explode(".", $_FILES["file"]["name"]);
	foreach ($explosion as $i){
		$extension = $i;
	}
	$extension = strtolower($extension);
	
	// size checks
	$image = imagecreatefromjpeg($_FILES["file"]["tmp_name"]);
	$image_width = imagesx($image);
	$image_height = imagesy($image);
	if ($image_height != 128 || $image_width != 128)
	{
		exit("Size must be 128x128");
	}

	// check type and extension
	$allowedExts = array("jpg", "jpeg", "gif", "png");
	if ((($_FILES["file"]["type"] != "image/gif")
		&& ($_FILES["file"]["type"] != "image/jpeg")
		&& ($_FILES["file"]["type"] != "image/png")
		&& ($_FILES["file"]["type"] != "image/pjpeg"))
	|| ($_FILES["file"]["size"] > 100000)
	|| !in_array($extension, $allowedExts))
	{
		exit("Invalid file");
	}

	// rename if necessary
	$filename = $filepart.".".$extension;
    while (file_exists($location."/" . $filename))
    {
		$filename = $filepart."_".mt_rand(11,99).".".$extension;
    }

	// all OK, copy image
    move_uploaded_file($_FILES["file"]["tmp_name"], $location . "/" . $filename);
	return $filename;
	
}

// Parse image
function ParseImage($imgFile)
{
	// read image
	$image = imagecreatefromjpeg($imgFile);
	$image_width = imagesx($image);
	$image_height = imagesy($image);

	// iterate through x axis
	for ($x = 0; $x < $image_width; $x++) {

		// iterate through y axis
		for ($y = 0; $y < $image_height; $y++) {

			// look at current pixel
			$rgb = imagecolorat($image, $x, $y);
			$r = (($rgb >> 16) & 0xFF) / 255;
			$g = (($rgb >> 8) & 0xFF) / 255;
			$b = ($rgb & 0xFF) / 255;
		
			// get YIQ values
			$y_values[$x][$y] = 0.299*$r + 0.587*$g + 0.114*$b; 
			$i_values[$x][$y] = 0.596*$r - 0.275*$g - 0.321*$b; 
			$q_values[$x][$y] = 0.212*$r - 0.523*$g + 0.311*$b; 	
		}
	}
	return array($y_values, $i_values, $q_values);
}

// insert file to database
function InsertFileToDB($imgFile,$y_average,$i_average,$q_average,$connection)
{

	$query = "INSERT INTO images VALUES(NULL,'".$imgFile."',".$y_average.",".$i_average.",".$q_average.")";

	$result = mysql_query($query);
	if (!$result) die('Invalid query: ' . mysql_error());
		
	$result = mysql_query("SELECT LAST_INSERT_ID()",$connection);
	while($row = mysql_fetch_array($result))
		$last_id = $row[0];

	return $last_id;
}

// save coefficients
function InsertCoeffsToDB($dbtable,$coefftable,$imageid)
{
	global $COEFFNUM;
	
	$query = "INSERT INTO ".$dbtable." VALUES";
	for ($i = 0; $i < $COEFFNUM; $i++) {
		if ($i>0) $query.=",";
		$query.="(".$coefftable['x'][$i].",".$coefftable['y'][$i].",'".$coefftable['sign'][$i]."',".$imageid.")";
	}
	$result = mysql_query($query);
	if (!$result) die('Invalid query: ' . mysql_error());
}

// Transpose table
function transpose($array)
{
    array_unshift($array, null);
    return call_user_func_array('array_map', $array);
}

// Decompose Array
function DecomposeArray(&$array)
{
	//get length
    $h = count($array);
	
	// initialize array
	for ($x = 0; $x < $h; $x++) {
		$array[$x] = $array[$x] / sqrt($h);
	}
	
	// do the transformation
	while ($h > 1) {
		$h = $h /2;
		for ($i = 0; $i < $h; $i++) {
			$arraynew[$i]    = ($array[2*$i] + $array[2*$i+1])/sqrt(2);
			$arraynew[$h+$i] = ($array[2*$i] - $array[2*$i+1])/sqrt(2);
		}
		// copy arraynew to array
		for ($i = 0; $i < 2*$h; $i++) {
			$array[$i] = $arraynew[$i];
		}
	}
}

// Decompose Image
function DecomposeImage(&$array)
{
	//get length
    $rows = count($array);
	
	// decompose rows
	for ($x = 0; $x < $rows; $x++) {
		DecomposeArray($array[$x]);
	}
	
	// transpose matrix
	$array = transpose($array);
	
	// decompose rows again
	for ($x = 0; $x < $rows; $x++) {
		DecomposeArray($array[$x]);
	}
	
	// transpose matrix back
	$array = transpose($array);
}

// Truncate Coefficients - return array: x,y,sign
function TruncateCoeffs($multi_array,$m)
{
	list ($abs_array, $sign_array) = TableToArrays($multi_array);
	arsort($abs_array);
	
	$i = 0;
	foreach ($abs_array as $key => $value){
		if ($i==$m)
			break;
			
		$coord = explode(",", $key);
			
		$trunc['x'][] = $coord[0];
		$trunc['y'][] = $coord[1];
		$trunc['sign'][] = $sign_array[$key];
		
		$i++;
	}
	return $trunc;
}

// Convert multi-dimensional array to array
function TableToArrays($multi)
{
	foreach ($multi as $x => $array){
		foreach ($array as $y => $value){
			$key = $x.",".$y;
			$abs[$key] = abs($value);
			$sign[$key] = ($value > 0 ? "+" : "-");
		}
	}
	return array ($abs, $sign);
}

// Bin
function bin($i,$j)
{
	return min(max($i,$j),5);
}

// Database Variables

$db_server["host"] = "localhost"; //database server
$db_server["username"] = "root"; // DB username
$db_server["password"] = ""; // DB password
$db_server["database"] = "cbir";// database name


// Constant Variables

$COEFFNUM = 40;
$w['Y'] = array( 5.00, 0.83, 1.01, 0.52, 0.47, 0.30);
$w['I'] = array(19.21, 1.26, 0.44, 0.53, 0.28, 0.14);
$w['Q'] = array(34.37, 0.36, 0.45, 0.14, 0.18, 0.27);

?>