<?php

require_once './php/Ini.php';

date_default_timezone_set( "Asia/Tokyo" );
@session_start();

$user_id = isset( $_SESSION['user_id'] ) ? $_SESSION['user_id'] : "";
if( $user_id != "" ) {
    error_log("logined.");
    header("Location: ./");
}

$fixedData = Ini::read( "../data/FixedData.ini", true );
if( $fixedData == false ) {
  error_log("failed reading ini file.");
  header("Location: ../error.php");
}

//$mode 0:初回 1:登録処理 2:エラー -1:ログイン済み
$mode = filter_input(INPUT_POST, "mode", FILTER_VALIDATE_INT);
if ($mode === null) {
    $mode = 0;
}

$mail = "";
$user_id = "";
$password = "";
$password2 = "";

$error_flag = 0;


if( $mode == 0 ) {
} else if( $mode == 1 ) {

    $mail = (string)filter_input(INPUT_POST, "mail", FILTER_VALIDATE_EMAIL);
    $user_id = (string)filter_input(INPUT_POST, "user_id");
    $password = (string)filter_input(INPUT_POST, "password");
    $password2 = (string)filter_input(INPUT_POST, "password2");


    try {
        $options = [
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false
        ];
        $pdo_username = $fixedData['setting']['pdo_username'];
        $pdo_password = $fixedData['setting']['pdo_password'];
        $pdo = new PDO("sqlite:../data/blog_terminal_db.sqlite3", $pdo_username, $pdo_password, $options);

        $sql = "select count(*) from account where mail = ?";
        $ps = $pdo->prepare( $sql );
        $ps->bindValue( 1, $mail, PDO::PARAM_STR );
        $ps->execute();
        if( $ps->fetchColumn() > 0 ) {
            $mode = 2;
            $error_flag |= 0x02;
        }

        $sql = "select count(*) from account where user_id = ?";
        $ps = $pdo->prepare( $sql );
        $ps->bindValue( 1, $user_id, PDO::PARAM_STR );
        $ps->execute();
        if( $ps->fetchColumn() > 0 ) {
            $mode = 2;
            $error_flag |= 0x20;
        }
    
    } catch (PDOException $e) {
        error_log("PDOException: " . $e->getMessage());
        header("Location: ../error.php");
        exit();
    }

    if( $mail == "" ) {
        $mode = 2;
        $error_flag |= 0x01;
    } else {
        if( strpos( $mail, "@" ) == NULL  ||  strpos( $mail, "." ) == NULL  ||  strlen( $mail ) < 5  || strlen( $mail ) > 50 ) {
            $mode = 2;
            $error_flag |= 0x04;
        }
    }
    if( $user_id == "" ) {
        $mode = 2;
        $error_flag |= 0x10;
    } else {
        if( strlen($user_id) < 3 ) {
            $mode = 2;
            $error_flag |= 0x40;
        }
        if( strlen($user_id) > 20 ) {
            $mode = 2;
            $error_flag |= 0x80;
        }
    }
    if( $password == "" ) {
        $mode = 2;
        $error_flag |= 0x100;
    } else {
        if( strlen($password) < 6 ) {
            $mode = 2;
            $error_flag |= 0x200;
        }
        if( strlen($password) > 20 ) {
            $mode = 2;
            $error_flag |= 0x400;
        }
        if( $password != $password2 ) {
            $mode = 2;
            $error_flag |= 0x800;
        }
    }

} else if ( $mode == -1 ) {
    error_log("already login.");
    header("Location: ../error.php");
} else {
    error_log("Validate: mode out of lange.");
    header("Location: ../error.php");
    exit();
}

if( $mode == 1 ) {
    try {
        $sql = "insert into account (mail, user_id, password, user_name, user_icon_name, registry_datetime, flag) values (:mail, :user_id, :password, :user_name, :user_icon_name, :registry_datetime, :flag)";
        $ps = $pdo->prepare( $sql );
        $ps->bindValue( ":mail", $mail, PDO::PARAM_STR );
        $ps->bindValue( ":user_id", $user_id, PDO::PARAM_STR );
        $ps->bindValue( ":password", $password, PDO::PARAM_STR );
        $ps->bindValue( ":user_name", $user_id, PDO::PARAM_STR );
        $ps->bindValue( ":user_icon_name", 'default.png', PDO::PARAM_STR );
        $tmp = new Datetime( 'now', new DateTimeZone('UTC') );
        $ps->bindValue( ":registry_datetime", $tmp->format( DateTimeInterface::ATOM ), PDO::PARAM_STR );
        $ps->bindValue( ":flag", 0, PDO::PARAM_STR );
        $ps->execute();

        $sql = "select account_id from account where user_id=:user_id";
        $ps = $pdo->prepare( $sql );
        $ps->bindValue( ":user_id", $user_id, PDO::PARAM_STR );
        $ps->execute();
        $account_id = $ps->fetchColumn();

        $sql = "insert into blog ( account_id, title, sub_title, introduction, cover_image_name, css, mostfront_article_id, create_datetime, flag) values (:account_id, :title, :sub_title, :introduction, :cover_image_name, :css, :mostfront_article_id, :create_datetime, :flag)";
        $ps = $pdo->prepare( $sql );
        $ps->bindValue( ":account_id", $account_id );
        $ps->bindValue( ":title", "{$user_id}のブログ", PDO::PARAM_STR );
        $ps->bindValue( ":sub_title", "ブログです。", PDO::PARAM_STR );
        $ps->bindValue( ":introduction", "ブログの概要", PDO::PARAM_STR );
        $ps->bindValue( ":cover_image_name", "cover.png", PDO::PARAM_STR );
        $ps->bindValue( ":css", 'dark.css', PDO::PARAM_STR );
        $ps->bindValue( ":mostfront_article_id", null );
        $ps->bindValue( ":create_datetime", $tmp->format( DateTimeInterface::ATOM ), PDO::PARAM_STR );
        $ps->bindValue( ":flag", 0 );
        $ps->execute();
    } catch (PDOException $e) {
        error_log("PDOException: " . $e->getMessage());
        header("Location: ../error.php");
        exit();
    }

    mkdir("./{$user_id}", 0644);
    mkdir("./{$user_id}/image", 0644);
    copy( "../template/index.php", "./{$user_id}/index.php" );
    copy( "../template/entry.php", "./{$user_id}/entry.php" );
    copy( "../template/cover_template.png", "./{$user_id}/image/cover.png" );

    $_SESSION['user_id'] = $user_id;
    header("Location: ./{$user_id}/");
}

