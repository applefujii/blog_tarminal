<?php

require_once 'Ini.php';

/**
 * アクセス解析
 * 現状ユニークアクセスかを判断する用途のみ
 * [blog/data/FixedData.ini]を編集することで様々な条件に対応できる
 */
class Analytics {

    private $name;
    private $sDistance;
    private $distance;
    private $fGoodBreak;
    private $pathDepth = 0;
    private $pdo_username;
    private $pdo_password;
    //const TIME_FORMAT = array( 'Y', 'n', 'N', 'j', 'H', 'i', 's' );
    //const TIME_FORMAT2 = array( '%y', '%m', '%d', '%h', '%i', '%s' );
    const TIME_FORMAT_INTERVAL = array( 'Y', 'M', 'W', 'D', 'H', 'M', 'S' );
    const TRANS_SEC = array( 60*60*24*31*366, 60*60*24*31, 60*60*24*7, 60*60*24, 60*60, 60, 1 );

    public function __construct( $iniPath, $name ) {
        $this->name = $name;
        $this->pathDepth = str_repeat( '../', substr_count( $iniPath, '../' ) );
        //-----データ読み込み-----
        $fixedData = Ini::read( $iniPath, true );
        if( $fixedData == false ) {
          error_log("failed reading ini file.");
          header("Location: " . $this->pathDepth . "error.php");
        }
        $this->pdo_username = $fixedData['setting']['pdo_username'];
        $this->pdo_password = $fixedData['setting']['pdo_password'];
        $this->distance = array( 'y' => 0, 'mon' => 0, 'w' => 0, 'd' => 0, 'h' => 0, 'm' => 0, 's' => 0 );
        $this->fGoodBreak = $fixedData['setting']['uneque_access_good_break'];
        if( $this->fGoodBreak ) {
            $this->sDistance['full'] = strtoupper( $fixedData['setting']['uneque_access_good_break_criteria'] );
            $this->sDistance['DotW'] = strtoupper( $fixedData['setting']['uneque_access_good_break_criteria_DotW'] );
        } else {
            $this->sDistance['full'] = strtoupper( $fixedData['setting']['uneque_access_distance'] );
        }

        //-----$this->distance[]に各値を代入 + 年月週日と時分秒に分けて$this->sDistance[]に代入
        //error_log( strtok( $this->sDistance['full'], 'P' ));  //※
        $tmp = strtok( $this->sDistance['full'], 'T' );
        error_log($tmp);
        $this->sDistance['date'] = $tmp;
        $i = 0;
        foreach( $this->distance as $key => $row ) {
            if( $i == 4 ) {
                $tmp = strtok( '' );
                error_log($tmp);
                $this->sDistance['time'] = $tmp;
            }
            $ma = Analytics::TIME_FORMAT_INTERVAL[$i];
            if ( preg_match("/\d+{$ma}/ui", $tmp, $tmp2 ) ) {
                preg_match("/\d+/ui", $tmp2[0], $tmp2 );
                $this->distance[$key] = (int)$tmp2[0];
                error_log("$key = $tmp2[0]");
            }
            $i++;
        }

        //$this->fGoodBreak が1で年月週日に入力がなければ日に1を代入
        if( $this->fGoodBreak  &&  $this->sDistance['DotW'] == 0  &&  $this->distance['y'] == 0  &&  $this->distance['mon'] == 0  &&  $this->distance['w'] == 0  &&  $this->distance['d'] == 0) {
            $this->distance['d'] = 1;
            $this->sDistance['full'] = preg_replace( "/(?=T)/u", "1D", $this->sDistance['full'] );
        }
    }

