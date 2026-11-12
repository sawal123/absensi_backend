<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesRoles;
use App\Http\Controllers\Controller;
use App\Http\Requests\BulkShiftAssignmentRequest;
use App\Http\Resources\ShiftAssignmentResource;
use App\Models\ShiftAssignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;

class ShiftAssignmentController extends Controller
{
    use AuthorizesRoles;

    public function index(Request $request): AnonymousResourceCollection
    {
        $actor = $request->user();

        $from = Carbon::parse($request->query('from', Carbon::today()->startOfWeek()->toDateString()));
        $to = Carbon::parse($request->query('to', Carbon::today()->startOfWeek()->addDays(6)->toDateString()));

        $query = ShiftAssignment::query()
            ->with('shift')
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()]);

        if ($actor->isManager()) {
            if ($request->filled('employee_id')) {
                $query->where('employee_id', (int) $request->query('employee_id'));
            }
        } else {
            $query->where('employee_id', $actor->id);
        }

        return ShiftAssignmentResource::collection(
            $query->orderBy('date')->get(),
        );
    }

    public function store(BulkShiftAssignmentRequest $request): JsonResponse
    {
        $this->ensureManager($request);

        $employeeId = (int) $request->input('employee_id');
        $shiftId = $request->input('shift_id');
        $dates = $this->datesFromRequest($request);

        $created = [];

        foreach ($dates as $date) {
            if ($shiftId === null) {
                ShiftAssignment::query()
                    ->where('employee_id', $employeeId)
                    ->whereDate('date', $date)
                    ->delete();

                continue;
            }

            $created[] = ShiftAssignment::query()->updateOrCreate(
                ['employee_id' => $employeeId, 'date' => $date],
                ['shift_id' => (int) $shiftId],
            );
        }

        $assignments = ShiftAssignment::query()
            ->with('shift')
            ->where('employee_id', $employeeId)
            ->whereIn('date', $dates)
            ->orderBy('date')
            ->get();

        return response()->json([
            'data' => ShiftAssignmentResource::collection($assignments)->resolve($request),
        ]);
    }

    public function destroy(Request $request, ShiftAssignment $assignment): JsonResponse
    {
        $this->ensureManager($request);

        $assignment->delete();

        return response()->json(['data' => ['message' => 'Plotting shift dihapus.']]);
    }

    /**
     * @return array<int, string>
     */
    private function datesFromRequest(BulkShiftAssignmentRequest $request): array
    {
        if (is_array($request->input('dates')) && count($request->input('dates')) > 0) {
            return array_values(array_unique(array_map(
                fn ($date) => Carbon::parse($date)->toDateString(),
                $request->input('dates'),
            )));
        }

        $dates = [];
        $cursor = Carbon::parse($request->input('from'))->startOfDay();
        $end = Carbon::parse($request->input('to'))->startOfDay();

        while ($cursor->lte($end)) {
            $dates[] = $cursor->toDateString();
            $cursor->addDay();
        }

        return $dates;
    }
}
