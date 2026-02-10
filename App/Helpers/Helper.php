<?php
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Dotenv\Dotenv;

// Load environment variables if .env file exists
$envPath = __DIR__ . '/../..';
if (file_exists($envPath . '/.env')) {
    $dotenv = Dotenv::createImmutable($envPath);
    $dotenv->load();
}

if (!function_exists('dd')) {
    /**
     * Dump the passed variables and end the script.
     *
     * @param  mixed  $args
     * @return void
     */
    function dd(...$args)
    {
        echo '<pre style="background-color: #1a1a1a; color: #e5f2ff; padding: 15px; border-radius: 15px; font-family: Arial, sans-serif;">';
        foreach ($args as $arg) {
            echo '<code>';
            var_dump($arg);
            echo '</code>';
        }
        echo '</pre>';
        die(1);
    }
}

function getPostDataInput()
{
    $secretKey = $_ENV['JWT_SECRET'] ?? $_ENV['SECRET_KEY'] ?? null;
    $algo = $_ENV['JWT_ALGO'] ?? 'HS256';

    $rawInput = file_get_contents('php://input');
    $decodedJson = json_decode($rawInput, true);

    if (json_last_error() === JSON_ERROR_NONE && is_array($decodedJson)) {
        $payload = $decodedJson;
    } else {
        parse_str($rawInput, $parsedFormData);
        $payload = !empty($parsedFormData) ? $parsedFormData : ($_POST ?? []);
    }

    $postData = (object)$payload;

    $request_token = getTokenFromRequest();
    $token = $request_token->headers ?? $request_token->query ?? $request_token->body ?? null;

    if($token && $secretKey){
        try {
            $decoded = JWT::decode($token, new Key($secretKey, $algo));

            if($decoded){
                $postData->user_detail = $decoded;
            }
        } catch (\Exception $e) {
            // If token is invalid or expired, return false
            return false;
        }
    }

    return $postData;
}

function getPath($version = true)
{
    $requestUri = strtolower(parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH));
    $requestUri = trim($requestUri, '/');

    if ($requestUri === '') {
        return '';
    }

    $segments = array_values(array_filter(explode('/', $requestUri)));
    $versionIndex = null;

    foreach ($segments as $index => $segment) {
        if (preg_match('/^v\d+$/', $segment)) {
            $versionIndex = $index;
            break;
        }
    }

    if ($versionIndex !== null) {
        $segments = array_slice($segments, $version ? $versionIndex : $versionIndex + 1);
    }

    return implode('/', $segments);
}

function getApiVersion(){
    $requestUri = strtolower(parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH));
    $requestUri = trim($requestUri, '/');
    $segments = array_values(array_filter(explode('/', $requestUri)));

    foreach ($segments as $segment) {
        if (preg_match('/^v\d+$/', $segment)) {
            return $segment;
        }
    }

    return null;
}

function getTokenFromRequest(){
    $jsonData = file_get_contents('php://input');
    $postData = (object)json_decode($jsonData, true);

    $authorizationHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['Authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null;

    if($authorizationHeader && stripos($authorizationHeader, 'Bearer ') === 0){
        $authorizationHeader = trim(substr($authorizationHeader, 7));
    }

    return (object) [
        "headers" => $_SERVER['HTTP_TOKEN'] ?? $authorizationHeader ?? null,
        "query"   => $_GET['token'] ?? null,
        "body"    => $postData->token ?? null,
        "ip"      => $_SERVER['REMOTE_ADDR'] ?? null
    ];
}

function uploadBase64($base64string, $folderPath = 'uploads'){
    // Create directory if it doesn't exist
    if (!is_dir($folderPath)) {
        mkdir($folderPath, 0755, true);
    }

    $data = explode(',', $base64string);
    if (count($data) < 2) {
        throw new \Exception('Invalid base64 string format');
    }
    
    $base64 = $data[1];
    $format = explode(';', (explode('/', $data[0])[1]))[0];

    $fileName = RandomString(15) . '-' . time() . '.' . $format;
    $path = $folderPath . '/' . $fileName;

    // saving image
    $image = base64_to_jpeg($base64string, $path);

    return $fileName;
}

function base64_to_jpeg($base64_string, $output_file) {
    // Create directory if it doesn't exist
    $dir = dirname($output_file);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    // open the output file for writing
    $ifp = fopen( $output_file, 'wb' );
    
    if ($ifp === false) {
        throw new \Exception("Failed to open file for writing: $output_file");
    }

    // split the string on commas
    // $data[ 0 ] == "data:image/png;base64"
    // $data[ 1 ] == <actual base64 string>
    $data = explode( ',', $base64_string );

    if (count($data) < 2) {
        fclose($ifp);
        throw new \Exception('Invalid base64 string format');
    }

    // we could add validation here with ensuring count( $data ) > 1
    $decoded = base64_decode( $data[ 1 ], true );
    if ($decoded === false) {
        fclose($ifp);
        throw new \Exception('Failed to decode base64 string');
    }

    fwrite( $ifp, $decoded );

    // clean up the file resource
    fclose( $ifp );

    return $output_file;
}

function RandomString($length = 10)
{
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randstring = '';
    for ($i = 0; $i < $length; $i++) {
        $randstring .= $characters[rand(0, strlen($characters)-1)];
    }
    return $randstring;
}

if (!function_exists('base_url')) {
    /**
     * Get base URL
     * 
     * @param string $path Optional path to append
     * @return string Base URL
     */
    function base_url($path = '') {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        
        // Extract base path from script name (remove index.php or server.php)
        $basePath = dirname($scriptName);
        if ($basePath === '/' || $basePath === '\\') {
            $basePath = '';
        }
        
        // Remove project folder name if present (e.g., /Madras_Api)
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        if (preg_match('#^/([^/]+)/#', $requestUri, $matches)) {
            $basePath = '/' . $matches[1];
        }
        
        $baseUrl = $protocol . $host . $basePath;
        
        // Ensure trailing slash
        if (substr($baseUrl, -1) !== '/') {
            $baseUrl .= '/';
        }
        
        // Append path if provided
        if ($path !== '') {
            $path = ltrim($path, '/');
            $baseUrl .= $path;
        }
        
        return $baseUrl;
    }
}

if (!function_exists('validation_errors')) {
    /**
     * Get validation errors (CodeIgniter compatibility)
     * 
     * @return string HTML formatted validation errors
     */
    function validation_errors($prefix = '', $suffix = '') {
        // Get validation errors from session or global variable
        $errors = $_SESSION['validation_errors'] ?? $GLOBALS['validation_errors'] ?? [];
        
        if (empty($errors)) {
            return '';
        }
        
        $output = '';
        if (!is_array($errors)) {
            $errors = [$errors];
        }
        
        foreach ($errors as $error) {
            $output .= $prefix . $error . $suffix . "\n";
        }
        
        return $output;
    }
}

if (!function_exists('set_value')) {
    /**
     * Set form field value (CodeIgniter compatibility)
     * 
     * @param string $field Field name
     * @param mixed $default Default value
     * @return mixed Field value or default
     */
    function set_value($field, $default = '') {
        // Get value from POST data or default
        return $_POST[$field] ?? $_GET[$field] ?? $default;
    }
}
