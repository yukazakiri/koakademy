<?php

declare(strict_types=1);

namespace App\Filament\Resources\Classes\Api\Handlers;

use App\Filament\Resources\Classes\Api\Requests\UpdateClassesRequest;
use App\Filament\Resources\Classes\ClassesResource;
use Rupadana\ApiService\Http\Handlers;

final class UpdateHandler extends Handlers
{
    public static ?string $uri = '/{id}';

    public static ?string $resource = ClassesResource::class;

    protected static string $permission = 'Update:Classes';

    public static function getMethod()
    {
        return Handlers::PUT;
    }

    public static function getModel()
    {
        return self::$resource::getModel();
    }

    /**
     * Update Classes
     */
    public function handler(UpdateClassesRequest $request): \Illuminate\Http\JsonResponse
    {
        $id = $request->route('id');

        $model = self::getModel()::find($id);

        if (! $model) {
            return self::sendNotFoundResponse();
        }

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
        // Update regular model attributes
        $model->fill($requestData);

        // Update settings if any were provided
        if ($settingsData !== []) {
            $currentSettings = $model->settings ?? [];
            $model->settings = array_merge($currentSettings, $settingsData);
        }

        $model->save();

        // Handle Schedule Updates
        if ($request->has('schedules')) {
            $schedules = $request->input('schedules');

            // Delete existing schedules
            $model->schedules()->delete();

            // Create new schedules
            if (is_array($schedules)) {
                foreach ($schedules as $scheduleData) {
                    $model->schedules()->create([
                        'day_of_week' => $scheduleData['day_of_week'],
                        'start_time' => $scheduleData['start_time'],
                        'end_time' => $scheduleData['end_time'],
                        'room_id' => $scheduleData['room_id'],
                    ]);
                }
            }
        }

        return self::sendSuccessResponse($model, 'Successfully Update Resource');
    }
}
