<?php

namespace App\Http\Controllers;

use App\Models\StockSupplyItem;

class StockSupplyItemController extends Controller
{
    public function destroy(StockSupplyItem $stockSupplyItem)
    {
        if ($stockSupplyItem->quantity_in !== $stockSupplyItem->quantity_remaining) {
            return response()->json([
                'message' => 'Cannot delete item because stock has already been used.'
            ], 422);
        }

        $stockSupplyItem->delete();

        return response()->json([
            'message' => 'Item deleted successfully.'
        ]);
    }
}
