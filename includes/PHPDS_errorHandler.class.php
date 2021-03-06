<?php

/**
 * Error handling function with use of FirePHP
 *
 * @author Grzegorz Godlewski redone by Jason Schoeman
 */
include_once dirname(__FILE__) . '/PHPDS_exception.class.php';

/**
 * Error handler class
 */
class PHPDS_errorHandler extends PHPDS_dependant
{
    /**
     * Should error handler ignores notices.
     * @var bool
     */
    protected $ignore_notices = false;
    /**
     * Should error handler ignores warnings.
     * @var bool
     */
    protected $ignore_warnings = false;
    /**
     * Should warning are handled as Exceptions.
     * @var bool
     */
    protected $warningsAreFatal = true;
    /**
     * Should notices are handled as Exceptions.
     * @var bool
     */
    protected $noticesAreFatal = false;
    /**
     * Log to syslog using error_log()
     * @var bool
     */
    protected $serverlog = true;
    /**
     * Log folder location.
     * @var string
     */
    protected $file = '';
    /**
     * Mail address for exception errors.
     * @var string
     */
    protected $mail = '';
    /**
     * Should error handler display error to output.
     * @var bool
     */
    protected $display = true;
    /**
     * Should error handler send error to firebug.
     * @var bool
     */
    protected $firebug = false;
    /**
     * Firephp instance.
     * @var object
     */
    protected $firephp = null;
    /**
     * System switch to halt exceptions as something serious is wrong.
     * @var bool
     */
    protected $I_give_up = false;
    /**
     * Error handler handles errors differently in production (safe mode).
     * @var bool
     */
    protected $production = false;
    /**
     * Should a backtrace be created. (Causes problems some times)
     * @var bool
     */
    public $error_backtrace = false;
    /**
     * Array of iPHPDS_errorConduit
     * @var array
     */
    protected $conduits = array();
    /**
     * In case they are error AFTER the exception reported is triggered
     * @var array
     */
    protected $crumbs = array();

    /**
     * Constructor
     */
    public function construct()
    {
        if ($this->PHPDS_dependance()->isEmbedded()) {
            return;
        }

        $flags = E_ALL;
        $cfg = $this->configuration;
        $this->production = !empty($cfg['production']);

        if ($this->production) {
            $cfg['debug']['enable']  = false;
            $cfg['error']['firePHP'] = false;
            $cfg['error']['display'] = false;
            $cfg['debug']['level']   = 1;
        }

        if (isset($cfg['error'])) {
            $configuration = $cfg['error'];

            if ($configuration['mask']) $flags = intval($configuration['mask']);

            if (isset($configuration['ignore_notices'])) $this->ignore_notices =
                !empty($configuration['ignore_notices']);
            if (isset($configuration['ignore_warnings'])) $this->ignore_warnings =
                !empty($configuration['ignore_warnings']);
            if (isset($configuration['warningsAreFatal'])) $this->warningsAreFatal =
                !empty($configuration['warningsAreFatal']);
            if (isset($configuration['noticesAreFatal'])) $this->noticesAreFatal =
                !empty($configuration['noticesAreFatal']);

            if (!empty($configuration['serverlog'])) $this->serverlog = !empty($configuration['serverlog']);
            if (!empty($configuration['file_log_dir'])) $this->file = $configuration['file_log_dir'];
            if (!empty($configuration['email_critical'])) $this->mail = $configuration['email_critical'];
            if (isset($configuration['display'])) $this->display = !empty($configuration['display']);
            if (isset($configuration['firePHP'])) $this->firebug = !empty($configuration['firePHP']);

            if ($this->firebug) {
                require_once ('debug/FirePHPCore/FirePHP.class.php');
                $this->firephp = FirePHP::getInstance(true);
            }

            if (!empty($this->ignore_notices)) {
                $flags = $flags ^ E_NOTICE;
                $flags = $flags ^ E_USER_NOTICE;
            }
            if (!empty($this->ignore_warnings)) {
                $flags = $flags ^ E_WARNING;
                $flags = $flags ^ E_USER_WARNING;
            }

            if (isset($configuration['conduits']) && is_array($configuration['conduits'])) {
                foreach ($configuration['conduits'] as $conduit) {
                    $this->addConduit($conduit);
                }
            }
        }

        error_reporting($flags);
        set_error_handler(array($this, "doHandleError"), $flags);
        set_exception_handler(array($this, "doHandleException"));
        register_shutdown_function(array($this, "doHandleShutdown"));
    }

