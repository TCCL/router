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

/**
 * This interface represents a handler object. Class-based request handlers must
 * implement this interface.
 */
interface RequestHandler {
    /**
     * This function should run the request handler.
     *
     * @param callable $flush
     *  A callback to flush headers set by the calling context; this should be
     *  called if the application is going to write a large amount of output
     * @param array $info
     *  An associative array containing the following properties:
     *  - int &$statusCode
     *     An assignable reference to set the response code
     *  - string &$contentType
     *     An assignable reference to set the content type
     *  - string $method
     *     The request method
     *  - string $uri
     *     The request uri
     *  - array $params
     *     An associative array containing the request parameters
     * @param array $matches
     *  Will contain any regex matches from the URI string
     */
    function run(callable $flush,array $info,array $matches);
}

/**
 * This exception type is used to pass an exception along with the handler-defined
 * content type.
 */
class HandlerException extends Exception {
    private $contentType; // Content type for reporting from handler

    public function __construct($message,$contentType) {
        parent::__construct($message);
        $this->contentType = $contentType;
    }

    public function getContentType() {
        return $this->contentType;
    }
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
    );

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
        $statusCode = 200;
        $contentType = CONTENT_HTML;
        $uri = parse_url($uri,PHP_URL_PATH);

        // Find path component relative to the specified base directory.
        if (!empty($basedir) && strpos($uri,$basedir) === 0) {
            $uri = substr($uri,strlen($basedir));
        }

        // Get the correct set of request parameters.
        $method = strtoupper($method);
        if ($method == 'GET') {
            $params = $_GET;
        }
        else if ($method == 'POST') {
            $params = $_POST;
        }
        else {
            // Otherwise we need to parse the request parameters from the
            // request body.
            $input = file_get_contents('php://input');
            parse_str($input,$params);
        }

        // Try to see if a literal match works.
        if (isset($this->routeTable[$method][$uri])) {
            $handler = $this->routeTable[$method][$uri];
        }
        else {
            // Go through each item under the specified method. Try to interpret
            // the URI as a regex and perform a regex match.
            foreach ($this->routeTable[$method] as $regex => $hand) {
                if (@preg_match($regex,$uri,$matches)) {
                    $handler = $hand;
                    break;
                }
            }
            if (!isset($handler)) {
                $handler = $this->notFound;
            }
        }
        if (!isset($matches)) {
            $matches = array();
        }

        // Create a function that the handler can call to flush the statusCode
        // and contentType. This is important if the handler is going to output
        // any large amount of information. Note that we must import the
        // variables into the closure by reference.
        $flushHeaders = function() use (&$contentType,&$statusCode) {
            // Set status code and content type.
            http_response_code($statusCode);
            header("Content-Type: $contentType");
        };

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
                throw new Exception(__METHOD__.': request handler object must implement RequestHandler');
            }

            $handler = array($handler,'run');
        }

        // Prepare info array.
        $info = array(
            'statusCode' => &$statusCode,
            'contentType' => &$contentType,
            'method' => $method,
            'uri' => $uri,
            'params' => $params,
        );

        // Invoke the handler.
        try {
            $handler($flushHeaders,$info,$matches);
        } catch (Exception $ex) {
            throw new HandlerException($ex->getMessage(),$contentType);
        }
    }
}
