<?php
/**
 * Simply checks if string is a constant or not.
 *
 * @param string $is_constant
 * @return mixed will return constant if it exists.
 */
function PU_isConstant($is_constant)
{
    if (defined($is_constant)) {
        return constant($is_constant);
    } else {
        return $is_constant;
    }
}

/**
 * Convert string unsigned CRC32 value. This is unique and can help predict a entries id beforehand.
 * Use for folder names insuring unique id's.
 *
 * @param string $convert_to_id To convert to integer.
 * @return integer
 */
function PU_nameToId($convert_to_id)
{
    return sprintf('%u', crc32($convert_to_id));
}

/**
 * Copy an array to another and defaults to false if the value isn't set
 *
 * @param array  $source    the array to extract values from
 * @param mixed  $target    the array to add the values to
 * @param array  $indexes   the indexes of the values to copy
 * @param string $type      the type of value to cast (currently only boolean or null for everything else)
 */
function PU_copyArray($source, &$target, $indexes = array(), $type = null)
{
    if (!is_array($indexes)) $indexes = array($indexes);
    switch ($type) {
        case 'boolean':
            foreach ($indexes as $index) {
                $target[$index] = (boolean) $source[$index];
            }
            break;
        default:
            if (empty($indexes)) {
                foreach ($source as $key => $value) {
                    $target[$key] = $value;
                }
            } else {
                foreach ($indexes as $index) {
                    $target[$index] = $source[$index];
                }
            }
            break;
    }
}

/**
 * Search for array values inside array and returns key.
 *
 * @param array $needle
 * @param array $haystack
 * @return mixed
 */
function PU_arraySearch($needle, $haystack)
{
    if (empty($needle) || empty($haystack)) {
        return false;
    }

    foreach ($haystack as $key => $value) {
        $exists = 0;
        foreach ($needle as $nkey => $nvalue) {
            if (!empty($value[$nkey]) && $value[$nkey] == $nvalue) {
                $exists = 1;
            } else {
                $exists = 0;
            }
        }
        if ($exists)
            return $key;
    }

    return false;
}

/**
 * Create gettext functions.
 */
if (function_exists('gettext')) {

    function __($gettext, $domain = '')
    {
        if (empty($domain)) {
            return gettext($gettext);
        } else {
            return dgettext($domain, $gettext);
        }
    }

    function ___($gettext)
    {
        return dgettext('core.lang', $gettext);
    }

    function _e($text)
    {
        echo gettext($text);
    }

    function __e($text, $domain)
    {
        echo dgettext($domain, $text);
    }
} else {

    function ___($gettext)
    {
        return dgettext('core.lang', $gettext);
    }

    function gettext($text)
    {
        return $text;
    }

    function dgettext($domain, $text)
    {
        return $text;
    }

    function _($text)
    {
        return $text;
    }

    function __($gettext, $domain = false)
    {
        return $gettext;
    }

    function _e($text)
    {
        echo $text;
    }

    function __e($text)
    {
        echo $text;
    }

    function textdomain($textdomain)
    {
        return '';
    }

}

/**
 * Get rid of all buffer, optionally flushing (i.e. writing to the browser)
 * Default behavior is to ignore.
 *
 * @param boolean $flush do we flush or ignore?
 */
function PU_cleanBuffers($flush = false)
{
    try {
        for ($loop = ob_get_level(); $loop; $loop--) {
            $flush ? ob_end_flush() : ob_end_clean();
        }

        // these catches are only there to mask the exception and prevent it from bubbling
    } catch (Exception $e) {
        $a = 0;
    } catch (ErrorException $e) {
        $a = 0;
    } catch (PHPDS_fatalError $e) {
        $a = 0;
    }
}

/**
 * Add a header if and only if headers have not been sent yet
 *
 * @param string $header the header string to add
 */
function PU_silentHeader($header)
{
    if (!headers_sent()) {
        header($header);
    }
}

/**
 * Determines if the current request has been made by some kind of ajax call (i.e. XMLHttpRequest)
 *
 * @param boolean $json set to true if you want to force the request's result as json
 * @return boolean
 */
