<?php
// lib.php - Fonctions de compatibilite PHP 4 - ASCII pur

// == Sessions ==
function cms_session_start() {
    $save_path = dirname(dirname(__FILE__)) . '/sessions';
    if (!is_dir($save_path)) { mkdir($save_path, 0700); }
    session_save_path($save_path);
    session_start();
}

// == Ecriture fichier ==
function file_write($path, $content) {
    $fp = fopen($path, 'w');
    if (!$fp) return false;
    fwrite($fp, $content);
    fclose($fp);
    return true;
}

// == Lecture fichier ==
function file_read($path) {
    if (!file_exists($path)) return '';
    $fp = fopen($path, 'r');
    if (!$fp) return '';
    $content = fread($fp, filesize($path) + 1);
    fclose($fp);
    return $content;
}

// == Mot de passe : md5 + sel ==
function cms_hash_password($password) {
    $salt = substr(md5(uniqid(rand(), true)), 0, 8);
    return $salt . md5($salt . $password);
}
function cms_verify_password($password, $hash) {
    $salt = substr($hash, 0, 8);
    return md5($salt . $password) === substr($hash, 8);
}

// == Neutraliser magic_quotes_gpc (actif par defaut en PHP 4) ==
function strip_array($arr) {
    $keys = array_keys($arr);
    for ($i = 0; $i < count($keys); $i++) {
        $k = $keys[$i];
        if (is_array($arr[$k])) {
            $arr[$k] = strip_array($arr[$k]);
        } else {
            $arr[$k] = stripslashes($arr[$k]);
        }
    }
    return $arr;
}
function cms_strip_magic_quotes() {
    if (!get_magic_quotes_gpc()) return;
    $_POST   = strip_array($_POST);
    $_GET    = strip_array($_GET);
    $_COOKIE = strip_array($_COOKIE);
}

// == JSON encode PHP 4 ==
function json_encode_compat($val) {
    if (is_null($val))  return 'null';
    if (is_bool($val))  return $val ? 'true' : 'false';
    if (is_int($val))   return (string)$val;
    if (is_float($val)) return (string)$val;
    if (is_string($val)) {
        $val = str_replace('\\', '\\\\', $val);
        $val = str_replace('"',  '\\"',  $val);
        $val = str_replace("\r", '',     $val);
        $val = str_replace("\n", '\\n',  $val);
        $val = str_replace("\t", '\\t',  $val);
        return '"' . $val . '"';
    }
    if (is_array($val)) {
        $is_list = true;
        $i = 0;
        foreach ($val as $k => $v) {
            if ($k !== $i) { $is_list = false; break; }
            $i++;
        }
        if ($is_list) {
            $parts = array();
            foreach ($val as $v) { $parts[] = json_encode_compat($v); }
            return '[' . implode(',', $parts) . ']';
        } else {
            $parts = array();
            foreach ($val as $k => $v) {
                $parts[] = json_encode_compat((string)$k) . ':' . json_encode_compat($v);
            }
            return '{' . implode(',', $parts) . '}';
        }
    }
    return 'null';
}

