<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponds;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTermRequest;
use App\Http\Requests\UpdateTermRequest;
use App\Models\Term;
use Illuminate\Http\JsonResponse;

class TermController extends Controller
{
    use ApiResponds;

    public function index(): JsonResponse
    {
        return $this->success(Term::all(['id', 'name']));
    }

    public function store(StoreTermRequest $request): JsonResponse
    {
        $term = Term::create($request->only('academic_year_id', 'name', 'term_number', 'start_date', 'end_date'));

        return $this->created($term, 'Term created.');
    }

    public function update(UpdateTermRequest $request, Term $term): JsonResponse
    {
        $term->update($request->only('name', 'term_number', 'start_date', 'end_date'));

        return $this->success($term->fresh(), 'Term updated.');
    }

    public function destroy(Term $term): JsonResponse
    {
        $term->delete();

        return $this->success(message: 'Term deleted.');
    }
}
