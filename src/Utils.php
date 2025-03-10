<?php
namespace FastApiPHP;


class Utils {

    public static function getRemoteIP() {
        $ipaddress = '';
        if (isset($_SERVER['HTTP_CLIENT_IP']))
            $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
        else if(isset($_SERVER['HTTP_X_FORWARDED_FOR']))
            $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
        else if(isset($_SERVER['HTTP_X_FORWARDED']))
            $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
        else if(isset($_SERVER['HTTP_X_CLUSTER_CLIENT_IP']))
            $ipaddress = $_SERVER['HTTP_X_CLUSTER_CLIENT_IP'];
        else if(isset($_SERVER['HTTP_FORWARDED_FOR']))
            $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
        else if(isset($_SERVER['HTTP_FORWARDED']))
            $ipaddress = $_SERVER['HTTP_FORWARDED'];
        else if(isset($_SERVER['REMOTE_ADDR']))
            $ipaddress = $_SERVER['REMOTE_ADDR'];
        else
            $ipaddress = 'UNKNOWN';
        return $ipaddress;
    }
	
    public static function getSlug($str, $withCase=false, $asFile=false) {
        $tr = array(
            "А"=>"a","Б"=>"b","В"=>"v","Г"=>"g",
            "Д"=>"d","Е"=>"e","Ё"=>"e","Ж"=>"j","З"=>"z","И"=>"i",
            "Й"=>"y","К"=>"k","Л"=>"l","М"=>"m","Н"=>"n",
            "О"=>"o","П"=>"p","Р"=>"r","С"=>"s","Т"=>"t",
            "У"=>"u","Ф"=>"f","Х"=>"h","Ц"=>"ts","Ч"=>"ch",
            "Ш"=>"sh","Щ"=>"sch","Ъ"=>"","Ы"=>"yi","Ь"=>"",
            "Э"=>"e","Ю"=>"yu","Я"=>"ya","а"=>"a","б"=>"b",
            "в"=>"v","г"=>"g","д"=>"d","е"=>"e","ё"=>"e","ж"=>"j",
            "з"=>"z","и"=>"i","й"=>"y","к"=>"k","л"=>"l",
            "м"=>"m","н"=>"n","о"=>"o","п"=>"p","р"=>"r",
            "с"=>"s","т"=>"t","у"=>"u","ф"=>"f","х"=>"h",
            "ц"=>"ts","ч"=>"ch","ш"=>"sh","щ"=>"sch","ъ"=>"y",
            "ы"=>"yi","ь"=>"","э"=>"e","ю"=>"yu","я"=>"ya", 
            " "=>"_", "."=>"_", ","=>"_", "/"=>"_", "\\"=>"_", 
            "'"=>"", "\""=>"", ":"=>"_", ";"=>"_"
        );
        if ($asFile) $tr["."]=".";

        if ($withCase) {
            return strtr($str,$tr);
        } else {
            return strtolower(strtr($str,$tr));
        }
    }

    public static function strPadRight($str,$len,$ch) { $str = substr($str,0,$len); return str_pad($str, $len, $ch, STR_PAD_RIGHT); } 
    public static function strPadLeft($str,$len,$ch) { $str = substr($str,0,$len); return str_pad($str, $len, $ch, STR_PAD_LEFT); } 
    public static function strPadBoth($str,$len,$ch) { $str = substr($str,0,$len); return str_pad($str, $len, $ch, STR_PAD_BOTH); } 
    public static function getStrAfter($after, $string){ if (!is_bool(strpos($string, $after))) return substr($string, strpos($string,$after)+strlen($after)); } 
    public static function getStrBefore($before, $string){ return substr($string, 0, strpos($string, $before)); } 
    public static function numberFormat($number, $delim=""){ return number_format((float)$number, 2, ".", $delim); } 

