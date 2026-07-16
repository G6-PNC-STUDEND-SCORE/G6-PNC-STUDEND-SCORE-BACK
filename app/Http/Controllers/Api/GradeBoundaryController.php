<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GradeBoundary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GradeBoundaryController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => GradeBoundary::orderBy('min_percent', 'desc')->get(),
        ]);
    }

    public function update(Request $request, GradeBoundary $gradeBoundary): JsonResponse
    {
        $request->validate([
            'grade' => 'sometimes|string|max:10',
            'min_percent' => 'sometimes|numeric|min:0|max:100',
            'max_percent' => 'sometimes|numeric|min:0|max:100',
            'label' => 'sometimes|string|max:100',
            'color' => 'nullable|string|max:20',
            'is_active' => 'sometimes|boolean',
        ]);

        $gradeBoundary->update($request->only('grade', 'min_percent', 'max_percent', 'label', 'color', 'is_active'));

        return response()->json([
            'success' => true,
            'data' => $gradeBoundary->fresh(),
            'message' => 'Grade boundary updated successfully',
        ]);
    }
}
