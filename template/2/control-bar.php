<!-- 左 -->
<nav class="navbar fixed-top navbar navbar-expand-lg navbar-dark bg-dark">
  <a class="navbar-brand" href="<?= $fixedData['url']['oun_site'] ?>">Blog Tarminal</a>
  <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
    <span class="navbar-toggler-icon"></span>
  </button>
  
  <div class="collapse navbar-collapse" id="navbarSupportedContent">
    <ul class="navbar-nav mr-auto">
      <li class="nav-item active">
        <a class="nav-link" href="<?= $fixedData['url']['oun_site'] ?>">Home<span class="sr-only">(current)</span></a>
      </li>
      <li class="nav-item active">
        <a class="nav-link" href="<?= $fixedData['url']['oun_site'] . $user_id ?>/">MyBlog</a>
      </li>
    </ul>

    <!-- 右の中心寄り -->
    <div class="center">
      <form id="search-form" class="form-inline my-2 my-lg-0" action='./index.php' method='get'>
        <div class="input-group">
          <div class="input-group-prepend">
            <select id="search-dropdown" form="" name="scope" onchange="changeSearchAction();">
              <option value="local"><?= $blog["title"] ?></option>
              <option value="tag-local">タグ -<?= $blog["title"] ?></option>
              <option value="all">全体</option>
              <option value="tag-all">タグ -全体</option>
            </select>
          </div>
          <input id="search-text" type="text" name="q" class="form-control bg-dark text-light" aria-label="Text input with dropdown button" placeholder="検索">

          <button class="btn btn-outline-success my-2 my-sm-0 search" type="submit" onclick="saveSearchState();">Search</button>
        </div>
      </form>
    </div>

    <!-- 右 -->
    <?php
    if( $user_id == "" ) {
    ?>
      <!-- ログイン 登録 -->
      <form name="login" action="../login.php" method="post">
        <input type="hidden" name="mode" value="<?= $user_id == "" ? 0 : -1 ?>"></input>
        <input type="hidden" name="return_url" value="<?= (empty($_SERVER['HTTPS']) ? 'http://' : 'https://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] ?>"></input>
        <button class="btn btn-info my-2 my-sm-0" type="send">ログイン</button>
      </form>
      <a href="../register.php"><button class="btn btn-outline-success my-2 my-sm-0" type="submit">新規登録</button></a>
    <?php
    } else {
    ?>
      <!-- 記事作成、設定、ログアウト -->
      <div class="navbar-nav">
        <a class="nav-link active" href="../edit.php">記事を書く</a>
        <?= "<a class='nav-link active' href='../setting.php'>ID:{$user_id}</a>" ?>
        <form name="logout" action="../logout.php" method="post">
          <input type="hidden" name="return_url" value="<?= (empty($_SERVER['HTTPS']) ? 'http://' : 'https://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] ?>"></input>
        <!--  <a class="nav-link active" href="../logout.php">ログアウト</a>  -->
          <button class="btn btn-secondary my-2 my-sm-0" type="send">ログアウト</button>
        </form>
      </div>
    <?php
    }
    ?>
  </div>
</nav>
<div class="navMargin"></div>

<script>

if (window.performance) {
  if (performance.navigation.type === 1) {
    // リロードされた
    deleteSearchState();
  } else {
    // リロードされていない
  }
}

window.onload = function() {
  var ss = sessionStorage.getItem('search-scope');
  var st = sessionStorage.getItem('search-word');
  if( ss != null ) {
    document.getElementById("search-dropdown").value = ss;
  }
  document.getElementById("search-text").value = st;
  changeSearchAction();
}

function changeSearchAction()
{
  var dd = document.getElementById('search-dropdown').value;
  if( dd == 'local' ) {
    document.getElementById('search-form').setAttribute("action","./index.php");
    document.getElementById('search-text').setAttribute("name","q");
  } else if( dd == 'tag-local' ) {
    document.getElementById('search-form').setAttribute("action","./index.php");
    document.getElementById('search-text').setAttribute("name","tag");
  } else if( dd == 'all' ) {
    document.getElementById('search-form').setAttribute("action","../search.php");
    document.getElementById('search-text').setAttribute("name","q");
  } else if( dd == 'tag-all' ) {
    document.getElementById('search-form').setAttribute("action","../search.php");
    document.getElementById('search-text').setAttribute("name","tag");
  }
}

function saveSearchState() {
  var ss = document.getElementById('search-dropdown').value;
  var st = document.getElementById("search-text").value;
  sessionStorage.setItem('search-scope', ss);
  sessionStorage.setItem('search-word', st);
}

function deleteSearchState() {
  sessionStorage.removeItem('search-scope');
  sessionStorage.removeItem('search-word');
}

</script>
