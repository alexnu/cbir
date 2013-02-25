<?
include('functions.php');

// get uploaded image
$imgFile = UploadImage("images");
  
//start timer
$start = microtime(true);

// process image
ProcessImage($imgFile,$COEFFNUM);

echo "Image added successfully<br>";
echo "Execution time: ".number_format(microtime(true) - $start,2). " secs<br>";
header( "refresh:5;url=index.php" ) ;

?>

