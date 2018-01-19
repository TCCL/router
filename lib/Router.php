<?php

/**
 * Router.php
 *
 * This library provides an extremely simple request router. It is designed to
 * be utterly minimal.
 *
 * @package tccl/router
 */

namespace TCCL\Router;

use Exception;

/**
 * Define content-type constants for convenience.
 */
define('CONTENT_TEXT','text/plain');
define('CONTENT_HTML','text/html');
define('CONTENT_JSON','application/json');
define('CONTENT_FILE_DOWNLOAD','application/octet-stream');
define('CONTENT_FORM_URLENCODED','application/x-www-form-urlencoded');

/**
 * Router
 *
 * This class provides a Router object that is used to define request routes
 * for an application-backend.
 */
class Router {
    const HTTP_GET = 'GET';
    const HTTP_POST = 'POST';
    const HTTP_PUT = 'PUT';
    const HTTP_DELETE = 'DELETE';
    const HTTP_PATCH = 'PATCH';
    const HTTP_HEAD = 'HEAD';
    const HTTP_OPTIONS = 'OPTIONS';
    const HTTP_ALL = '__ALL__';

    /**
     * A route entry that does nothing.
     *
     * @var array
     */
    private static $DEFAULT_ROUTE = ['/^.*$/' => 'TCCL\Router\Router::nop'];

    /**
     * This is the handler to call when no valid route is found.
     *
     * @var mixed (callable, RequestHandler or Router)
     */
    private $notFound;

    /**
     * This is the route table that maps request URIs to a handler. The mappings
     * are sorted into buckets by request method. Each URI can either be a
     * literal string or a regex. Handlers are either PHP callables or a class
     * (or instance of a class) that implements RequestHandler or extends
     * Router.
     *
     * The callables implement the same interface as RequestHandler::run.
     *
     * @var array
     */
    private $routeTable = array(
        'GET' => array(),
        'POST' => array(),
        'PUT' => array(),
        'DELETE' => array(),
        'PATCH' => array(),
        'HEAD' => array(),
        'OPTIONS' => array(),
    );

    /**
     * The path used to determine the basePath to any URI such as one generated
     * with getURI().
     *
     * @var string
     */
    private $basePath;

    /**
     * Request information: publicly available for reading.
     */
    public $matches = array();
    public $method;
    public $uri;
    public $params;

    /**
     * Response information: publicly available for reading and/or writing.
     */
    public $statusCode = 200;
    public $contentType = CONTENT_HTML;
    public $headers = array();

    /**
     * A request handler callback dedicated to doing nothing. This is used to
     * define a default route.
     */
    public static function nop() {

    }

    /**
     * Constructs a new Router object.
     *
     * @param callable|RequestHandler $notFoundHandler
     *  A default handler to call when a specified route does not exist
     */
    public function __construct($notFoundHandler) {
        $this->notFound = $notFoundHandler;
    }

    /**
     * This function registers a new route. If the route already exists, then
     * the existing route is overwritten.
     *
     * @param mixed $method
     *  The HTTP request method to handle, or an array of such strings.
     * @param string $uri
     *  The URI against which to match; this may be a literal string or regex.
     * @param mixed $handler
     *  Either a callable or a class that implements RequestHandler that
     *  represents the handler for the request
     */
    public function addRoute($method,$uri,$handler) {
        if ($method == self::HTTP_ALL) {
            $method = array_keys($this->routeTable);
        }

        // Add the handler to the route table.
        if (is_array($method)) {
            foreach ($method as $m) {
                if (!isset($this->routeTable[$m])) {
                    throw new Exception(__METHOD__.": bad request method '$m'");
                }
                $this->routeTable[$m][$uri] = $handler;
            }
        }
        else {
            $this->routeTable[$method][$uri] = $handler;
        }
    }

    /**
     * This function performs the specified request by routing control to a
     * registered request handler.
     *
     * @param string $method
     *  The HTTP request method
     * @param string $uri
     *  The request URI.
     * @param string $basedir
     *  The base directory of the requests. URIs are transformed to be relative
     *  to this directory so that routes can happen under subdirectories. This
     *  should be an absolute path (under the Web root).
     */
    public function route($method,$uri,$basedir = null) {
        $this->basePath = rtrim($basedir,'/');
        $this->uri = self::get_relative_path($this->basePath,parse_url($uri,PHP_URL_PATH));
        $this->method = strtoupper($method);

        $this->parseInputParameters();
        $this->routeImpl();
    }

    /**
     * Adds a header to the list of headers to write upon flush().
     *
     * @param string $key
     *  The header key
     * @param string $value
     *  The header value
     */
    public function addHeader($key,$value) {
        $this->headers[$key] = $value;
    }

    /**
     * Finalizes response metadata (i.e. status code and headers) to the output
     * stream. If you forego calling this method then the metadata stored in the
     * Router object will not be written to the output stream (which can be
     * desireable in some cases).
     */
    public function flush() {
        http_response_code($this->statusCode);
        header("Content-Type: $this->contentType");
        foreach ($this->headers as $key => $value) {
            header("$key: $value");
        }
    }

    /**
     * Send a Location header to redirect the user agent to the requested
     * URI. This function will terminate the program.
     *
     * @param string $uri
     *  The URI to which to redirect the user agent.
     * @param array $params
     *  Any query parameters to include in the URI.
     */
    public function redirect($uri,$params = false) {
        header('Location: ' . $this->getURI($uri,$params));
        exit;
    }

