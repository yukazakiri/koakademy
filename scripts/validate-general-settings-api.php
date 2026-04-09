#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';

use App\Http\Controllers\Api\V1\GeneralSettingController;
use App\Http\Resources\GeneralSettingResource;
use App\Models\GeneralSetting;
use App\Services\GeneralSettingsService;
use Illuminate\Http\Request;

echo "🔍 Validating General Settings API Implementation\n";
echo "=============================================\n\n";

// 1. Check if Model exists and is properly configured
echo "✓ GeneralSetting Model:\n";
try {
    $settings = GeneralSetting::first();
    if ($settings) {
        echo "  - Found existing general settings (ID: {$settings->id})\n";
        echo "  - Site name: {$settings->site_name}\n";
    } else {
        echo "  - No existing settings found (this is OK for testing)\n";
    }
} catch (Exception $e) {
    echo "  ❌ Error: {$e->getMessage()}\n";
}

// 2. Check if Resource can transform data
echo "\n✓ GeneralSettingResource:\n";
try {
    $settings = GeneralSetting::first();
    if ($settings) {
        $resource = new GeneralSettingResource($settings);
        $data = $resource->toArray(new Request());
        echo "  - Resource transforms model correctly\n";
        echo '  - Contains '.count($data)." fields\n";
        echo '  - Includes computed fields: '.(isset($data['school_year']) ? 'Yes' : 'No')."\n";
    }
} catch (Exception $e) {
    echo "  ❌ Error: {$e->getMessage()}\n";
}

// 3. Check if Controller exists and methods are defined
echo "\n✓ GeneralSettingController:\n";
try {
    $controller = new GeneralSettingController(new GeneralSettingsService());

    $reflection = new ReflectionClass($controller);
    $methods = ['index', 'show', 'store', 'update', 'destroy', 'current', 'getSetting', 'serviceSettings', 'restore', 'forceDestroy'];

    foreach ($methods as $method) {
        if ($reflection->hasMethod($method)) {
            echo "  - {$method}() method exists\n";
        } else {
            echo "  ❌ {$method}() method missing\n";
        }
    }
} catch (Exception $e) {
    echo "  ❌ Error: {$e->getMessage()}\n";
}

// 4. Check if Service exists
echo "\n✓ GeneralSettingsService:\n";
try {
    $service = new GeneralSettingsService();
    echo "  - Service instantiated successfully\n";

    // Test service methods
    $semester = $service->getCurrentSemester();
    echo "  - Current semester: {$semester}\n";

    $schoolYear = $service->getCurrentSchoolYearString();
    echo "  - School year string: {$schoolYear}\n";

} catch (Exception $e) {
    echo "  ❌ Error: {$e->getMessage()}\n";
}

// 5. Check routes
echo "\n✓ API Routes:\n";
try {
    $routes = app('router')->getRoutes();
    $apiRoutes = [];

    foreach ($routes as $route) {
        if (str_starts_with($route->uri(), 'api/settings')) {
            $apiRoutes[] = [
                'method' => implode('|', $route->methods()),
                'uri' => $route->uri(),
                'name' => $route->getName() ?? 'N/A',
            ];
        }
    }

    foreach ($apiRoutes as $route) {
        echo "  - {$route['method']} {$route['uri']} ({$route['name']})\n";
    }
} catch (Exception $e) {
    echo "  ❌ Error: {$e->getMessage()}\n";
}

echo "\n📝 API Structure Summary:\n";
echo "========================\n";
echo "The General Settings API provides the following structure:\n\n";
echo "1. /api/settings - List all settings\n";
echo "2. /api/settings/current - Get current settings (most common)\n";
echo "3. /api/settings/key/{key} - Get specific setting by key\n";
echo "4. /api/settings/service - Get service settings (computed values)\n";
echo "5. /api/settings/{id} - Get specific settings by ID\n";
echo "6. Standard CRUD endpoints (POST, PUT, DELETE, restore, forceDelete)\n\n";

echo "🎉 Validation complete!\n";
