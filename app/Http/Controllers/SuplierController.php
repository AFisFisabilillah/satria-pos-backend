<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSuplierRequest;
use App\Http\Requests\UpdateSuplierRequest;
use App\Http\Resources\SuplierResource;
use App\Models\Suplier;
use Illuminate\Http\Request;

class SuplierController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->query('search');
        $size = $request->query('size', 10);

        $query = Suplier::query();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $supliers = $query->paginate($size);

        return SuplierResource::collection($supliers);
    }

    public function store(StoreSuplierRequest $request)
    {
        $suplier = Suplier::create($request->validated());

        return new SuplierResource($suplier);
    }

    public function show(Suplier $suplier)
    {
        return new SuplierResource($suplier);
    }

    public function update(UpdateSuplierRequest $request, Suplier $suplier)
    {
        $suplier->update($request->validated());

        return new SuplierResource($suplier);
    }

    public function destroy(Suplier $suplier)
    {
        $suplier->delete();

        return response()->json();
    }

    public function bulkDelete(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:supliers,id',
        ]);

        Suplier::whereIn('id', $request->ids)->delete();

        return response()->json();
    }
}