    /**
     * Gets an absolute URI based on a relative one.
     *
     * @param string $component
     *  The relative URI component
     * @param array $params
     *  Any query parameters to include after the URI.
     *
     * @return string
     */
    public function getURI($component,$params = false) {
        // Ensure leading path separator in component.
        if (empty($component) || $component[0] != '/') {
            $component = "/$component";
        }

        // Add query parameters.
        if (!empty($params) && is_array($params)) {
            $component .= '?' . http_build_query($params);
        }

        return "$this->basePath$component";
    }

    /**
     * Gets the content type indicated by the request headers.
     *
     * @return string
     *  The MIME type of the request as indicated by the client, or null if no
     *  such value could be reliably determined.
     */
    public function getRequestType() {
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $type = explode(';',$_SERVER['CONTENT_TYPE'])[0];
        }
        else if (isset($_SERVER['HTTP_CONTENT_TYPE'])) {
            $type = explode(';',$_SERVER['HTTP_CONTENT_TYPE'])[0];
        }
        else {
            $type = null;
        }
        return $type;
    }

    /**
     * Gets the named request parameter.
     *
     * @param $name string
     *  The request parameter name.
     * @param $default mixed
     *  A value to return if the request parameter is not found.
     *
     * @return string
     */
    public function getRequestParam($name,$default = null) {
        if (isset($this->params[$name])) {
            return $this->params[$name];
        }

        return $default;
    }

    private function parseInputParameters() {
        $type = $this->getRequestType();

        if ($type == CONTENT_FORM_URLENCODED) {
            if ($this->method == 'POST') {
                $this->params = $_POST;
            }
            else {
                // Otherwise we need to parse the request parameters from the
                // request body.
                $input = file_get_contents('php://input');
                parse_str($input,$this->params);
            }
        }
        else if ($type == CONTENT_JSON) {
            $this->params = json_decode(file_get_contents('php://input'),true);
        }
        else if ($this->method == 'GET') {
            // GET requests shouldn't have a content-type since the body is
            // empty.
            $this->params = $_GET;
        }
        else if ($this->method == 'POST') {
            $this->params = $_POST;
        }
        else {
            $this->params = [];
        }
    }

    private function copyFrom(Router $other) {
        // Copy request information.
        if (isset($other->matches[0])) {
            // Interpret the full regex match as the new base path for the
            // subrouter's URI. However do not update the $basePath member
            // property so that the subrouter can still return URIs relative to
            // the original application base path.

            $this->uri = self::get_relative_path($other->matches[0],$other->uri);
        }
        else {
            $this->uri = $other->uri;
        }
        $this->basePath = $other->basePath;
        $this->method = $other->method;
        $this->params = $other->params;

        // Inherit headers in case any were specified globally.
        $this->headers = array_merge($this->headers,$other->headers);
    }

    private function routeImpl() {
        // Augment HEAD and OPTIONS tables with the default route so that there
        // is basic support of these methods. We do this here so that any
        // user-supplied routes have precedence.
        $this->routeTable[self::HTTP_HEAD] += self::$DEFAULT_ROUTE;
        $this->routeTable[self::HTTP_OPTIONS] += self::$DEFAULT_ROUTE;

        // Try to see if a literal match works.
        if (isset($this->routeTable[$this->method][$this->uri])) {
            $handler = $this->routeTable[$this->method][$this->uri];
        }
        else {
            // Go through each item under the specified method. Try to interpret
            // the URI as a regex and perform a regex match.
            foreach ($this->routeTable[$this->method] as $regex => $hand) {
                if (@preg_match($regex,$this->uri,$this->matches)) {
                    $handler = $hand;
                    break;
                }
            }
            if (!isset($handler)) {
                $handler = $this->notFound;
            }
        }

        // Find and prepare handler for execution.
        if (!is_callable($handler)) {
            // Transform the handler into a callable. We assume that it may
            // either be an object whose class implements RequestHandler.
            // Otherwise it is a class name that implements
            // RequestHandler. Alternatively, the class/object may derive from
            // Router, in which case we delegate control to that router
            // instance.

            if (!is_object($handler)) {
                // Assume $handler is a class name.
                $handler = new $handler;
            }

            if (is_a($handler,'\TCCL\Router\Router')) {
                // If the handler is another Router instance, forward the
                // request to that router. It will function as a subrouter. A
                // full regex match should be available for a subrouter route
                // that will server as the new base path.

                if (!isset($this->matches[0])) {
                    throw new Exception(__METHOD__.': expected regex match for subrouter');
                }

                $handler->copyFrom($this);
                return $handler->routeImpl();
            }

            // Make sure object's class implements RequestHandler.
            if (!is_a($handler,'\TCCL\Router\RequestHandler')) {
                throw new Exception(__METHOD__.': request handler object must '
                                    . 'implement RequestHandler interface');
            }

            $handler = array($handler,'run');
        }

        // Invoke the handler.
        $handler($this);
    }

    private static function get_relative_path($basedir,$uri) {
        // Find path component relative to the specified base directory.
        if (!empty($basedir) && strpos($uri,$basedir) === 0) {
            $uri = substr($uri,strlen($basedir));
            if (empty($uri)) {
                $uri = '/';
            }
        }

        return $uri;
    }
}
