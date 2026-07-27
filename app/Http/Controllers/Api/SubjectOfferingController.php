<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ApiResponds;
use App\Models\SubjectOffering;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubjectOfferingController extends Controller
{
    use ApiResponds;

    public function index(Request $request): JsonResponse
    {
        $query = SubjectOffering::with(['subject', 'teacher.user', 'class', 'term'])
            ->where('status', 'active');

        if ($request->filled('term_id')) {
            $query->where('term_id', $request->term_id);
        }
        if ($request->filled('class_id')) {
            $query->where('class_id', $request->class_id);
        }
        if ($request->filled('teacher_id')) {
            $query->where('teacher_id', $request->teacher_id);
        }

        return $this->success($query->get());
    }

    public function enrollments(SubjectOffering $offering): JsonResponse
    {
        $enrollments = \App\Models\StudentSubjectEnrollment::with([
            'student.user',
            'score.details.assessmentType',
        ])->where('subject_offering_id', $offering->id)->get();

        return $this->success($enrollments);
    }
}
