<?php

class PHPDS_debug extends PHPDS_dependant
{
    /* levels of verbosity: the higher, the more data will be sent */
    const DEBUG = 4;
    const INFO  = 3;
    const WARN  = 2;
    const ERROR = 1;
    const LOG   = 0;
    /**
     * Is the data to be sent.
     * @var bool
     */
    protected $enabled = false;
    /**
     * Level of verbosity.
     * @var int
     */
    protected $level = PHPDS_debug::LOG;
    /**
     * Error handler instance.
     * @var object
     */
    protected $conduits = null;

    /**
     * Constructor.
     *
     * @return boolean
     */
    public function construct()
    {
        $this->conduits = $this->errorHandler;

        $configuration = $this->configuration['debug'];
        $this->enabled = empty($configuration['enable']) ? false : true;
        $this->level   = empty($configuration['level']) ? PHPDS_debug::LOG : intval($configuration['level']);

        return true;
    }

    /**
     * Enable or disable the debugger output ; get the current state
     * Note: at this time, the debugger has to be enabled at startup
     *
     * @param boolean $doit (optional) enable (true or disable (false)
     * @return boolean whether it's currently enabled
     */
    function enable($doit = null)
    {
        if (!is_null($doit)) $this->enabled = (boolean)$doit;
        return $this->enabled;
    }

    /**
     * Is this instance sending data?
     *
     * @return boolean
     */
    public function isEnabled()
    {
        return ($this->enabled == true);
    }

    /**
     * Magic method: shortcut to log($ata)
     */
    public function __invoke($data, $label = null)
    {
        $this->debug->log($data, $label);
    }

    /**
     * Dump the content of a variable to the backends
     *
     * @param string $data
     * @param string $label
     */
    public function dump($data, $label = 'data')
    {
        if (!$this->enabled) return;

        if ($this->firephp) $this->firephp->dump($label, $data);
        $this->error('DUMP', $data);
    }

    /**
     * Log the data to the backend with the LOG level (the smallest, most often seen)
     *
     * @param string $data
     * @param string $label
     * @return void|null
     */
    public function log($data, $label = null)
    {
        if (!$this->enabled || ($this->level < PHPDS_debug::LOG)) return null;

        $this->conduits->conductor($data, PHPDS_debug::LOG, $label);
    }

    /**
     * Push Firebug Debug Info
     *
     * @param mixed $data
     * @param string $label
     * @return void|null
     */
    public function debug($data, $label = null)
    {
        if (!$this->enabled || ($this->level < PHPDS_debug::DEBUG)) return null;

        $this->conduits->conductor($data, PHPDS_debug::DEBUG, $label);
    }

    /**
     * Push Firebug Info
     *
     * @param mixed
     * @param string $label
     * @return void|null
     */
    public function info($data, $label = null)
    {
        if (!$this->enabled || ($this->level < PHPDS_debug::INFO)) return null;

        $this->conduits->conductor($data, PHPDS_debug::INFO, $label);
    }

    /**
     * Push Firebug Warning
     *
     * @param mixed $data
     * @param string $label
     * @return void|null
     */
    public function warn($data, $label = null)
    {
        if (!$this->enabled || ($this->level < PHPDS_debug::WARN)) return null;

        $this->conduits->conductor($data, PHPDS_debug::WARN, $label);
    }

    /**
     * Push Firebug Warning
     *
     * @param mixed $data
     * @param string $label
     */
    public function warning($data, $label = null)
    {
        $this->warn($data, $label);
    }

    /**
     * Push the given error to the error system
     *
     * @param mixed $data
     * @param mixed $code
     * @return $this
     */
    public function error($data, $code = null)
    {
        if (!$this->enabled || ($this->level < PHPDS_debug::ERROR)) return null;

        $this->conduits->conductor($data, PHPDS_debug::ERROR, null, $code);

        return $this;
    }

    /**
     * FirePHP Process
     *
     * @param mixed $data
     * @return void
     */
    public function process($data)
    {
        $content = $data['sql'];
        $key     = '[' . $data['counter'] . '] ' . $data['key'] . ' (' . $data['result'] . ')';

        $this->debug->log($content, $key);
    }

}