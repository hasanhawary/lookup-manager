<?php

namespace HasanHawary\LookupManager\Http\Controllers;

use HasanHawary\LookupManager\Facades\Lookup;
use HasanHawary\LookupManager\Http\Requests\HelpConfigRequest;
use HasanHawary\LookupManager\Http\Requests\HelpEnumRequest;
use HasanHawary\LookupManager\Http\Requests\HelpModelRequest;
use Illuminate\Http\JsonResponse;

class HelpController
{
    public function models(HelpModelRequest $request): JsonResponse
    {
        return $this->success(Lookup::getModels($request->validated()));
    }

    public function enums(HelpEnumRequest $request): JsonResponse
    {
        return $this->success(Lookup::getEnums($request->validated()));
    }

    public function config(HelpConfigRequest $request): JsonResponse
    {
        return $this->success(Lookup::getConfigs($request->validated()));
    }

    private function success(array $data = [], ?string $message = null, int $code = 200): JsonResponse
    {
        return response()->json([
            'status' => true,
            'code' => $code,
            'message' => $message ?? 'Success',
            'data' => $data,
        ], $code);
    }
}
