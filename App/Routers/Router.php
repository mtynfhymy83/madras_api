<?php

namespace App\Routers;

use App\Traits\ResponseTrait;
use App\Middlewares\CheckAccessMiddleware;
use App\Exceptions\ValidationException;
use Swoole\Http\Request;
use ReflectionMethod;

class Router
{
    use ResponseTrait;

    private array $staticRoutes = [];
    private array $dynamicRoutes = [];
    private array $controllerCache = [];
    private array $reflectionCache = [];
    private ?CheckAccessMiddleware $accessMiddleware = null;
    private string $currentPrefix = '';
    private bool $useControllerCache = true;

    public function __construct()
    {
        $this->accessMiddleware = new CheckAccessMiddleware();
        $this->useControllerCache = (($_ENV['CONTROLLER_CACHE'] ?? 'false') === 'true');
    }

    public function get(string $version, string $path, string $controller, string $method, $access = false, $inaccess = false): void
    {
        $this->registerRoute($version, 'GET', $path, $controller, $method, $access, $inaccess);
    }

    public function post(string $version, string $path, string $controller, string $method, $access = false, $inaccess = false): void
    {
        $this->registerRoute($version, 'POST', $path, $controller, $method, $access, $inaccess);
    }

    public function put(string $version, string $path, string $controller, string $method, $access = false, $inaccess = false): void
    {
        $this->registerRoute($version, 'PUT', $path, $controller, $method, $access, $inaccess);
    }

    public function delete(string $version, string $path, string $controller, string $method, $access = false, $inaccess = false): void
    {
        $this->registerRoute($version, 'DELETE', $path, $controller, $method, $access, $inaccess);
    }

    public function any(string $version, string $path, string $controller, string $method, $access = false, $inaccess = false): void
    {
        foreach (['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS'] as $m) {
            $this->registerRoute($version, $m, $path, $controller, $method, $access, $inaccess);
        }
    }

    public function prefix(string $prefix, callable $callback): void
    {
        $previousPrefix = $this->currentPrefix;
        $this->currentPrefix = $previousPrefix . $prefix;
        $callback($this);
        $this->currentPrefix = $previousPrefix;
    }

    public function group(callable $callback): void
    {
        $callback($this);
    }

    private function normalizePath(string $version, string $prefix, string $path): string
    {
        $fullPath = '/' . $version . '/' . $prefix . '/' . $path;
        $clean = preg_replace('#/+#', '/', $fullPath);
        return $clean === '/' ? '/' : rtrim($clean, '/');
    }

    private function registerRoute(string $version, string $httpMethod, string $path, string $controller, string $method, $access, $inaccess): void
    {
        $fullPath = $this->normalizePath($version, $this->currentPrefix, $path);

        $routeData = [
            'controller' => $controller,
            'method' => $method,
            'access' => $access,
            'inaccess' => $inaccess,
        ];

        if (str_contains($fullPath, '{')) {
            $pattern = $this->convertPathToRegex($fullPath);
            $key = $this->getDynamicPrefixKey($fullPath);
            $this->dynamicRoutes[$version][$httpMethod][$key][] = [
                'pattern' => $pattern,
                'route' => $routeData,
            ];
        } else {
            $this->staticRoutes[$version][$httpMethod][$fullPath] = $routeData;
        }
    }

