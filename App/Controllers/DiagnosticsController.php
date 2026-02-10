<?php

namespace App\Controllers;

class DiagnosticsController
{
    public function index(): array
    {
        ob_start();
        
        echo "🔍 Madras API Server Diagnostics\n";
        echo "=================================\n\n";

        // 1. PHP Version
        echo "1️⃣ PHP Version: " . PHP_VERSION . "\n\n";

        // 2. Check critical files
        echo "2️⃣ Checking critical files:\n";
        $files = [
            'App/Core/MiddlewareInterface.php',
            'App/Core/Pipeline.php',
            'App/core/MiddlewareInterface.php',
            'App/core/Pipeline.php',
            'App/Database/PDOPool.php',
            'vendor/autoload.php',
            '.env'
        ];

        foreach ($files as $file) {
            $path = '/var/www/html/' . $file;
            if (file_exists($path)) {
                echo "   ✅ $file (" . filesize($path) . " bytes)\n";
            } else {
                echo "   ❌ $file NOT FOUND\n";
            }
        }

        echo "\n";

        // 3. Check folders
        echo "3️⃣ Checking App/ subfolders:\n";
        $appDir = '/var/www/html/App';
        if (is_dir($appDir)) {
            $dirs = scandir($appDir);
            foreach ($dirs as $d) {
                if ($d[0] === '.') continue;
                $path = $appDir . '/' . $d;
                if (is_dir($path)) {
                    echo "   📁 $d\n";
                }
            }
        } else {
            echo "   ❌ App/ folder not found!\n";
        }

        echo "\n";

        // 4. Check environment variables
        echo "4️⃣ Environment variables (from \$_ENV):\n";
        $envVars = ['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USERNAME', 'JWT_SECRET', 'APP_HOST', 'APP_PORT'];
        foreach ($envVars as $var) {
            $val = $_ENV[$var] ?? null;
            if ($val !== null) {
                if (strpos($var, 'PASSWORD') !== false || strpos($var, 'SECRET') !== false) {
                    echo "   ✅ $var = ***MASKED***\n";
                } else {
                    echo "   ✅ $var = $val\n";
                }
            } else {
                echo "   ❌ $var = NOT SET\n";
            }
        }

        echo "\n";

        // 5. Check environment from getenv()
        echo "5️⃣ Environment variables (from getenv):\n";
        foreach ($envVars as $var) {
            $val = getenv($var);
            if ($val !== false) {
                if (strpos($var, 'PASSWORD') !== false || strpos($var, 'SECRET') !== false) {
                    echo "   ✅ $var = ***MASKED***\n";
                } else {
                    echo "   ✅ $var = $val\n";
                }
            } else {
                echo "   ❌ $var = NOT SET (getenv)\n";
            }
        }

        echo "\n";

        // 6. Check Composer autoload
        echo "6️⃣ Checking Composer autoload:\n";
        $autoloadPsr4 = '/var/www/html/vendor/composer/autoload_psr4.php';
        if (file_exists($autoloadPsr4)) {
            $psr4 = require $autoloadPsr4;
            if (isset($psr4['App\\'])) {
                echo "   ✅ App\\ namespace found in autoload\n";
                echo "   Path: " . json_encode($psr4['App\\']) . "\n";
            } else {
                echo "   ❌ App\\ namespace NOT in autoload PSR-4!\n";
            }
        } else {
            echo "   ❌ autoload_psr4.php not found\n";
        }

        echo "\n";
        echo "✅ Diagnostics complete!\n";

        $output = ob_get_clean();
        
        return [
            'success' => true,
            'data' => $output,
            'message' => 'Diagnostics',
            'content_type' => 'text/plain'
        ];
    }
}
