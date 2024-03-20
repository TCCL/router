<?php

/**
 * Router.php
 *
 * @package tccl\router
 */

namespace TCCL\Router;

/**
 * Provides web application request routing functionality.
 */
class Router {
    /**
     * HTTP Method Constants
     */
    const HTTP_GET = 'GET';
    const HTTP_POST = 'POST';
    const HTTP_PUT = 'PUT';
    const HTTP_DELETE = 'DELETE';
    const HTTP_PATCH = 'PATCH';
    const HTTP_HEAD = 'HEAD';
    const HTTP_OPTIONS = 'OPTIONS';
    const HTTP_ALL = '__ALL__';

    /**
     * Content-Type Constants
     */
    const CONTENT_TEXT = 'text/plain';
    const CONTENT_HTML = 'text/html';
    const CONTENT_JSON = 'application/json';
    const CONTENT_FILE_DOWNLOAD = 'application/octet-stream';
    const CONTENT_FORM_URLENCODED = 'application/x-www-form-urlencoded';

    /**
     * A route entry that does nothing.
     *
     * @var array
     */
    private static $DEFAULT_ROUTE = ['/^.*$/' => ['TCCL\Router\Router','nop']];

    /**
     * Stores the Router that last executed routeImpl().
     *
     * @var Router
     */
    private static $executingRouter;

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
    private $routeTable = [
        'GET' => [],
        'POST' => [],
        'PUT' => [],
        'DELETE' => [],
        'PATCH' => [],
        'HEAD' => [],
        'OPTIONS' => [],
    ];

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
    public $matches = [];
    public $method;
    public $uri;
    public $params;

    /**
     * Response information: publicly available for reading and/or writing.
     */
    public $statusCode = 200;
    public $contentType = self::CONTENT_HTML;
    public $headers = [];

    /**
     * A request handler callback dedicated to doing nothing. This is used to
     * define a default route.
     */
    public static function nop() : void {

    }

    /**
     * Gets the Router instance that last executed.
     *
     * @return TCCL\Router\Router
     *  Returns null if no router has executed.
     */
    public static function getExecutingRouter() : ?Router {
        return self::$executingRouter;
    }

    /**
     * Calls payload verification implementation.
     *
     * @param mixed $vars
     * @param mixed $format
     * @param array $options
     */
    public static function verifyPayload(&$vars,$format,array $options = []) : void {
        PayloadVerify::verify($vars,$format,$options);
    }

    /**
     * Constructs a new Router object.
     *
     * @param callable|RequestHandler $notFoundHandler
     *  A default handler to call when a specified route does not exist
     * @param string $basePath
     *  The base path of the requests. URIs are transformed to be relative to
     *  this directory so that routes can happen under subdirectories. This
     *  should be an absolute path (under the Web root).
     */
    public function __construct($notFoundHandler,string $basePath = '') {
        $this->notFound = $notFoundHandler;
        $this->setBasePath($basePath);
    }

    /**
     * This function registers a new route. If the route already exists, then
     * the existing route is overwritten.
     *
     * @param mixed $method
     *  The HTTP request method to handle, or an array of such strings.
     * @param string $uri
     *  The URI pattern against which the request URI is matched; this may be a
     *  literal string or regex.
     * @param mixed $handler
     *  Either a callable or a class that implements RequestHandler that
     *  represents the handler for the request
     */
    public function addRoute($method,string $uri,$handler) : void {
        if ($method == self::HTTP_ALL) {
            $ms = array_keys($this->routeTable);
        }
        else {
            $ms = $method;
        }

        if (!is_array($ms)) {
            $ms = [$ms];
        }

        // Add the handler to the route table.
        foreach ($ms as $m) {
            if (!isset($this->routeTable[$m])) {
                throw new \Exception("Bad request method '$m'");
            }

            $this->routeTable[$m][$uri] = $handler;
        }
    }

