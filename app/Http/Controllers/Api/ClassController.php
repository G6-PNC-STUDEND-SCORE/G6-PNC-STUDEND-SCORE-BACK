<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
<<<<<<< HEAD
use Illuminate\Http\Request;

class ClassController extends Controller
{
    public function index(Request $request)
    {
        $classes = SchoolClass::all(['id', 'name']);

        return response()->json([
            'success' => true,
            'data' => $classes
=======
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
>>>>>>> 89f08095771340c40dcab33d01cd9989ac4dac40
        ]);
    }
}
