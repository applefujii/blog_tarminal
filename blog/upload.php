<?php

require './php/ImageUpload.php';

date_default_timezone_set( "Asia/Tokyo" );
@session_start();

$user_id = isset( $_SESSION['user_id'] ) ? $_SESSION['user_id'] : "";

if( $_SESSION['edit_image'] != "" ) $_SESSION['edit_image'] .= ' ';
$_SESSION['edit_image'] .= ImageUpload( $_FILES["article_image"], "./{$user_id}/image/" );
$count = substr_count( $_SESSION['edit_image'], ' ' ) + 1;

?>

<html><head>
<script>
window.onload=function(){
 if(window.parent) {
  let textarea = window.parent.document.getElementById('text');
  textarea.value += "/i<?= $count ?>" + "\n";
 }
}
</script>
</head><body></body></html>