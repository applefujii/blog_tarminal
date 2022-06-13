<?php

require_once '../php/Ini.php';
require_once '../php/Analytics.php';
require_once '../php/CalendarBlogLink.php';

@session_start();

$fixedData = Ini::read( "../../data/FixedData.ini", true );
if( $fixedData == false ) {
  error_log("failed reading ini file.");
  header("Location: ../error.php");
}
//※ファイルアップロードサイズが小さすぎる

$user_id = isset( $_SESSION['user_id'] ) ? $_SESSION['user_id'] : "";
$uri = explode( "/", $_SERVER['REQUEST_URI'] );
$blog_owner = $uri[1];
$article_num = 0;
$print_article_num = $fixedData['setting']['pages'];
$page = 1;

$page = (int)filter_input(INPUT_GET, "page");
if ($page == "") {
  $page = 1;
}
if (filter_var($page, FILTER_VALIDATE_INT) === false) {
  error_log("Validate: page is not int.");
  header("Location: ../error.php");
  exit();
}

$scope = (string)filter_input(INPUT_GET, "scope");
if ($scope === "") {
}

$searchText = (string)filter_input(INPUT_GET, "q");
if ($searchText === "") {
}
$searchText = mb_convert_kana( $searchText, 's' );
$aSearchText = explode( " ", $searchText );

$tag = (string)filter_input(INPUT_GET, "tag");
if (mb_strlen($tag) > 255) {
    error_log("Validate: tag length > 255");
    header("Location: error.php");
    exit();
}
$tag = mb_convert_kana( $tag, 's' );
$aTag = explode( " ", $tag );

$date[0] = (string)filter_input(INPUT_GET, "date");
if (mb_strlen($date[0]) > 255) {
    error_log("Validate: date[0] length > 255");
    header("Location: error.php");
    exit();
}
if( $date[0] != "" ) {
  $date[1] = substr( $date[0], 0, 4 );
  $date[2] = substr( $date[0], 4, 2 );
  $date[3] = substr( $date[0], 6, 2 );
}

//URLの後ろに追加するやつ
$urlGet = "";
$urlGet .= $searchText != '' ? "&q={$searchText}" : "";
$urlGet .= $tag != '' ? "&tag={$tag}" : "";
$urlGet .= $date[0] != '' ? "&date={$date[0]}" : "";

$tags = preg_split('/ +/', $tag, -1, PREG_SPLIT_NO_EMPTY);
//$tags = mb_convert_kana($tag, 's');
//str_replace( "\xc2\xa0", " ", $tags );
//$tags = explode( " ", $tag );


