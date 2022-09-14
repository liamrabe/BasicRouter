# BasicRouter
A basic router

## Install

```bash
composer require liamrabe/basic-router
```

## Basic usage

```php
use Liam\BasicRouter\Middleware\AbstractMiddleware;
use Liam\BasicRouter\Controller\AbstractErrorController;
use Liam\BasicRouter\DataCollection\Response;
use Liam\BasicRouter\DataCollection\Request;
use Liam\BasicRouter as Router;

class APIMiddleware {
	
	public static function handle() {
		return true;
	}

}

class AppMiddleware {

	public static function handle() {
		return true;
	}

}

function handleRoute(Request $request, Response $response): Response {
	$response->setHeader('Content-Type', 'text/html');
	$response->setBody('Hello, world!');
	$response->setStatus(200);
	
	return $response;
}

try {

	/** setErrorController & setMiddleware are required before adding routes */
	Router::setErrorController(ErrorController::class, 'handleError');
	Router::setMiddleware(AppMiddleware::class, 'handleRequest');

	Router::redirect('/', '/home');

	Router::get('/home', 'handleRoute');

	Router::put('/home', 'handleRoute');

	Router::post('/home', 'handleRoute');

	Router::delete('/home', 'handleRoute');

	Router::all('/home', 'handleRoute');

	Route::group('/api', static function() {
		/** Added route will have URI '/api/v1/customer' */
		Route::get('/v1/customers', 'handleRoute');
	}, [APIMiddleware::class, 'handleRequest']);

	Router::run();

} catch (Exception $ex) {
	echo $ex->getMessage();
}
```

## Documentation
BasicRouter supports `GET`, `PUT`, `POST` and `DELETE`

### HTTP Methods

**All**

```php
/** Use this if you want to register a route on all HTTP methods */
Router::all('/', [AbstractController::class, 'handleRoute']);
```

**GET**
```php
Router::get('/', [AbstractController::class, 'handleRoute']);
```

**PUT**
```php
Router::put('/', [AbstractController::class, 'handleRoute']);
```

**POST**
```php
Router::post('/', [AbstractController::class, 'handleRoute']);
```

**DELETE**
```php
Router::delete('/', [AbstractController::class, 'handleRoute']);
```

### General methods

**Redirect**
````php
/** Redirect route '/' to '/home' with response code 301 */
Router::redirect('/', '/home', 301);
````

### Regex URI
You can define regex directly on the URI

**Format**: `{[PARAMETER_NAME]:[REGEX_PATTERN]}`

**Example**:

```php
/** Regex on route doesn't need parenthesis will be applied at runtime */
Router::get('/api/customers/{customer_id:[a-zA-Z0-9]+}', 'handleRoute');
```

| Function                | Returns                               |
|-------------------------|---------------------------------------|
| Router::**getMethod**() | string - `$_SERVER['REQUEST_METHOD']` |
| Router::**getUri**()    | string - `$_SERVER['REQUEST_URI']`    |
| Router::**getSemVer**() | string - `1.0.0`                      |
| Router::**getMajor**()  | int - `1`                             |
| Router::**getMinor**()  | int - `0`                             |
| Router::**getPatch**()  | int - `0`                             |
