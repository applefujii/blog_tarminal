<?php
$cover_uri = "";
$ext = explode( '.', $blog['cover_image_name'] );
if( $blog['cover_image_name'] == "" ) {
  $cover_uri = "../image/default_cover.png";
} else {
  $cover_uri = "./image/cover.{$ext[1]}";
}
$cssJum = "
          background-image: url('{$cover_uri}'), linear-gradient(rgba(128,128,128,0.3),rgba(128,128,128,0.3)), url('{$cover_uri}');
          background-size: contain, cover, cover;
          background-repeat: no-repeat, no-repeat, no-repeat;
          background-position: center, center, center;
        ";
?>
<div class="jumbotron jumbotron-fluid width-1200" style="<?= $cssJum ?>">
    <div class="container">
      <a href="http://localhost:8000/<?= $blog_owner ?>/">
        <h1 class="display-3"><?= $blog["title"] ?></h1>
      </a>
      <a href="http://localhost:8000/<?= $blog_owner ?>/">
        <p class="lead"><?= $blog["sub_title"] ?></p>
      </a>
    </div>
</div>