    public function getFirePHP()
    {
        return $this->firephp;
    }

    public function addConduit($conduitName)
    {
        if (!is_string($conduitName)) {
            throw new PHPDS_exception('New conduit name must be a string.');
        }
        if (empty($this->conduits[$conduitName])) {
            $this->conduits[$conduitName] = $this->factory($conduitName); //iPHPDS_errorConduit
        }
        return $this;
    }

    /**
     * Handle critical errors (if set to)
     */
    public function doHandleShutdown()
    {
        if ($this->I_give_up) return; // avoid re-entrance

        $error   = error_get_last();
        $errmask = error_reporting();
        if ($errmask & $error['type']) {
            $this->doHandleException(new PHPDS_fatalError());
        }

        $this->I_give_up = true;
    }

    /**
     * Exception handler
     *
     * @param Exception $ex Exception
     */
    public function doHandleException(Exception $ex)
    {
        // Do an immediate database rollback.
        if (!empty($this->db->autoTransact)) $this->db->rollBack();

        if ($this->I_give_up) return;

        if (is_a($ex, 'PHPDS_exception')) {
            $ex = $ex->getRealException();
        }

        try {
            $errMsg    = $ex->getMessage();
            $backtrace = $ex->getTrace();
            if (!$ex instanceof errorHandler) {
                $errMsg_subject = get_class($ex) . ': ' . $errMsg;
                $errMsg         = $errMsg_subject . " file : {$ex->getFile()} (line {$ex->getLine()})";
                array_unshift($backtrace,
                    array('file' => $ex->getFile(), 'line' => $ex->getLine(),
                          'function' => 'throw ' . get_class($ex), 'args' => array($errMsg, $ex->getCode())));
            }
            $errMsg .= ' | ' . date("Y-m-d H:i:s");
            if (empty($_SERVER['HTTP_HOST'])) {
                $errMsg .= ' | ' . implode(' ', $_SERVER['argv']);
            } else {
                $errMsg .= ' | ' . $_SERVER['HTTP_HOST'] .
                    " (" . $_SERVER['SERVER_ADDR'] . ":" . $_SERVER['SERVER_PORT'] . ")" . "\n";
            }
            if ($this->error_backtrace == true) {
                $trace = PHPDS_backtrace::asText(2, $backtrace);
            } else {
                $trace = false;
            }

            // This will take care of Firebug (textual), in-page alerts, and syslog
            $this->conductor($errMsg, PHPDS_debug::ERROR);


            // SENDING THROUGH FIREBUG (extended info with backtrace)
            if ($this->firephp && !$this->production && !headers_sent()) {
                $this->firephp->fb($ex);
            }

            ///// DISPLAYING ON THE WEB PAGE
            try {
                // in production we capture the whole output but display only a generic message
                $output = $this->showException($ex, !$this->production);
            } catch (Exception $e) {
                $output = 'An exception occured in the exception display.' . $e;
                error_log('An exception occured in the exception display.' . $e);
            }

            ///// WRITING TO A LOG FILE
            if ($this->file) {
                $dir = BASEPATH . $this->file;

                if (is_writable($dir)) {
                    $prefix   = 'error.' . date('Y-m-d');
                    $filepath = $dir . $prefix . '.log';

                    $unique_html_name = $prefix . '.' . uniqid() . '.html';
                    $detailedfilepath = $dir . $unique_html_name;
                    $detailedurlpath  = $this->configuration['absolute_url'] . '/' . $this->configuration['error']['file_log_dir'] . $unique_html_name;

                    $fp = fopen($filepath, "a+");
                    if (flock($fp, LOCK_EX)) {
                        fwrite($fp, "----\n$detailedfilepath | $detailedurlpath | " . $errMsg . "\n" . $trace . "\n");
                        flock($fp, LOCK_UN);
                    }
                    fclose($fp);

                    /// STORE EXTENDED REPORT
                    $fp = fopen($detailedfilepath, "a+");
                    if (flock($fp, LOCK_EX)) {
                        fwrite($fp, $output);
                        flock($fp, LOCK_UN);
                    }
                    fclose($fp);
                }
            }

            // SENDING AN EMAIL
            if ($this->mail && !empty($errMsg_subject) && !empty($detailedfilepath) && !empty($detailedurlpath)) {
                $headers = 'MIME-Version: 1.0' . "\n" . 'Content-type: text/plain; charset=UTF-8' . "\n" . 'From: Error Handler <' . $this->mail . ">\n";
                @mail("$this->mail", "$errMsg_subject", "$errMsg\r\n$trace\r\n----\r\n$detailedfilepath\r\n----\r\n$detailedurlpath", $headers);
            }

        } catch (Exception $e) {
            // something bad happened in the exception handler, we build a new exception to describe that in the error page
            $this->I_give_up = true;
            $msg             = _('An exception occured in the exception handler. URL was: "' . $_SERVER['REQUEST_URI'] . '"');
            new PHPDS_exception($msg, 0, $e);
            $this->notif->add($msg);
        }

        //restore_error_handler(); // we won't handle any more errors
        exit(); // bye bye
    }

