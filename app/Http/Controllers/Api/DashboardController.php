<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        protected DashboardService $dashboardService
    ) {}

    /**
     * Get all dashboard data in a single API response.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'generation_id' => 'nullable|integer|exists:generations,id',
            'term_id'       => 'nullable|integer|exists:terms,id',
            'class_id'      => 'nullable|integer|exists:classes,id',
            'department_id' => 'nullable|integer|exists:departments,id',
            'teacher_id'    => 'nullable|integer|exists:teachers,id',
        ]);

        $data = $this->dashboardService->getDashboardData($filters);

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }

    /**
     * Get filter options for the dashboard.
     */
    public function filters(): JsonResponse
    {
        $filters = $this->dashboardService->getFilterOptions();

        return response()->json([
            'success' => true,
            'data'    => $filters,
        ]);
    }
}
