<?php

/**
 * A class to deal with route (ie mapping URL to a node)
 *
 * The purpose of this class is to find what previously registered "catcher" should be
 * selected when a given URL is requested.
 *
 * Note it does'nt do anything with said catcher, just return it for the caller to do
 * whatever is necessary.
 *
 * Note: to comply with usual terminology, we use "module" here in the meaning of what we
 * call "plugin" in general in PHPDevShell.
 */
class PHPDS_router extends PHPDS_dependant
{
    /**
     * The list of route descriptors.
     * This is a plain array so we can handle identical patterns - route are matched in order.
     * @var array
     */
    public $routes = array();
    /**
     * Collects active catcher or alias node as per route config.
     * @var string
     */
    public $alias = null;
    /**
     * A simple associative array with the parameters (ie URL variables) found in the URL.
     * @var array
     */
    protected $parameters = array();

    /**
     * Add a route to the list.
     *
     * @param mixed       $catcher  whatever should be run when the route is matched (usually the ID of the node).
     * @param string      $pattern  the pattern which, if matched, will trigger the node.
     * @param null|array  $defaults default parameters for this route (default values for the variables).
     *
     * @return bool, true if the route has been added.
     */
    public function addRoute($catcher, $pattern, $defaults = null)
    {
        if (empty($catcher) || empty($pattern)) {
            return false;
        }
        $pattern   = trim($pattern, '/');
        $c_pattern = rtrim(strstr($pattern, ':', true), '/');
        $c_pattern = ($c_pattern) ? $c_pattern : $pattern;

        $this->routes[$c_pattern] = array(
            'catcher'  => $catcher,
            'pattern'  => $pattern,
            'defaults' => $defaults
        );

        return $catcher;
    }

    /**
     * Test a url path to find a matching node.
     *
     * @param string $path the path part of the URL.
     *
     * @return string|bool, the ID of the matching node, or false if none have been found.
     */
    public function matchRoute($path)
    {
        if (!empty($this->configuration['routes'])) {
            foreach ($this->configuration['routes'] as $route_) {
                $this->addRoute($route_['catcher'], $route_['pattern'], $route_['defaults']);
            }
        }

        $parts    = $this->splitURL($path);
        $result   = false;
        $routes   = $this->routes;

        if (is_array($parts)) {
            if (!empty($parts[0])) $this->alias = $parts[0];
            if (!empty($routes[$path])) {
                // Ah that was easy.
                $result = $routes[$path];
            } else {
                // We need to look a little deeper.
                $parts_reverse = array_reverse($parts);
                // If this is 1, the node is most certainly not available, bring on 404.
                if (count($parts_reverse) == 1) return false;
                array_unshift($parts_reverse, '/');
                foreach ($parts_reverse as $part) {
                    $match_path = str_replace("/$part", '', $path);
                    foreach ($routes as $pattern) {
                        if (strpos($pattern['pattern'], $match_path) !== false) {
                            $c_pattern = rtrim(strstr($pattern['pattern'], ':', true), '/');
                            if ($c_pattern === $match_path) {
                                // Now we found a really good match, lets do .
                                $this->match1Route($pattern, $parts);
                                $result = $pattern;
                                break 2;
                            }
                        }
                    }
                }
            }
        }
        return $result ? $result['catcher'] : false;
    }

    /**
     * Try to match a given route pattern to the url path.
     *
     * @param string $route_        the route pattern.
     * @param array  $parts         the pieces of the url path (split on slash).
     */
    public function match1Route($route_, array $parts)
    {
        $defaults = explode("/", $route_['defaults']);
        $route    = $route_['pattern'];

        $pieces   = $this->splitURL($route);

        foreach ($pieces as $key => $piece) {
            if (strpos($route, $piece) !== false) {
                $part = array_shift($parts);
                if (empty($part) && !empty($defaults[$key]))
                    $part = $defaults[$key];

                if (strpos($piece, ':') !== false) {
                    $this->parameters[trim($piece, ':')] = $part;
                }
            }
        }
    }

    /**
     * Internal function to split array segments up.
     *
     * @param string $url
     * @return array
     */
    protected function splitURL($url)
    {
        $parts = explode('/', $url);
        return $parts;
    }

    /**
     * Read only accessor to the parameters found in the URL
     *
     * @param string $param Possible single value of a url parameter
     * @return array|string|int, associative array of (name=>value) or value of param
     */
    public function parameters($param = null)
    {
        if (!empty($param)) {
            if (!empty($this->parameters[$param])) {
                return $this->parameters[$param];
            } else {
                return null;
            }
        } else {
            return $this->parameters;
        }
    }

    /**
     * Returns available routes
     *
     * @return array
     */
    public function routes()
    {
        return $this->routes;
    }
}