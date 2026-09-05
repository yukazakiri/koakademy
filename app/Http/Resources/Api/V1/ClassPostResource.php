<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Services\ClassPostPayloadService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ClassPostResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return app(ClassPostPayloadService::class)->serialize($this->resource, ! $request->user()?->isStudentRole());
    }
}