    private function convertPathToRegex(string $path): string
    {
        $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\?\}/', '(?<$1>[^/]+)?', $path);
        $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?<$1>[^/]+)', $pattern);
        return '#^' . $pattern . '$#';
    }

    public function resolve(string $version, string $requestMethod, string $path, ?Request $request = null): mixed
    {
        $path = $this->normalizePath($version, '', $path);

        if (isset($this->staticRoutes[$version][$requestMethod][$path])) {
            return $this->handleRoute($this->staticRoutes[$version][$requestMethod][$path], $request, []);
        }

        if (isset($this->dynamicRoutes[$version][$requestMethod])) {
            $key = $this->getDynamicRequestKey($path);
            $buckets = $this->dynamicRoutes[$version][$requestMethod];
            $candidates = array_merge($buckets[$key] ?? [], $buckets['*'] ?? []);
            foreach ($candidates as $item) {
                $pattern = $item['pattern'];
                $route = $item['route'];
                if (preg_match($pattern, $path, $matches)) {
                    $params = array_filter($matches, fn($k) => !is_numeric($k), ARRAY_FILTER_USE_KEY);
                    return $this->handleRoute($route, $request, $params);
                }
            }
        }

        if ($requestMethod === 'OPTIONS') {
            return $this->sendResponse(null, 'OK', false, 200);
        }

        return $this->sendResponse(null, "Route Not Found ($path)", true, 404);
    }

    private function handleRoute(array $route, ?Request $request, array $params): mixed
    {
        try {
            $this->checkAccess($route['access'], $route['inaccess'], $request);

            $controllerClass = $route['controller'];
            $method = $route['method'];

            if ($this->useControllerCache) {
                if (!isset($this->controllerCache[$controllerClass])) {
                    if (!class_exists($controllerClass)) {
                        throw new \Exception("Controller $controllerClass not found", 500);
                    }
                    $this->controllerCache[$controllerClass] = new $controllerClass();
                }
                $controllerInstance = $this->controllerCache[$controllerClass];
            } else {
                if (!class_exists($controllerClass)) {
                    throw new \Exception("Controller $controllerClass not found", 500);
                }
                $controllerInstance = new $controllerClass();
            }

            if (!method_exists($controllerInstance, $method)) {
                throw new \Exception("Method $method not found in $controllerClass", 500);
            }

            $bodyData = $this->extractBody($request);
            $response = $this->invokeControllerMethod($controllerClass, $controllerInstance, $method, $request, $bodyData, $params);

            if ($this->isRawResponse($response) || is_string($response)) {
                return $response;
            }

            // Allow controllers to pass custom headers alongside data
            if (is_array($response) && array_key_exists('__headers', $response)) {
                $headers = $response['__headers'] ?? [];
                $data = $response['__data'] ?? null;
                $wrapped = $this->sendResponse($data, 'Success', false, 200);
                $wrapped['headers'] = $headers;
                return $wrapped;
            }

            return $this->sendResponse($response, 'Success', false, 200);
        } catch (ValidationException $e) {
            return $this->sendResponse(null, $e->getErrors(), true, 422);
        } catch (\Throwable $e) {
            error_log('[Router Error] ' . $e->getMessage());
            $code = $e->getCode();
            $codeInt = (int) $code;
            $status = ($codeInt >= 400 && $codeInt <= 599) ? $codeInt : 500;
            $debug = ($_ENV['APP_DEBUG'] ?? '0') === '1';
            $data = $debug ? ['trace' => $e->getTraceAsString()] : null;
            return $this->sendResponse($data, $e->getMessage(), true, $status);
        }
    }

    private function invokeControllerMethod(string $class, object $instance, string $method, ?Request $request, array $bodyData, array $routeParams): mixed
    {
        $cacheKey = $class . '::' . $method;
    
        // کش کردن متادیتای پارامترها برای جلوگیری از تکرار پردازش Reflection
        if (!isset($this->reflectionCache[$cacheKey])) {
            $reflection = new ReflectionMethod($instance, $method);
            $paramsInfo = [];
            foreach ($reflection->getParameters() as $param) {
                $paramsInfo[] = [
                    'name' => $param->getName(),
                    'type' => $param->getType()?->getName(),
                    'default' => $param->isDefaultValueAvailable() ? $param->getDefaultValue() : null,
                    'hasDefault' => $param->isDefaultValueAvailable(),
                ];
            }
            $this->reflectionCache[$cacheKey] = $paramsInfo;
        }

        $passParams = [];

        $queryParams = [];
        if ($request && isset($request->server['query_string'])) {
            parse_str($request->server['query_string'], $queryParams);
        }
    
        // به جای ذخیره آرایه اطلاعات، خودِ آبجکت پارامترها را نگه دار و اینجا سریع فیلتر کن
        foreach ($this->reflectionCache[$cacheKey] as $info) {
            $name = $info['name'];
            $type = $info['type'];

            if ($type === Request::class) {
                $passParams[] = $request;
            } elseif (isset($routeParams[$name])) {
                $passParams[] = $routeParams[$name];
            } elseif ($name === 'request') {
                $passParams[] = array_merge($queryParams, $bodyData);
            } elseif ($name === 'data' || $name === 'inputs') {
                $passParams[] = $bodyData;
            } else {
                $value = $bodyData[$name] ?? $queryParams[$name] ?? null;
                if ($value === null && !empty($info['hasDefault'])) {
                    $value = $info['default'];
                }
                $passParams[] = $value;
            }
        }
    
        return $instance->$method(...$passParams);
    }
    private function extractBody(?Request $request): array
    {
        if (!$request) return [];
        $ctype = strtolower($request->header['content-type'] ?? '');
        if (str_contains($ctype, 'application/json')) {
            $raw = $request->rawContent();
            return !empty($raw) ? (json_decode($raw, true) ?? []) : [];
        }
        return $request->post ?? [];
    }

    private function checkAccess($access, $inaccess, $request): void
    {
        if ($access) {
            $roles = is_array($access) ? $access : [$access];
            if ($access === 'owners') $roles = ['support', 'admin'];
            if ($access === 'all') $roles = ['support', 'admin', 'guest', 'user'];
            if ($access === 'auth') $roles = ['support', 'admin', 'guest', 'user'];
            $this->accessMiddleware->checkAccess($roles, $request);
        }
    }

    private function isRawResponse($response): bool
    {
        return is_array($response) && (isset($response['swagger_file']) || isset($response['data']['swagger_file']));
    }

    private function getDynamicPrefixKey(string $fullPath): string
    {
        $clean = trim($fullPath, '/');
        if ($clean === '') return '*';
        $parts = explode('/', $clean);
        return $parts[1] ?? '*';
    }

    private function getDynamicRequestKey(string $path): string
    {
        $clean = trim($path, '/');
        if ($clean === '') return '*';
        $parts = explode('/', $clean);
        return $parts[1] ?? '*';
    }
}
