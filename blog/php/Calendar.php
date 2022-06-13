<?php

class Calendar {

    protected $width;
    protected $now;           //->format Y:年 n:月 j:日
    const em = 15;

    public function __construct( $width =300 ) {
        $this->now = new Datetime( 'now', new DateTimeZone('UTC') );
        $this->width = $width;
    }

    /**
     * draw カレンダーを描画
     * $offset 描画したい月の今月からの相対値
     */
    public function draw( $offset ) {
        $fontSize = $this->width/(8+14*Calendar::em/2)*14 -Calendar::em/2;
        $borderSize = $this->width/(8+14*Calendar::em/2)*1;
        $titleFontSize = $this->width/12;
        $padding = Calendar::em/2 /2;
        $lintHeight = $fontSize + $fontSize*0.2;
        $styleDiv = "
                width:{$this->width}px;
                margin: 0 auto;
                background-color: darkslategrey;
                font-size: {$fontSize}px;
                margin-bottom: 1rem;
                ";
        $styleYearMonth = "
                display: block;
                width: 100%;
                color: white;
                font-size: {$titleFontSize}px;
                margin: 0 auto;
                text-align: center;
                background-color: cadetblue;
                ";
        $styleTable = "
                width: 100%;
                border-collapse: collapse;
                border: 1;
                ";
        $styleTd = "
                padding-right: {$padding}px;
                text-align: right;
                border: {$borderSize}px #FFFFFF80 ridge;
                line-height: {$lintHeight}px;
                ";

        $state = 0;
        $today = 0;
        $tmp = clone $this->now;
        $month = new Datetime( $tmp->format( DateTimeInterface::ATOM ) , new DateTimeZone( 'Asia/Tokyo' ) );
        if( $offset == 0 ) $today = $month->format( "j" );
        //指定月に移動
        if( $offset >= 0 ) {
            $month = $month->add( new DateInterval( "P" . strval( $offset ) . "M" ) );
        } else {
            $month = $month->sub( new DateInterval( "P" . strval( abs( $offset ) ) . "M" ) );
        }
        $month->setDate( $month->FORMAT( 'Y' ), $month->FORMAT( 'n' ), 1 );     //指定月の1日
        $dayOfWeek = $month->format( "w" );     //指定月の始まる曜日
        $endDay = (int)$month->format('t');     //指定月に何日あるか
        $tmp = clone $this->now;    //※下の行と合わせてnew Datetimeにする
        $tmp = new Datetime( $tmp->format( DateTimeInterface::ATOM ) , new DateTimeZone( 'Asia/Tokyo' ) );
        $tmp->setDate( $month->FORMAT( 'Y' ), $month->FORMAT( 'n' ), $endDay );
        
        //-----指定月に何週あるか-----
        //iso仕様によるバグ回避
        $fDWeekOfMonth = (int)date( 'W', (int)$month->format('U') + 86400 );
        if (in_array($month->format('m-d'), array('12-29', '12-30', '12-31'))  &&  $fDWeekOfMonth == 1) {
            $fDWeekOfMonth = 53;
        //1月に6週ある場合、1月初週を52～54週目と判定される件の回避
        } else if ( in_array($month->format('m-d'), array('01-01', '01-02', '01-03', '01-04', '01-05', '01-06', '01-07'))  &&  $fDWeekOfMonth >= 52) {
            $fDWeekOfMonth = 0;
        }
        //何週あるか
        $weeksOfMonth = 1 + (int)date( 'W', (int)$tmp->format('U') + 86400 ) - $fDWeekOfMonth;
        error_log( $tmp->format('Y,m,j') );
        error_log( $month->format('Y,m,j') );
        error_log( '1+'.date( 'W', (int)$tmp->format('U') + 86400 ).'-'.$fDWeekOfMonth);
        error_log( $weeksOfMonth );

        echo "<div class='calendar' style='{$styleDiv}'>";
        echo "<span class='year-month' style='{$styleYearMonth}'><b>{$month->FORMAT( 'Y' )}年 {$month->FORMAT( 'n' )}月</b></span>";
        echo "<table class='calendar' style='{$styleTable}'>";
        $iDay = 1;
        for( $j =0 ; $j <$weeksOfMonth ; $j++ ) {
            echo "<tr>";
            for( $i =0 ; $i <7 ; $i++ ) {
                if( $state == 0 ) {
                    if( $i == $dayOfWeek ) $state = 1;
                } else if( $state == 1 ) {
                    if( $iDay > $endDay ) $state = 2;
                }
                $tmp = "<td style='{$styleTd}";
                if( $i == 0 ) $tmp .= " color: rgb(255, 140, 140)";
                else if( $i == 6 ) $tmp .= " color: rgb(140, 140, 255)";
                if( $iDay == $today ) $tmp .= " background-color: rgb(38, 179, 108)";
                $tmp .= "'><tt>";
                echo $tmp;
                if( $state == 1 ) {
                    echo $iDay++;
                }
                echo "</tt></td>";
            }
            echo "</tr>";
        }
        echo "</table>";
        echo "</div>";
    }

    //-----------setter------------------------
    public function setWidth( $width ) {
        $this->width = $width;
    }

}

?>
