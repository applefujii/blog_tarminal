<?php

require_once 'php/Ini.php';

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
  header("Location: ./");
}
$edit_mode = 0;     //0:新規作成 1:編集

$article_id = filter_input(INPUT_POST, "article_id", FILTER_VALIDATE_INT);
if ($article_id === null) {
  $edit_mode = 0;
} else {
  $edit_mode = 1;
}

if( isset( $_SESSION['edit_image'] ) ) {
  $images = explode( ' ', $_SESSION['edit_image'] );
  foreach( $images as $image ) {
    $tmp = unlink( $user_id . '/image/' . $image );
  }
  $_SESSION['edit_image'] = null;
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

  //ブログ情報
  $sql = "select * from blog where account_id=?";
  $ps = $pdo->prepare( $sql );
  $ps->bindValue( 1, $account['account_id'], PDO::PARAM_INT );
  $ps->execute();
  $blog = $ps->fetch();

  if( $edit_mode == 0 ) {
  } else if( $edit_mode == 1) {
    //更新する場合の元記事
    $sql = "select * from article where article_id=:article_id limit 1";
    $ps = $pdo->prepare( $sql );
    $ps->bindValue(":article_id", $article_id, PDO::PARAM_INT);
    $ps->execute();
    $article = $ps->fetch();
  }

} catch (PDOException $e) {
  error_log("PDOException: " . $e->getMessage());
  header("Location: ../error.php");
}

if( $edit_mode == 1) {
  $_SESSION['edit_image'] = $article['image'];
}

?>

<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0,minimum-scale=1.0,user-scalable=yes">
    <link rel="stylesheet" href="general.css">
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
        <li class="nav-item"><a class="nav-link active" href="./<?= $user_id ?>">Top</a></li>
        <li class="nav-item"><a class="nav-link disabled" href="#!">記事作成</a></li>
    </ul>

    <div id="contents">

      <div id="blog-edit">
          <div class="blog-entry-field">
            <?php
              $t1=""; $t2=""; $t3=""; $t4="";
              if( $edit_mode == 1 ) {
                $t1 = $article["article_id"];
                $t2 = $article['title'];
                $t3 = $text = preg_replace( "/(<h[1,2,3]).*?(>)/u", '\1\2', strip_tags( $article['text'], '<b><i><u><s><h1><h2><h3>' ) );
                $t4 = $article['tag'];
              }
            ?>

            <input form="article_send" type="hidden" name="article_id" value="<?= $t1 ?>"></input>
            <input form="article_send" type="hidden" name="blog_id" value="<?= $blog['blog_id'] ?>" ></input>
            <input form="article_send" type="hidden" name="flag" value="0" ></input>
            <h5>タイトル</h5><p>※32文字まで</p>
            <input form="article_send" type="text" name="title" class="title" value="<?= $t2 ?>"></input>

            <h5>本文</h5><p>※5000文字まで</p>
            <textarea form="article_send" id="text" name="text" rows="10" class="text"><?= $t3 ?></textarea>
            <!-- タグパレット -->
            <div class="text-option">
              <div class="palette">
                <span class="palette-object" onclick="paletteAction('<h1>', '</h1>');">h1</span>
                <span class="palette-object" onclick="paletteAction('<h2>', '</h2>');">h2</span>
                <span class="palette-object" onclick="paletteAction('<h3>', '</h3>');">h3</span>
                <span class="palette-object" onclick="paletteAction('<b>', '</b>');"><b>b</b></span>
                <span class="palette-object" onclick="paletteAction('<i>', '</i>');"><i>i</i></span>
                <span class="palette-object" onclick="paletteAction('<u>', '</u>');"><u>u</u></span>
                <span class="palette-object" onclick="paletteAction('<s>', '</s>');"><s>s</s></span>
              </div>

              <iframe name="upload" src="" style="width:0px;height:0px;border:0px; display: block; background-color: lightgreen;"></iframe>
              <!-- 画像アップロード -->
              <form class="upload-image" target="upload" method="POST" action="upload.php" accept="image/*" enctype="multipart/form-data">
                <input type="hidden" name="article_id" value="<?= $t1 ?>"></input>
                <input type="file" name="article_image" accept="image/*" onchange="previewImage(this, 'preview', false, '120px', '120px');" />
                <input type="submit" value="アップロード" />
              </form>
            </div>

            <div>
              <div id="preview" class="preview"></div>

              <!-- スマートなアップロード処理(動作がおかしい)<?php/*
              <form id="image_upload" target="upload" method="POST" action="upload.php" enctype="multipart/form-data">
                <label id="image_file_label" class="input_image_file">
                  <i class="material-icons">add_image</i>
                  <input type="file" name="article_image" id="input_file" accept="image/*" d onchange="execPost( 'upload.php', {'article_id':<?= $t1 ?>}, 'image_upload' ); previewImage(this, 'preview', false, '50px', '50px');" style="display: none;"></input>
                </label>
              </form>  */?>-->
            </div>

            <h5>タグ</h5><p>※1つ16文字まで スペース区切りで複数入力</p>
            <input form="article_send" type="text" name="tag" class="tag" value="<?= $t4 ?>"></input><br />

            <form id="article_send" name="entry" action="<?= $edit_mode==0 ? 'create.php' : 'update.php' ?>" method="post" enctype='multipart/form-data'>
              <button type="send" class="btn btn-primary send"><?= $edit_mode == 0 ? '記事作成' : '記事更新' ?></button>
            </form>
          </div>
      </div>

        <div id="sidebar">
            <h4>ヘルプ</h4>
            <hr color="#ffffff" align="left">
            <p>タグは<?= htmlentities( '<h1><h2><h3><b><i><u><s>' ) ?>が使えます。</p>
            <p>/i1、/i2… 画像に置き換わります。</p>
            <p>/list 見出し一覧に置き換わります。</p>
        </div>

    </div>

</div>

<footer>

<?php include( "../template/1/footer.html" ); ?>

</footer>

</body>

<script src='./js/previewImage.js'></script>
<script async src="https://cdn.jsdelivr.net/npm/exif-js"></script>
<script src='./js/execPost.js'></script>
<script>
function paletteAction( insStrStart, insStrEnd="" ) {
  var area = document.getElementById('text');
  var text = area.value;
  area.value = text.substr(0, area.selectionStart)
			+ insStrStart
			+ text.substr(area.selectionStart, area.selectionEnd - area.selectionStart)
			+ insStrEnd
			+ text.substr(area.selectionEnd);
}
</script>

</html>