    public function doHandleError($errno, $errstr, $errfile, $errline)
    {
        $errmask = error_reporting();
        if (!($errmask & $errno)) { // if error has been masked with error_reporting() or suppressed with an @
            return false;
        }

        if (!$this->I_give_up) {
            // in these two cases, an new exception is thrown so the catcher from the original code can be triggered
            if (((E_WARNING == $errno) || (E_STRICT == $errno)) && $this->warningsAreFatal) {
                throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
            }
            if ((E_NOTICE == $errno) && $this->noticesAreFatal) {
                throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
            }
        }

        $errMsg = $errstr . " ($errfile line $errline )";

        switch ($errno) {
            case E_WARNING:
                $level = PHPDS_debug::WARN;
                break;
            case E_NOTICE:
                $level = PHPDS_debug::INFO;
                break;
            default:
                $level = 0;
        }

        $this->conductor($errMsg, $level);

        if ($this->I_give_up) {
            $this->crumbs[] = $errMsg;
        }


        return true; // to reset internal error
    }

    public function conductor($msg, $level = 0, $label = '', $code = null)
    {
        // first send through registered conduits, as they may report even in production
        foreach ($this->conduits as $conduit) {
            $conduit->message('', $msg, $level, $label, $code);
        }
        // then report through built-in conduits, only if not in production
        if ($this->production) return;

        $template = $this->PHPDS_dependance()->PHPDS_template(false);

        if (empty($template)) {
            $template = false;
        }

        $emsg       = empty($label) ? $msg : "($label) $msg";
        $ajax_error = (PU_isAJAX()) ? true : false;

        switch ($level) {
            case PHPDS_debug::ERROR:
                if ($this->display && !$ajax_error) {
                    if (!method_exists($template, 'error')) echo $this->message($emsg);
                }

                if ($this->firephp && !headers_sent()) $this->firephp->error($msg, $label);

                if ($this->serverlog) $this->error_log('ERROR', $emsg);
                break;

            case PHPDS_debug::WARN:
                if ($this->display && !$ajax_error) {
                    if (method_exists($template, 'warning')) echo $template->warning($emsg, 'return');
                    else echo $this->message($emsg);
                }

                if ($this->firephp && !headers_sent()) $this->firephp->warn($msg, $label);

                if ($this->serverlog) $this->error_log('WARNING', $emsg);
                break;

            case PHPDS_debug::INFO:

                if ($this->display && !$ajax_error) {
                    if (method_exists($template, 'notice')) echo $template->notice($emsg, 'return');
                    else echo $this->message($emsg);
                }

                if ($this->firephp && !headers_sent()) $this->firephp->info($msg, $label);

                if ($this->serverlog) $this->error_log('NOTICE', $emsg);
                break;

            case PHPDS_debug::DEBUG:
                if ($this->display && !$this->firephp && !$ajax_error) {
                    if (method_exists($template, 'debug')) echo $template->debug($emsg, 'return');
                    else echo $this->message($emsg);
                }

                if ($this->firephp && !headers_sent()) $this->firephp->log($msg, $label);

                if ($this->serverlog) $this->error_log('DEBUG', $emsg);
                break;

            default:
                if ($this->display && !$ajax_error) {
                    if (method_exists($template, 'note')) echo $template->note($emsg, 'return');
                    else echo $this->message($emsg);
                }

                if ($this->firephp && !headers_sent()) $this->firephp->log($msg, $label);

                if ($this->serverlog) $this->error_log('LOG', $emsg);
                break;
        }

        return $this;
    }


