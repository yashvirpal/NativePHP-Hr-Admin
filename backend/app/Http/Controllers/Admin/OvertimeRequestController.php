<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OvertimeRequest;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OvertimeRequestController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status', 'all');

        return view('admin.overtime.index', [
            'status' => $status,
            'requests' => OvertimeRequest::with('employee.user')
                ->when($status !== 'all', fn ($q) => $q->where('status', $status))
                ->orderByDesc('work_date')
                ->paginate(25)
                ->withQueryString(),
        ]);
    }
}
