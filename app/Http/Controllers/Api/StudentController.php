<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentController extends Controller
{
    /**
     * Get the URL path for a stored photo.
     */
    private function storePhoto($file): string
    {
        $dir = public_path('uploads/photos');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
        $file->move($dir, $filename);
        return '/uploads/photos/' . $filename;
    }

    /**
     * Delete a photo file.
     */
    private function deletePhoto(?string $photoPath): void
    {
        if ($photoPath && str_starts_with($photoPath, '/uploads/')) {
            $fullPath = public_path(ltrim($photoPath, '/'));
            if (file_exists($fullPath)) {
                @unlink($fullPath);
            }
        }
    }

    /**
     * Display a listing of students.
     */
    public function index(): JsonResponse
    {
        $students = Student::with('class')->orderBy('name')->get();

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
            'name' => 'required|string|max:255',
            'gender' => 'required|in:Male,Female',
            'class_id' => 'nullable|exists:classes,id',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'status' => 'nullable|in:active,inactive',
        ]);

        if ($request->hasFile('photo')) {
            $validated['photo'] = $this->storePhoto($request->file('photo'));
        } else {
            $validated['photo'] = null;
        }

        $student = Student::create($validated);

        return response()->json([
            'message' => 'Student created successfully',
            'student' => $student->load('class'),
        ], 201);
    }

    /**
     * Display the specified student.
     */
    public function show(Student $student): JsonResponse
    {
        return response()->json([
            'student' => $student->load('class'),
        ]);
    }

    /**
     * Update the specified student.
     */
    public function update(Request $request, Student $student): JsonResponse
    {
        $rules = [
            'name' => 'sometimes|string|max:255',
            'gender' => 'sometimes|in:Male,Female',
            'class_id' => 'nullable|exists:classes,id',
            'status' => 'nullable|in:active,inactive',
        ];

        if ($request->hasFile('photo')) {
            $rules['photo'] = 'image|mimes:jpeg,png,jpg,gif,webp|max:2048';
        } else {
            $rules['photo'] = 'nullable|string|max:500';
        }

        $validated = $request->validate($rules);

        if ($request->hasFile('photo')) {
            $this->deletePhoto($student->photo);
            $validated['photo'] = $this->storePhoto($request->file('photo'));
        }

        $student->update($validated);

        return response()->json([
            'message' => 'Student updated successfully',
            'student' => $student->fresh()->load('class'),
        ]);
    }

    /**
     * Remove the specified student.
     */
    public function destroy(Student $student): JsonResponse
    {
        $this->deletePhoto($student->photo);
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
            'student' => $student->fresh()->load('class'),
        ]);
    }
}