    function textualize($text)
    {
        $text = preg_replace('/[\x00-\x1F]+/', ' ', $text);
        return $text;
    }

    function error_log($prefix, $data)
    {
        if (is_array($data)) foreach ($data as $text) $this->error_log('-', $text);
        else error_log('[ PHPDS ] ' . $prefix . ': ' . $this->textualize($data));
    }

    public static function getArgument($arg)
    {
        switch (strtolower(gettype($arg))) {
            case 'string':
                return ('"' . str_replace(array("\n", "\""), array('', '"'), $arg) . '"');
            case 'boolean':
                return (bool)$arg;
            case 'object':
                return 'object(' . get_class($arg) . ')';
            case 'array':
                return 'array[' . count($arg) . ']';
            case 'resource':
                return 'resource(' . get_resource_type($arg) . ')';
            default:
                return var_export($arg, true);
        }
    }

    public function message($messages, $trace = '')
    {
        // Simple styled message.
        if (!empty($trace)) $trace = "=>[$trace]";
        if (is_string($messages)) {
          return $this->textualize($messages) . "$trace";
        }
        return null;
    }


    /**
     * Display an Exception
     * This function will load a predefined template page (in PHP form) in order to warn the user something has gone wrong.
     * If an exception is provided, it will be detailed as much as possible ; if not, only a generic message will be displayed
     *
     * @return string the whole output
     *
     * @param Exception $e        (optional) the exception to explain
     * @param boolean   $detailed whether the details should be displayed or replaced y a generic message
     */
    public function showException(Exception $e = null, $detailed = true)
    {
        // we stop on the first unhandled error
        $this->I_give_up = true;

        if ($this->PHPDS_dependance()->isEmbedded()) return;

        PU_cleanBuffers();
        $lineno   = 0;
        $filepath = 'unknown';
        if (is_a($e, 'Exception')) {
            $lineno   = $e->getLine();
            $filepath = $e->getFile();

            $trace  = (is_a($e, 'PHPDS_exception')) ? $e->getExtendedTrace() : $e->getTrace();
            $ignore = (is_a($e, 'PHPDS_exception')) ? $e->getIgnoreLines() : -1;

            ///////////////////////////////////
            // Used in error theme file.
            $theme['filefragment'] = PHPDS_backtrace::fetchCodeFragment($filepath, $lineno);
            if (isset($trace[$ignore])) {
                $frame = $trace[$ignore];
                ///////////////////////////////////
                // Used in error theme file.
                $theme['filefragment'] = PHPDS_backtrace::fetchCodeFragment($frame['file'], $frame['line']);
            } else {
                $ignore = -1;
            }

            $message = $e->getMessage();
            ///////////////////////////////////
            // Used in error theme file.
            $theme['code'] = $e->getCode();
            ///////////////////////////////////
            // Used in error theme file.
            $theme['extendedMessage'] = (is_a($e, 'PHPDS_exception')) ? $e->getExtendedMessage() : '';
            $config          = $this->configuration;
            if (!empty($config)) {
                if (isset($config['config_files_used']))
                    $conf['used'] = PU_dumpArray($config['config_files_used']);

                if (isset($config['config_files_missing']))
                    $conf['missing'] = PU_dumpArray($config['config_files_missing']);
            }
            $theme['bt'] = PHPDS_backtrace::asHTML($ignore, $trace);
        } else {
            $message = "Unknown exception...";
            $code    = null;
        }

        // now use the theme's error page to format the actual display
        $protocol = empty($_SERVER['HTTPS']) ? 'http://' : 'https://';
        // Need this for absolute URL configuration to be sef safe.
        ///////////////////////////////////
        // Used in error theme file.
        $theme['aurl'] = $protocol . $_SERVER['SERVER_NAME'] . str_replace('/index.php', '', $_SERVER['PHP_SELF']);

        if (PU_isAJAX()) {
            // Have this here otherwise you wont know what query caused an ajax error for instance.
            if (! empty($theme['extendedMessage'])) {
                $message_extended  = strip_tags($theme['extendedMessage']);
            } else {
                $message_extended  = '';
            }
            // If the error occurred during an AJAX request, we'll send back a lightweight ouput
            $message = $this->display ? "$message - file $filepath line $lineno || " . $message_extended : 'Error Concealed - Disabled in config';
            PU_silentHeader('Status: 500 ' . $message);
            PU_silentHeader('HTTP/1.1 500 ' . $message);
            print $message;
            return null;
        } else {
            $theme['message'] = $message;
            ob_start();
            // Load error page: $e is the handled exception
            include BASEPATH . 'themes/default/error.php';
            $output = ob_get_clean();

            if (!empty($this->crumbs)) {
                $output = str_replace('<crumbs/>', implode("\n", $this->crumbs), $output);
            }

            // for a regular request, we present a nicely formatted html page; if provided,
            // an extended description of the error is displayed
            if ($detailed) {
                echo $output;
            } else {
                ///////////////////////////////////
                // Used in error theme file.
                $theme['message'] = '';
                require BASEPATH . 'themes/cloud/error.php'; // $message being empty, only a genetic message is output
            }
            return $output;
        }
    }
}

