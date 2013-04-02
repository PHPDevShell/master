<?php

class PHPDS_view extends PHPDS_dependant
{
    /**
     * Contains the current active theme.
     *
     * @var string
     */
    public $theme;

    /**
     * @var object
     */
    protected $viewPlugin;

    /**
     * Constructor
     *
     * @return parent
     */
    public function construct()
    {
        $this->theme = $this->core->activeTemplate();
        return parent::construct();
    }

    /**
     * Class loads an active view plugin, this class can be overwritten.
     */
    public function plugin()
    {
        $this->viewPlugin = $this->factory('views');
    }

    /**
     * Sets mustache variables to be passed to it.
     *
     * @param mixed $var
     * @param mixed $value
     */
    public function set($var, $value)
    {
        $this->viewPlugin->set[$var] = $value;
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
        echo $this->viewPlugin->show($load_view);
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
        return $this->viewPlugin->getView($load_view);
    }

    /**
     * Looks up and returns data assigned to it in controller with $this->set();
     *
     * @param string $name
     * @return mixed
     */
    public function get($name)
    {
        if (!empty($this->core->toView->{$name}))
            return $this->core->toView->{$name};
        else
            return $this->core->toView[$name];
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
