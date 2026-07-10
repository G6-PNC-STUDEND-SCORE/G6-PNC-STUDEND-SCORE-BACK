<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\SchoolClass;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentController extends Controller
{
    /**
     * Display a listing of students.
     */
    public function index(): JsonResponse
    {
        $students = Student::with(['class', 'academicYear', 'user'])
            ->orderBy('student_number')
            ->get();

        return response()->json([
            'students' => $students,
        ]);
    }

    /**
     * Store a newly created student.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'student_number' => 'required|string|max:20|unique:students,student_number',
            'intake_year' => 'required|integer',
            'sequence_number' => 'required|integer',
            'class_id' => 'nullable|exists:classes,id',
            'academic_year_id' => 'nullable|exists:academic_years,id',
            'enrollment_date' => 'nullable|date',
        ]);

        $student = Student::create($validated);

        return response()->json([
            'message' => 'Student created successfully',
            'student' => $student->load(['class', 'academicYear', 'user']),
        ], 201);
    }

    /**
     * Display the specified student.
     */
    public function show(Student $student): JsonResponse
    {
        return response()->json([
            'student' => $student->load(['class', 'academicYear', 'user']),
        ]);
    }

    /**
     * Update the specified student.
     */
    public function update(Request $request, Student $student): JsonResponse
    {
        $validated = $request->validate([
            'class_id' => 'nullable|exists:classes,id',
            'academic_year_id' => 'nullable|exists:academic_years,id',
            'enrollment_date' => 'nullable|date',
        ]);

        $student->update($validated);

        return response()->json([
            'message' => 'Student updated successfully',
            'student' => $student->fresh()->load(['class', 'academicYear', 'user']),
        ]);
    }

    /**
     * Remove the specified student.
     */
    public function destroy(Student $student): JsonResponse
    {
        $student->delete();

        return response()->json([
            'message' => 'Student deleted successfully',
        ]);
    }

    /**
     * Assign student to a class.
     */
    public function assignClass(Request $request, Student $student): JsonResponse
    {
        $validated = $request->validate([
            'class_id' => 'required|exists:classes,id',
        ]);

        $student->update([
            'class_id' => $validated['class_id'],
        ]);

        return response()->json([
            'message' => 'Student assigned to class successfully',
            'student' => $student->fresh()->load(['class', 'academicYear', 'user']),
        ]);
    }
}