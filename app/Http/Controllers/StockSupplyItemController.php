<?php

namespace App\Http\Controllers;

use App\Http\Requests\StockSupplyItemRequest;
use App\Http\Resources\StockSupplyItemResource;
use App\Models\StockSupplyItem;

class StockSupplyItemController extends Controller
{
    public function index()
    {
        return StockSupplyItemResource::collection(StockSupplyItem::all());
    }

    public function store(StockSupplyItemRequest $request)
    {
        return new StockSupplyItemResource(StockSupplyItem::create($request->validated()));
    }

    public function show(StockSupplyItem $stockSupplyItem)
    {
        return new StockSupplyItemResource($stockSupplyItem);
    }

    public function update(StockSupplyItemRequest $request, StockSupplyItem $stockSupplyItem)
    {
        $stockSupplyItem->update($request->validated());

        return new StockSupplyItemResource($stockSupplyItem);
    }

    public function destroy(StockSupplyItem $stockSupplyItem)
    {
        $stockSupplyItem->delete();

        return response()->json();
    }
}
