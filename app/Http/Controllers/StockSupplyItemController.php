<?php

namespace App\Http\Controllers;

use App\Http\Requests\StockSupplyItemRequest;
use App\Http\Resources\StockSupplyItemResource;
use App\Models\StockSupply;
use App\Models\StockSupplyItem;
use Illuminate\Database\Eloquent\Builder;
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

    public function index(){
        $stockSupply = request()->query("stockSupply");
        $search = request()->query("search");

        $query = StockSupplyItem::query();
        $query->when($stockSupply, function (Builder $query)use($stockSupply) {
            $query->where("stock_supply_id", $stockSupply);
        });

        $query->when($search, function (Builder $query)use($search) {
            $query->whereHas('product', function ($query) use ($search) {
                $query->where("name", "like", "%{$search}%");
            });
        });

        return StockSupplyItemResource::collection($query->get());
    }

//    public function store(StockSupplyItemRequest $request){
//        $data  = $request->validated();
//        $stockSupply = StockSupplyItem::where("stock_supply_items.stock_supply_id", "=", $data["stock_supply_id"])->where("product_id","=", $data["product_id"])->first();
//        if ($stockSupply) {
//
//        }
//    }
}