try {
  $options = [
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_EMULATE_PREPARES => false
  ];
  $pdo_username = $fixedData['setting']['pdo_username'];
  $pdo_password = $fixedData['setting']['pdo_password'];
  $pdo = new PDO("sqlite:../../data/blog_terminal_db.sqlite3", $pdo_username, $pdo_password, $options);

  //アカウント情報
  $sql = "select * from account where user_id=?";
  $ps = $pdo->prepare( $sql );
  $ps->bindValue( 1, $blog_owner, PDO::PARAM_STR );
  $ps->execute();
  $account = $ps->fetch();

  //ブログ情報
  $sql = "select * from blog where account_id=?";
  $ps = $pdo->prepare( $sql );
  $ps->bindValue( 1, $account['account_id'], PDO::PARAM_INT );
  $ps->execute();
  $blog = $ps->fetch();

  //記事の件数
  $sql = "select count(*) from article where blog_id=:blog_id";
  if( $aSearchText[0] != "" ) {
    $i = 1;
    foreach( $aSearchText as $st ) {
      //$sql .= " and CONCAT(ar.title, ' ', ar.text, ' ', ar.tag) LIKE :q{$i}";
      $sql .= " and (title LIKE :q{$i} OR text LIKE :q{$i} OR tag LIKE :q{$i})";
      $i++;
    }
  }
  if( $aTag[0] != "" ) {
    $i = 1;
    foreach( $aTag as $t ) {
      $sql .= " and tag like :tag{$i}";
      $i++;
    }
  }
  if( $date[0] != "" ) $sql .= " and create_datetime like :date";
  $sql .= " order by create_datetime desc limit $print_article_num offset :offset";
  $ps = $pdo->prepare( $sql );
  $ps->bindValue( ":blog_id", $blog['blog_id'], PDO::PARAM_INT );
  if( $aSearchText[0] != "" ) {
    $i = 1;
    foreach( $aSearchText as $st ) {
      $ps->bindValue( ":q{$i}", '%'.$st.'%', PDO::PARAM_STR );
      $i++;
    }
  }
  if( $aTag[0] != "" ) {
    $i = 1;
    foreach( $aTag as $t ) {
      $ps->bindValue( ":tag{$i}", '%'.$t.'%', PDO::PARAM_STR );
      $i++;
    }
  }
  if( $date[0] != "" ) $ps->bindValue( ":date", $date[1].'_'.$date[2].'_'.$date[3].'%', PDO::PARAM_STR );
  $ps->bindValue( ":offset", ($page -1) * $print_article_num, PDO::PARAM_INT );
  $ps->execute();
  $article_num = $ps->fetchColumn();

  //記事のヘッダー
  $sql = "select article_id, create_datetime, title, text_index, tag from article where blog_id=:blog_id";
  if( $aSearchText[0] != "" ) {
    $i = 1;
    foreach( $aSearchText as $st ) {
      //$sql .= " and CONCAT(ar.title, ' ', ar.text, ' ', ar.tag) LIKE :q{$i}";
      $sql .= " and (title LIKE :q{$i} OR text LIKE :q{$i} OR tag LIKE :q{$i})";
      $i++;
    }
  }
  if( $aTag[0] != "" ) {
    $i = 1;
    foreach( $aTag as $t ) {
      $sql .= " and tag like :tag{$i}";
      $i++;
    }
  }
  if( $date[0] != "" ) $sql .= " and create_datetime like :date";
  $sql .= " order by create_datetime desc limit $print_article_num offset :offset";
  $ps = $pdo->prepare( $sql );
  $ps->bindValue( ":blog_id", $blog['blog_id'], PDO::PARAM_INT );
  if( $aSearchText[0] != "" ) {
    $i = 1;
    foreach( $aSearchText as $st ) {
      $ps->bindValue( ":q{$i}", '%'.$st.'%', PDO::PARAM_STR );
      $i++;
    }
  }
  if( $aTag[0] != "" ) {
    $i = 1;
    foreach( $aTag as $t ) {
      $ps->bindValue( ":tag{$i}", '%'.$t.'%', PDO::PARAM_STR );
      $i++;
    }
  }
  if( $date[0] != "" ) $ps->bindValue( ":date", $date[1].'_'.$date[2].'_'.$date[3].'%', PDO::PARAM_STR );
  $ps->bindValue( ":offset", ($page -1) * $print_article_num, PDO::PARAM_INT );
  $ps->execute();
  $article_head = $ps->fetchAll();

  //最新記事を5件取得
  $sql = "select article_id, title from article where blog_id=:blog_id order by create_datetime desc limit 5";
  $ps = $pdo->prepare( $sql );
  $ps->bindValue(":blog_id", $blog['blog_id'], PDO::PARAM_INT);
  $ps->execute();
  $article_newer = $ps->fetchAll();

  //count降順にタグ名を取得
  $sql = "select tag.name as name, ltb.count as count from tag join link_tag_blog as ltb on tag.tag_id = ltb.tag_id where ltb.blog_id=:blog_id order by ltb.count desc limit 10";
  $ps = $pdo->prepare( $sql );
  $ps->bindValue(":blog_id", $blog['blog_id'], PDO::PARAM_INT);
  $ps->execute();
  $popularTag = $ps->fetchAll();

} catch (PDOException $e) {
  error_log("PDOException: " . $e->getMessage());
  header("Location: ../error.php");
}

//アクセス解析
$analytics = array(
          new Analytics( '../../data/FixedData.ini', "top" ),
          new Analytics( '../../data/FixedData.ini', "blog-{$blog['blog_id']}" )
);
$bI = 0x01;
foreach( $analytics as $ana ) {
  if( $ana->unequeAccessCheck() == 1 ) {
    $tmp = new DateTime( 'now' );
    $tmp->setTimezone( new DateTimeZone( 'Asia/Tokyo' ) );
    $log_param = array(
      'account_id' => $user_id,
      'site' => $bI == 0x01 ? 1 : 0,
      'blog_id' => $bI == 0x02 ? $blog['blog_id'] : 0,
      'article_id' => 0,
      'flag_unique' => true,
      'datetime' => $tmp->format( DateTimeInterface::ATOM )
    );
    $ana->outputLog( $log_param );
  }
  $bI <<= 1;
}

?>

<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0,minimum-scale=1.0,user-scalable=yes">
    <link rel="stylesheet" href="../css/<?= $blog['css'] ?>">
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

<?php include("../../template/2/control-bar.php"); ?>

<?php include("../../template/2/jumbotron.php"); ?>

</header>

