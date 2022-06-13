<?php

require './php/Ini.php';
require './php/ImageUpload.php';

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

$tmp = new Datetime( 'now', new DateTimeZone('UTC') );
$update_datetime = $tmp->format( DateTimeInterface::ATOM );

$article_id = filter_input(INPUT_POST, "article_id");
if ($article_id === null) {
    error_log("Validate: article_id is required.");
    header("Location: error.php");
    exit();
}

$blog_id = filter_input(INPUT_POST, "blog_id");
if ($blog_id === null) {
    error_log("Validate: blog_id is required.");
    header("Location: error.php");
    exit();
}

$title = (string)filter_input(INPUT_POST, "title");
if ($title === "") {
    error_log("Validate: title is required.");
    header("Location: error.php");
    exit();
}
if (mb_strlen($title) > 32) {
    error_log("Validate: title length > 32");
    header("Location: error.php");
    exit();
}

$text = (string)filter_input(INPUT_POST, "text");
if ($text === "") {
    error_log("Validate: text is required.");
    header("Location: error.php");
    exit();
}
if (mb_strlen($text) > 5000) {
    error_log("Validate: title length > 5000");
    header("Location: error.php");
    exit();
}

$tag = (string)filter_input(INPUT_POST, "tag");
if (mb_strlen($tag) > 255) {
    error_log("Validate: tag length > 255");
    header("Location: error.php");
    exit();
}

//htmlタグ削除
$title = strip_tags( $title );
//$text = strip_tags( $text, ['b','i','u','s','h1','h2'] );     //第2引数の除外が機能しない
$text = strip_tags( $text, '<b><i><u><s><h1><h2><h3>' );
$tag = strip_tags( $tag );
//[/i]タグの前後が改行でなければ改行する
$text = preg_replace( "/(?<!\n)(\/i\d+)/u", "\n" . '${1}', $text );
$text = preg_replace( "/(\/i\d+)(?!\n)/u", '${1}' . "\n", $text );
//$text = preg_replace( "/[^\r\n{\r\n}](\/i\d+)/u", "\n" . '${1}', $text );
//$text = preg_replace( "/(\/i\d+)[^\r\n{\r\n}]/u", '${1}' . "\n", $text );
//$text = mb_ereg_replace( "(?![\n\r])/i(\d+)", "\n/\1", $text );
//$text = mb_ereg_replace( "/(\d+)(?![\n\r])", "/\1\n", $text );
//[h1,h2にidを振る]
$num = 1;
$text = preg_replace_callback(
                        '/<h1|<h2|<h3/u', 
                        function($matches) use(&$num){
                            $re = $matches[0] . " id='jump{$num}' class='jump'";
                            $num++;
                            return $re;
                        }, 
                        $text
                    );
$aNum = [0, 0];
$aFlag = [false, false];
$text = preg_replace_callback(
                        '/<h([1,2,3])(.*?)>/u', 
                        function($matches) use(&$aNum, &$aFlag){
                            $re = "";
                            $num = null;
                            switch( $matches[1] ) {
                                case 1:
                                    $aFlag[1-1] = true;
                                    $num = &$aNum[1-1];
                                    break;
                                case 2:
                                    $aFlag[2-1] = true;
                                    $num = &$aNum[2-1];
                                    break;
                                case 3:
                                    return $matches[0];
                            }
                            $num++;
                            if( $num == 1 ) {
                                $re = "<span class='list{$matches[1]}'>" . $matches[0];
                            } else {
                                for( $i=$matches[1] ; $i<=1 ; $i++ ) {     //追加が必要になった時用
                                    if( $aFlag[$i] ) {
                                        $re .= "</span>";
                                        $aFlag[$i] = false;
                                        $aNum[$i] = 0;
                                    }
                                }
                                $re .= "</span><span class='list{$matches[1]}'>" . $matches[0];
                            }
                            return $re;
                        }, 
                        $text
                    );
