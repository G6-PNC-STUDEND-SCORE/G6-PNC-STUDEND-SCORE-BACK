<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    /**
     * Get paginated, filtered activity logs.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->integer('per_page', 20);
        $perPage = min($perPage, 100); // cap at 100

        $query = ActivityLog::with('user:id,name,email');

        // ── Search filter (searches description & user name) ──
        if ($search = $request->input('search')) {
            $search = trim($search);
            $query->where(function (Builder $q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhereHas('user', fn (Builder $u) => $u->where('name', 'like', "%{$search}%"));
            });
        }

        // ── Action filter ──
        if ($action = $request->input('action')) {
            $query->where('action', $action);
        }

        // ── Module filter ──
        if ($module = $request->input('module')) {
            $query->where('module', $module);
        }

        // ── Date range filter ──
        if ($dateFrom = $request->input('date_from')) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo = $request->input('date_to')) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $logs = $query->orderBy('created_at', 'desc')->paginate($perPage);

        // Get distinct modules & actions for filter dropdowns
        $modules = ActivityLog::select('module')->distinct()->orderBy('module')->pluck('module');
        $actions = ActivityLog::select('action')->distinct()->orderBy('action')->pluck('action');

        // Transform the data
        $logs->getCollection()->transform(fn ($log) => [
            'id'             => $log->id,
            'action'         => $log->action,
            'module'         => $log->module,
            'description'    => $log->description,
            'user_name'      => $log->user?->name ?? 'System',
            'ip_address'     => $log->ip_address,
            'created_at'     => $log->created_at->diffForHumans(),
            'created_at_raw' => $log->created_at->toISOString(),
        ]);

        return response()->json([
            'success' => true,
            'data'    => [
                'logs'    => $logs,
                'filters' => [
                    'modules' => $modules,
                    'actions' => $actions,
                ],
            ],
        ]);
    }
}
