<?php

require_once 'php/Ini.php';
require_once 'php/Analytics.php';
require_once 'php/Calendar.php';

//session_save_path("/tmp");
ini_set( 'session.gc_maxlifetime', 60 * 60 * 24 * 7 );
@session_start(['cookie_lifetime' => 60 * 60 * 24 * 7]);

$fixedData = Ini::read( "../data/FixedData.ini", true );
if( $fixedData == false ) {
  error_log("failed reading ini file.");
  header("Location: ../error.php");
}

$print_search_article_num = 20;
$page = 1;

$user_id = isset( $_SESSION['user_id'] ) ? $_SESSION['user_id'] : "";


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
error_log( "q:{$searchText}" );

$tag = (string)filter_input(INPUT_GET, "tag");
if (mb_strlen($tag) > 255) {
    error_log("Validate: tag length > 255");
    header("Location: error.php");
    exit();
}
$tag = mb_convert_kana( $tag, 's' );
$aTag = explode( " ", $tag );
error_log( "tag:{$tag}" );

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

try {
  $options = [
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_EMULATE_PREPARES => false
  ];
  $pdo_username = $fixedData['setting']['pdo_username'];
  $pdo_password = $fixedData['setting']['pdo_password'];
  $pdo = new PDO("sqlite:../data/blog_terminal_db.sqlite3", $pdo_username, $pdo_password, $options);

  //記事の件数
  $sql = "select count(*)";
  $sql .= " from article as ar join account as ac on ar.account_id = ac.account_id join blog as bl on ar.blog_id = bl.blog_id where 1=1";
  if( $aSearchText[0] != "" ) {
    $i = 1;
    foreach( $aSearchText as $st ) {
      //$sql .= " and CONCAT(ar.title, ' ', ar.text, ' ', ar.tag) LIKE :q{$i}";
      $sql .= " and (ar.title LIKE :q{$i} OR ar.text LIKE :q{$i} OR ar.tag LIKE :q{$i})";
      $i++;
    }
  }
  if( $aTag[0] != "" ) {
    $i = 1;
    foreach( $aTag as $t ) {
      $sql .= " and ar.tag like :tag{$i}";
      $i++;
    }
  }
  if( $date[0] != "" ) $sql .= " and ar.create_datetime like :date";
  $ps = $pdo->prepare( $sql );
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
  $ps->execute();
  $article_num = $ps->fetchColumn();

  //記事のヘッダー
  $sql = "select ar.article_id, ar.title, ar.text_index, ar.tag, ac.user_id, ac.user_icon_name, bl.title as bl_title";
  $sql .= " from article as ar join account as ac on ar.account_id = ac.account_id join blog as bl on ar.blog_id = bl.blog_id where 1=1";
  if( $aSearchText[0] != "" ) {
    $i = 1;
    foreach( $aSearchText as $st ) {
      //$sql .= " and CONCAT(ar.title, ' ', ar.text, ' ', ar.tag) LIKE :q{$i}";
      $sql .= " and (ar.title LIKE :q{$i} OR ar.text LIKE :q{$i} OR ar.tag LIKE :q{$i})";
      $i++;
    }
  }
  if( $aTag[0] != "" ) {
    $i = 1;
    foreach( $aTag as $t ) {
      $sql .= " and ar.tag like :tag{$i}";
      $i++;
    }
  }
  if( $date[0] != "" ) $sql .= " and ar.create_datetime like :date";
  $sql .= " order by create_datetime desc limit $print_search_article_num offset :offset";
  $ps = $pdo->prepare( $sql );
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
  $ps->bindValue( ":offset", ($page -1) * $print_search_article_num, PDO::PARAM_INT );
  $ps->execute();
  $article_head = $ps->fetchAll();

} catch (PDOException $e) {
  error_log("PDOException: " . $e->getMessage());
  header("Location: ../error.php");
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

    <div id="search">

        <div class="index">
          <h3>検索結果</h3>
          <select class="order" name=”order”>
            <option value=”newer”>新着順</option>
          </select>

        <?php
        $fBranch = false;
        if( $searchText != "" ) echo "<p>検索ワード:{$searchText}</p>"; $fBranch = true;
        if( $tag != "" ) echo "<p>検索タグ:{$tag}</p>"; $fBranch = true;
        if( $date[0] != "" ) echo "<p>検索日:{$date[1]}年{$date[2]}月{$date[3]}月</p>"; $fBranch = true;
        if( $fBranch ) echo "<p>検索結果 {$article_num}件</p>";
        ?>
        </div>
        
        <div class="pickup">
            <?php
            $i = 0;
            foreach( $article_head as $row ) {
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

        <div id="page-select">
            <?php
            if( $article_num != 0 ) {
            $number = 10 -1;
            $offset = 3;
            $max_page = (int)($article_num / $print_search_article_num) +1;
            $start = $page - $offset;
            if( $start+$number > $max_page ) $start = $max_page - $number;
            if( $start < 1 ) $start = 1;
            $end = $start+$number > $max_page ? $max_page : $start+$number;
            $n_page = $page - 1;
            if( $page != 1 ) echo "<a href='./search.php?page={$n_page}{$urlGet}'>&lt前</a>";
            foreach( range($start, $end) as $i ) {
                if( $i==1 ) echo "&nbsp";
                if( $i == $page ) echo "&nbsp&nbsp&nbsp{$i}&nbsp&nbsp&nbsp";
                else echo "&nbsp&nbsp<a href='./search.php?page={$i}{$urlGet}'>{$i}</a>&nbsp&nbsp";
                if( $i==$max_page ) echo "&nbsp";
            }
            $n_page = $page + 1;
            if( $page != $max_page ) echo "<a href='./search.php?page={$n_page}{$urlGet}'>次&gt</a>";
            }
            ?>
        </div>

    </div>

  </div>
</div>


<footer>

<?php include( "../template/1/footer.html" ); ?>

</footer>

</body>

</html>