<div id="main" class="width-1200">

    <div id="contents">
    
        <div id="blog-list">
            <?php
            $fBranch = false;
            if( $searchText != "" ) { echo "<p>検索ワード:{$searchText}</p>"; $fBranch = true; }
            if( $tag != "" ) { echo "<p>検索タグ:{$tag}</p>"; $fBranch = true; }
            if( $date[0] != "" ) { echo "<p>検索日:{$date[1]}年{$date[2]}月{$date[3]}月</p>"; $fBranch = true; }
            if( $fBranch ) echo "<p>検索結果 {$article_num}件</p>";
            foreach ($article_head as $row) {
              $row['text_index'] = strip_tags( $row['text_index'] );
              $tmp = new DateTime( $row["create_datetime"] );
              $tmp->setTimezone( new DateTimeZone( 'Asia/Tokyo' ) );
              $create_datetime = explode( "-", $tmp->format( "Y-m-d-H-i-s" ) );
            ?>
              <div class="blog-article-head">
                <p class="right">
                <?= "{$create_datetime[0]}/{$create_datetime[1]}/{$create_datetime[2]} {$create_datetime[3]}:{$create_datetime[4]}:{$create_datetime[5]}" ?>
                </p>
                <div class="blog-title">
                  <a class="no-change" href="./entry.php?id=<?= $row["article_id"] ?>"><h3><?= $row["title"] ?></h3></a>
                </div>
                <div class="blog-text">
                  <?= nl2br($row["text_index"]) ?>
                </div>
                <image src="../image/blog-article-head-gradation.png" width=100% height=150px class="gradation">
                <div class="blog-tag">
                  <?php
                  foreach( preg_split('/ +/u', $row['tag'], -1, PREG_SPLIT_NO_EMPTY) as $t ) {
                    echo "<a href='./index.php?tag={$t}'>#{$t}</a>";
                  }
                  ?>
                </div>
                <a class="view" href="./entry.php?id=<?= $row["article_id"] ?>">続きを見る</a>
              </div>
            <?php } ?>

            <div id="page-select">
              <?php
              if( $article_num != 0 ) {
                $number = 10 -1;
                $offset = 3;
                $max_page = (int)($article_num / $print_article_num) +1;
                $start = $page - $offset;
                if( $start+$number > $max_page ) $start = $max_page - $number;
                if( $start < 1 ) $start = 1;
                $end = $start+$number > $max_page ? $max_page : $start+$number;
                $n_page = $page - 1;
                if( $page != 1 ) echo "<a href='./index.php?page={$n_page}{$urlGet}'>&lt前</a>";
                foreach( range($start, $end) as $i ) {
                  if( $i==1 ) echo "&nbsp";
                  if( $i == $page ) echo "&nbsp&nbsp&nbsp{$i}&nbsp&nbsp&nbsp";
                  else echo "&nbsp&nbsp<a href='./index.php?page={$i}{$urlGet}'>{$i}</a>&nbsp&nbsp";
                  if( $i==$max_page ) echo "&nbsp";
                }
                $n_page = $page + 1;
                if( $page != $max_page ) echo "<a href='./index.php?page={$n_page}{$urlGet}'>次&gt</a>";
              }
              ?>
            </div>
        </div>

        <div id="sidebar">
        
          <div class="block">
            <h4>オーナー</h4>
            <hr color="#ffffff" align="left">
            <img src="../image/user_icon/<?= $account['user_icon_name'] ?>" width="128px", height="128px">
            <h5><?= $account["user_name"] ?></h5>
            <?php $blog["introduction"] = nl2br($blog["introduction"]); ?>
            <p><?= $blog["introduction"] ?></p>
          </div>

          <div class="block">
            <h4>最近の記事</h4>
            <hr color="#ffffff" align="left">
            <div class="newer-article">
              <?php
              foreach ($article_newer as $row) {
                echo "<a href=entry.php?id={$row['article_id']}>" . strip_tags($row['title']) . "</a>";
              }
              ?>
            </div>
          </div>

          <div class="block">
            <h4>タグ</h4>
            <hr color="#ffffff" align="left">
            <div class="newer-tag">
              <?php
                foreach ($popularTag as $row) {
                  echo "<a href=index.php?tag={$row['name']}>" . strip_tags($row['name']) . " ({$row['count']})" . "</a>";
                }
              ?>
            </div>
          </div>

          <?php
          $cal = new CalendarBlogLink( $pdo, $blog['blog_id'], 270 );
          ?>

          <div class="block">
            <hr color="#ffffff" align="left">
            <?php
            $cal->draw( -1 );
            $cal->draw( 0 );
            ?>
          </div>

        </div>
    </div>
</div>

<?php include( "../../template/2/footer.html" ); ?>

</body>

</html>
