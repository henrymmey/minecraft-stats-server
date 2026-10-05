<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\IngestBatchRequest;
use App\Services\Ingest\IngestService;
use Illuminate\Http\JsonResponse;

class IngestController
{
    public function __construct(private readonly IngestService $ingest)
    {
    }

    public function store(IngestBatchRequest $request): JsonResponse
    {
        return response()->json(
            $this->ingest->handle(
                $request->validated(),
                $request->attributes->get('api_key'),
                $request,
            ),
        );
    }
}
