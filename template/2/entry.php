<?php

require_once '../php/Ini.php';
require_once '../php/Analytics.php';
require_once '../php/CalendarBlogLink.php';

@session_start();

$user_id = isset( $_SESSION['user_id'] ) ? $_SESSION['user_id'] : "";

$fixedData = Ini::read( "../../data/FixedData.ini", true );
if( $fixedData == false ) {
  error_log("failed reading ini file.");
  header("Location: ../error.php");
}

$uri = explode( "/", $_SERVER['REQUEST_URI'] );
$blog_owner = "$uri[1]";
$entry = 1;
$article_num = 8;
$print_article_num = 5;

$id = (int)filter_input(INPUT_GET, "id");
if ($id === "") {
    error_log("Validate: id is required.");
    header("Location: ../error.php");
    exit();
}
if (filter_var($id, FILTER_VALIDATE_INT) === false) {
  error_log("Validate: id is not int.");
  header("Location: ../error.php");
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
  $pdo = new PDO("sqlite:../../data/blog_terminal_db.sqlite3", $pdo_username, $pdo_password, $options);

  //user_idからaccount_idを取得
  $sql = "select * from account where user_id=:user_id";
  $ps = $pdo->prepare( $sql );
  $ps->bindValue(":user_id", $blog_owner, PDO::PARAM_STR);
  $ps->execute();
  $account = $ps->fetch();

  //ブログ情報を取得
  $sql = "select * from blog where account_id=:account_id";
  $ps = $pdo->prepare( $sql );
  $ps->bindValue(":account_id", $account['account_id'], PDO::PARAM_INT);
  $ps->execute();
  $blog = $ps->fetch();

  //記事情報を取得
  $sql = "select * from article where article_id=:article_id";
  $ps = $pdo->prepare( $sql );
  $ps->bindValue(":article_id", $id, PDO::PARAM_INT);
  $ps->execute();
  $article = $ps->fetch();

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

$images = explode( ' ', $article['image'] );

//アクセス解析
$analytics = array(
  new Analytics( '../../data/FixedData.ini', "top" ),
  new Analytics( '../../data/FixedData.ini', "blog-{$blog['blog_id']}" ),
  new Analytics( '../../data/FixedData.ini', "article-{$article['article_id']}" )
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
    'article_id' => $bI == 0x04 ? $article['article_id'] : 0,
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

<?php include("../../template/2/control-bar.php"); ?>

<?php include("../../template/2/jumbotron.php"); ?>

<div id="main" class="width-1200">

    <ul class="nav">
        <li class="nav-item"><a class="nav-link active" href="./">Top</a></li>
        <li class="nav-item"><a class="nav-link disabled" href="#!"><?= strip_tags($article["title"]) ?></a></li>
    </ul>

    <div id="contents">

      <div id="blog-view">
          <div class="blog-article">
            <?php
            if( $user_id == $blog_owner ) {
            ?>
              <div class="control-botton">
                <form name="delete" action="../delete.php" method="post">
                  <input type="hidden" name="article_id" value="<?= $article["article_id"] ?>"></input>
                  <button type="send" class="btn btn-secondary delete">記事削除</button>
                </form>
                <form name="edit" action="../edit.php" method="post">
                  <input type="hidden" name="article_id" value="<?= $article["article_id"] ?>"></input>
                  <button type="send" class="btn btn-primary edit">記事編集</button>
                </form>
              </div>
            <?php
            }
            $tmp = new DateTime( $article["create_datetime"] );
            $tmp->setTimezone( new DateTimeZone( 'Asia/Tokyo' ) );
            $create_datetime = explode( "-", $tmp->format( "Y-m-d-H-i-s" ) ); 
            ?>
            <p class="right"><?= "{$create_datetime[0]}/{$create_datetime[1]}/{$create_datetime[2]} {$create_datetime[3]}:{$create_datetime[4]}:{$create_datetime[5]}" ?></p>
            <div class="blog-title">
              <p class="title"><?= strip_tags($article["title"]) ?></p>
            </div>
            <div class="blog-text">
              <?php
              $article["text"] = nl2br($article["text"]);
              //※ -----タグの処理-----
              //[/i-] 画像タグ置換
              for( $i =0 ; $i <count($images) ; $i++ ) {
                $ii = $i +1;
                $article["text"] = str_replace( "/i{$ii}", "<a href='" . "./image/{$images[$i]}" . "'><img src='" . "./image/{$images[$i]}" . "'></a>", $article["text"] );
              }
              //[/list]をリンクに置換
              $list = "";
              $tag_id = 1;
              $dom = new DOMDocument();
              $dom->encoding = 'UTF-8';
              $dom->loadHTML( mb_convert_encoding($article["text"], 'HTML-ENTITIES', 'utf-8') );
              $xpath = new DOMXPath($dom);
              $list .= "<div class='list'><ul>";
              foreach( $xpath->query( "//span[@class='list1']" ) as $pNode ) {
                $nodeList = $xpath->query( ".//h1", $pNode );
                $node = $nodeList[0];
                $list .= "<li><a href='#jump{$tag_id}'>" . $node->nodeValue . PHP_EOL . "</a></li>";
                $tag_id++;

                $list .= "<ul>";
                foreach( $xpath->query( ".//span[@class='list2']", $pNode ) as $pNode2 ) {
                  $nodeList2 = $xpath->query( ".//h2", $pNode2 );
                  $node2 = $nodeList2[0];
                  $list .= "<li><a href='#jump{$tag_id}'>" . $node2->nodeValue . PHP_EOL . "</a></li>";
                  $tag_id++;

                  $list .= "<ul>";
                  $nodeList3 = $xpath->query( ".//h3", $pNode2 );
                  foreach( $nodeList3 as $node3 ) {
                    $list .= "<li><a href='#jump{$tag_id}'>" . $node3->nodeValue . PHP_EOL . "</a></li>" ;
                    $tag_id++;
                  }
                  $list .= "</ul>";
                }
                $list .= "</ul>";
              }
              $list .= "</div></ul>";
              $list = nl2br( $list );
              $article["text"] = str_replace( "/list", $list, $article["text"] );
              ?>
              <p><?= $article["text"] ?></p>
            </div>
            <?php /* 画像テスト用
            <div class='test'>
            <?php foreach( $images as $image ) { ?>
              <p><img src="./image/<?= $image ?>" width='200px' height='200px'></p>
            <?php } ?>
            </div> */?>
            <div class="blog-tag">
            <?php
            foreach( preg_split('/ +/u', $article['tag'], -1, PREG_SPLIT_NO_EMPTY) as $t ) {
              echo "<a href='./index.php?tag={$t}'>#{$t}</a>";
            }
            ?>
            </div>
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

</div>

<?php include( "../../template/2/footer.html" ); ?>

</body>

</html>
