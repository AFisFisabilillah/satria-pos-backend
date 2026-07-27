<?php

namespace App\Http\Controllers;

use App\Http\Requests\UnitRequest;
use App\Models\Unit;
use Illuminate\Http\Request;

class UnitController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->search;
        return Unit::where('name', 'like', '%' . $search . '%')->limit(5)->get();
    }

    public function store(UnitRequest $request)
    {
        return Unit::create($request->validated());
    }

    public function destroy(Unit $unit)
    {
        $unit->delete();

        return response()->json();
    }
}
