<?php

require_once 'php/Ini.php';
require_once 'php/Analytics.php';

//session_save_path("/tmp");
ini_set( 'session.gc_maxlifetime', 60 * 60 * 24 * 7 );
@session_start(['cookie_lifetime' => 60 * 60 * 24 * 7]);

$fixedData = Ini::read( "../data/FixedData.ini", true );
if( $fixedData == false ) {
  error_log("failed reading ini file.");
  header("Location: ../error.php");
}

$user_id = isset( $_SESSION['user_id'] ) ? $_SESSION['user_id'] : "";

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

  $sql = "select user_id from account";
  $ps = $pdo->prepare( $sql );
  $ps->execute();
  $blogs = $ps->fetchAll();

  //新しい記事取得
  $sql = "select ar.article_id, ar.title, ar.text_index, ar.tag, ac.user_id, ac.user_icon_name, bl.title as bl_title";
  $sql .= " from article as ar join account as ac on ar.account_id = ac.account_id join blog as bl on ar.blog_id = bl.blog_id order by ar.create_datetime desc limit 6";
  $ps = $pdo->prepare( $sql );
  $ps->execute();
  $newer_article = $ps->fetchAll();

  //たくさん見られている記事取得
  $sql2 = "select article_id, count(*) as co from access_log where not (article_id=0) group by article_id order by co desc limit 6";
  $ps = $pdo2->prepare( $sql2 );
  $ps->execute();
  $tmpId = $ps->fetchAll();
  $sql = "select ar.article_id, ar.title, ar.text_index, ar.tag, ac.user_id, ac.user_icon_name, bl.title as bl_title";
  $sql .= " from article as ar join account as ac on ar.account_id = ac.account_id join blog as bl on ar.blog_id = bl.blog_id where 1=0";
  foreach( $tmpId as $row ) $sql .= " or article_id = {$row['article_id']}";
  $sql .= " limit 6";
  $ps = $pdo->prepare( $sql );
  $ps->execute();
  $populer_article = $ps->fetchAll();
  for( $i =0 ; $i <count($populer_article) ; $i++ ) {
    foreach( $tmpId as $row2 ) {
      if( $populer_article[$i]['article_id'] == $row2['article_id'] ) {
        $populer_article[$i]['count'] = $row2["co"];
        break;
      }
    }
  }
  usort( $populer_article, function( $a, $b ){
          if( $a['count'] == $b['count'] ) return 0;
          return $a['count'] < $b['count'] ? 1 : -1;
        });

  //たくさん見られているブログ取得
  $sql2 = "select blog_id, count(*) as co from access_log where not (blog_id=0) group by blog_id order by co desc limit 6";
  $ps = $pdo2->prepare( $sql2 );
  $ps->execute();
  $tmpId = $ps->fetchAll();
  $sql = "select blog.*, account.user_id, account.user_name, account.user_icon_name from blog join account using(account_id) where 1=0";
  foreach( $tmpId as $row ) $sql .= " or blog_id = {$row['blog_id']}";
  $sql .= " limit 6";
  $ps = $pdo->prepare( $sql );
  $ps->execute();
  $populer_blog = $ps->fetchAll();
  for( $i =0 ; $i <count($populer_blog) ; $i++ ) {
    foreach( $tmpId as $row2 ) {
      if( $populer_blog[$i]['blog_id'] == $row2['blog_id'] ) {
        $populer_blog[$i]['count'] = $row2["co"];
        break;
      }
    }
  }
  usort( $populer_blog, function( $a, $b ){
          if( $a['count'] == $b['count'] ) return 0;
          return $a['count'] < $b['count'] ? 1 : -1;
        });

} catch (PDOException $e) {
  error_log("PDOException: " . $e->getMessage());
  header("Location: ../error.php");
}

