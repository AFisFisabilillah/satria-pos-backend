<?php

namespace App\Http\Controllers;

use App\Models\StockSupplyItem;
use OpenApi\Attributes as OA;

class StockSupplyItemController extends Controller
{
    #[OA\Delete(
        path: '/api/stock-supply-items/{stockSupplyItem}',
        summary: 'Delete Stock Supply Item',
        security: [['bearerAuth' => []]],
        tags: ['Stock Supply'],
        parameters: [
            new OA\Parameter(name: 'stockSupplyItem', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Item deleted'),
            new OA\Response(response: 422, description: 'Cannot delete because stock used')
        ]
    )]
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
