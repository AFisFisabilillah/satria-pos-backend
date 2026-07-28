<?php

namespace App\Http\Controllers;

use App\Http\Requests\StockSupplyRequest;
use App\Http\Resources\StockSupplyResource;
use App\Models\StockSupply;
use Carbon\Carbon;

class StockSupplyController extends Controller
{
    public function index()
    {
        $search = request()->query('search');
        $startDate = request()->query('startDate');
        $endDate = request()->query('endDate');
        $size = request()->query('size',10);

        $stockSupply = StockSupply::query();
        $stockSupply->when($search, function ($query, $search) {
            $query->whereHas("suplier", function ($query) use ($search) {
                $query->where("name", "like", "%{$search}%");
            })->orWhere("invoice_number", "like", "%{$search}%");
        });
        $stockSupply->when($startDate, function ($query, $startDate) {
            $query->where("date", ">=", $startDate);
        });
        $stockSupply->when($endDate, function ($query, $endDate) {
            $query->where("date", "<=", $endDate);
        });

        return StockSupplyResource::collection($stockSupply->with("suplier")->paginate($size));
    }

    public function store(StockSupplyRequest $request)
    {
        $data = $request->validated();
        $data["date"] = \Illuminate\Support\now();
        $stockSupply = StockSupply::create($data);
        $stockSupplyItem = array_map(function ($item) {
            return [
                "product_id" => $item["product_id"],
                "quantity_in" => $item["quantity_in"],
                "quantity_remaining" => $item["quantity_in"], // default is the quantity in
                "purchase_price" => $item["purchase_price"],
                "expired_at" => $item["expired_date"],
            ];
        }, $data["products"]);

        $stockSupply->stockSupplyItem()->createMany($stockSupplyItem);
        return new StockSupplyResource($stockSupply->load(['suplier', 'stockSupplyItem']));
    }


}