    public function unequeAccessCheck() {
        $now_utc = new Datetime( 'now', new DateTimeZone('UTC') );
        $now = new Datetime( $now_utc->format( DateTimeInterface::ATOM ) , new DateTimeZone( 'Asia/Tokyo' ) );
        if( is_array( @$_COOKIE["viewed_datetime"] ) ) {
            $tmp = $_COOKIE["viewed_datetime"];
            if( isset( $tmp[$this->name] ) ) {
                $viewed = new Datetime( $tmp[$this->name], new DateTimeZone('UTC') );
                $viewed->setTimezone( new DateTimeZone( 'Asia/Tokyo' ) );
                if( $this->fGoodBreak ) {
                    //キリのいい時間まで重複カウントしない
                    if( $this->_CheckGoodBreak( $now, $viewed ) ) {
                        $this->_SetCookie( $now_utc );
                        return 1;
                    }
                } else {
                    //時間が経つまでまで重複カウントしない
                    if( $this->_CheckDistance( $now, $viewed ) ) {
                        $this->_SetCookie( $now_utc );
                        return 1;
                    } 
                }
            } else {
                $this->_SetCookie( $now_utc );
                return 1;
            }
        } else {
            $this->_SetCookie( $now_utc );
            return 1;
        }
        return 0;

        /*
        $day = 60*60*24;
        $now = time();
        if( isset( $_COOKIE['viewed_datetime_unix'] ) ) {
            if( $now - $_COOKIE['viewed_datetime_unix'] <= 60*60 ) return 0;
        }
        setcookie('viewed_datetime_unix', $now, $now + $day );
        return 1;*/
    }

    /**
     * ログデータベースに出力
     * log_param: [
     *          (int)account_id,
     *          (int)site,
     *          (int)blog_id,
     *          (int)article_id,
     *          (bool)flag_unique,
     *          (str)datetime
     *      ]
     */
    public function outputLog( $log_param ) {
        $tmp = new DateTime( $log_param['datetime'] );
        try {
            $options = [
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_EMULATE_PREPARES => false
            ];
            $pdo = new PDO("sqlite:" . $this->pathDepth . "data/access_log_db.sqlite3", $this->pdo_username, $this->pdo_password, $options);
          
            $sql = "insert into access_log (account_id, site, blog_id, article_id, flag_unique, datetime, time_difference, year, month, day, hour) values (:account_id, :site, :blog_id, :article_id, :flag_unique, :datetime, :time_difference, :year, :month, :day, :hour)";
            $ps = $pdo->prepare($sql);
            $ps->bindValue(":account_id", $log_param['account_id'], PDO::PARAM_STR);
            $ps->bindValue(":site", $log_param['site'], PDO::PARAM_INT);
            $ps->bindValue(":blog_id", $log_param['blog_id'], PDO::PARAM_INT);
            $ps->bindValue(":article_id", $log_param['article_id'], PDO::PARAM_INT);
            $ps->bindValue(":flag_unique", $log_param['flag_unique'], PDO::PARAM_BOOL);
            $ps->bindValue(":datetime", $log_param['datetime'], PDO::PARAM_STR);
            $ps->bindValue(":time_difference", $tmp->format( "P" ), PDO::PARAM_STR);
            $ps->bindValue(":year", $tmp->format( "Y" ), PDO::PARAM_STR);
            $ps->bindValue(":month", $tmp->format( "n" ), PDO::PARAM_STR);
            $ps->bindValue(":day", $tmp->format( "j" ), PDO::PARAM_STR);
            $ps->bindValue(":hour", $tmp->format( "H" ), PDO::PARAM_STR);
            $ps->execute();
          
          } catch (PDOException $e) {
            error_log("PDOException: " . $e->getMessage());
            header("Location: " . $this->pathDepth . "error.php");
          }
          
    }

    /**
     * ユニークアクセスチェック 時間が経つまでまで重複カウントしない
     */
    private function _CheckDistance( $now, $viewed ) {
        $criteria = $viewed->add( new DateInterval($this->sDistance['full']) );
        error_log( 'criteria: ' . $criteria->format( DateTimeInterface::ATOM ) );   //※
        if( $criteria < $now ) return 1;
        else return 0;
    }