//アクセス解析
$analytics = new Analytics( '../data/FixedData.ini', 'top' );
if( $analytics->unequeAccessCheck() == 1 ) {
  $tmp = new DateTime( 'now' );
  $tmp->setTimezone( new DateTimeZone( 'Asia/Tokyo' ) );
  $log_param = array(
    'account_id' => $user_id,
    'site' => 1,
    'blog_id' => 0,
    'article_id' => 0,
    'flag_unique' => true,
    'datetime' => $tmp->format( DateTimeInterface::ATOM )
  );
  $analytics->outputLog( $log_param );
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
  <div id="contents">
    <div id="top">

      <h1>Blog Tarminal</h1>

      <h3>新着記事</h3>
      <div class="pickup" id="newer-article">
        <?php
        $i = 0;
        foreach( $newer_article as $row ) {
          $row['text_index'] = strip_tags( $row['text_index'] );
          //独自のタグを取り除く
          //$row['text_index'] = preg_replace( "/\/i\d+/u", "", $row['text_index'] );
          //$row['text_index'] = preg_replace( "/\/list/u", "", $row['text_index'] );
          echo "<div class='article-index'>";
          echo "<h4><a href='./{$row['user_id']}/entry.php?id={$row['article_id']}'>{$row['title']}</a></h4>";
          echo "<p class='article-text'>{$row['text_index']}</p>";
          echo "<span class='blog-tag'>";
          foreach( preg_split('/ +/u', $row['tag'], -1, PREG_SPLIT_NO_EMPTY) as $t ) {
            echo "<a href='./search.php?tag={$t}'>#{$t}</a>";
          }
          echo "</span>";
          echo "<span class='blog-name'><a href='./{$row['user_id']}/'><img src='./image/user_icon/{$row['user_icon_name']}' class='icon'>{$row['bl_title']}</a></span>";
          echo "</div>";
          $i++;
        }
        if( $i %2 == 1 ) {
          echo "<div class='article-index' style='background-color:#0000'>";
          echo "</div>";
        }
        ?>
      </div>

      <h3>人気の記事</h3>
      <div class="pickup" id="populer-article">
        <?php
        $i = 0;
        foreach( $populer_article as $row ) {
          $row['text_index'] = strip_tags( $row['text_index'] );
          echo "<div class='article-index'>";
          echo "<h4><a href='./{$row['user_id']}/entry.php?id={$row['article_id']}'>{$row['title']}</a></h4>";
          echo "<p class='article-text'>{$row['text_index']}</p>";
          echo "<span class='blog-tag'>";
          foreach( preg_split('/ +/u', $row['tag'], -1, PREG_SPLIT_NO_EMPTY) as $t ) {
            echo "<a href='./search.php?tag={$t}'>#{$t}</a>";
          }
          echo "</span>";
          echo "<span class='blog-name'><a href='./{$row['user_id']}/'><img src='./image/user_icon/{$row['user_icon_name']}' class='icon'>{$row['bl_title']}</a></span>";
          echo "</div>";
          $i++;
        }
        if( $i %2 == 1 ) {
          echo "<div class='article-index' style='background-color:#0000'>";
          echo "</div>";
        }
        ?>
      </div>

      <h3>人気のブログ</h3>
      <div class="pickup" id="populer-blog">
      <?php
      $i = 0;
      foreach( $populer_blog as $row ) {
        $row['introduction'] = nl2br( $row['introduction'] );
        echo "<div class='blog-index'>";
        echo "<h4><a href='./{$row['user_id']}/'>{$row['title']}</a></h4>";
        echo "<p class='sub_title'>{$row['sub_title']}</p>";
        echo "<img src='./image/user_icon/{$row['user_icon_name']}' class='icon'>";
        echo "<p class='introduction'>{$row['introduction']}</p>";
        echo "</div>";
        $i++;
      }
      if( $i %2 == 1 ) {
        echo "<div class='article-index' style='background-color:#0000'>";
        echo "</div>";
      }
      ?>
      </div>
    
    <?php
    //簡易ブログ一覧
    /*
    foreach( $blogs as $row ) {
      echo "<p><a href='./" . $row['user_id'] . "/'>" . $row['user_id'] . "のブログ</a><p>";
    }
    */
    ?>
    </div>
  </div>
</div>


<footer>

<?php include( "../template/1/footer.html" ); ?>

</footer>


</body>

</html>
