<?php

@session_start();

$return_url = (string)filter_input(INPUT_POST, "return_url");
if ($return_url === "") {
    //$return_url = "./"
    error_log("Validate: return_url is required.");
    header("Location: error.php");
    exit();
}
if (mb_strlen($return_url) > 128 ) {
    error_log("Validate: return_url length > 128");
    header("Location: error.php");
    exit();
}

$_SESSION = [];
setcookie(session_name(), '', time() - 3600, "/");
session_unset();
session_destroy();
header("Location: {$return_url}");

?>
