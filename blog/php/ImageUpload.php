<?php

function ImageUpload( $files, $dir="./", $save_file_name="" ) {
    $return_name="";
    if( isset($files) ) {
        if( is_array($files['name']) ) {
            $return_name = ImageUploadMultiple( $files, $dir, $save_file_name );
        } else {
            $return_name = ImageUploadSingle( $files, $dir, $save_file_name );
        }
    } else {
        error_log( '画像ファイルを入れてください' );
    }
    return $return_name;
}

function ImageUploadSingle( $file, $dir="./", $save_file_name="" ) {
$return_name = "";
    if( empty($file['name']) ) {
        $msg = 'ファイルを入れてください';
    } else {
        if( $save_file_name == "" ) {
            $photo = Date('Ymdhis');
            $photo .= uniqid(mt_rand(), false);
        } else {
            $photo = $save_file_name;
        }
        $tempfile = $file['tmp_name'];
        if( is_uploaded_file( $tempfile ) == false ) {
            $msg = 'アップロードされた画像ではありません';
        } else {
            switch (@exif_imagetype($tempfile)) {
                case 1:
                    $photo .= '.gif';
                    break;
                case 2:
                    $photo .= '.jpg';
                    break;
                case 3:
                    $photo .= '.png';
                    break;
                default:
                    header('Location: error.php');
                    exit();
            }
            $save_dir = $dir . $photo;

            if (move_uploaded_file($tempfile, $save_dir)) {
                $msg = '画像アップロード完了';
                $return_name .= $photo;
            } else {
                $msg = '画像ファイルをアップロードできませんでした';
            }
        }
    }

    error_log($msg);
    return $return_name;
}

function ImageUploadMultiple( $files, $dir="./", $save_file_name="" ) {
    $return_name = "";
    for ($i = 0 ; $i < count($files['name']) ; $i++) {
        foreach( array_keys( $files ) as $key ) {
            $file[$key] = $files[$key][$i];
        }
            $return_name .= ImageUploadSingle( $file, $dir, $save_file_name );
            if( $i != count($files['name']) -1 ) $return_name .= ' ';
    }
    return $return_name;
}

?>