if( $aFlag[0] ) $text .= "</span>";
if( $aFlag[1] ) $text .= "</span>";
//全角スペースを半角スペースに変換
$tag = mb_convert_kana( $tag, 's' );
//タグ被りだったら消す
$tags = array(); 
foreach( preg_split('/ +/u', $tag, -1, PREG_SPLIT_NO_EMPTY) as $row ) {
    if( in_array( $row, $tags ) ) continue;
    $tags[] = $row;
}
$tag = implode( ' ', $tags );

$flag = filter_input(INPUT_POST, "flag");
if ($flag === null) {
    error_log("Validate: flag is required.");
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

    //アカウント情報
    $sql = "select * from account where user_id=?";
    $ps = $pdo->prepare( $sql );
    $ps->bindValue( 1, $user_id, PDO::PARAM_STR );
    $ps->execute();
    $account = $ps->fetch();

    //記事のタグ取得
    $sql = "SELECT tag FROM article WHERE article_id = :article_id";
    $ps = $pdo->prepare($sql);
    $ps->bindValue(":article_id", $article_id, PDO::PARAM_INT);
    $ps->execute();
    $articleTags = explode( ' ',$ps->fetchColumn() );

    $sql = "update article set account_id=:account_id, blog_id=:blog_id, update_datetime=:update_datetime, title=:title, text_index=:text_index, text=:text, image=:image, tag=:tag, flag=:flag where article_id=:article_id";
    $ps = $pdo->prepare($sql);
    $ps->bindValue(":article_id", $article_id);
    $ps->bindValue(":account_id", $account['account_id']);
    $ps->bindValue(":blog_id", $blog_id);
    $ps->bindValue(":update_datetime", $update_datetime, PDO::PARAM_STR);
    $ps->bindValue(":title", $title, PDO::PARAM_STR);
    $ps->bindValue(":text_index", mb_substr($text, 0, 512, 'utf8'), PDO::PARAM_STR);
    $ps->bindValue(":text", $text, PDO::PARAM_STR);
    $ps->bindValue(":image", $_SESSION['edit_image'], PDO::PARAM_STR);
    $ps->bindValue(":tag", $tag, PDO::PARAM_STR);
    $ps->bindValue(":flag", $flag);
    $ps->execute();

    //-----タグの変動をtag,link_tag_blogテーブルに反映-----
    $diff = array_diff( $tags, $articleTags );      //1にあって2にない
    //追加
    foreach( $diff as $d ) {
        if( $d == "" ) continue;
        error_log("add:".$d);
        addTag( $pdo, $blog_id, $d );
    }
    $diff = array_diff( $articleTags, $tags );
    //削除
    foreach( $diff as $d ) {
        if( $d == "" ) continue;
        error_log("sub:".$d);
        subTag( $pdo, $blog_id, $d );
    }

    header("Location: {$user_id}/");
} catch (PDOException $e) {
    error_log("PDOException: " . $e->getMessage());
    header("Location: error.php");
}

$_SESSION['edit_image'] = null;


