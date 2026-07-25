<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ApiResponds;
use App\Models\Generation;
use Illuminate\Http\JsonResponse;

class AcademicYearController extends Controller
{
    use ApiResponds;

    public function index(): JsonResponse
    {
        return $this->success(Generation::all(['id', 'name']));
    }
}