    /**
     * Adds a list of routes from a table.
     *
     * @param array $table
     *  An associative array mapping HTTP request method => URI pattern =>
     *  request handler. The URI pattern is either a literal string or a
     *  regex. The handler is a callable or class that implements
     *  RequestHandler.
     */
    public function addRoutesFromTable(array $table) : void {
        foreach ($table as $method => $bucket) {
            if (!isset($this->routeTable[$method])) {
                throw new \Exception("Bad request method '$method'");
            }

            $this->routeTable[$method] += $bucket;
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
     */
    public function route(string $method,string $uri) : void {
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
    public function addHeader(string $key,string $value) : void {
        $this->headers[$key] = $value;
    }

    /**
     * Finalizes response metadata (i.e. status code and headers) to the output
     * stream. If you forego calling this method then the metadata stored in the
     * Router object will not be written to the output stream (which can be
     * desireable in some cases).
     */
    public function flush() : void {
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
    public function redirect(string $uri,array $params = null) : void {
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
    public function getURI($component,array $params = null) : string {
        // Ensure leading path separator in component.
        if (empty($component) || $component[0] != '/') {
            $component = "/$component";
        }

        // Add query parameters.
        if (isset($params)) {
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
    public function getRequestType() : ?string {
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
     * @param string $name
     *  The request parameter name.
     * @param mixed $default
     *  A value to return if the request parameter is not found.
     *
     * @return mixed
     */
    public function getRequestParam(string $name,$default = null) {
        if (isset($this->params[$name])) {
            return $this->params[$name];
        }

        return $default;
    }

    /**
     * Gets the named request parameter and verifies its contents.
     *
     * @param string $name
     *  The request parameter name.
     * @param mixed $format
     *  The verification format parameter.
     * @param mixed $default
     *  A value to return if the request parameter is not found.
     * @param array $options
     *  Additional options to pass to the verify functionality.
     *
     * @return mixed
     *
     * @throws TCCL\Router\RouterException
     *  Throws a generic HTTP-400 RouterException upon verification failure.
     */
    public function getRequestParamVerify(
        string $name,
        $format,
        $default = null,
        array $options = [])
    {
        if (isset($this->params[$name])) {
            $value = $this->params[$name];
            PayloadVerify::verify($value,$format,$options);
            return $value;
        }

        return $default;
    }

    /**
     * Gets the request payload after verifying its contents.
     *
     * @param mixed $format
     *  The verification format parameter.
     * @param array $options
     *  Additional options to pass to the verify functionality.
     *
     * @return array
     *
     * @throws TCCL\Router\RouterException
     *  Throws a generic HTTP-400 RouterException upon verification failure.
     */
    public function getPayloadVerify($format,array $options = []) : array {
        $copy = $this->params;
        PayloadVerify::verify($copy,$format,$options);
        return $copy;
    }

    /**
     * Creates an executable handler from the provided handler description. A
     * derived Router implementation may override this method. However it should
     * call back down to the base class method to preserve the canonical
     * behavior.
     *
     * @param mixed $handler
     *  Any handler description provided by the user. The canonical
     *  implementation (seen below) supports callables and classes that are
     *  derived from \TCCL\Router\RequestHandler.
     *
     * @return callable
     *  A callable that is invoked by this router to handle a request. The
     *  callable will be passed the executing router as its sole parameter. If
     *  the function returns NULL, then the route was handled by a subrouter and
     *  no further action is necessary.
     */
    protected function createHandler($handler) : callable {
        // Straight callables are just forwarded directly.
        if (is_callable($handler)) {
            return $handler;
        }

        // Transform the handler into a callable. We assume that it may either
        // be an object whose class implements RequestHandler.  Otherwise it is
        // a class name that implements RequestHandler. Alternatively,
        // the class/object may derive from Router, in which case we delegate
        // control to that router instance.

        if (!is_object($handler)) {
            // Assume $handler is a class name.
            $handler = new $handler;
        }

        if ($handler instanceof Router) {
            // If the handler is another Router instance, forward the request to
            // that router. It will function as a subrouter. A full regex match
            // should be available for a subrouter route that will serve as the
            // new base path.

            if (!isset($this->matches[0])) {
                throw new \Exception('Expected regex match for subrouter');
            }

            $handler->copyFrom($this);
            return new RouterClosure(\Closure::fromCallable([$handler,'routeImpl']));
        }

        // Make sure object's class implements RequestHandler.
        if (!is_a($handler,'\TCCL\Router\RequestHandler')) {
            throw new \Exception(
                'Request handler object must implement RequestHandler interface'
            );
        }

        $handler = [$handler,'run'];

        return $handler;
    }

    /**
     * Handles the result of the route handler operation. The canonical
     * implementation does nothing.
     *
     * @param mixed $result
     *  The result of the route handler operation.
     */
    protected function resultHandler($result) : void {
        // Do nothing...
    }

    /**
     * Sets the implicit base path used to prefix any URI generated with the
     * Router instance (such as via getURI()).
     *
     * @param string $basePath
     *  The base path to set.
     */
    final protected function setBasePath(string $basePath) : void {
        // Replace all backslashes with forward slashes. This allows the
        // implementation to work on MS Windows.
        $basePath = str_replace('\\','/',$basePath);

        $this->basePath = rtrim($basePath,'/');
    }

    private function parseInputParameters() : void {
        $type = $this->getRequestType();

        if ($type == self::CONTENT_FORM_URLENCODED) {
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
        else if ($type == self::CONTENT_JSON) {
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

    private function copyFrom(Router $other) : void {
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

    private function routeImpl() : void {
        // Set executing router.
        self::$executingRouter = $this;

        // Augment HEAD and OPTIONS tables with the default route so that there
        // is basic support of these methods. We do this here so that any
        // user-supplied routes have precedence.
        $this->routeTable[self::HTTP_HEAD] += self::$DEFAULT_ROUTE;
        $this->routeTable[self::HTTP_OPTIONS] += self::$DEFAULT_ROUTE;

        // Try to see if a literal match works.
        if (isset($this->routeTable[$this->method][$this->uri])) {
            $handler = $this->routeTable[$this->method][$this->uri];
        }
        else if (isset($this->routeTable[$this->method])) {
            // Go through each item under the specified method. Try to interpret
            // the URI as a regex and perform a regex match.
            foreach ($this->routeTable[$this->method] as $regex => $hand) {
                if (@preg_match($regex,$this->uri,$this->matches)) {
                    $handler = $hand;
                    break;
                }
            }
            if (!isset($handler)) {
                $this->statusCode = 404;
                $handler = $this->notFound;
            }
        }
        else {
            // The request method is unrecognized. Return HTTP 501 Not
            // Implemented.
            $this->statusCode = 501;
            $this->flush();
            exit(1);
        }

        // Create the handler and invoke. Pass any result value to the result
        // handler if we don't have a .
        $handler = $this->createHandler($handler);
        if ($handler instanceof RouterClosure) {
            $handler();
        }
        else {
            $result = $handler($this);
            $this->resultHandler($result);
        }
    }

    private static function get_relative_path(string $basePath,string $uri) : string {
        // Find path component relative to the specified base path.
        if (!empty($basePath) && strpos($uri,$basePath) === 0) {
            $uri = substr($uri,strlen($basePath));
            if (empty($uri)) {
                $uri = '/';
            }
        }

        if ($uri[0] != '/') {
            $uri = "/$uri";
        }

        return $uri;
    }
}