/**
 * Generate a pretty (formatted to be read) backtrace, skipping the first lines if asked
 *
 * @return string
 */
class PHPDS_backtrace
{
    /**
     * Returns a formatted string with the last line of the backtrace
     *
     * @param array $backtrace (optional) a backtrace array, like debug_backtrace() gives
     * @return string
     */
    public static function lastLine($backtrace = null)
    {
        if (empty($backtrace)) $backtrace = debug_backtrace();

        $b      = $backtrace[1];
        $result = 'at line ' . $b['line'] . ' of file "' . $b['file'] . '"';
        //if ($b['function']) $result .= ' in function "'.$b['function'].'"';

        return $result;
    }

    /**
     * Returns a text-only backtrace, suitable for text-only supports (like logfiles)
     *
     * @param integer $ignore    number of lines to ignore at the beginning of the backtrace
     * @param array   $backtrace (optional) a backtrace array, like debug_backtrace() gives
     * @return string
     */
    public static function asText($ignore = 0, $backtrace = null)
    {
        if (empty($backtrace)) $backtrace = debug_backtrace();

        $ignore = intval($ignore);

        $trace = '';
        foreach ($backtrace as $v) {
            if (empty($v['file'])) $v['file'] = '';
            if (empty($v['line'])) $v['line'] = '';
            $v['file'] = preg_replace('!^' . $_SERVER['DOCUMENT_ROOT'] . '!', '', $v['file']);
            $trace .= $v['file'] . "\t" . $v['line'] . "\t";
            if (isset($v['class'])) {
                $trace .= $v['class'] . '::' . $v['function'] . '(';
                if (isset($v['args'])) {
                    $errRow[]  = $v['args'];
                    $separator = '';
                    foreach ($v['args'] as $arg) {
                        $trace .= $separator . PHPDS_errorHandler::getArgument($arg);
                        $separator = ', ';
                    }
                }
                $trace .= ')';
            } elseif (isset($v['function'])) {
                $trace .= $v['function'] . '(';
                $errRow[] = $v['function'];
                if (!empty($v['args'])) {
                    $errRow[]  = $v['args'];
                    $separator = '';
                    foreach ($v['args'] as $arg) {
                        $trace .= $separator . PHPDS_errorHandler::getArgument($arg);
                        $separator = ', ';
                    }
                }
                $trace .= ')';
            }
            $trace .= "\n";
        }

        return $trace;
    }

