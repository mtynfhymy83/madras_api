<?php

namespace App\Services\Storage;

use App\Contracts\StorageInterface;
use Aws\S3\S3Client;
use Aws\Exception\AwsException;

class S3Storage implements StorageInterface
{
    private S3Client $client;
    private string $bucket;
    private string $region;
    private ?string $cdnUrl;

    public function __construct()
    {
        // Unset proxy environment variables BEFORE creating S3Client
        // This must be done early to prevent Guzzle from reading proxy settings
        putenv('HTTP_PROXY=');
        putenv('HTTPS_PROXY=');
        putenv('http_proxy=');
        putenv('https_proxy=');
        putenv('NO_PROXY=*');
        putenv('no_proxy=*');
        unset($_ENV['HTTP_PROXY'], $_ENV['HTTPS_PROXY'], $_ENV['http_proxy'], $_ENV['https_proxy']);
        
        $this->bucket = $_ENV['AWS_BUCKET'] ?? '';
        $this->region = $_ENV['AWS_DEFAULT_REGION'] ?? 'us-east-1';
        $this->cdnUrl = $_ENV['AWS_CDN_URL'] ?? null;
        
        if (empty($this->bucket)) {
            throw new \RuntimeException('AWS_BUCKET is not configured');
        }

        // Get endpoint - always set it to avoid null issues
        $endpoint = $_ENV['AWS_ENDPOINT'] ?? 'https://s3.amazonaws.com';
        $usePathStyle = filter_var($_ENV['AWS_USE_PATH_STYLE_ENDPOINT'] ?? 'true', FILTER_VALIDATE_BOOLEAN);
        
        $config = [
            'version' => 'latest',
            'region' => $this->region,
            'credentials' => [
                'key' => $_ENV['AWS_ACCESS_KEY_ID'] ?? '',
                'secret' => $_ENV['AWS_SECRET_ACCESS_KEY'] ?? '',
            ],
            'endpoint' => $endpoint,
            'use_path_style_endpoint' => $usePathStyle,
            // Disable proxy in HTTP client configuration
            'http' => [
                'proxy' => null,
                'verify' => true,
            ],
        ];

        $this->client = new S3Client($config);
    }

    public function upload(string $source, string $destination, array $options = []): string
    {
        // Validate inputs
        if (empty($destination)) {
            throw new \InvalidArgumentException('Destination path cannot be empty');
        }
        
        if (empty($this->bucket)) {
            throw new \RuntimeException('Bucket is not configured');
        }
        
        $params = [
            'Bucket' => $this->bucket,
            'Key' => $destination,
            'ACL' => $options['acl'] ?? 'public-read',
            'CacheControl' => $options['cache_control'] ?? 'public, max-age=31536000, immutable',
        ];

        // Check if source is file path or content
        if (file_exists($source)) {
            $params['SourceFile'] = $source;
        } else {
            $params['Body'] = $source;
        }

        // Set content type
        if (isset($options['content_type'])) {
            $params['ContentType'] = $options['content_type'];
        } else {
            $params['ContentType'] = $this->guessContentType($destination);
        }

        try {
            $result = $this->client->putObject($params);
            return $destination;
        } catch (AwsException $e) {
            error_log('S3 Upload Error: ' . $e->getMessage());
            error_log('Bucket: ' . $this->bucket);
            error_log('Key: ' . $destination);
            error_log('Endpoint: ' . ($_ENV['AWS_ENDPOINT'] ?? 'not set'));
            throw new \Exception('Failed to upload to S3: ' . $e->getMessage());
        }
    }

    public function delete(string $path): bool
    {
        try {
            $this->client->deleteObject([
                'Bucket' => $this->bucket,
                'Key' => $path,
            ]);
            return true;
        } catch (AwsException $e) {
            return false;
        }
    }

    public function exists(string $path): bool
    {
        return $this->client->doesObjectExist($this->bucket, $path);
    }

    public function url(string $path): string
    {
        // Validate path
        if (empty($path)) {
            throw new \InvalidArgumentException('Path cannot be empty');
        }
        
        // Use CDN URL if configured
        if ($this->cdnUrl) {
            return rtrim($this->cdnUrl, '/') . '/' . ltrim($path, '/');
        }

        // Return S3 URL - ensure bucket and path are valid
        if (empty($this->bucket)) {
            throw new \RuntimeException('Bucket is not configured');
        }
        
        // Construct URL manually to avoid getObjectUrl() issues with custom endpoints
        $endpoint = $_ENV['AWS_ENDPOINT'] ?? 'https://s3.amazonaws.com';
        $usePathStyle = filter_var($_ENV['AWS_USE_PATH_STYLE_ENDPOINT'] ?? 'true', FILTER_VALIDATE_BOOLEAN);
        
        $endpoint = rtrim($endpoint, '/');
        $path = ltrim($path, '/');
        
        if ($usePathStyle) {
            // Path-style URL: https://endpoint/bucket/key
            return $endpoint . '/' . $this->bucket . '/' . $path;
        } else {
            // Virtual-hosted style: https://bucket.endpoint/key
            $host = parse_url($endpoint, PHP_URL_HOST);
            $scheme = parse_url($endpoint, PHP_URL_SCHEME) ?? 'https';
            return $scheme . '://' . $this->bucket . '.' . $host . '/' . $path;
        }
    }

    public function temporaryUrl(string $path, int $expiresIn = 3600): string
    {
        // Validate path
        if (empty($path)) {
            throw new \InvalidArgumentException('Path cannot be empty');
        }
        
        try {
            $cmd = $this->client->getCommand('GetObject', [
                'Bucket' => $this->bucket,
                'Key' => $path,
            ]);

            $request = $this->client->createPresignedRequest($cmd, "+{$expiresIn} seconds");

            return (string) $request->getUri();
        } catch (\Exception $e) {
            // Fallback to regular URL if presigned request fails
            return $this->url($path);
        }
    }

    private function guessContentType(string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        
        $mimeTypes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'pdf' => 'application/pdf',
            'mp3' => 'audio/mpeg',
            'mp4' => 'video/mp4',
        ];

        return $mimeTypes[$extension] ?? 'application/octet-stream';
    }
}
