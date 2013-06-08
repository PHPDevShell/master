<?php

class PHPDS_controller extends PHPDS_dependant
{
    /**
     * Stored POST information.
     * @var array
     */
    protected $_POST;
    /**
     * Stored GET information.
     * @var array
     */
    protected $_GET;
    /**
     * Contains view object if it exists.
     * @var object
     */
    public $view;
    /**
     * Direct instance to model plugin.
     * @var object
     */
    public $views;
    /**
     * The views plugin that should be supporting controller view,
     * can be overwritten to only use other plugin view or php.
     * @var string
     */
    public $viewPlugin = 'views';
    /**
     * Contains model object if it exists.
     * @var object
     */
    public $model;
    /**
     * Direct link to model plugin.
     * @var object
     */
    public $models;
    /**
     * The model plugin that should be supporting controller model,
     * can be overwritten to only use other plugin view or php.
     * @var string
     */
    public $modelPlugin = 'models';
    /**
     * General construction.
     *
     * @return object
     */
    public function construct()
    {
        unset($_REQUEST['_SESSION']);
        unset($_POST['_SESSION']);
        unset($_GET['_SESSION']);

        $this->_POST = empty($_POST) ? array() : $_POST;
        $this->_GET  = empty($_GET)  ? array() : $_GET;

        return parent::construct();
    }

    /**
     * Creates model instance with a defined plugin.
     * @throws PHPDS_exception
     */
    public function model()
    {
        if (is_string($this->modelPlugin) && class_exists($this->modelPlugin) && is_object($this->model)) {
            $this->model->instance = $this->factory($this->modelPlugin);
            // Shorter version for direct plugin access.
            $this->models =& $this->model->instance;
        }
    }

    /**
     * Creates view instance with a defined plugin.
     * @throws PHPDS_exception
     */
    public function view()
    {
        if (is_string($this->viewPlugin) && class_exists($this->viewPlugin) && is_object($this->view)) {
            $this->view->instance = $this->factory($this->viewPlugin);
            // Shorter version for direct plugin access.
            $this->views =& $this->view->instance;
        }
    }

    /**
     * Return a value from the _POST meta array
     *
     * @param string|null $key     the name of the post variable to fetch; if null, the entire array is returned
     * @param mixed|array $default a default value to return when the post variable is not set; when returning the
     *        entire array, an array can be given here with default values
     * @param integer     $options
     *
     * @return mixed the content of the post variable or the whole array, possibly with default value(s)
     */
    public function POST($key = null, $default = null, $options = 0)
    {
        if (!empty($key)) {
            return (isset($this->_POST[$key])) ? $this->_POST[$key] : $default;
        } else {
            if (is_array($default)) return array_merge($default, $this->_POST);
            else return $this->_POST;
        }
    }

    /**
     * Return a secured (preventing sql injection) value from the security->post meta array
     *
     * @param string|null $key     the name of the post variable to fetch; if null, the entire array is returned
     * @param mixed|array $default a default value to return when the post variable is not set; when returning the
     *        entire array, an array can be given here with default values
     * @param integer     $options
     *
     * @return mixed the content of the post variable or the whole array, possibly with default value(s)
     */
    public function P($key = null, $default = null, $options = 0)
    {
        return $this->POST($key, $default, $options);
    }

    /**
     * Return a value from the _GET meta array
     *
     * @param string|null $key     the name of the get variable to fetch; if null, the entire array is returned
     * @param mixed|array $default a default value to return when the get variable is not set; when returning the
     *        entire array, an array can be given here with default values
     * @param integer     $options
     *
     * @return mixed the content of the get variable or the whole array, possibly with default value(s)
     */
    public function GET($key = null, $default = null, $options = 0)
    {
        if (!empty($key)) {
            return (isset($this->_GET[$key])) ? $this->_GET[$key] : $default;
        } else {
            if (is_array($default)) return array_merge($default, $this->_GET);
            else return $this->_GET;
        }
    }

    /**
     * Return a secured (preventing sql injection) value from the security->get meta array
     *
     * @param string|null $key     the name of the get variable to fetch; if null, the entire array is returned
     * @param mixed|array $default a default value to return when the get variable is not set; when returning the
     *        entire array, an array can be given here with default values
     * @param integer     $options
     *
     * @return mixed the content of the get variable or the whole array, possibly with default value(s)
     */
    public function G($key = null, $default = null, $options = 0)
    {
        return $this->GET($key, $default, $options);
    }

