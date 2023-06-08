# TCCL Routing Library

This project provides a class library for routing requests to a PHP application. The library is designed to be small and minimal.

## Installation

This library is available as a composer package. Require `tccl/router` in your composer.json file and then install.

~~~bash
composer require tccl/router
~~~

## Interfaces

### Classes

| Name | Description |
| -- | -- |
| `TCCL\Router\Router` | The core routing object type |
| `TCCL\Router\RequestHandler` | An abstract interface for class-based request handlers |
| `TCCL\Router\RouterException` | Exception type for handling request handler errors |

### Traits

| Name | Description |
| -- | -- |
| `TCCL\Router\RouterExceptionHandling` | Optional trait to add to router subclass for exception handling |
| `TCCL\Router\RouterMethodHandling` | Optional trait to add method handling support to a router subclass |
| `TCCL\Router\RouterRESTHandling` | Optional trait to add REST API support to a router subclass |

## Usage

### Creating a router

Router provides a mechanism for routing control to a handler based on an input URI. It is very easy to setup and use. Just create an instance of type `TCCL\Router\Router`. The constructor takes a handler argument which is the default handler used when a route does not match:

~~~php
use TCCL\Router\Router;

function not_found(Router $router) {
	$router->contentType = Router::CONTENT_TEXT;
	$router->flush();

	echo "Not found\n";
}

$router = new Router('not_found');
~~~

### Adding routes

Now you can add routes using the `addRoute()` or `addRoutesFromTable()` methods. Each route maps a request method and route specifier to a handler specifier.

~~~php
// Method: Router::HTTP_GET (i.e. 'GET')
// Route Specifier: '/help'
// Handler Specifier: 'generate_help_page'

$router->addRoute(Router::HTTP_GET,'/help','generate_help_page');
$router->addRoute('GET','/\/help\/topics\/([0-9]+)/','generate_help_topic');

// The addRoutesFromTable() method allows multiple routes
// to be added from an array in one call.
$router->addRoutesFromTable([
	Router::HTTP_GET => [
		'/help' => 'generate_help_page',
		'/\/help\/topics\/([0-9]+)/' => 'generate_help_topic',
	],
]);
~~~

### Route specifiers

A route specifier indicates the URI that identifies a route. This can either be an exact match or a regex match.

~~~php
$router->addRoute(Router::HTTP_GET,'/page/home','load_home_page');

// The following route specifier matches URIs such as:
//   /page/1
//   /page/27
//   /page/33

// NOTE: forward-slashes in the URI spec must be properly escaped
// when using forward slashes to bracket the regex.
$router->addRoute(Router::HTTP_GET,'/\/page\/([0-9]+)/','load_page');
~~~

If the regex specified match groups, the matched values can be accessed via the `Router::$matches` property during request handling.

### Handler specifier

A handler specifier identifies an executable context that can handle the route (i.e. the handler). The canonical implementation supports the following handler specifiers:

| Specifier Type | Explanation |
| -- | -- |
| Callable | Anything that can be called as a function in PHP |
| Class name | The name of a class that implements `TCCL\Router\RequestHandler`, or, for sub-routing, `TCCL\Router\Router` |
| Object instance | An instance of a class that implements `TCCL\Router\RequestHandler`, or, for sub-routing, `TCCL\Router\Router` |

#### Handler specifier: callable

