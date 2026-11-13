<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class HealthController extends Controller
{
    public function live(): JsonResponse
    {
        return response()->json([
            'status' => 'alive',
            'version' => (string) config('app.version', '0.1.0'),
        ]);
    }

    public function ready(): JsonResponse
    {
        return response()->json([
            'status' => 'ready',
            'version' => (string) config('app.version', '0.1.0'),
        ]);
    }
}