    /**
     * Does security check and runs controller.
     *
     * @return mixed
     */
    public function run()
    {
        // Build the foundation ///////  i
        $this->onLoad();            ////  n
        $this->routerAPI();         /////  Him
        $this->model();             //////  i
        $this->view();              ///////  m
        ////////////////////////////////////  we trust

        $result = null;
        if ($this->core->ajaxType) {
            /**
             * This allows to load a widget/ajax theme controller via ajax without triggering the runAjax.
             * Now runAjax can still be used within the widget/ajax node type controller.
             */
            if ($this->core->ajaxType != 'light' && $this->core->ajaxType != 'light+mods') {
                $this->tellHeaderAboutNode();
                $result = $this->execute();
            } else {
                $result = $this->runAJAX();
            }
            $this->fetchAjaxNotif();
        } else {
            $result = $this->runRegular();
        }
        return $result;
    }

    /**
     * Will send all "TYPE messages" passed to notif to header for processing.
     */
    public function fetchAjaxNotif()
    {
        $json_notifs = $this->notif->fetchAsJson();
        if (!empty($json_notifs)) {
            PU_silentHeader("PHPDS-ajaxResponseMessage: " . $json_notifs);
        }
    }

    /**
     * Tell the response more about the requested node.
     */
    public function tellHeaderAboutNode()
    {
        $n = $this->navigation->navigation;
        $c = $this->configuration;

        $node_name = $n[$c['m']]['node_name'];
        $node_id   = $n[$c['m']]['node_id'];

        $json = json_encode(array('title' => $node_name, 'node_id' => $node_id));

        PU_silentHeader("PHPDS-ajaxAboutNode: " . $json);
    }

    /**
     * Run a controller when called with ajax
     *
     * @return mixed
     */
    public function runRegular()
    {
        $raw_data = $this->execute();
        return $this->handleResult($raw_data);
    }

    /**
     * Run a controller when called with ajax.
     *
     * @return mixed
     * @throws PHPDS_exception
     */
    public function runAJAX()
    {
        if (!empty($this->api->routeMethod)) {
            $method = key($this->api->routeMethod);
            if (method_exists($this, $method)) {
                if (!empty($this->api->routeMethod[$method])) {
                    $raw_data = call_user_func_array(array($this, $method), $this->api->routeMethod[$method]);
                } else {
                    $raw_data = call_user_func(array($this, $method));
                }
            } else {
                throw new PHPDS_exception('Ajax call for an unknown method "' . $method . '"');
            }
        } else {
            $raw_data = $this->viaAJAX();
        }
        return $this->handleResult($raw_data);
    }

    /**
     * Deal with the controller's output.
     *
     * @param mixed $raw_data
     * @return mixed
     * @throws PHPDS_exception
     */
    public function handleResult($raw_data)
    {
        $core = $this->core;

        $encoded_data = PU_isJSON($raw_data);
        if (false !== $encoded_data) {
            $core->themeFile = '';
            $core->data      = $encoded_data;
            return true;
        } else {
            if (is_null($raw_data)) { // deal with it the usual way
                return true;
            } else {
                $core->themeFile = '';
                if (false === $raw_data) { //  we consider it's an error
                    // IMPORTANT (DON'T REMOVE): Also send false to response
                    $core->data = 'false';
                    return false;
                } elseif (true === $raw_data) { // controller handled output
                    // IMPORTANT (DON'T REMOVE): Also send true to response
                    $core->data = 'true';
                    return true;
                } elseif (is_string($raw_data)) { // bare data, using empty template
                    $core->data = $raw_data;
                    return true;
                } elseif (is_int($raw_data)) {
                    $core->data = $raw_data;
                    return true;
                } else {
                    throw new PHPDS_exception(sprintf('The return value of controller %d is invalid.',
                        $this->configuration['m']));
                }
            }
        }
    }

    /**
     * This method is meant to be the entry point of your class. Most checks and cleanup should have been done by
     * the time it's executed
     *
     * @return mixed if you return "false" output will be truncated
     */
    public function execute()
    {
        // Override
    }

    /**
     * This method is run if your controller is called in an ajax context
     *
     * @return mixed, there are 3 cases: "true" (or nothing)  the output will be handled by the template the usual way,
     * "false" it's an error, otherwise the result data will be displayed in an empty template
     */
    public function viaAJAX()
    {
        // Override
    }

    /**
     * This method will always load, its almost like the construct method but loads at a later stage so that
     * post and other data can be read.
     */
    public function onLoad()
    {
        // Override
    }

    /**
     * Pre-loads any router and api configuration the developer may want to apply.
     */
    public function routerAPI()
    {
        // Override
    }
}