The simplest handler specifier is a callable. This can be a function or class method (static or non-static). Consult the [PHP manual on callables](https://www.php.net/manual/en/language.types.callable.php) for more.

#### Handler specifier: class name or instance of `TCCL\Router\RequestHandler`

Typically, you will use a handler that implements the `TCCL\Router\RequestHandler` interface, as it provides more robust functionality. If you specify a class name, the router will create a new instance of the class if and when the route is executed. Otherwise, if you provide an instance, then the instance will be used as-is when the route is executed.

When a route executes with a `RequestHandler`, it will invoke the `run()` method, passing the executing `Router` as the parameter.

~~~php
use TCCL\Router\Router;
use TCCL\Router\RequestHandler;

class PageHandler implements RequestHandler {
	public static function not_found(Router $router) {
	
	}

	public function run(Router $router) {
		$pageNumber = (int)$router->matches[1];

	}
}

$router = new Router("PageHandler::not_found");
$router->addRoute(Router::HTTP_GET,'/\/page\/([0-9]+)/','PageHandler');
~~~

#### Handler specifier: sub-router

If the handler specifier is a class name or instance of type `TCCL\Router\Router`, then the router will invoke a sub-router. Sub-routers work well for cases when you want to classify a set of routes under a common prefix. The route specifier for a sub-router should be a regex that matches a common prefix, and the `Router::HTTP_ALL` special method specifier should be used to match any request method.

~~~php
// Let 'APIRouter' process all URIs having a prefix of /api.
$router->addRoute(Router::HTTP_ALL,'/^\/api\//','APIRouter');
~~~

### Executing the router

To actually route a request, you must execute the router using its `route` method. You must specify the HTTP method and URI to route. These values can be obtained from `_$SERVER` depending on your PHP SAPI.

~~~php
$router->route($_SERVER['REQUEST_METHOD'],$_SERVER['REQUEST_URI']);
~~~

#### Base path

The `route` method also takes a final parameter indicating the base path for all requests. This is useful for when an application is running under a sub-directory of the document root. The router will automatically fixup the request URI when matching against the routing table.

> You can also set the base path using the projected method `setBasePath` if you are subclassing `TCCL\Router\Router`.

Pro Tip: To allow your application to work arbitrarily under any sub-directory of the document root, calculate the base path using the file name of entry point script and the `DOCUMENT_ROOT` server variable.

~~~php
// Given __FILE__:"/path/to/www/app/index.php"

$entryPointPath = dirname(__FILE__);
$documentRoot = $_SERVER['DOCUMENT_ROOT'];
$basePath = substr($entryPointPath,strlen($documentRoot));

// Given documentRoot:"/path/to/www" and entryPointPath:"/path/to/www/app",
// then we get basePath:"/app"

$router->route($_SERVER['REQUEST_METHOD'],$_SERVER['REQUEST_URI'],$basePath);
~~~

This trick works if you have an entry point script (e.g. `index.php`) that is called for each route; the entry point script must be installed at the root of the project tree.

### Subclassing `Router`

For non-trivial applications, subclassing the `TCCL\Router\Router` class can better encapsulate routing functionality.

~~~php
use TCCL\Router\Router;
use TCCL\Router\RouterException;

class MyRouter extends Router {
	public function __construct() {
		parent::__construct(function(){
            throw new RouterException(
                404,
                'Not Found',
                'The specified resource was not found on this server.'
            );
        });

		$this->addRoutesFromTable([/* ... */]);
	}
}
~~~

### Additional router handling

The library provides additional handling support to account for a number of common use cases including:

- Exception handling
- Method handling
- REST handling

Additional handling is implemented as traits that you import into a custom `TCCL\Router\Router` subclass.

#### Exception handling

Router exception handling allows the router to handle exceptions in a convenient and streamlined manner. To add exception handling to a `Router` subclass, use trait `TCCL\Router\RouterExceptionHandling`.

You must also implement the `handleServerError` and `handleRouterError` methods to handle exceptions. The later method is for exceptions of type `TCCL\Router\RouterException` and the former is for any other exception.

#### Method handling

Router method handling allows you to define a single handler class with multiple methods that handle each request. To add method handling to a `Router` subclass, use trait `TCCL\Router\RouterMethodHandling`.

Once method handling has been added, you can add a method name to a class name handler specifier (e.g. `Namespace\Class::methodName`).

~~~php
use TCCL\Router\Router;
use TCCL\Router\RouterException;
use TCCL\Router\RouterMethodHandling;

class MyRouter extends Router {
	use RouterMethodHandling;

	public function __construct() {
		parent::__construct(function(){
            throw new RouterException(
                404,
                'Not Found',
                'The specified resource was not found on this server.');
        });

		$this->addRoute(Router::HTTP_GET,'/time','Handler::getTime');
	}
}

class Handler {
	public function getTime(MyRouter $router) {
		/* ... */
	}
}
~~~

#### REST handling

REST handling allows you to add special functionality to a custom `Router` subclass that makes writing REST API endpoints using JSON more convenient. The handling allows your request handler to return a payload that is then automatically converted into JSON. The correct HTTP headers are also applied.

To add REST handling to a `Router` subclass, use trait `TCCL\Router\RouterRESTHandling`.

> Note: REST handling works well when paired with method handling.

~~~php
use TCCL\Router\Router;
use TCCL\Router\RouterException;
use TCCL\Router\RouterMethodHandling;
use TCCL\Router\RouterRESTHandling;

class MyRouter extends Router {
	use RouterMethodHandling;
	use RouterRESTHandling;

	public function __construct() {
		parent::__construct(function(){
            throw new RouterException(
                404,
                'Not Found',
                'The specified resource was not found on this server.');
        });

		$this->addRoute(Router::HTTP_GET,'/time','Handler::getTime');
	}
}

class Handler {
	public function getTime(MyRouter $router) {
		$dt = new \DateTime;
		$repr = [
			'year' => (int)$dt->format('Y'),
			'month' => (int)$dt->format('m'),
			'date' => (int)$dt->format('d'),
			'hour' => (int)$dt->format('H'),
			'minute' => (int)$dt->format('i'),
			'second' => (int)$dt->format('s'),
			'tz' => $dt->getTimezone()->getOffset(),
		];

		return $repr;
	}
}
~~~

### Request Payload Verification

The library provides a mechanism for verifying a request payload that can avoid tedious boilerplate in the implementation of a request handler. Verification also helps sanitize user input.

Payload verification functionality is defined in the `TCCL\Router\PayloadVerify` class, but is primarily accessed via `TCCL\Router\Router::getPayloadVerify`.

#### Background

Payload verification validates a request parameter payload using a format argument.

~~~php
$format = [
	'name' => 's',
	'email' => 's',
];
$payload = $router->getPayloadVerify($format);
~~~

The payload is generated from the request parameters. This works for any type of HTTP request method.

> Note: For HTTP `GET` requests, the data type for each parsed parameter will be `string`. You can apply promotions via payload verification, but you will want to make sure you are only type validating for `string`. For other request methods (e.g. `POST`), the content type of the request payload can allow for other data types to be encoded.

If verification fails, then a `TCCL\Router\PayloadVerifyException`. The `PayloadVerifyException` class is a sub-type of a `TCCL\Router\RouterException` having status code `400`. You can catch these exceptions and call `printDebug()` to obtain diagnostic information about how the payload failed to verify.

#### Verification options

The payload verification functionality has a number of options that can be configured:

| Option Name | Description | Default Value |
| -- | -- | -- |
| `checkExtraneous` | Determines if extraneous items in the payload cause verification to fail | `true` |

~~~php
$format = [
	'name' => 's',
	'email' => 's',
];
$options = [
	'checkExtranous' => false,
];
$payload = $router->getPayloadVerify($format,$options);
~~~

#### Verification format

The verification format argument indicates the names and expected data types for the request parameters in the payload. The format can also indicate other actions to perform such as type promotions and constraint checks.

The format argument is an associative array mapping the parameter names to verification specifier values.

~~~php
$format = [
	'name' => [
		'first' => 's',
		'last' => 's',
	],
	'job_title' => 's',
	'address_lines' => ['s'],
	'age?' => 'i',
];
~~~

If a parameter name (e.g. `name`) contains a trailing `?` character (e.g. `name?`), then the parameter may be omitted (or `null`) and the payload will still verify correctly. See the entry for `age` in the above example.

If a scalar value is expected for a parameter, then the specifier is a string indicating the accepted types and any promotions/checks to apply. See the entry for `job_title` in the above example.

If an indexed array of values is expected for a parameter, then the specifier is an indexed singleton array containing a scalar specifier. This specifier is applied to each element in the indexed array. See the entry for `address_lines` in the above example.

If a nested associative array structure is expected for a parameter, then the specifier is a nested format array that is processed recursively. See the entry for `name` in the above example.

#### Building scalar format strings

A scalar format string tells the verification system to perform a number of actions to verify that a scalar value is correct. A format string has the following base structure:

	<TYPE-SPECIFIER> [PROMOTION-SPECIFIER] [CHECK-SPECIFIER] ['?']

The type specifier portion of the string is the concatenation of one or more type specifier characters representing the set of types that are allowed. If the value does _not_ match one of these types, then verification fails.

Type specifier characters are always lower-case letter or a symbol. The following type specifiers are provided in the current implementation:

| Specifier Character | Type | Notes |
| -- | -- | -- |
| `b` | Boolean | |
| `s` | String | |
| `i` | Integer | |
| `f` | Float | |
| `d` | Double | Note that double is functionally equivalent to float |

Example type specifiers:

| Specifier | Meaning |
| -- | -- |
| `i` | Allow only integer |
| `si` | Allow string or integer |

The promotion specifier indicates an optional promotion operation to perform after the type validation. A promotion is generally an operation that converts the value; most of the core promotions provided by the current implementation are type promotions.

The promotion specifier portion of the format string is a single upper-case letter or symbol. The following promotions are provided in the current implementation:

| Specifier Character | Meaning |
| -- | -- |
| `B` | Convert to boolean |
| `S` | Convert to string |
| `I` | Convert to integer |
| `F` | Convert to float |
| `D` | Convert to double (alias of float) |
| `^` | Trim string value (will promote to string) |

Example specifiers with promotion:

| Specifier | Meaning |
| -- | -- |
| `siS` | Allow string or integer and promote to string |
| `ibB` | Allow integer or boolean and promote to boolean |
| `s^` | Allow string and trim |

The check specifier performs one or more additional validation actions on the value after the promotion. Check actions are predicate actions that fail validation when they return false.

The check specifier portion of the format string is the concatenation of one or more non-letter characters. The following check actions are provided in the current implementation:

| Specifier Character | Meaning |
| -- | -- |
| `!` | Ensures a string is not empty |
| `+` | Ensures a numeric value is positive |
| `-` | Ensures a numeric value is negative |
| `*` | Ensures a numeric value is non-negative |
| `%` | Ensures a numeric value is non-zero |

Example specifiers with checks:

| Specifier | Meaning |
| -- | -- |
| `siI+` | Ensures value is positive integer, accepts string representation of integer |
| `s^!` | Ensures trimmed string value is not empty |

#### Custom Types, Promotions and Checks

Using the `TCCL\Router\PayloadVerify` class, you can add register custom type, promotion and check specifiers as described in the previous section. Example:

~~~php
use TCCL\Router\PayloadVerify;

function is_user_id($value) : bool {
    if (!is_numeric($value)) {
	    return false;
    }

	$id = (int)$value;
	return $id >= 1;
}

PayloadVerify::registerType('u','is_user_id');
~~~

Note that when you register custom type, promotion or check specifiers, you must be careful to not override an existing specifier if you intend to keep using it. The `PayloadVerify` class with validate the specifiers to ensure there are no conflicts. Specifiers must meet the requirements listed below:

| Item | Requirements |
| -- | -- |
| Type Specifier | Must be lower-case letter `a..z` |
| Promotion Specifier |  Must be upper-case letter `A..Z` or a non-letter character; the set of non-letter promotion specifier characters must be mutually exclusive of the set of non-letter check specifier characters |
| Check Specifier |  Must be a non-letter character; the set of non-letter check specifier characters must be mutually exclusive of the set of non-letter promotion specifier characters |