// == JSON decode PHP 4 ==
function json_decode_compat($str) {
    $str = trim($str);
    return _json_parse($str, 0, $end);
}
function _json_parse($str, $pos, &$end) {
    $len = strlen($str);
    while ($pos < $len && ($str[$pos] == ' ' || $str[$pos] == "\n" || $str[$pos] == "\r" || $str[$pos] == "\t")) $pos++;
    if ($pos >= $len) { $end = $pos; return null; }
    $c = $str[$pos];
    if (substr($str, $pos, 4) == 'null')  { $end = $pos+4; return null; }
    if (substr($str, $pos, 4) == 'true')  { $end = $pos+4; return true; }
    if (substr($str, $pos, 5) == 'false') { $end = $pos+5; return false; }
    if ($c == '"') {
        $result = '';
        $i = $pos + 1;
        while ($i < $len) {
            if ($str[$i] == '\\') {
                $i++;
                if ($i >= $len) break;
                switch ($str[$i]) {
                    case '"':  $result .= '"'; break;
                    case '\\': $result .= '\\'; break;
                    case '/':  $result .= '/'; break;
                    case 'n':  $result .= "\n"; break;
                    case 'r':  $result .= "\r"; break;
                    case 't':  $result .= "\t"; break;
                    default:   $result .= $str[$i];
                }
                $i++;
            } elseif ($str[$i] == '"') { $i++; break; }
            else { $result .= $str[$i]; $i++; }
        }
        $end = $i;
        return $result;
    }
    if ($c == '-' || ($c >= '0' && $c <= '9')) {
        $i = $pos;
        if ($str[$i] == '-') $i++;
        while ($i < $len && $str[$i] >= '0' && $str[$i] <= '9') $i++;
        if ($i < $len && $str[$i] == '.') { $i++; while ($i < $len && $str[$i] >= '0' && $str[$i] <= '9') $i++; }
        $num = substr($str, $pos, $i - $pos);
        $end = $i;
        return strpos($num, '.') !== false ? (float)$num : (int)$num;
    }
    if ($c == '[') {
        $arr = array();
        $i = $pos + 1;
        while ($i < $len) {
            while ($i < $len && ($str[$i] == ' ' || $str[$i] == "\n" || $str[$i] == "\r" || $str[$i] == "\t")) $i++;
            if ($i >= $len) break;
            if ($str[$i] == ']') { $i++; break; }
            if ($str[$i] == ',') { $i++; continue; }
            $v = _json_parse($str, $i, $next);
            $arr[] = $v;
            $i = $next;
        }
        $end = $i;
        return $arr;
    }
    if ($c == '{') {
        $obj = array();
        $i = $pos + 1;
        while ($i < $len) {
            while ($i < $len && ($str[$i] == ' ' || $str[$i] == "\n" || $str[$i] == "\r" || $str[$i] == "\t")) $i++;
            if ($i >= $len) break;
            if ($str[$i] == '}') { $i++; break; }
            if ($str[$i] == ',') { $i++; continue; }
            $key = _json_parse($str, $i, $next);
            $i = $next;
            while ($i < $len && ($str[$i] == ' ' || $str[$i] == ':')) $i++;
            $val = _json_parse($str, $i, $next);
            $obj[$key] = $val;
            $i = $next;
        }
        $end = $i;
        return $obj;
    }
    $end = $pos + 1;
    return null;
}

// == Auth ==
function cms_auth_check() {
    if (empty($_SESSION['cms_auth'])) {
        header('Location: login.php');
        exit;
    }
}

// == HTML escape ==
function h($str) {
    return htmlspecialchars($str);
}

// == Mot de passe : lecture/ecriture ==
function cms_get_password_hash() {
    $f = dirname(__FILE__) . '/pwdhash.txt';
    if (!file_exists($f)) {
        $hash = cms_hash_password('thothelier2024');
        file_write($f, $hash);
        return $hash;
    }
    return trim(file_read($f));
}
function cms_save_password_hash($hash) {
    $f = dirname(__FILE__) . '/pwdhash.txt';
    return file_write($f, $hash);
}

// == Lecture/ecriture donnees JSON ==
function data_read($filename) {
    $path = dirname(dirname(__FILE__)) . '/_data/' . $filename;
    if (!file_exists($path)) return array();
    $content = file_read($path);
    return json_decode_compat($content);
}
function data_write($filename, $data) {
    $path = dirname(dirname(__FILE__)) . '/_data/' . $filename;
    return file_write($path, json_encode_compat($data));
}

// == Conversion accents vers entites HTML ==
// Necessite pour ecrire dans les fichiers HTML Latin-1 du site
function entites($str) {
    $from = array(
        chr(195).chr(169), chr(195).chr(168), chr(195).chr(170), chr(195).chr(171),
        chr(195).chr(160), chr(195).chr(162), chr(195).chr(164),
        chr(195).chr(185), chr(195).chr(187), chr(195).chr(188),
        chr(195).chr(174), chr(195).chr(175),
        chr(195).chr(180), chr(195).chr(182),
        chr(195).chr(167),
        chr(195).chr(137), chr(195).chr(136), chr(195).chr(138),
        chr(195).chr(128), chr(195).chr(130),
        chr(195).chr(153), chr(195).chr(155),
        chr(195).chr(142), chr(195).chr(148), chr(195).chr(135),
        chr(197).chr(147), chr(195).chr(166),
        chr(226).chr(130).chr(172), chr(194).chr(176),
        chr(226).chr(128).chr(153), chr(226).chr(128).chr(152)
    );
    $to = array(
        '&eacute;','&egrave;','&ecirc;','&euml;',
        '&agrave;','&acirc;','&auml;',
        '&ugrave;','&ucirc;','&uuml;',
        '&icirc;','&iuml;',
        '&ocirc;','&ouml;',
        '&ccedil;',
        '&Eacute;','&Egrave;','&Ecirc;',
        '&Agrave;','&Acirc;',
        '&Ugrave;','&Ucirc;',
        '&Icirc;','&Ocirc;','&Ccedil;',
        '&oelig;','&aelig;',
        '&euro;','&deg;',
        '&#39;','&#39;'
    );
    return str_replace($from, $to, $str);
}
?>
