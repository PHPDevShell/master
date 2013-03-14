<?php

class PHPDS_notif extends PHPDS_dependant
{
    const SILENT  = 0;
    const MESSAGE = 1;
    const URGENT  = 2;

    /**
     * Legacy
     * @var string
     */
    protected $legacy;

    /**
     * Message type constant.
     * @var array
     */
    protected $heritage = array(
        PHPDS_notif::SILENT  => array(),
        PHPDS_notif::MESSAGE => array(),
        PHPDS_notif::URGENT  => array()
    );

    /**
     * The notification var.
     * @var string
     */
    protected $varName = 'PHPDS_notifications';

    /**
     * @param string $message
     * @param int    $priority
     */
    public function add($message, $priority = PHPDS_notif::MESSAGE)
    {
        $this->heritage[$priority][] = $message;
    }

    /**
     * @param int $priority
     * @return array
     */
    public function fetch($priority = PHPDS_notif::MESSAGE)
    {
        $this->import();
        $notifications = array_merge($this->legacy[$priority], $this->heritage[$priority]);
        $this->clear();

        return $notifications;
    }

    /**
     * Fetch message as json
     *
     * @return string
     */
    public function fetchAsJson()
    {
        $notifications = $this->fetch();
        if (is_array($notifications)) {
            foreach ($notifications as $notif) {
                if (!empty($notif[0]) && !empty($notif[1])) {
                    $notif_new_array[] = array('type' => $notif[0], 'message' => $notif[1]);
                }
            }
        }
        if (!empty($notif_new_array)) {
            return json_encode($notif_new_array);
        } else {
            return false;
        }
    }

    /**
     * Destruct
     */
    public function __destruct()
    {
        $this->import();
        $this->save();
    }

    /**
     * Import messages.
     */
    protected function import()
    {
        if (is_null($this->legacy)) {
            $this->legacy = !empty($_SESSION[$this->varName]) ? $_SESSION[$this->varName] : array(
                PHPDS_notif::SILENT  => array(),
                PHPDS_notif::MESSAGE => array(),
                PHPDS_notif::URGENT  => array()
            );
            $this->set(null);
        }
    }

    /**
     * Store message in legacy.
     */
    protected function save()
    {
        $this->set(array_merge($this->legacy, $this->heritage));
    }

    /**
     *
     */
    public function waiting()
    {

    }

    /**
     * Clear all messages.
     */
    public function clear()
    {
        $this->legacy   = null;
        $this->heritage = array();

        $this->set(null);
    }

    /**
     * Set new message.
     * @param string $value
     */
    protected function set($value = null)
    {
        $_SESSION[$this->varName] = $value;
    }
}
