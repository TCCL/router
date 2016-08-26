<?php

/**
 * router.php.inc
 *
 * @package router
 */

/**
 * Define content-types.
 */
define('CONTENT_JSON','application/json');
define('CONTENT_FILE_DOWNLOAD','application/octet-stream');

/**
 * This interface represents a handler object.
 */
interface RequestHandler {
    /**
     * This function should run the request handler.
     *
     * @param  string $statusCode
     *  Used to set the status code
     * @param  string $contentType
     *  Used to set the content type
     * @param  array $params
     *  Will contain the request parameters as an associative array
     * @param  array $matches
     *  Will contain any regex matches from the URI
     */
    function run(&$statusCode,&$contentType,$params,$matches = array());
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
     * This is the handler to call when
     *
     * @var handler (i.e. callable or RequestHandler)
     */
    private $notFound;

    /**
     * This is the route table that maps request URIs to a handler. The mappings
     * are sorted into buckets by request method. Each URI can either be a
     * literal string or a regex. Handlers are either PHP callables or an instance
     * of a class that implements RequestHandler.
     *
     * Callback function:
     *  @param string $statusCode
     *   The status code returned by the request handler
     *  @param string $contentType
     *   The content type returned by the request handler
     *  @param array $params
     *   An associative array of request parameters
     *  @param array $matches
     *   If the URI was a regex, this will contain any matches. Otherwise it is
     *   an empty array.
     **
     * function callback(&$statusCode,&$contentType,$params,$matches = array());
     **
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
        if (!is_callable($notFoundHandler) && ! ($notFoundHandler instanceof RequestHandler)) {
            throw new Exception(__METHOD__.": handler must be a callable or RequestHandler");
        }
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
     *  Either a callable or instance of RequestHandler that represents the
     *  handler for the request
     */
    public function addRoute($method,$uri,$handler) {
        // Make sure the handler is either a callback or a kind of RequestHandler
        if (!is_callable($handler) && ! ($handler instanceof RequestHandler)) {
            throw new Exception(__METHOD__.": handler must be a callable or RequestHandler");
        }

        // Add the handler to the route table.
        $this->routeTable[$method][$uri] = $handler;
    }

    /**
     * This function performs the specified request by routing control to a
     * registered request handler.
     *
     * @param  string $method
     *  The HTTP request method
     * @param  string $uri
     *  The request URI
     */
    public function route($method,$uri) {
        $statusCode = 200;
        $contentType = 'text/html';
        $uri = parse_url($uri,PHP_URL_PATH);

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

        // Invoke the handler.
        if (!is_callable($handler)) {
            // Transform the handler into a callable.
            $handler = array($handler,'run');
        }
        try {
            $handler($statusCode,$contentType,$params,$matches);
        } catch (Exception $ex) {
            throw new HandlerException($ex->getMessage(),$contentType);
        }

        // Set status code and content type.
        http_response_code($statusCode);
        header("Content-Type: $contentType");
    }
}
