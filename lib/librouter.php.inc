<?php

/**
 * router.php.inc
 *
 * This library provides an extremely simple request router. It is designed to
 * be utterly minimal.
 *
 * @package router
 */

/**
 * Define content-type constants for convenience.
 */
define('CONTENT_TEXT','text/plain');
define('CONTENT_HTML','text/html');
define('CONTENT_JSON','application/json');
define('CONTENT_FILE_DOWNLOAD','application/octet-stream');
define('CONTENT_FORM_URLENCODED','application/x-www-form-urlencoded');

/**
 * This interface represents a handler object. Class-based request handlers must
 * implement this interface.
 */
interface RequestHandler {
    /**
     * This function should run the request handler.
     *
     * @param Router $router
     *  The router instance invoking the request handler.
     */
    function run(Router $router);
}

/**
 * This class provides a Router object that is used to define request routes
 * for an application-backend.
 */
class Router {
    /**
     * This is the handler to call when no valid route is found.
     *
     * @var handler (i.e. callable or RequestHandler)
     */
    private $notFound;

    /**
     * This is the route table that maps request URIs to a handler. The mappings
     * are sorted into buckets by request method. Each URI can either be a
     * literal string or a regex. Handlers are either PHP callables or a class
     * (or instance of a class) that implements RequestHandler.
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
        'HEAD' => array( '/^.*$/' => array('Router','nop') ),
        'PATCH' => array(),
    );

    /**
     * The path leading up to the current application's routes. All routes are
     * interpreted relative to this route.
     *
     * @var string
     */
    private $basePath;

    /**
     * Request information:
     */
    public $matches = array();
    public $method;
    public $uri;
    public $params;

    /**
     * Response information:
     */
    public $statusCode = 200;
    public $contentType = CONTENT_HTML;
    public $headers = array();

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
     * @param  string $method
     *  The HTTP request method to handle
     * @param  string $uri
     *  The URI against which to match; this may be a literal string or regex
     * @param  mixed $handler
     *  Either a callable or a class that implements RequestHandler that
     *  represents the handler for the request
     */
    public function addRoute($method,$uri,$handler) {
        // Add the handler to the route table.
        $this->routeTable[$method][$uri] = $handler;
    }

    /**
     * This function performs the specified request by routing control to a
     * registered request handler.
     *
     * @param string $method
     *  The HTTP request method
     * @param string $uri
     *  The request URI
     * @param string $basedir
     *  The base directory of the requests. URIs are transformed to be relative
     *  to this directory so that routes can happen under subdirectories. This
     *  should be an absolute path (under the Web root).
     */
    public function route($method,$uri,$basedir = null) {
        $uri = parse_url($uri,PHP_URL_PATH);
        $this->basePath = $basedir;

        // Find path component relative to the specified base directory.
        if (!empty($basedir) && strpos($uri,$basedir) === 0) {
            $uri = substr($uri,strlen($basedir));
            if (empty($uri)) {
                $uri = '/';
            }
        }

        $this->uri = $uri;
        $this->method = strtoupper($method);

        // Get the correct set of request parameters.
        $this->parseInputParameters();

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
            // either be an object whose class implements
            // RequestHandler. Otherwise it is a class name that implements
            // RequestHandler.

            if (!is_object($handler)) {
                // Assume $handler is a class name.
                $handler = new $handler;
            }

            // Make sure object's class implements RequestHandler.
            if (!is_a($handler,'RequestHandler')) {
                throw new Exception(__METHOD__.': request handler object must implement RequestHandler interface');
            }

            $handler = array($handler,'run');
        }

        // Invoke the handler.
        $handler($this);
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
     * Gets an absolute URI based on a relative one.
     *
     * @param string $component
     *  The relative URI component
     *
     * @return string
     */
    public function getURI($component) {
        // Ensure leading path separator in component.
        if (empty($component) || $component[0] != '/') {
            $component = "/$component";
        }
        return "$this->basePath$component";
    }

    private function parseInputParameters() {
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $type = explode(';',$_SERVER['CONTENT_TYPE'])[0];
        }
        else if (isset($_SERVER['HTTP_CONTENT_TYPE'])) {
            $type = explode(';',$_SERVER['HTTP_CONTENT_TYPE'])[0];
        }
        else {
            $type = null;
        }

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

    static private function nop() {
        exit;
    }
}
