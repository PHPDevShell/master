<?php

class PHPDS_model extends PHPDS_dependant
{
    /**
     * Contains the active plugin instance object.
     * @var object
     */
    public $model;

    /**
     * Checks if main class is extended, if not, disable master set property.
     * @var bool
     */
    public $extends = false;
}