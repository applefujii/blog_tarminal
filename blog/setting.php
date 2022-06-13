<?php

require './php/Ini.php';
require './php/ImageUpload.php';

date_default_timezone_set( "Asia/Tokyo" );
@session_start();

$fixedData = Ini::read( "../data/FixedData.ini", true );
if( $fixedData == false ) {
  error_log("failed reading ini file.");
  header("Location: ../error.php");
}

$user_id = isset( $_SESSION['user_id'] ) ? $_SESSION['user_id'] : "";

if( $user_id == "" ) {
    error_log("not login.");
    header("Location: ../error.php");
    exit();
}

//$mode 0:初回 1:ブログ登録処理 2:ユーザー登録処理 11:ブログ登録処理エラー
$mode = filter_input(INPUT_POST, "mode", FILTER_VALIDATE_INT);
if ($mode === null) {
    $mode = 0;
}

$error_flag = filter_input(INPUT_POST, "error_flag", FILTER_VALIDATE_INT);
if( $error_flag === null ) $error_flag = 0;

$title = "";
$sub_title = "";
$introduction = "";
$css = "";
$sql_flag_cover = false;
$sql_flag_icon = false;
$userName = "";

try {
    $options = [
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false
    ];
    $pdo_username = $fixedData['setting']['pdo_username'];
    $pdo_password = $fixedData['setting']['pdo_password'];
    $pdo = new PDO("sqlite:../data/blog_terminal_db.sqlite3", $pdo_username, $pdo_password, $options);
  
    $sql = "select * from account where user_id=:user_id";
    $ps = $pdo->prepare( $sql );
    $ps->bindValue( ':user_id', $user_id );
    $ps->execute();
    $account = $ps->fetch();

    $sql = "select * from blog where account_id=:account_id";
    $ps = $pdo->prepare( $sql );
    $ps->bindValue( ':account_id', $account['account_id'] );
    $ps->execute();
    $blog = $ps->fetch();

} catch (PDOException $e) {
  error_log("PDOException: " . $e->getMessage());
  header("Location: ../error.php");
}

if( $mode == 0  ||  $mode >= 11 ) {
  $css = array_search( $blog["css"], $fixedData['css'] );
} else if( $mode == 1 ) {
  $title = filter_input(INPUT_POST, "title");
  $sub_title = filter_input(INPUT_POST, "sub_title");
  $introduction = filter_input(INPUT_POST, "introduction");
  if( isset( $_FILES['cover_image']['size'] ) ) {
    if( $_FILES['cover_image']['size'] != 0 ) $sql_flag_cover = true;
  }
  $css = $fixedData['css'][filter_input(INPUT_POST, "theme")];

  if( mb_strlen( $title ) < 5 ) {
    $mode = 11;
    $error_flag |= 0x01;
  }
  if( mb_strlen( $title ) > 20 ) {
    $mode = 11;
    $error_flag |= 0x02;
  }
  if( mb_strlen( $sub_title ) > 32 ) {
    $mode = 11;
    $error_flag |= 0x10;
  }
  if( mb_strlen( $introduction ) > 512 ) {
    $mode = 11;
    $error_flag |= 0x100;
  }
} else if( $mode == 2 ) {
  $userName = filter_input(INPUT_POST, "user_name");
  if( isset( $_FILES['user_icon']['size'] ) ) {
    if( $_FILES['user_icon']['size'] != 0 ) $sql_flag_icon = true;
  }
  if( mb_strlen( $userName ) == 0 ) {
    $mode = 12;
    $error_flag |= 0x1000;
  }
  if( mb_strlen( $userName ) > 20 ) {
    $mode = 12;
    $error_flag |= 0x2000;
  }
} else {
  error_log("Validate: mode out of lange.");
  header("Location: error.php");
  exit();
}

if( $mode == 1 ) {
  if( $sql_flag_cover ) {
    $cover_image_name = ImageUpload( $_FILES["cover_image"], "./{$user_id}/image/", 'cover' );
  }
  try {
      $sql = "update blog set title=:title, sub_title=:sub_title, introduction=:introduction";
      if( $sql_flag_cover ) $sql .= ", cover_image_name=:cover_image_name";
      $sql .= ", css=:css where blog_id=:blog_id";
      $ps = $pdo->prepare( $sql );
      $ps->bindValue( ":title", $title, PDO::PARAM_STR );
      $ps->bindValue( ":sub_title", $sub_title, PDO::PARAM_STR );
      $ps->bindValue( ":introduction", $introduction, PDO::PARAM_STR );
      if( $sql_flag_cover ) {
        $ps->bindValue( ":cover_image_name", $cover_image_name, PDO::PARAM_STR );
      }
      $ps->bindValue( ":css", $css, PDO::PARAM_STR );
      $ps->bindValue( ":blog_id", $blog['blog_id'], PDO::PARAM_INT );
      $ps->execute();
  } catch (PDOException $e) {
      error_log("PDOException: " . $e->getMessage());
      header("Location: ../error.php");
  }

  header("Location: ./{$user_id}/");
  
} else if( $mode == 2 ) {
  if( $sql_flag_icon ) {
    $user_icon_name = ImageUpload( $_FILES["user_icon"], './image/user_icon/', $user_id );
  }
  try {
    $sql = "update account set user_name=:user_name";
    if( $sql_flag_icon ) $sql .= ", user_icon_name=:user_icon_name";
    $sql .= " where user_id=:user_id";
    $ps = $pdo->prepare( $sql );
    $ps->bindValue( ":user_name", $userName, PDO::PARAM_STR );
    if( $sql_flag_icon ) $ps->bindValue( ":user_icon_name", $user_icon_name, PDO::PARAM_STR );
    $ps->bindValue( ":user_id", $user_id, PDO::PARAM_STR );
    $ps->execute();
  } catch (PDOException $e) {
    error_log("PDOException: " . $e->getMessage());
    header("Location: ../error.php");
  }
  header("Location: ./{$user_id}/");
}

