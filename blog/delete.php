<?php

require_once './php/Ini.php';

@session_start();

$user_id = isset( $_SESSION['user_id'] ) ? $_SESSION['user_id'] : "";

if( $user_id == "" ) {
    error_log("not login.");
    header("Location: error.php");
    exit();
}

$fixedData = Ini::read( "../data/FixedData.ini", true );
if( $fixedData == false ) {
  error_log("failed reading ini file.");
  header("Location: ../error.php");
}

$article_id = filter_input(INPUT_POST, "article_id");
if ($article_id === "") {
    error_log("Validate: article_id is required.");
    header("Location: error.php");
    exit();
}
if (filter_var($article_id, FILTER_VALIDATE_INT) === false) {
    error_log("Validate: article_id is not int.");
    header("Location: error.php");
    exit();
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

    //-----削除する記事のタグ取得-----
    $sql = "SELECT blog_id, tag FROM article WHERE article_id = :article_id";
    $ps = $pdo->prepare($sql);
    $ps->bindValue(":article_id", $article_id, PDO::PARAM_INT);
    $ps->execute();
    $articleInfo = $ps->fetch();
    $articleTags = preg_split('/ +/', $articleInfo['tag'], -1, PREG_SPLIT_NO_EMPTY);

    //-----記事削除-----
    $sql = "DELETE FROM article WHERE article_id = :article_id";
    $ps = $pdo->prepare($sql);
    $ps->bindValue(":article_id", $article_id, PDO::PARAM_INT);
    $ps->execute();

    //-----tag,link_tag_blog テーブルからタグ使用回数を引く-----
    foreach( $articleTags as $rowTag ) {
        //tag.count 取得
        $sql = "SELECT count FROM tag WHERE name = :name";
        $ps = $pdo->prepare($sql);
        $ps->bindValue(":name", $rowTag, PDO::PARAM_STR);
        $ps->execute();
        $count = $ps->fetchColumn();

        //link_tag_blog 取得
        $sql = "SELECT ltb.tag_id AS ltb_tag_id, ltb.count AS ltb_count FROM link_tag_blog AS ltb JOIN tag ON ltb.tag_id=tag.tag_id WHERE ltb.blog_id=:blog_id AND tag.name = :name";
        $ps = $pdo->prepare($sql);
        $ps->bindValue(":blog_id", $articleInfo['blog_id'], PDO::PARAM_INT);
        $ps->bindValue(":name", $rowTag, PDO::PARAM_STR);
        $ps->execute();
        $row = $ps->fetch();

        $sql = "UPDATE tag SET count=:count WHERE name=:name";
        $ps = $pdo->prepare($sql);
        $ps->bindValue(":count", --$count, PDO::PARAM_INT);
        $ps->bindValue(":name", $rowTag, PDO::PARAM_STR);
        $ps->execute();

        $sql = "UPDATE link_tag_blog SET count=:count WHERE tag_id=:tag_id";
        $ps = $pdo->prepare($sql);
        $ps->bindValue(":count", --$row['ltb_count'], PDO::PARAM_INT);
        $ps->bindValue(":tag_id", $row['ltb_tag_id'], PDO::PARAM_STR);
        $ps->execute();

        //countが0以下なら削除
        if( $count <= 0 ) {
            $sql = "DELETE FROM tag WHERE name=:name";
            $ps = $pdo->prepare($sql);
            $ps->bindValue(":name", $rowTag, PDO::PARAM_STR);
            $ps->execute();
        }
        if( $row['ltb_count'] <= 0 ) {
            $sql = "DELETE FROM link_tag_blog where tag_id=:tag_id";
            $ps = $pdo->prepare($sql);
            $ps->bindValue(":tag_id", $row['ltb_tag_id'], PDO::PARAM_STR);
            $ps->execute();
        }
    }

    header("Location: {$user_id}/");
} catch (PDOException $e) {
    error_log("PDOException: " . $e->getMessage());
    header("Location: error.php");
}
