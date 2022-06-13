<?php

require './php/Ini.php';

@session_start();

$fixedData = Ini::read( "../data/FixedData.ini", true );
if( $fixedData == false ) {
  error_log("failed reading ini file.");
  header("Location: ../error.php");
}

//$mode 0:初回 1:ログイン処理 2:エラー -1:ログイン済み
$mode = filter_input(INPUT_POST, "mode", FILTER_VALIDATE_INT);
if ($mode === null) {
    $mode = 0;
}
$error_flag = 0;

$user_id = "";

$return_url = (string)filter_input(INPUT_POST, "return_url");
if ($return_url === "") {
    error_log("Validate: return_url is required.");
    header("Location: error.php");
    exit();
}
if (mb_strlen($return_url) > 128 ) {
    error_log("Validate: return_url length > 128");
    header("Location: error.php");
    exit();
}


if( $mode == 0 ) {
} else if( $mode == 1  ||  $mode == 2 ) {

    $user_id = (string)filter_input(INPUT_POST, "user_id");
    if ($user_id === "") {
        error_log("Validate: user_id is required.");
        header("Location: error.php");
        exit();
    }
    if (mb_strlen($user_id) > 20 ) {
        error_log("Validate: user_id length > 20");
        header("Location: error.php");
        exit();
    }

    $password = (string)filter_input(INPUT_POST, "password");
    if ($password === "") {
        error_log("Validate: password is required.");
        header("Location: error.php");
        exit();
    }
    if (mb_strlen($password) > 20 ) {
        error_log("Validate: password length > 20");
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

        $sql = "select count(*) from account where user_id = :user_id and password = :password";
        $ps = $pdo->prepare( $sql );
        $ps->bindValue( ":user_id", $user_id, PDO::PARAM_STR );
        $ps->bindValue( ":password", $password, PDO::PARAM_STR );
        $ps->execute();
    
    } catch (PDOException $e) {
        error_log("PDOException: " . $e->getMessage());
        header("Location: ../error.php");
    }

    if( $ps->fetchColumn() != 1 ) {
        $mode = 2;
        $error_flag |= 0x01;
    }

    if( $mode == 1 ) {
        $_SESSION['user_id'] = $user_id;
        header("Location: {$return_url}");
    }

} else if ( $mode == -1 ) {
    error_log("already login.");
    header("Location: error.php");
} else {
    error_log("Validate: mode out of lange.");
    header("Location: error.php");
    exit();
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
        <h3>ログイン</h3>
        <div id="blog-edit">
            <div class="blog-entry-field">
                <?php
                if( $mode == 0  ||  $mode == 2 ) {
                    if( $error_flag & 0x01 ) echo "IDかパスワードが違います。";
                    ?>
                    <form name="login" action="./login.php" method="post">
                    <input type="hidden" name="mode" value="1">
                    <input type="hidden" name="return_url" value="<?= $return_url ?>">
                    <div class="form-group">
                        <label for="formGroupExampleInput">ID</label>
                        <input type="text" name="user_id" class="form-control" id="formGroupExampleInput" value="<?= $user_id ?>" placeholder="">
                    </div>
                    <div class="form-group">
                        <label for="formGroupExampleInput2">password</label>
                        <input type="password" name="password" class="form-control" id="formGroupExampleInput2" placeholder="">
                    </div>
                    <button type="send" class="btn btn-primary">ログイン</button>
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
