<?php

class PHPDS_view extends PHPDS_dependant
{
    /**
     * Contains the active plugin view object.
     * @var object
     */
    public $instance;

    /**
     * Array of mixed values to be used in php view class.
     * @var mixed
     */
    public $set;

    /**
     * Checks if main class is extended, if not, disable master set property.
     * @var bool
     */
    public $extends = false;

    /**
     * Sets mustache variables to be passed to it.
     *
     * @param mixed $name
     * @param mixed $value
     */
    public function set($name, $value)
    {
        if ($this->extends && is_string($name)) {
            if (is_object($value)) {
                $this->set          = new stdClass();
                $this->set->{$name} = $value;
            } else {
                $this->set[$name] = $value;
            }
        }
        if (isset($this->instance) && is_object($this->instance))
            $this->instance->set[$name] = $value;
    }

    /**
     * Looks up and returns data assigned to it in controller with $this->set();
     *
     * @param string $name
     * @return mixed
     */
    public function get($name)
    {
        if (!empty($this->set->{$name}))
            return $this->set->{$name};
        else
            return $this->set[$name];
    }

    /**
     * Loads the default or custom template (tpl) file and prints it out.
     * Enter the template file for appropriate script here.
     *
     * @param string $load_view Load an alternative view directly.
     * @return string
     */
    public function show($load_view = '')
    {
        if (isset($this->instance) && is_object($this->instance))
            echo $this->instance->show($load_view);
        else
            echo $this->execute();
    }

    /**
     * Loads the default or custom template (tpl) file and returns it.
     * Works well where blocks of html from ajax requests are wanted.
     *
     * @param string $load_view Load an alternative view directly.
     * @return string
     */
    public function getView($load_view = '')
    {
        if (isset($this->instance) && is_object($this->instance))
            return $this->instance->getView($load_view);
        else
            return $this->execute();
    }

    /**
     * Main execution point for class view.
     * Will execute automatically.
     */
    public function run()
    {
        $this->execute();
    }

    /**
     * This method is meant to be the entry point of your class. Most checks and cleanup should have been done by the time it's executed
     *
     * @return mixed if you return "false" output will be truncated
     */
    public function execute()
    {
        // Your code here
    }
}