?>

<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0,minimum-scale=1.0,user-scalable=yes">
    <link rel="stylesheet" href="./general.css">
    <title>Title</title>

    <meta name="viewport"
    content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" 
    href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" 
    integrity="sha384-JcKb8q3iqJ61gNV9KGb8thSsNjpSL0n8PARn9HuZOnIxN0hoP+VmmDGMN5t9UJ0Z" 
    crossorigin="anonymous">

</head>



<body>

<!-- Optional JavaScript -->
<!-- jQuery first, then Popper.js, then Bootstrap JS -->
<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js" 
integrity="sha384-DfXdz2htPH0lsSSs5nCTpuj/zy4C+OGpamoFVy38MVBnE+IbbVYUew+OrCXaRkfj" 
crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js" 
integrity="sha384-9/reFTGAW83EW2RDu2S0VKaIzap3H66lZH81PoYlFhbGU+6BZp6G7niu735Sk7lN" 
crossorigin="anonymous"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js" 
integrity="sha384-B4gt1jrGC7Jh4AgTPSdUtOBvfO8shuf57BaghqFfPlYxofvL8/KUEfYiJOMMV+rV" 
crossorigin="anonymous"></script>

<header>
  
<?php include("../template/1/control-bar.php"); ?>

</header>


<body>

<div id="main" class="width-1200">

    <div id="contents">
        <h3>新規登録</h3>
        <div id="blog-edit">
            <div class="blog-entry-field">
                <?php
                if( $mode == 0  ||  $mode == 2 ) {
                ?>
                    <?php
                    if( $error_flag & 0x01 ) echo "<p>メールアドレスが入力されていません。</p>";
                    if( $error_flag & 0x02 ) echo "<p>そのメールアドレスは既に登録されています。</p>";
                    if( $error_flag & 0x04 ) echo "<p>メールアドレスが正しくありません。</p>";
                    if( $error_flag & 0x10 ) echo "<p>IDが入力されていません。</p>";
                    if( $error_flag & 0x20 ) echo "<p>IDが重複しています。</p>";
                    if( $error_flag & 0x40 ) echo "<p>IDが短すぎます。</p>";
                    if( $error_flag & 0x80 ) echo "<p>IDが長すぎます。</p>";
                    if( $error_flag & 0x100 ) echo "<p>パスワードが入力されていません。</p>";
                    if( $error_flag & 0x200 ) echo "<p>パスワードが短すぎます。</p>";
                    if( $error_flag & 0x400 ) echo "<p>パスワードが長すぎます。</p>";
                    if( $error_flag & 0x800 ) echo "<p>パスワードが一致していません。</p>";
                    ?>
                    <form class="register" name="register" action="./register.php" method="post">
                    <input type="hidden" name="mode" value="1">
                    <div class="form-group">
                        <label for="form-mail">Mail*</label>
                        <input type="text" name="mail" class="form-control" id="form-mail" value="<?= $mail ?>" placeholder="">
                    </div>
                    <div class="form-group">
                        <label for="form-id">ID*</label>
                        <input type="text" name="user_id" class="form-control" id="form-id" value="<?= $user_id ?>" placeholder="">
                    </div>
                    <div class="form-group">
                        <label for="form-password">password* 6～20文字</label>
                        <input type="password" name="password" class="form-control" id="form-password" placeholder="">
                    </div>
                    <div class="form-group">
                        <label for="form-password-conf">password(確認)*</label>
                        <input type="password" name="password2" class="form-control" id="form-password-conf" placeholder="">
                    </div>
                    <button type="send" class="btn btn-primary">登録</button>
                    </form>
                <?php
                }
                ?>
            </div>
        </div>

        <div id="sidebar">
          <h4>ヘルプ</h4>
          <hr color="#ffffff" align="left">
        </div> 
   </div> 

</div>


<footer>

<?php include( "../template/1/footer.html" ); ?>

</footer>

</body>
