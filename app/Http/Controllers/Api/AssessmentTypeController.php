<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponds;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAssessmentTypeRequest;
use App\Http\Requests\UpdateAssessmentTypeRequest;
use App\Models\AssessmentType;
use Illuminate\Http\JsonResponse;

class AssessmentTypeController extends Controller
{
    use ApiResponds;

    public function index(): JsonResponse
    {
        return $this->success(AssessmentType::where('is_active', true)->get());
    }

    public function store(StoreAssessmentTypeRequest $request): JsonResponse
    {
        $type = AssessmentType::create($request->only('code', 'name', 'weight_percent', 'is_active'));

        return $this->created($type, 'Assessment type created.');
    }

    public function update(UpdateAssessmentTypeRequest $request, AssessmentType $assessmentType): JsonResponse
    {
        $assessmentType->update($request->only('name', 'weight_percent', 'is_active'));

        return $this->success($assessmentType->fresh(), 'Assessment type updated.');
    }

    public function destroy(AssessmentType $assessmentType): JsonResponse
    {
        if ($assessmentType->scoreDetails()->exists()) {
            return $this->error('Cannot delete: assessment type is in use by score details.', 409);
        }

        $assessmentType->delete();

        return $this->success(message: 'Assessment type deleted.');
    }
}
