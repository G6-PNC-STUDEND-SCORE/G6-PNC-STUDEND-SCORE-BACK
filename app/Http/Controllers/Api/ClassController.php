<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use Illuminate\Http\JsonResponse;

class ClassController extends Controller
{
    /**
     * Return a simple list of classes (for dropdowns).
     */
    public function list(): JsonResponse
    {
        $classes = SchoolClass::orderBy('name')->get(['id', 'name']);

        return response()->json([
            'classes' => $classes,
        ]);
    }
}
