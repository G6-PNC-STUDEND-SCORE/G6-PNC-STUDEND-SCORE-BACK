<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ApiResponds;
use App\Http\Requests\StoreAcademicYearRequest;
use App\Http\Requests\UpdateAcademicYearRequest;
use App\Models\AcademicYear;
use Illuminate\Http\JsonResponse;

class AcademicYearController extends Controller
{
    use ApiResponds;

    public function index(): JsonResponse
    {
        return $this->success(AcademicYear::orderBy('year', 'desc')->get());
    }

    public function store(StoreAcademicYearRequest $request): JsonResponse
    {
        if ($request->boolean('is_current')) {
            AcademicYear::where('is_current', true)->update(['is_current' => false]);
        }

        $academicYear = AcademicYear::create($request->only('year', 'name', 'start_date', 'end_date', 'is_current'));

        return $this->created($academicYear, 'Academic year created.');
    }

    public function update(UpdateAcademicYearRequest $request, AcademicYear $academicYear): JsonResponse
    {
        if ($request->boolean('is_current')) {
            AcademicYear::where('is_current', true)->where('id', '!=', $academicYear->id)->update(['is_current' => false]);
        }

        $academicYear->update($request->only('year', 'name', 'start_date', 'end_date', 'is_current'));

        return $this->success($academicYear->fresh(), 'Academic year updated.');
    }

    public function destroy(AcademicYear $academicYear): JsonResponse
    {
        $academicYear->delete();

        return $this->success(message: 'Academic year deleted.');
    }
}
