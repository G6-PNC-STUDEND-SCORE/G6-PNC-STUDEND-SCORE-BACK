<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponds;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreGenerationRequest;
use App\Http\Requests\UpdateGenerationRequest;
use App\Models\Generation;
use Illuminate\Http\JsonResponse;

class GenerationController extends Controller
{
    use ApiResponds;

    public function index(): JsonResponse
    {
        return $this->success(Generation::orderBy('year', 'desc')->get());
    }

    public function store(StoreGenerationRequest $request): JsonResponse
    {
        if ($request->boolean('is_current')) {
            Generation::where('is_current', true)->update(['is_current' => false]);
        }

        // Matches the naming convention already used by StudentSeeder for generations.
        $generation = Generation::create([
            ...$request->only('year', 'is_current'),
            'name' => 'Batch ' . $request->year,
        ]);

        return $this->created($generation, 'Generation created.');
    }

    public function update(UpdateGenerationRequest $request, Generation $generation): JsonResponse
    {
        if ($request->boolean('is_current')) {
            Generation::where('is_current', true)->where('id', '!=', $generation->id)->update(['is_current' => false]);
        }

        $generation->update($request->only('year', 'is_current'));

        return $this->success($generation->fresh(), 'Generation updated.');
    }

    public function destroy(Generation $generation): JsonResponse
    {
        $generation->delete();

        return $this->success(message: 'Generation deleted.');
    }
}
