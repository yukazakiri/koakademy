<?php

declare(strict_types=1);

namespace App\Filament\Resources\Classes\Api\Handlers;

use App\Filament\Resources\Classes\Api\Requests\CreateClassesRequest;
use App\Filament\Resources\Classes\ClassesResource;
use Rupadana\ApiService\Http\Handlers;

final class CreateHandler extends Handlers
{
    public static ?string $uri = '/';

    public static ?string $resource = ClassesResource::class;

    protected static string $permission = 'Create:Classes';

    public static function getMethod()
    {
        return Handlers::POST;
    }

    public static function getModel()
    {
        return self::$resource::getModel();
    }

    /**
     * Create Classes
     */
    public function handler(CreateClassesRequest $request): \Illuminate\Http\JsonResponse
    {
        $model = new (self::getModel());

        // Separate settings fields from regular model attributes
        $settingsFields = [
            'background_color',
            'accent_color',
            'banner_image',
            'theme',
            'enable_announcements',
            'enable_grade_visibility',
            'enable_attendance_tracking',
            'allow_late_submissions',
            'enable_discussion_board',
            'custom',
        ];

        $settingsData = [];
        $requestData = $request->all();

        // Extract settings from request
        foreach ($settingsFields as $field) {
            if (array_key_exists($field, $requestData)) {
                $settingsData[$field] = $requestData[$field];
                unset($requestData[$field]);
            }
        }

        // Update regular model attributes
        $model->fill($requestData);

        // Set default settings if none provided
        if ($settingsData === []) {
            $model->settings = self::getModel()::getDefaultSettings();
        } else {
            // Merge with default settings and provided settings
            $defaultSettings = self::getModel()::getDefaultSettings();
            $model->settings = array_merge($defaultSettings, $settingsData);
        }

        $model->save();

        return self::sendSuccessResponse($model, 'Successfully Create Resource');
    }
}
