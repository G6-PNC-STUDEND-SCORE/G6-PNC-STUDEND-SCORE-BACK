# Database Structure Improvements

This document outlines the improvements made to the VC2 Student Score database schema for better historical data tracking and data integrity.

## New Tables Created

### 1. `student_class_histories`
Tracks the history of students moving between classes across generations.

**Purpose**: When a student changes classes (e.g., from Class A to Class B), the old record in `students.class_id` will be lost. This table preserves the complete academic history.

```php
Schema::create('student_class_histories', function (Blueprint $table) {
    $table->id();
    $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
    $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
    $table->foreignId('generation_id')->constrained('generations')->cascadeOnDelete();
    $table->date('start_date');
    $table->date('end_date')->nullable();
    $table->enum('status', ['active', 'completed', 'transferred'])->default('active');
    $table->timestamps();
});
```

### 2. `subject_offerings`
Tracks which subjects are taught by which teacher, in which class, during which term and generation.

**Purpose**: When a teacher changes for a subject, the old record is lost. This table preserves the complete teaching history.

```php
Schema::create('subject_offerings', function (Blueprint $table) {
    $table->id();
    $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
    $table->foreignId('teacher_id')->nullable()->constrained('teachers')->nullOnDelete();
    $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
    $table->foreignId('generation_id')->constrained('generations')->cascadeOnDelete();
    $table->foreignId('term_id')->constrained('terms')->cascadeOnDelete();
    $table->enum('status', ['active', 'inactive'])->default('active');
    $table->timestamps();
});
```

## Modified Tables

### 3. `score_details` - Added `max_score` and `order_number`
```php
// Added columns:
$table->integer('order_number')->nullable()->after('label');
$table->integer('max_score')->nullable()->after('order_number');
```

**Purpose**: 
- `max_score`: Enables proper weighted average calculation (e.g., Quiz 1: 8/10, Quiz 2: 18/20 → Average: 85%, not 90%)
- `order_number`: Controls display order for quiz/assignment items

### 4. `scores` - Added `generation_id`
```php
// Added column:
$table->foreignId('generation_id')->nullable()->constrained('generations')->nullOnDelete();
```

**Purpose**: Distinguishes scores across different academic years (2026 Term 1 vs 2027 Term 1).

### 5. `student_subject_enrollments` - Added `subject_offering_id`, `generation_id`, `term_id`
```php
// Added columns:
$table->foreignId('subject_offering_id')->nullable()->constrained('subject_offerings')->nullOnDelete();
$table->foreignId('generation_id')->nullable()->constrained('generations')->nullOnDelete();
$table->foreignId('term_id')->nullable()->constrained('terms')->nullOnDelete();
```

**Purpose**: Links enrollment to a specific offering, automatically inheriting subject, teacher, class, generation, and term.

### 6. `report_cards` - Added `generation_id`
```php
// Added column:
$table->foreignId('generation_id')->nullable()->constrained('generations')->nullOnDelete();
```

**Purpose**: Ensures report cards are uniquely identified by student + generation + term.

### 7. `subjects` - Removed `teacher_id` and `class_id`
```php
// These are now in subject_offerings with historical tracking
// Removed: teacher_id, class_id
// Kept: name, status (catalog-only)
```

## Score Calculation Logic

The `ScoreController::recalculateTotal()` method has been updated to properly calculate weighted averages:

### Before (Simple Average):
```
Quiz 1: 8/10
Quiz 2: 18/20
Average: (8 + 18) / 2 = 13 → 90% (incorrect!)
```

### After (Weighted Average):
```
Quiz 1: 8/10
Quiz 2: 18/20
Average: (8 + 18) / (10 + 20) * 100 = 85% (correct!)
```

## Migration Order

Run migrations in this order:
1. `2026_07_08_000018_create_student_class_histories_table.php`
2. `2026_07_08_000019_create_subject_offerings_table.php`
3. `2026_07_08_000020_update_student_subject_enrollments_table.php`
4. `2026_07_08_000021_update_score_details_table.php`
5. `2026_07_08_000022_update_report_cards_table.php`
6. `2026_07_08_000023_update_scores_table.php`
7. `2026_07_08_000024_update_subjects_table.php`

## API Changes

### ScoreController
- Added `generation_id` parameter to store and validation
- Added `max_score` and `order_number` to detail creation/update
- Updated `recalculateTotal()` to use weighted average calculation

### Request Body Examples

#### Create Score with Details:
```json
{
    "student_id": 1,
    "subject_id": 1,
    "term_id": 1,
    "generation_id": 1,
    "remarks": "Good performance",
    "details": [
        {
            "type": "quiz",
            "label": "Quiz 1",
            "mark": 8,
            "max_score": 10,
            "order_number": 1
        },
        {
            "type": "quiz",
            "label": "Quiz 2",
            "mark": 18,
            "max_score": 20,
            "order_number": 2
        }
    ]
}
```

## Model Relationships

### StudentClassHistory
- `student()`: BelongsTo Student
- `class()`: BelongsTo SchoolClass
- `generation()`: BelongsTo Generation

### SubjectOffering
- `subject()`: BelongsTo Subject
- `teacher()`: BelongsTo Teacher
- `class()`: BelongsTo SchoolClass
- `generation()`: BelongsTo Generation
- `term()`: BelongsTo Term

### Updated StudentSubjectEnrollment
- `subjectOffering()`: BelongsTo SubjectOffering (NEW)
- `generation()`: BelongsTo Generation (NEW)
- `term()`: BelongsTo Term (NEW)

### Updated Subject
- `offerings()`: HasMany SubjectOffering (NEW - replaces teacher/class direct relations)