if($mode==11) $css = strstr($css, '.css', true);
?>


<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0,minimum-scale=1.0,user-scalable=yes">
    <link rel="stylesheet" href="general.css">
    <link rel="stylesheet" href="tabs.css">
    <script src='./js/previewImage.js'></script>
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


<div id="main" class="width-1200">

    <ul class="nav">
        <li class="nav-item"><a class="nav-link active" href="./">Top</a></li>
        <li class="nav-item"><a class="nav-link disabled" href="#!">アカウント設定</a></li>
    </ul>

    <div id="contents">
      <div id="blog-edit">

          <div class="tabs">
            <input id="account_manage" type="radio" name="tab_item" <?= $mode != 11 ? 'checked' : '' ?>>
            <label class="tab_item" for="account_manage">アカウント設定</label>
            <input id="blog_manage" type="radio" name="tab_item" <?= $mode == 11 ? 'checked' : '' ?>>
            <label class="tab_item" for="blog_manage">ブログ設定</label>

            <!-- アカウント設定タブ -->
            <div class="tab_content" id="account_manage_content">
              <div class="tab_content_description">
              <?php
              if( $error_flag & 0x1000 ) echo "<p>ユーザーネームを入力してください</p>";
              if( $error_flag & 0x2000 ) echo "<p>ユーザーネームが長すぎます。</p>";
              ?>
              <form name="setting" enctype="multipart/form-data" action="./setting.php" method="post">
                  <input type="hidden" name="mode" value="2">
                  <div class="form-group">
                    <label for="formGroupExampleInput">ユーザーネーム ※20文字以内</label>
                    <input type="text" name="user_name" class="form-control" id="formGroupExampleInput" value="<?= $userName=="" ? $account['user_name'] : $userName ?>" placeholder="">
                  </div>
                  <div class="form-group">
                    <label for="formGroupExampleInput2">ユーザーアイコン 推奨512x512px</label>
                    <input type="file" name="user_icon" class="form-control-file" id="exampleFormControlFile1" accept="image/*" onchange="previewImage(this, 'preview', true, '120px', '120px');">
                    <p id="preview"></p>
                  </div>
                  <button type="send" class="btn btn-primary">更新</button>
                </form>
              </div>
            </div>

            <!-- ブログ設定タブ -->
            <div class="tab_content" id="blog_manage_content">
              <div class="tab_content_description">
                <?php
                if( $error_flag & 0x01 ) echo "<p>タイトルが短すぎます。</p>";
                if( $error_flag & 0x02 ) echo "<p>タイトルが長すぎます。</p>";
                if( $error_flag & 0x10 ) echo "<p>サブタイトルが長すぎます。</p>";
                if( $error_flag & 0x100 ) echo "<p>紹介文が長すぎます。</p>";
                ?>
                <form name="setting" enctype="multipart/form-data" action="./setting.php" method="post">
                  <input type="hidden" name="mode" value="1">
                  <div class="form-group">
                      <label for="formGroupExampleInput">ブログタイトル</label>
                      <input type="text" name="title" class="form-control" id="formGroupExampleInput" value="<?= $title=="" ? $blog['title'] : $title ?>" placeholder="">
                  </div>
                  <div class="form-group">
                      <label for="formGroupExampleInput">サブタイトル</label>
                      <input type="text" name="sub_title" class="form-control" id="formGroupExampleInput" value="<?= $sub_title=="" ? $blog['sub_title'] : $sub_title ?>" placeholder="">
                  </div>
                  <div class="form-group">
                      <label for="formGroupExampleInput2">紹介文</label>
                      <textarea name="introduction" class="form-control introduction" id="formGroupExampleInput2" placeholder=""><?= $introduction=="" ? $blog['introduction'] : $introduction ?></textarea>
                  </div>
                  <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" id="inlineRadio1a" value="dark" name="theme"<?= $css=='dark' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="inlineRadio1a">ダーク</label>
                  </div>
                  <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" id="inlineRadio1b" value="light" name="theme"<?= $css=='light' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="inlineRadio1b">ライト</label>
                  </div>

                  <div class="form-group">
                      <label for="formGroupExampleInput2">カバー画像 推奨1200x300px</label>
                      <input type="file" name="cover_image" class="form-control-file" id="exampleFormControlFile1" accept="image/*" onchange="previewImage(this, 'preview2', true, '200px', '50px');">
                      <p id="preview2"></p>
                  </div>
                  <button type="send" class="btn btn-primary">更新</button>
                </form>
              </div>
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

</html>