function PU_isAJAX($json = false)
{
    $ajax = !empty($_SERVER["HTTP_X_REQUESTED_WITH"]) && strtolower($_SERVER["HTTP_X_REQUESTED_WITH"] == 'XMLHttpRequest');
    if ($ajax && $json) {
        PU_cleanBuffers();
        PU_silentHeader('Content-Type: application/json');
    }
    return $ajax;
}

/**
 * Checks for a json context and if so, outputs data
 *
 * @param mixed $data   the data to be encoded and sent
 * @param bool  $force (optional) do we pretend it's json context even if it's not?
 * @return boolean false if it's not JSON, or the encoded data
 */
function PU_isJSON($data, $force = false)
{
    $json = $force || (isset($_SERVER["HTTP_X_REQUESTED_TYPE"]) && ($_SERVER["HTTP_X_REQUESTED_TYPE"] == 'json'));
    if ($json && PU_isAJAX(true)) {
        return json_encode($data);
    }
    return false;
}

/**
 * Get rid of null values inside an array
 * All values which are null in the array are remove, shortening the array
 *
 * @param array $a the array to compact
 * @return array
 */
function PU_array_compact(array $a)
{
    foreach ($a as $k => $v) {
        if (is_null($a[$k])) unset($a[$k]);
    }
    return $a;
}

/**
 * Version of sprintf for cases where named arguments are desired (python syntax)
 * with sprintf: sprintf('second: %2$s ; first: %1$s', '1st', '2nd');
 * with sprintfn: sprintfn('second: %(second)s ; first: %(first)s', array(
 *  'first' => '1st',
 *  'second'=> '2nd'
 * ));
 *
 * @param string $format sprintf format string, with any number of named arguments
 * @param array  $args   array of [ 'arg_name' => 'arg value', ... ] replacements to be made
 * @return string|bool result of sprintf call, or bool false on error
 * @throws PHPDS_sprintfnException
 */
function PU_sprintfn($format, array $args = array())
{
    try {
        // map of argument names to their corresponding sprintf numeric argument value
        $arg_nums = array_slice(array_flip(array_keys(array(0 => 0) + $args)), 1);

        // find the next named argument. each search starts at the end of the previous replacement.
        for ($pos = 0; preg_match('/(?<=%)\(([a-zA-Z_]\w*)\)/', $format, $match, PREG_OFFSET_CAPTURE, $pos);) {
            $arg_pos = $match[0][1];
            $arg_len = strlen($match[0][0]);
            $arg_key = $match[1][0];

            // programmer did not supply a value for the named argument found in the format string
            if (!array_key_exists($arg_key, $arg_nums)) {
                throw new PHPDS_sprintfnException(array($format, $arg_key), $args);
            }

            // replace the named argument with the corresponding numeric one
            $format = substr_replace($format, $replace = $arg_nums[$arg_key] . '$', $arg_pos, $arg_len);
            $pos    = $arg_pos + strlen($replace); // skip to end of replacement for next iteration
        }

        $result = vsprintf($format, array_values($args));
        return $result;
    } catch (Exception $e) {
        throw new PHPDS_sprintfnException($format, $args, $e);
    }
}

/**
 * Add an include path to check in for classes.
 *
 * @param string $path
 * @return string|bool
 */
function PU_addIncludePath($path)
{
    if (!empty($path) && file_exists($path)) {
        return set_include_path(get_include_path() . PATH_SEPARATOR . $path);
    }
    return false;
}

/**
 * Outputs an array in html
 * A slightly better version of print_r()
 * Note: this output is html
 *
 * @param array   $a
 * @param string  $title
 * @param boolean $htmlize (default to false) if true html is escaped to be displayed as source
 *
 * @return string
 */
