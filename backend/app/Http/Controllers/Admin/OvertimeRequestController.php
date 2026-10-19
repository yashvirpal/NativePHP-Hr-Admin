<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OvertimeRequest;
use App\Services\OvertimeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OvertimeRequestController extends Controller
{
    public function __construct(private OvertimeService $overtime) {}

    public function index(Request $request): View
    {
        $status = $request->query('status', 'pending');

        return view('admin.overtime.index', [
            'status' => $status,
            'requests' => OvertimeRequest::with('employee.user')
                ->when($status !== 'all', fn ($q) => $q->where('status', $status))
                ->orderByDesc('work_date')
                ->paginate(25)
                ->withQueryString(),
        ]);
    }

    public function decide(Request $request, OvertimeRequest $overtimeRequest, string $decision): RedirectResponse
    {
        $remarks = $request->validate(['remarks' => ['nullable', 'string', 'max:500']])['remarks'] ?? null;

        $this->overtime->decide($overtimeRequest, $request->user(), $decision === 'approve' ? 'approved' : 'rejected', $remarks);

        return back()->with('status', 'Overtime request '.($decision === 'approve' ? 'approved' : 'rejected').'.');
    }
}
