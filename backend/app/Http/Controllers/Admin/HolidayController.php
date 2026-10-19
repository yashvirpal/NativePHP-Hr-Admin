<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Holiday;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HolidayController extends Controller
{
    public function index(): View
    {
        return view('admin.holidays.index', ['holidays' => Holiday::orderBy('date')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        Holiday::create($request->validate([
            'name' => ['required', 'string', 'max:255'],
            'date' => ['required', 'date', 'unique:holidays,date'],
            'is_optional' => ['sometimes', 'boolean'],
        ]) + ['is_optional' => false]);

        return back()->with('status', 'Holiday added.');
    }

    public function destroy(Holiday $holiday): RedirectResponse
    {
        $holiday->delete();

        return back()->with('status', 'Holiday removed.');
    }
}