    public function unicode_decode($str) {  
        if ($str[0] == '"' && $str[strlen($str) - 1] == '"') {
            return json_decode($str);  
        } else {
            return json_decode('"' . ($str ?? ""). '"');  
        }
    }
   
    public static function sendMail($to, $from_user, $from_email, $subject = '(No subject)', $message = '') {
        $from_user = "=?UTF-8?B?".base64_encode($from_user)."?=";
        $subject = "=?UTF-8?B?".base64_encode($subject)."?=";

        $headers = "From: $from_user <$from_email>\r\n".
                   "MIME-Version: 1.0" . "\r\n" .
                   "Content-type: text/html; charset=UTF-8" . "\r\n";

        $message = str_replace(["\n","\r\n"], ["<br>", "<br>"], $message);

        return mail($to, $subject, $message, $headers);
    }

    public static function random_str($length, $keyspace = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ')
    {
        $pieces = [];
        $max = mb_strlen($keyspace, '8bit') - 1;
        for ($i = 0; $i < $length; ++$i) {
            $pieces []= $keyspace[random_int(0, $max)];
        }
        return implode('', $pieces);
    }


    //вхождение элемента или массива в массив
    public static function inArray($arr1, $arr2) {
        if (!is_array($arr1)) return in_array($arr1, $arr2);
        $rez = false;
        foreach($arr1 as $v) {
            if (in_array($v, $arr2)) $rez = true;
        }
        return $rez;
    }

    //Дату в формат SQL yyyy-mm-dd
    public static function convDateToSQL($dt, $withtime=true) {
        if (strlen($dt)<10) return null;
        if (strpos(substr($dt,0,10),'-')!==false) { return $dt; }

        $out=substr($dt,6,4)."-".substr($dt,3,2)."-".substr($dt,0,2); 
        if ($withtime) $out.=substr($dt,10,9);
        return $out;
    }

    //Дату в формат даты dd.mm.yyyy
    public static function convDateToDate($dt, $withtime=true) {
        if (strlen($dt ?? "")<10) return null;
        if (strpos(substr($dt,0,10),'.')!==false) { return $dt; }
        if (substr($dt,0,1)=="-") { $dt=substr($dt,1); }

        $out=substr($dt,8,2).".".substr($dt,5,2).".".substr($dt,0,4); 
        if ($withtime) $out.=substr($dt,10,9);
        return $out;
    }


    public static function replaceTextLikeVue($msg, $fields) {
        $fields = self::objectToArray($fields);
        $params = [];
        foreach($fields as $key=>$val) {
            $params["{{".$key."}}"] = $val;
        }
        $msg = str_replace(array_keys($params), array_values($params), $msg);
        $msg = preg_replace('|{{(.*)}}|isU', '', $msg);

        return $msg;
    }


    public static function makeWordDocument($inFile, $outFile, $fields=[]) {
        if (file_exists($outFile)) unlink($outFile);
        copy($inFile, $outFile);

        $zip = new \ZipArchive();
        if (!$zip->open($outFile)) { return ["error"=>1, "message"=>"File not open."]; }
        $documentXml = $zip->getFromName('word/document.xml');
        $i=0;
        while (strpos($documentXml, "{{") !== false ) {
            $i++;
            $pos = strpos($documentXml, "{{");
            $pos_end = strpos($documentXml, "}}", $pos)-$pos;
            $text = substr($documentXml, $pos+strlen("{{"), $pos_end-strlen("}}"));
            $text = trim( strip_tags($text) );
            if (isset($fields[$text])) { $text = $fields[$text]; } else { $text = ""; }
            $documentXml = substr_replace($documentXml, $text, $pos, $pos_end+strlen("}}"));
            if ($i > 500) break;
        }
        $zip->deleteName('word/document.xml');
        $zip->addFromString('word/document.xml', $documentXml);
        $zip->close();

        return ["error"=>0, "file"=>$outFile];
    }


    public static function makeExcelDocument($inFile, $outFile, $fields=[]) {
        if (file_exists($outFile)) unlink($outFile);
        copy($inFile, $outFile);

        $zip = new \ZipArchive();
        if (!$zip->open($outFile)) { return ["error"=>1, "message"=>"File not open."]; }

        $documentXml = $zip->getFromName('xl/sharedStrings.xml');
        $i=0;
        while (strpos($documentXml, "{{") !== false ) {
            $i++;
            $pos = strpos($documentXml, "{{");
            $pos_end = strpos($documentXml, "}}", $pos)-$pos;
            $text = substr($documentXml, $pos+strlen("{{"), $pos_end-strlen("}}"));
            $text = trim( strip_tags($text) );
            if (isset($fields[$text])) { $text = $fields[$text]; } else { $text = ""; }
            $documentXml = substr_replace($documentXml, $text, $pos, $pos_end+strlen("}}"));
            if ($i > 500) break;
        }
        $zip->deleteName('xl/sharedStrings.xml');
        $zip->addFromString('xl/sharedStrings.xml', $documentXml);
        $zip->close();

        return ["error"=>0, "file"=>$outFile];
    }


    public static function sendFile($file, $contentType="application/octet-stream") {
        header('Content-Description: File Transfer');
        header('Content-Type: '.$contentType);
        header('Content-Disposition: attachment; filename="'.basename($file).'"');
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        header('x-MD5: '.md5_file($file), true);
        header('Content-Length: ' . filesize($file));
        ob_clean();
        flush();
        readfile($file);
    }


    public static function get_include_contents($filename, $params) {
        if (is_file($filename)) {
            ob_start();
            include($filename);
            return ob_get_clean();
        }
        return false;
    }



    public static function sendTelegramMessage($token="", $chat="", $msg="") {
        if (strlen($token) == 0 || strlen($chat) == 0 || strlen($msg) == 0) return;
        $msg = urlencode($msg);
        $url = "https://api.telegram.org/bot{$token}/sendMessage?chat_id={$chat}&text={$msg}";
        $ch = curl_init();
        curl_setopt_array($ch, [CURLOPT_URL=>$url, CURLOPT_RETURNTRANSFER=>true]);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }


    public static function extToIcon($ext=".png"){
        if (in_array($ext, [".png"])) return "https://img.icons8.com/color/64/000000/png.png";
        if (in_array($ext, [".gif"])) return "https://img.icons8.com/color/64/000000/gif.png";
        if (in_array($ext, [".mp3"])) return "https://img.icons8.com/color/64/000000/mp3.png";
        if (in_array($ext, [".mpg"])) return "https://img.icons8.com/color/64/000000/mpg.png";
        if (in_array($ext, [".avi"])) return "https://img.icons8.com/color/64/000000/avi.png";
        if (in_array($ext, [".pdf"])) return "https://img.icons8.com/color/64/000000/pdf.png";
        if (in_array($ext, [".css"])) return "https://img.icons8.com/color/64/000000/css.png";
        if (in_array($ext, ["html"])) return "https://img.icons8.com/color/64/000000/html.png";
        if (in_array($ext, [".txt"])) return "https://img.icons8.com/color/64/000000/txt.png";
        if (in_array($ext, [".zip"])) return "https://img.icons8.com/color/64/000000/zip.png";
        if (in_array($ext, [".rar"])) return "https://img.icons8.com/color/64/000000/rar.png";
        if (in_array($ext, [".jpg","jpeg"])) return "https://img.icons8.com/color/64/000000/jpg.png";
        if (in_array($ext, [".doc","docx"])) return "https://img.icons8.com/color/64/000000/doc.png";
        if (in_array($ext, [".xls","xlsx"])) return "https://img.icons8.com/color/64/000000/xls.png";
        if (in_array($ext, [".ppt","pptx"])) return "https://img.icons8.com/color/64/000000/ppt.png";

        return "https://img.icons8.com/color/64/000000/file.png";
    }
	


}//CLASS************************************
