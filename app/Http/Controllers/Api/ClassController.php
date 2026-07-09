<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use Illuminate\Http\Request;

class ClassController extends Controller
{
    public function index(Request $request)
    {
        $classes = SchoolClass::all(['id', 'name']);

        return response()->json([
            'success' => true,
            'data' => $classes
        ]);
    }
}
