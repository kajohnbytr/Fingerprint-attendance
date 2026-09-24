<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reading;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReadingController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'device_id' => ['required', 'string', 'max:100'],
            'temperature' => ['required', 'numeric', 'between:-999.99,999.99'],
            'humidity' => ['required', 'numeric', 'between:0,100'],
        ]);
        $token = $request->user()->currentAccessToken();
        abort_unless($token && $token->name === $data['device_id']
            && $request->user()->tokenCan('readings:create'), 403);
        $reading = Reading::create($data);

        return response()->json(['message' => 'Saved', 'id' => $reading->id], 201);
    }
}