function addTag( $pdo, $blog_id, $tagName ) {
    //新規タグか
    $sql = "select * from tag where name=?";
    $ps = $pdo->prepare( $sql );
    $ps->bindValue( 1, $tagName, PDO::PARAM_STR );
    $ps->execute();
    $tmpTag = $ps->fetch();

    //if 新規タグ
    if( $tmpTag === false ) {
        error_log( "新規タグ" );
        //タグのデータベースに追加
        $sql = "insert into tag (name, count) values (:name, :count)";
        $ps = $pdo->prepare($sql);
        $ps->bindValue(":name", $tagName, PDO::PARAM_STR);
        $ps->bindValue(":count", 1, PDO::PARAM_INT);
        $ps->execute();

        //追加したタグのtag_idを取得
        $sql = "select tag_id from tag where name=?";
        $ps = $pdo->prepare( $sql );
        $ps->bindValue( 1, $tagName, PDO::PARAM_STR );
        $ps->execute();
        $tagId = $ps->fetchColumn();

        //link_tag_blogに情報をセット
        $sql = "insert into link_tag_blog (tag_id, blog_id, count) values (:tag_id, :blog_id, :count)";
        $ps = $pdo->prepare($sql);
        $ps->bindValue(":blog_id", $blog_id, PDO::PARAM_INT);
        $ps->bindValue(":tag_id", $tagId, PDO::PARAM_INT);
        $ps->bindValue(":count", 1, PDO::PARAM_INT);
        $ps->execute();
    }
    //else 既にあるタグ
    else {
        error_log( "既にあるタグ" );
        //タグのカウントを+
        $sql = "update tag set count=:count where tag_id=:tag_id";
        $ps = $pdo->prepare($sql);
        $ps->bindValue(":count", ++$tmpTag['count'], PDO::PARAM_INT);
        $ps->bindValue(":tag_id", $tmpTag['tag_id'], PDO::PARAM_INT);
        $ps->execute();
        
        //ブログとタグがリンクされているか
        $sql = "select * from link_tag_blog where blog_id=? and tag_id=?";
        $ps = $pdo->prepare( $sql );
        $ps->bindValue( 1, $blog_id, PDO::PARAM_INT );
        $ps->bindValue( 2, $tmpTag['tag_id'], PDO::PARAM_INT );
        $ps->execute();
        $targetLinkTagBlog = $ps->fetch();

        //if ブログとタグがリンクされていなければ
        if( $targetLinkTagBlog['count'] === false ) {
            error_log( "ブログとタグがリンクされていなければ" );
            //ブログとタグをリンクする
            $sql = "insert into link_tag_blog (tag_id, blog_id, count) values (:tag_id, :blog_id, :count)";
            $ps = $pdo->prepare($sql);
            $ps->bindValue(":blog_id", $blog_id, PDO::PARAM_INT);
            $ps->bindValue(":tag_id", $tmpTag['tag_id'], PDO::PARAM_INT);
            $ps->bindValue(":count", 1, PDO::PARAM_INT);
            $ps->execute();
        }
        //else ブログとタブがリンクされていれば
        else {
            error_log( "ブログとタブがリンクされていれば" );
            //ブログに使われている回数のカウントを進める
            $sql = "update link_tag_blog set count=:count where tag_id=:tag_id";
            $ps = $pdo->prepare($sql);
            $ps->bindValue(":count", ++$targetLinkTagBlog['count'], PDO::PARAM_INT);
            $ps->bindValue(":tag_id", $tmpTag['tag_id'], PDO::PARAM_INT);
            $ps->execute();
        }
    }
}

function subTag( $pdo, $blog_id, $tagName ) {
    //tag.count 取得
    $sql = "SELECT count FROM tag WHERE name = :name";
    $ps = $pdo->prepare($sql);
    $ps->bindValue(":name", $tagName, PDO::PARAM_STR);
    $ps->execute();
    $count = $ps->fetchColumn();

    //link_tag_blog 取得
    $sql = "SELECT ltb.tag_id AS ltb_tag_id, ltb.count AS ltb_count FROM link_tag_blog AS ltb JOIN tag ON ltb.tag_id=tag.tag_id WHERE ltb.blog_id=:blog_id AND tag.name = :name";
    $ps = $pdo->prepare($sql);
    $ps->bindValue(":blog_id", $blog_id, PDO::PARAM_INT);
    $ps->bindValue(":name", $tagName, PDO::PARAM_STR);
    $ps->execute();
    $row = $ps->fetch();

    $sql = "UPDATE tag SET count=:count WHERE name=:name";
    $ps = $pdo->prepare($sql);
    $ps->bindValue(":count", --$count, PDO::PARAM_INT);
    $ps->bindValue(":name", $tagName, PDO::PARAM_STR);
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
        $ps->bindValue(":name", $tagName, PDO::PARAM_STR);
        $ps->execute();
    }
    if( $row['ltb_count'] <= 0 ) {
        $sql = "DELETE FROM link_tag_blog where tag_id=:tag_id";
        $ps = $pdo->prepare($sql);
        $ps->bindValue(":tag_id", $row['ltb_tag_id'], PDO::PARAM_STR);
        $ps->execute();
    }
}

?>
