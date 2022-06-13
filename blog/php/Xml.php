<?php

require_once 'Ini.php';

class Xml {

    static public function LogToXml() {
        $aCount = [];
        $fixedData = Ini::read( "../data/FixedData.ini", true );
        if( $fixedData == false ) {
        error_log("failed reading ini file.");
        header("Location: ../error.php");
        }

        try {
            $options = [
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_EMULATE_PREPARES => false
            ];
            $pdo_username = $fixedData['setting']['pdo_username'];
            $pdo_password = $fixedData['setting']['pdo_password'];
            $pdo = new PDO("sqlite:../data/access_log_db.sqlite3", $pdo_username, $pdo_password, $options);

            $sql = "select * from access_log";
            $ps = $pdo->prepare( $sql );
            $ps->execute();
            $logs = $ps->fetchAll();

        } catch (PDOException $e) {
            error_log("PDOException: " . $e->getMessage());
        }


        //-----XMLに出力-----
        $ELEMENTS = [ 'account_id', 'site', 'blog_id', 'article_id', 'flag_unique', 'datetime'];
        $dom = new DOMDocument();
        $dom->encoding = 'UTF-8';
        $dom->formatOutput = true;

        $root = $dom->createElement('access_log');
        $root = $dom->appendChild($root);

        //集計
        $aCount = [
            'site' => ['count' => 0],
            'blog' => ['count' => 0],
            'article' => ['count' => 0]
        ];
        foreach( $logs as $row ) {
            $dt = new Datetime( $row['datetime'] );
            $dt->setTimezone( new DateTimeZone( 'Asia/Tokyo' ) );
            $dt = explode( "-", $dt->format( "Y-n-j" ) );
            $dt = array_combine( array( 'year', 'month', 'day' ), $dt );
            $p;
            if( $row['site'] != 0 ) { $aCount['site']['count']++; $p = &$aCount['site'][$row['site']]; }
            else if( $row['blog_id'] != 0 ) { $aCount['blog']['count']++; $p = &$aCount['blog'][$row['blog_id']]; }
            else if( $row['article_id'] != 0 ) { $aCount['article']['count']++; $p = &$aCount['article'][$row['article_id']]; }
            else error_log( "エラー：正しい値ではない" );
            if( !is_null( @$p['count'] ) ) {
                $p['count']++;
            } else {
                $p['count'] = 1;
            }
            if( !is_null( @$p[$dt['year']]['count'] ) ) {
                $p[$dt['year']]['count']++;
            } else {
                $p[$dt['year']]['count'] = 1;
            }
            if( !is_null( @$p[$dt['year']][$dt['month']]['count'] ) ) {
                $p[$dt['year']][$dt['month']]['count']++;
            } else {
                $p[$dt['year']][$dt['month']]['count'] = 1;
            }
            if( !is_null( @$p[$dt['year']][$dt['month']][$dt['day']]['count'] ) ) {
                $p[$dt['year']][$dt['month']][$dt['day']]['count']++;
            } else {
                $p[$dt['year']][$dt['month']][$dt['day']]['count'] = 1;
            }

            //出力
            $log = $dom->createElement('log');
            $log = $root->appendChild($log);
            foreach( $ELEMENTS as $el ) {
                $element = $dom->createElement($el);
                $text = $dom->createTextNode($row[$el]);
                $element = $log->appendChild($element);
                $text = $element->appendChild($text);
            }
        }

        //出力
        $dt = new Datetime( 'now' );
        $dt->setTimezone( new DateTimeZone( 'Asia/Tokyo' ) );
        $fName = "log-". $dt->format( "YmdHis" );       //※ ファイル名
        echo 'Wrote: ' . $dom->save("../log/{$fName}.xml") . ' bytes';

        //-----集計XML出力-----
        $dom = new DOMDocument();
        $dom->encoding = 'UTF-8';
        $dom->formatOutput = true;
        $dom->preserveWhiteSpace = false;
        $root = null;
        $xpath = null;
        //ファイルがあるかないか
        if( file_exists( "../log/aggregate.xml" ) ) {
            $dom->load( "../log/aggregate.xml" );
            $root = $dom->getElementsByTagName("aggregate")->item(0);
            $xpath = new DOMXPath($dom);
        } else {
            $root = $dom->createElement('aggregate');
            $dom->appendChild($root);
        }

        /**
         * 集計された配列を基にXML出力 無名再起関数
         * $dom class DOMDocument
         * $xpath class DOMXPath
         * $name valueに入る値
         * $arr 集計した配列
         * $pNode 吊り下げ元のエレメント
         * $c 階層の深さ。再起で勝手に使われるので値は入れない
         */
        $agg = function( $dom, $xpath, $name, $arr, $pNode, $c = 0 ) use( &$agg ) {
            $DEPTH = [ 'place', 'place2', 'year', 'month', 'day' ];
            if( $xpath != null ) {
                $node = $xpath->query( ".//{$DEPTH[$c]}[@value='{$name}']", $pNode )->item(0);
            } else {
                $node = null;
            }
            if( $node == null ) {
                $node = $dom->createElement($DEPTH[$c]);
                $node->setAttribute("value", $name);
                $node->setAttribute("count", $arr['count']);
                $node = $pNode->appendChild($node);
            } else {
                $ele = $node->getAttributeNode( "count" );
                $ele->value += $arr['count'];
            }
            
            foreach( $arr as $key => $row ) {
                if( is_array( $row ) ) {
                    $agg( $dom, $xpath, $key, $row, $node, $c+1 );
                }
            }
            return;
        };

        foreach( $aCount as $key => $row ) $agg( $dom, $xpath, $key, $row, $root );

        //保存
        $dom->save("../log/aggregate.xml");

        
        //-----アクセスログのクリア-----
        try {
            $sql = "delete from access_log";
            $ps = $pdo->prepare( $sql );
            $ps->execute();

        } catch (PDOException $e) {
            error_log("PDOException: " . $e->getMessage());
            header("Location: error.php");
        }
    }

}

?>