function PU_dumpArray($a, $title = '', $htmlize = false)
{
    $s = $title ? "<p>$title</p>" : '';

    if (!(is_array($a) || is_object($a))) {
        $a = array($a);
    }

    if (count($a) == 0) {
        $s .= '(empty array)';
    } else {
        $s .= '<ul class="array_dump">';
        foreach ($a as $k => $e) {
            $t = gettype($e);
            switch ($t) {
                case 'array':
                    $t .= ', ' . count($e) . ' elements';
                    break;
                case 'string':
                    $t .= ', ' . strlen($e) . ' chars, ' . mb_detect_encoding($e);

                    break;
                case 'object':
                    $t .= ' of class "' . get_class($e) . '"';
                    break;
            }
            $s .= '<li>'
                . '<span class="array_key"><span class="array_grey">[&nbsp;</span>' . $k . '<span class="array_grey">&nbsp;]&nbsp;=&gt;</span></span>'
                . '&nbsp;<span class="array_type">(' . $t . ')</span>&nbsp;';
            if (is_array($e) || is_object($e)) {
                $e = PU_dumpArray($e, null, $htmlize);
            } else if ($htmlize) {
                $e = htmlentities($e);
            }
            $s .= '<span class="array_value">' . (string)$e . '</li>';
        }
        $s .= '</ul>';
    }

    return $s;
}

/**
 * Better GI than print_r or var_dump -- but, unlike var_dump, you can only dump one variable.
 * Added htmlentities on the var content before echo, so you see what is really there, and not the mark-up.
 *
 * Also, now the output is encased within a div block that sets the background color, font style, and left-justifies it
 * so it is not at the mercy of ambient styles.
 *
 * @param mixed        $var       -- variable to dump
 * @param string       $var_name  -- name of variable (optional) -- displayed in printout making it easier to sort out what variable is what in a complex output
 * @param string       $indent    -- used by internal recursive call (no known external value)
 * @param string       $reference -- used by internal recursive call (no known external value)
 */
function PU_printr(&$var, $var_name = NULL, $indent = NULL, $reference = NULL)
{
    $do_dump_indent = "<span style='color:#666666;'>|</span> &nbsp;&nbsp; ";
    $reference      = $reference . $var_name;
    $keyvar         = 'the_do_dump_recursion_protection_scheme';
    $keyname        = 'referenced_object_name';

    // So this is always visible and always left justified and readable
    echo "<div style='text-align:left; background-color:white; font: 100% monospace; color:black;'>";

    if (is_array($var) && isset($var[$keyvar])) {
        $real_var  = & $var[$keyvar];
        $real_name = & $var[$keyname];
        $type      = ucfirst(gettype($real_var));
        echo "$indent$var_name <span style='color:#666666'>$type</span> = <span style='color:#e87800;'>&amp;$real_name</span><br>";
    } else {
        $var  = array($keyvar => $var, $keyname => $reference);
        $avar = & $var[$keyvar];

        $type = ucfirst(gettype($avar));
        if ($type == "String")
            $type_color = "<span style='color:green'>";
        elseif ($type == "Integer")
            $type_color = "<span style='color:red'>"; elseif ($type == "Double") {
            $type_color = "<span style='color:#0099c5'>";
            $type       = "Float";
        } elseif ($type == "Boolean")
            $type_color = "<span style='color:#92008d'>"; elseif ($type == "NULL")
            $type_color = "<span style='color:black'>";

        if (is_array($avar)) {
            $count = count($avar);
            echo "$indent" . ($var_name ? "$var_name => " : "") . "<span style='color:#666666'>$type ($count)</span><br>$indent(<br>";
            $keys = array_keys($avar);
            foreach ($keys as $name) {
                $value = & $avar[$name];
                PU_printr($value, "['$name']", $indent . $do_dump_indent, $reference);
            }
            echo "$indent)<br>";
        } elseif (is_object($avar)) {
            echo "$indent$var_name <span style='color:#666666'>$type</span><br>$indent(<br>";
            foreach ($avar as $name => $value)
                PU_printr($value, "$name", $indent . $do_dump_indent, $reference);
            echo "$indent)<br>";
        } elseif (is_int($avar))
            echo "$indent$var_name = <span style='color:#666666'>$type(" . strlen($avar) . ")</span> $type_color" . htmlentities($avar) . "</span><br>"; elseif (is_string($avar))
            echo "$indent$var_name = <span style='color:#666666'>$type(" . strlen($avar) . ")</span> $type_color\"" . htmlentities($avar) . "\"</span><br>"; elseif (is_float($avar))
            echo "$indent$var_name = <span style='color:#666666'>$type(" . strlen($avar) . ")</span> $type_color" . htmlentities($avar) . "</span><br>"; elseif (is_bool($avar))
            echo "$indent$var_name = <span style='color:#666666'>$type(" . strlen($avar) . ")</span> $type_color" . ($avar == 1 ? "TRUE" : "FALSE") . "</span><br>"; elseif (is_null($avar))
            echo "$indent$var_name = <span style='color:#666666'>$type(" . strlen($avar) . ")</span> {$type_color}NULL</span><br>"; else
            echo "$indent$var_name = <span style='color:#666666'>$type(" . strlen($avar) . ")</span> " . htmlentities($avar) . "<br>";

        $var = $var[$keyvar];
    }

    echo "</div>";
}

