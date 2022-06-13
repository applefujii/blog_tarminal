<?php

require_once './php/Ini.php';
require_once './php/Xml.php';

date_default_timezone_set( "Asia/Tokyo" );

function check() {
    // 処理
    $fixedData = Ini::read( "../data/FixedData.ini", true );
    if( $fixedData == false ) {
        error_log("failed reading ini file.");
        header("Location: ../error.php");
    }

    try {
        $options = [
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false
        ];
        $pdo_username = $fixedData['setting']['pdo_username'];
        $pdo_password = $fixedData['setting']['pdo_password'];
        $pdo = new PDO("sqlite:../data/blog_terminal_db.sqlite3", $pdo_username, $pdo_password, $options);

        $options = [
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false
          ];
        $pdo_username = $fixedData['setting']['pdo_username'];
        $pdo_password = $fixedData['setting']['pdo_password'];
        $pdo2 = new PDO("sqlite:../data/access_log_db.sqlite3", $pdo_username, $pdo_password, $options);
    
        //-----予約投稿-----
        $sql = "select article_id, create_datetime, flag from article";
        $ps = $pdo->prepare( $sql );
        $ps->execute();
        $rows = $ps->fetchAll();
        foreach( $rows as $row ) {
            if( $row['flag'] &= 0x01  &&  $row['create_datetime'] == "" ) {
                $sql = "DELETE FROM article WHERE article_id = :article_id";
                $ps = $pdo->prepare($sql);
                $ps->bindValue(":article_id", $row['article_id'], PDO::PARAM_INT);
                $ps->execute();
            }
        }

        //-----アクセスログのクリア-----
        $sql = "delete from access_log";
        $ps = $pdo->prepare( $sql );
        $ps->execute();

    } catch (PDOException $e) {
        error_log("PDOException: " . $e->getMessage());
        header("Location: error.php");
    }

    //sleep(5 * 60); // 5分おき

}

?>

<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0,minimum-scale=1.0,user-scalable=yes">
    <link rel="stylesheet" href="general.css">
    <title>Title</title>

</head>



<body>
<h3>削除など実行</h3>
<?php 
//check();
Xml::LogToXml();
?>
<button type="button" width="6rem" height="2rem" onclick="ShowState();">実行</button>
<div id='state'></div>

<script>
function ShowState() {
    document.getElementById('state').innerHTML = "<p>実行</p>";
}
</script>

</body>
