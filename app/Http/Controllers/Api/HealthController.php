<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        try {
            DB::select('select 1');
        } catch (QueryException) {
            return response()->json([
                'status' => 'unhealthy',
                'database' => false,
            ], 503);
        }

        return response()->json([
            'status' => 'ok',
            'database' => true,
        ]);
    }
}