/**
 * This method creates a random string with mixed alphabetic characters.
 *
 * @param integer $length         The length the string should be.
 * @param boolean $uppercase_only Should the string be uppercase.
 * @return string Will return required random string.
 */
function PU_createRandomString($length = 4, $uppercase_only = false)
{
    if ($uppercase_only == true) {
        $template = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    } else {
        $template = '1234567890abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    }
    $length    = $length - 1;
    $rndstring = false;
    $a         = 0;
    $b         = 0;
    settype($length, 'integer');
    settype($rndstring, 'string');
    settype($a, 'integer');
    settype($b, 'integer');
    for ($a = 0; $a <= $length; $a++) {
        $b = rand(0, strlen($template) - 1);
        $rndstring .= $template[$b];
    }
    return $rndstring;
}

/**
 * Strip a string from the end of a string.
 * Is there no such function in PHP?
 *
 * @param string $str      The input string.
 * @param string $remove   OPTIONAL string to remove.
 *
 * @return string the modified string.
 */
function PU_rightTrim($str, $remove = null)
{
    $str    = (string)$str;
    $remove = (string)$remove;
    if (empty($remove)) {
        return rtrim($str);
    }
    $len    = strlen($remove);
    $offset = strlen($str) - $len;
    while ($offset > 0 && $offset == strpos($str, $remove, $offset)) {
        $str    = substr($str, 0, $offset);
        $offset = strlen($str) - $len;
    }
    return rtrim($str);
}

/**
 * This method simply renames a string to safe unix "file" naming conventions.
 *
 * @param string $name
 * @param string $replace Replace odd characters with what?
 *
 * @return string
 */
function PU_safeName($name, $replace = '-')
{
    $search            = array('--', '&trade;', '&quot;', '!', '@', '#', '$', '%', '^', '&', '*', '(', ')', '+', '{', '}', '|', ':', '"', '<', '>', '?', '[', ']', '\\', ';', "'", ',', '.', '/', '*', '+', '~', '`', '=', ' ');
    $new_replaced_name = strtolower(str_replace($search, $replace, $name));
    if (!empty($new_replaced_name)) {
        return $new_replaced_name;
    } else {
        return false;
    }
}

/**
 * Replaces accents with plain text for a given string.
 *
 * @param string $string
 * @return string
 */
function PU_replaceAccents($string)
{
    return str_replace(array('à', 'á', 'â', 'ã', 'ä', 'ç', 'è', 'é', 'ê', 'ë', 'ì', 'í', 'î', 'ï', 'ñ', 'ò', 'ó', 'ô', 'õ', 'ö', 'ù', 'ú', 'û', 'ü', 'ý', 'ÿ', 'À', 'Á', 'Â', 'Ã', 'Ä', 'Ç', 'È', 'É', 'Ê', 'Ë', 'Ì', 'Í', 'Î', 'Ï', 'Ñ', 'Ò', 'Ó', 'Ô', 'Õ', 'Ö', 'Ù', 'Ú', 'Û', 'Ü', 'Ý'), array('a', 'a', 'a', 'a', 'a', 'c', 'e', 'e', 'e', 'e', 'i', 'i', 'i', 'i', 'n', 'o', 'o', 'o', 'o', 'o', 'u', 'u', 'u', 'u', 'y', 'y', 'A', 'A', 'A', 'A', 'A', 'C', 'E', 'E', 'E', 'E', 'I', 'I', 'I', 'I', 'N', 'O', 'O', 'O', 'O', 'O', 'U', 'U', 'U', 'U', 'Y'), $string);
}

/**
 * This is a handy little function to strip out a string between two specified pieces of text.
 * This could be used to parse XML text, bbCode, or any other delimited code/text for that matter.
 * Can also return all text with replaced string between tags.
 *
 * @param string $string
 * @param string $start
 * @param string $end
 * @param string $replace Use %s to be replaced with the string between tags.
 * @param string $replace_char
 * @return string
 */
function PU_SearchAndReplaceBetween($string, $start, $end, $replace = '', $replace_char = '%')
{
    $ini = strpos($string, $start);
    if ($ini === false) return $string;
    $ini += strlen($start);
    $len            = strpos($string, $end, $ini) - $ini;
    $string_between = substr($string, $ini, $len);
    if (!empty($replace)) {
        if ($replace_char == '%') {
            $replaced_text = sprintf($replace, $string_between);
            return str_replace($start . $string_between . $end, $replaced_text, $string);
        } else {
            $replaced_text = str_replace($replace_char, $string_between, $replace);
            return str_replace($start . $string_between . $end, $replaced_text, $string);
        }
    } else {
        return $string_between;
    }
}

/**
 * Sets the header to correct status code.
 *
 * @param int $code
 * @param string $message
 * @return int|null
 */
function PU_silentHeaderStatus($code = null, $message = null) {
    if ($code !== NULL) {
        switch ($code) {
            case 100: $text = 'Continue'; break;
            case 101: $text = 'Switching Protocols'; break;
            case 200: $text = 'OK'; break;
            case 201: $text = 'Created'; break;
            case 202: $text = 'Accepted'; break;
            case 203: $text = 'Non-Authoritative Information'; break;
            case 204: $text = 'No Content'; break;
            case 205: $text = 'Reset Content'; break;
            case 206: $text = 'Partial Content'; break;
            case 300: $text = 'Multiple Choices'; break;
            case 301: $text = 'Moved Permanently'; break;
            case 302: $text = 'Moved Temporarily'; break;
            case 303: $text = 'See Other'; break;
            case 304: $text = 'Not Modified'; break;
            case 305: $text = 'Use Proxy'; break;
            case 400: $text = 'Bad Request'; break;
            case 401: $text = 'Unauthorized'; break;
            case 402: $text = 'Payment Required'; break;
            case 403: $text = 'Forbidden'; break;
            case 404: $text = 'Not Found'; break;
            case 405: $text = 'Method Not Allowed'; break;
            case 406: $text = 'Not Acceptable'; break;
            case 407: $text = 'Proxy Authentication Required'; break;
            case 408: $text = 'Request Time-out'; break;
            case 409: $text = 'Conflict'; break;
            case 410: $text = 'Gone'; break;
            case 411: $text = 'Length Required'; break;
            case 412: $text = 'Precondition Failed'; break;
            case 413: $text = 'Request Entity Too Large'; break;
            case 414: $text = 'Request-URI Too Large'; break;
            case 415: $text = 'Unsupported Media Type'; break;
            case 418: $text = 'Spambot detected'; break;
            case 500: $text = 'Internal Server Error'; break;
            case 501: $text = 'Not Implemented'; break;
            case 502: $text = 'Bad Gateway'; break;
            case 503: $text = 'Service Unavailable'; break;
            case 504: $text = 'Gateway Time-out'; break;
            case 505: $text = 'HTTP Version not supported'; break;
            default:
                exit('Unknown http status code "' . htmlentities($code) . '"');
                break;
        }
        $protocol = (isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0');
        $message_ = (empty($message)) ? $text : $message;
        PU_silentHeader($protocol . ' ' . $code . ' ' . $message_);
        PU_silentHeader("Status: " . $code);
        $GLOBALS['http_response_code'] = $code;
    } else {
        $code = (isset($GLOBALS['http_response_code']) ? $GLOBALS['http_response_code'] : 200);
    }
    return $code;
}

/**
 * Pack all available environment variable into a DB safe string
 * Useful mainly for log functions
 *
 * @return string
 */
function PU_packEnv()
{
    $env = array(
        'POST'    => $_POST,
        'GET'     => $_GET,
        'REQUEST' => $_REQUEST,
        'SERVER'  => $_SERVER,
        'COOKIE'  => $_COOKIE,
        'SESSION' => $_SESSION,
        'ENV'     => $_ENV
    );
    $env = addslashes(serialize($env));
    return $env;
}

/**
 * Logs the specified string to the specified file.
 *
 * @param string $text     The text you wish to log.
 * @param string $filename The filename to which to log the string to. "debug.log" is used if not specified.
 */
function PU_debugLog($text, $filename = '')
{
    if (empty($filename)) $filename = 'write/logs/debug.log';
    error_log(date('Y-m-d H:i:s') . ' - ' . $text . "\n", 3, $filename);
}

/**
 * Flattens the given $path and ensure it's below the given root
 * The goal is to avoid getting access to files outside the web site tree
 *
 * @param string $path
 * @param string $root
 * @return string|bool the actual path or false
 */
function PU_safeSubpath($path, $root)
{
    if (substr($path, 0, 1) != '/') {
        $path = $root . '/' . $path;
    }
    error_log('testing ' . $path . ' against ' . $root);
    $path   = realpath($path);
    $result = (substr($path, 0, strlen($root)) == $root) ? $path : false;

    return $result;
}

/**
 * Creates a "secret" version of the password
 *
 * @param string $password the clear password
 * @return string the hashed password
 */
function PU_hashPassword($password = '')
{
    return empty($password) ? '*' : md5($password);
}

/**
 * Encrypts a string with the configuration key provided.
 *
 * @param string $string
 * @param string $key
 * @return string
 */
function PU_encrypt($string, $key)
{
    $result = false;
    for ($i = 0; $i < strlen($string); $i++) {
        $char    = substr($string, $i, 1);
        $keychar = substr($this->configuration['crypt_key'], ($i % strlen($key)) - 1, 1);
        $char    = chr(ord($char) + ord($keychar));
        $result .= $char;
    }
    return urlencode(base64_encode($result));
}

/**
 * Decrypts a string with the configuration key provided.
 *
 * @param string $string
 * @param string $key
 * @return string
 */
function PU_decrypt($string, $key)
{
    $result = false;
    $string = base64_decode(urldecode($string));
    for ($i = 0; $i < strlen($string); $i++) {
        $char    = substr($string, $i, 1);
        $keychar = substr($this->configuration['crypt_key'], ($i % strlen($key)) - 1, 1);
        $char    = chr(ord($char) - ord($keychar));
        $result .= $char;
    }
    return $result;
}

/**
 * Returns the numerical value of the given value
 * Equivalent of intval() but safe for large number
 *
 * @param mixed $value
 * @return int
 */
function numval($value)
{
    return is_numeric($value) ? $value : 0;
}


///////////////////////// WINDOWS COMPATIBILITY FUNCTIONS //////////////////////////////
// thanks to me at rowanlewis dot com (http://fr2.php.net/manual/en/function.fnmatch.php)

if (!function_exists('fnmatch')) {
    define('FNM_PATHNAME', 1);
    define('FNM_NOESCAPE', 2);
    define('FNM_PERIOD', 4);
    define('FNM_CASEFOLD', 16);

    function fnmatch($pattern, $string, $flags = 0)
    {
        return pcre_fnmatch($pattern, $string, $flags);
    }

}

function pcre_fnmatch($pattern, $string, $flags = 0)
{
    $modifiers  = null;
    $transforms = array(
        '\*'   => '.*',
        '\?'   => '.',
        '\[\!' => '[^',
        '\['   => '[',
        '\]'   => ']',
        '\.'   => '\.',
        '\\'   => '\\\\'
    );

    // Forward slash in string must be in pattern:
    if ($flags & FNM_PATHNAME) {
        $transforms['\*'] = '[^/]*';
    }

    // Back slash should not be escaped:
    if ($flags & FNM_NOESCAPE) {
        unset($transforms['\\']);
    }

    // Perform case insensitive match:
    if ($flags & FNM_CASEFOLD) {
        $modifiers .= 'i';
    }

    // Period at start must be the same as pattern:
    if ($flags & FNM_PERIOD) {
        if (strpos($string, '.') === 0 && strpos($pattern, '.') !== 0)
            return false;
    }

    $pattern = '#^'
        . strtr(preg_quote($pattern, '#'), $transforms)
        . '$#'
        . $modifiers;

    return (boolean)preg_match($pattern, $string);
}

