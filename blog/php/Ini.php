<?php

class Ini {
    public static function write($assoc_arr, $path, $has_sections=false) {
        $content = '';

        self::_writeR($content, $assoc_arr, $has_sections);

        if (!$handle = fopen($path, 'w')) {
            return FALSE;
        }
        $success = fwrite($handle, $content);
        fclose($handle); 

        return $success; 
    }

    public static function read($path, $has_sections=false, $scanner_mode=INI_SCANNER_NORMAL) {
        return parse_ini_file( $path, $has_sections, $scanner_mode );
    }

    private static function _writeR(&$content, $assoc_arr, $has_sections)
    {
        foreach ($assoc_arr as $key => $val) {
            if (is_array($val)) {
                if($has_sections) {
                    $content .= "[$key]\n";
                    self::_writeR($content, $val, false);
                } else {
                    foreach($val as $iKey => $iVal) {
                        if (is_int($iKey))
                            $content .= $key ."[] = $iVal\n";
                        else
                            $content .= $key ."[$iKey] = $iVal\n";
                    }
                }
            } else {
                $content .= "$key = $val\n";
            }
        }
    }
}


/* 使い方
$sd = array(
'first' => array(
    'first-1' => 1,
    'first-2' => 2,
    'first-3' => 3,
    'first-4' => 4,
    'first-5' => 5,
),
'second' => array(
    'second-1' => 1,
    'second-2' => 2,
    'second-3' => 3,
    'second-4' => 4,
    'second-5' => 5,
)
);
Ini::Write($sd, "../data/{$user_id}.ini", true)
*/
?>