    /**
     * Returns a html backtrace, suitable for displaying in a browser
     *
     * @param integer $ignore    number of a stack frame to highlight
     * @param array   $backtrace (optional) a backtrace array, like debug_backtrace() gives
     * @return string
     */
    public static function asHTML($ignore = -1, $backtrace = null)
    {
        if (empty($backtrace)) $backtrace = debug_backtrace();

        $ignore = intval($ignore);

        $internals = get_defined_functions();
        $internals = $internals['internal'];

        $protocol = empty($_SERVER['HTTPS']) ? 'http://' : 'https://';
        // Need this for absolute URL configuration to be sef safe.
        $aurl = $protocol . $_SERVER['SERVER_NAME'] . str_replace('/index.php', '', $_SERVER['PHP_SELF']);

        $trace = '';
        $i     = 0;
        foreach ($backtrace as $v) {
            $i++;
            $class_collapsbody = "accordionbody" . $i;
            $ignore--;

            $class = (0 == $ignore) ? 'active' : '';

            $trace .= '<tr class="' . $class . '">';

            if (empty($v['file'])) $v['file'] = '';
            if (empty($v['line'])) $v['line'] = '';
            $filepath = preg_replace('!^' . $_SERVER['DOCUMENT_ROOT'] . '/!', '<span class="bt-line-number">...</span>', $v['file']);

            $trace .= '<td>' . $filepath . '</td><td>' . $v['line'] . '</td><td>';

            if (isset($v['class'])) {
                $fct  = $v['class'] . '::' . $v['function'];
                $call = $fct . '(';
                if (isset($v['args'])) {
                    $errRow[]  = $v['args'];
                    $separator = '';
                    foreach ($v['args'] as $arg) {
                        $call .= $separator . PHPDS_errorHandler::getArgument($arg);
                        $separator = ', ';
                    }
                }
                $call .= ')';
                $call = PHPDS_backtrace::highlightString(preg_replace("/,/", ", ", $call));
                $trace .= $call;
            } elseif (isset($v['function'])) {
                $fct      = $v['function'];
                $call     = $fct . '(';
                $errRow[] = $v['function'];
                if (!empty($v['args'])) {
                    $errRow[]  = $v['args'];
                    $separator = '';
                    foreach ($v['args'] as $arg) {
                        $call .= $separator . PHPDS_errorHandler::getArgument($arg);
                        $separator = ', ';
                    }
                }
                $call .= ')';
                $call = PHPDS_backtrace::highlightString(preg_replace("/,/", ", ", $call));
                /*if (!empty($internals[$fct]))*/
                $call = '<a href="http://www.php.net/manual-lookup.php?lang=en&pattern=' . urlencode($fct) . '" target="_blank"><img src="' . $aurl . '/themes/default/images/icons-16/book-question.png" /></a>&nbsp;' . $call;
                $trace .= $call;

            }
            $backtrace__ = PHPDS_backtrace::fetchCodeFragment($v['file'], $v['line']);
            $trace .= '</td><td><button type="button" class="btn" data-toggle="collapse" data-target="#' . $class_collapsbody . '"><i class="icon-eye-open"></i></button></td></tr>';
            $trace .= '<tr class="' . $class . '">';
            $trace .= <<<HTML
                    <td colspan="4">
                        <div id="{$class_collapsbody}" class="accordion-body collapse">
                            <pre>{$backtrace__}</pre>
                        </div>
                    </td>
                </tr>
HTML;
        }

        return $trace;
    }

