<?php
require_once './php/Ini.php';
$fixedData = Ini::read( "../data/FixedData.ini", true );
if( $fixedData == false ) {
  error_log("failed reading ini file.");
  header("Location: ../error.php");
}
$url = $fixedData['url']['oun_site'];
header("Location: {$url}");
?>
