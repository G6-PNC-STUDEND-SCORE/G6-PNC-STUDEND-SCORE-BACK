<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ClassController extends Controller
{
    public function index(Request $request)
    {
        $query = SchoolClass::query();

        // Search functionality
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('generation', 'like', "%{$search}%")
                  ->orWhere('room', 'like', "%{$search}%")
                  ->orWhere('status', 'like', "%{$search}%");
            });
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Load teacher relationship
        $classes = $query->with('teacher:id,name')->get();

        return response()->json([
            'success' => true,
            'data' => $classes
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'generation' => 'required|string|max:50',
            'teacher_id' => 'nullable|exists:users,id',
            'room' => 'required|string|max:50',
            'students' => 'nullable|integer|min:0',
            'status' => 'required|in:Active,Inactive',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $class = SchoolClass::create($request->all());

        // Only load teacher relationship if teacher_id exists
        if ($class->teacher_id) {
            $class->load('teacher:id,name');
        }

        return response()->json([
            'success' => true,
            'message' => 'Class created successfully',
            'data' => $class
        ], 201);
    }

    public function show($id)
    {
        $class = SchoolClass::with('teacher')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $class
        ]);
    }

    public function update(Request $request, $id)
    {
        $class = SchoolClass::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'generation' => 'required|string|max:50',
            'teacher_id' => 'nullable|exists:users,id',
            'room' => 'required|string|max:50',
            'students' => 'nullable|integer|min:0',
            'status' => 'required|in:Active,Inactive',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $class->update($request->all());

        // Only load teacher relationship if teacher_id exists
        if ($class->teacher_id) {
            $class->load('teacher:id,name');
        }

        return response()->json([
            'success' => true,
            'message' => 'Class updated successfully',
            'data' => $class
        ]);
    }

    public function destroy($id)
    {
        $class = SchoolClass::findOrFail($id);
        $class->delete();

        return response()->json([
            'success' => true,
            'message' => 'Class deleted successfully'
        ]);
    }

    public function importPDF(Request $request)
    {
        $request->validate([
            'pdf' => 'required|file|mimes:pdf|max:10240', // 10MB max
        ]);

        try {
            $pdfFile = $request->file('pdf');

            // Use Laravel's built-in PDF parsing or a simple text extraction
            // For now, we'll use a basic approach with str_getcsv for simple CSV-like PDFs
            // In production, you might want to use a library like smalot/pdfparser or thiagoalessio/tesseract_ocr

            $content = file_get_contents($pdfFile->getRealPath());

            // Simple text extraction from PDF (basic implementation)
            // Remove PDF binary data and extract text
            $text = $this->extractTextFromPDF($content);

            // Parse the text to extract class information
            $classes = $this->parseClassesFromText($text);

            $imported = 0;
            $errors = [];

            foreach ($classes as $classData) {
                try {
                    // Validate required fields
                    if (empty($classData['name']) || empty($classData['generation']) || empty($classData['room'])) {
                        $errors[] = "Skipped: Missing required fields for class";
                        continue;
                    }

                    // Check if class already exists
                    $existing = SchoolClass::where('name', $classData['name'])
                        ->where('generation', $classData['generation'])
                        ->first();

                    if ($existing) {
                        $errors[] = "Skipped: {$classData['name']} already exists";
                        continue;
                    }

                    // Create the class
                    SchoolClass::create([
                        'name' => $classData['name'],
                        'generation' => $classData['generation'],
                        'room' => $classData['room'],
                        'students' => $classData['students'] ?? 0,
                        'status' => $classData['status'] ?? 'Active',
                        'teacher_id' => $classData['teacher_id'] ?? null,
                    ]);

                    $imported++;
                } catch (\Exception $e) {
                    $errors[] = "Error importing {$classData['name']}: " . $e->getMessage();
                }
            }

            $message = "Successfully imported {$imported} class(es)";
            if (!empty($errors)) {
                $message .= ". " . count($errors) . " error(s) occurred.";
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'imported' => $imported,
                'errors' => $errors,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to import PDF: ' . $e->getMessage(),
            ], 422);
        }
    }

    private function extractTextFromPDF(string $pdfContent): string
    {
        // Basic PDF text extraction
        // This is a simplified version - in production use a proper PDF parser library

        // Remove PDF headers and binary data
        $text = '';

        // Try to extract text between stream and endstream markers
        if (preg_match_all('/stream(.*?)endstream/s', $pdfContent, $matches)) {
            foreach ($matches[1] as $stream) {
                // Remove binary characters and keep only printable text
                $cleaned = preg_replace('/[^\x20-\x7E]/', ' ', $stream);
                $text .= $cleaned . ' ';
            }
        }

        // Clean up the text
        $text = preg_replace('/\s+/', ' ', $text);
        $text = str_replace(['(', ')', '[', ']', '{', '}'], '', $text);

        return trim($text);
    }

    private function parseClassesFromText(string $text): array
    {
        $classes = [];

        // Try to parse class information from text
        // Look for patterns like: Class Name, Generation, Room, Students

        // Split by common delimiters
        $lines = preg_split('/[\n\r]+/', $text);

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            // Try to match class patterns
            // Pattern: Class 10A, 2025, Room 101, 30
            if (preg_match('/Class\s+(\w+)[,\s]+(\d{4})[,\s]+Room\s+(\w+)[,\s]+(\d+)/i', $line, $matches)) {
                $classes[] = [
                    'name' => 'Class ' . $matches[1],
                    'generation' => $matches[2],
                    'room' => 'Room ' . $matches[3],
                    'students' => (int) $matches[4],
                    'status' => 'Active',
                ];
            }
            // Pattern: 10A, 2025, Room 101, 30
            elseif (preg_match('/(\w+)[,\s]+(\d{4})[,\s]+Room\s+(\w+)[,\s]+(\d+)/i', $line, $matches)) {
                $classes[] = [
                    'name' => 'Class ' . $matches[1],
                    'generation' => $matches[2],
                    'room' => 'Room ' . $matches[3],
                    'students' => (int) $matches[4],
                    'status' => 'Active',
                ];
            }
        }

        return $classes;
    }
}