    /**
     * Format a html output of an code fragment (seven lines before and after) around the give line of the given source file
     *
     * @param string  $filepath path to the source file
     * @param integer $lineno   line number of the interesting line
     * @return string html formatted string
     */
    public static function fetchCodeFragment($filepath, $lineno)
    {
        if (!empty($filepath) && file_exists($filepath)) {
            $filecontent = file($filepath);
            $start       = max($lineno - 7, 0);
            $end         = min($lineno + 7, count($filecontent));
            $line        = '';

            $fragment = '';
            for ($loop = $start; $loop < $end; $loop++) {
                if (!empty($filecontent[$loop])) {
                    $line = $filecontent[$loop];
                    $line = preg_replace('#\n$#', '', $line);
                    $line = PHPDS_backtrace::highlightString($line, $loop + 1);
                }
                if ($loop == $lineno - 1) $line = '<span class="highlight-error">' . $line . '</span>';

                $fragment .= $line . "\n";
            }
            return $fragment;
        } else return null;
    }

    /**
     * Format the given code string as pretty html
     *
     * @param string  $string the code string to format
     * @param integer $lineno (optional) a line number to prefix
     * @return string
     */
    public static function highlightString($string, $lineno = null)
    {
        if ($lineno) $string = '<span class="bt-line-number">' . $lineno . '.&nbsp;</span>' . "<code class=\"prettyprint code-error-line language-php\">" . htmlentities($string) . "</code>";
        return $string;
    }

    /**
     * Cleans up php info to an appropriate state.
     *
     * @return string
     */
    public static function phpInfo()
    {
        ob_start();
        phpinfo(INFO_VARIABLES + INFO_CONFIGURATION + INFO_ENVIRONMENT);
        $html = ob_get_contents();
        ob_end_clean();

        // Delete styles from output
        $html = preg_replace('#(\n?<style[^>]*?>.*?</style[^>]*?>)|(\n?<style[^>]*?/>)#is', '', $html);
        $html = preg_replace('#(\n?<head[^>]*?>.*?</head[^>]*?>)|(\n?<head[^>]*?/>)#is', '', $html);
        $html = preg_replace('/,/', ', ', $html);
        $html = preg_replace('/::/', ':: ', $html);
        $html = preg_replace('/width=\"600\"/', '', $html);
        $html = preg_replace('/<table/', '<table class="table table-bordered"', $html);
        $html = preg_replace('/\<h1/', '<h3', $html);
        $html = preg_replace('/\<\/h1\>/', '</h3>', $html);
        $html = preg_replace('/\<h2\>/', '<h4>', $html);
        $html = preg_replace('/\<\/h2\>/', '</h4>', $html);
        // Delete DOCTYPE from output
        $html = preg_replace('/<!DOCTYPE html PUBLIC.*?>/is', '', $html);
        // Delete body and html tags
        $html = preg_replace('/<html.*?>.*?<body.*?>/is', '', $html);
        $html = preg_replace('/<\/body><\/html>/is', '', $html);

        return $html;
    }
}



















