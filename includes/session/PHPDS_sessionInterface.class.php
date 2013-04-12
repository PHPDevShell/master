<?php
/**
 * Delphex Web Framework Core
 *
 * @link http://www.delphexonline.com
 * @copyright Copyright (C) 2012 Delphex Technologies CC, All rights reserved.
 * @author Don Schoeman
 *
 * Copyright notice: See readme/notice
 * By using DWF you agree to notice and license, if you dont agree to this notice/license you are not allowed to use DWF.
 *
 */

// Session abstraction for session handlers
interface SessionIntf {
    public function start($storage = null);
    public function save();
    public function set($key, $value);
    public function get($key, $default = null);
    public function flush($force = false);
}