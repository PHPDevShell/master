<?php

/**
 * Basic API structure.
 * Class PHPDS_api
 */
class PHPDS_api extends PHPDS_dependant
{
    /**
     * Stores information about current route to method instruction.
     * @var array
     */
    public $routeMethod;

    /**
     * Structures method routing for delete purposes.
     *
     * @param $alias string The alias of the route that needs to be watched.
     * @param $method string The method that should be called if the string alias is matched.
     *
     * @return bool
     */
    public function delete($alias, $method)
    {
        return $this->action($alias, $method);
    }

    /**
     * Structures method routing for put purposes.
     *
     * @param $alias string The alias of the route that needs to be watched.
     * @param $method string The method that should be called if the string alias is matched.
     *
     * @return bool
     */
    public function put($alias, $method)
    {
        return $this->action($alias, $method);
    }

    /**
     * Structures method routing for post purposes.
     *
     * @param $alias string The alias of the route that needs to be watched.
     * @param $method string The method that should be called if the string alias is matched.
     *
     * @return bool
     */
    public function post($alias, $method)
    {
        return $this->action($alias, $method);
    }

    /**
     * Performs assignment and route to a specific method.
     *
     * @param $alias string The alias of the route that needs to be watched.
     * @param $method string The method that should be called if the string alias is matched.
     *
     * @return string
     */
    protected function action($alias, $method)
    {
        if (strpos($alias, '/') !== false) {
            $url = $_SERVER['REQUEST_URI'];
            if (strpos($url, $alias)) {
                $this->setMethod($method);
            }
            return $alias;
        } else if ($this->router->alias == $alias) {
            $this->setMethod($method);
            return $alias;
        } else {
            return null;
        }
    }

    /**
     * Assigns a found method for controller to execute if conditions are met.
     *
     * @param string $method
     */
    protected function setMethod($method)
    {
        $parameters = $this->router->parameters();
        if (!empty($parameters)) {
            $this->routeMethod[$method] = $parameters;
        } else {
            $this->routeMethod[$method] = array();
        }
    }
}