    /**
     * ユニークアクセスチェック キリのいい時間まで重複カウントしない
     * Y,M,W,Dのうち一番大きいものは周期を、その他は条件を指定するものとなる。
     */
    private function _CheckGoodBreak( $now, $viewed ) {
        $fmt = array( 'y'=>'Y', 'mon'=>'M', 'w'=>'W', 'd'=>'D' );
        $criteria = clone $viewed;
        $sKey = ''; $sRow = 0;
        $state = 0;         //一番大きな単位が年,月,週,日 ： 1,2,3,4
        //値が入っている一番大きな単位を調べる
        foreach( $this->distance as $key => $row ) {
            $state++;
            if( $row != 0 ) {
                $sKey = $key;
                $sRow = $row;
                break;
            }
            if( $state == 4 ) {
                $state = 0;
                break;
            }
        }

        //-----値によっての日付の補正-----
        //年だけ入っていて月日に入っていなければ月日に1をセット
        if( $state == 1  &&  $this->distance['mon'] == 0 ) {
            $criteria->setDate( $criteria->FORMAT( 'Y' ), 1, $criteria->FORMAT( 'j' ) );
        } else if( $state == 1  &&  $this->distance['day'] == 0 ) {
            $criteria->setDate( $criteria->FORMAT( 'Y' ), $criteria->FORMAT( 'n' ), 1 );
        }
        //月だけ入っていたら日に1をセット
        else if( $state == 2  &&  $this->distance['d'] == 0 ) {
            $criteria->setDate( $criteria->FORMAT( 'Y' ), $criteria->FORMAT( 'n' ), 1 );
        }

        //時間指定
        $criteria->setTime( $this->distance['h'], $this->distance['m'], $this->distance['s'] );

        //※現在時刻との比較
        $point = clone $now;
        $point2 = clone $criteria;
        $point->setDate( 1970, 1, 1 );
        $point2->setDate( 1970, 1, 1 );
        $d6 = '';
        if( $state == 4  &&  !($point > $point2) ) {
            $sRow -= 1;
        } else if( $state == 3  &&  !($point > $point2) ) {
            $sRow -= 1;
            $d6 = '6D';
        }
        //周期分進める
        $criteria = $criteria->add( new DateInterval('P' . $sRow . $fmt[$sKey] . $d6) );

        //-----etc.進める処理
        //何週目か指定が入っていたら進める
        if( $this->distance['w'] != 0  &&  $state == 1  ||  $state == 2 ) {
            while( true ) {
                if( $criteria->FORMAT( 'N' ) == 7 ) break;
                $criteria = $criteria->add( new DateInterval('P1D') );
            }
            for( $i =2 ; $i <$this->distance['w'] ; $i++ ) {
                $criteria = $criteria->add( new DateInterval('P1W') );
            }
        }
        //曜日指定が入っていたらその曜日まで進める
        if( $this->sDistance['DotW'] != 0 ) {
            //週日指定を無効化
            $this->distance['d'] = 0;
            if( $state == 4 ) $sRow = 0;
            $this->distance['w'] = 0;
            if( $state == 3 ) $sRow = 0;
            while( true ) {
                if( $criteria->FORMAT( 'N' ) == $this->sDistance['DotW'] ) break;
                $criteria = $criteria->add( new DateInterval('P1D') );
            }
        }

        error_log( 'criteria: ' . $criteria->format( DateTimeInterface::ATOM ) );   //※

        if( $criteria < $now ) return 1;
        else return 0;
    }

    private function _SetCookie( $time ) {
        $lifetime_sec = 0;
        $i = 0;
        foreach( $this->distance as $key => $row ) {
            if( $row != 0 ) $lifetime_sec += Analytics::TRANS_SEC[$i] * $row;
            $i++; 
        }
        setcookie("viewed_datetime[$this->name]", $time->format( DateTimeInterface::ATOM ), time() + $lifetime_sec );
    }

}

?>
