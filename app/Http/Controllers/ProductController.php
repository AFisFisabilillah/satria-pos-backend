<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\DetailProductResource;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;


class ProductController extends Controller
{
    public function index()
    {
        $search = request()->query('search');
        $size = request()->query('size', 10);

        $productQuery = Product::query()->with(["unit", "categories"])->withSum('stockSupplyItems', 'quantity_remaining');
        $productQuery->when($search, function ($query, $search) {
            return $query->where('name', 'like', '%' . $search . '%');
        });

        $products = $productQuery->paginate($size);
        return ProductResource::collection($products);
    }

    public function store(ProductRequest $request)
    {
        $data = $request->validated();
        $image = $request->file('image');
        $path = null;
        if ($image) {
            $fileName = $request->code . "-" . Str::uuid() . "." . $image->getClientOriginalExtension();
            $path = $image->storeAs('images/products', $fileName, 'public');
        }
        $slug = Str::slug($request->name, '-');
        $product = Product::create([
            "name" => $request->name,
            "code" => $request->code,
            "slug" => $slug,
            "description" => $request->description,
            "image" => $path,
            "active" => $request->active,
            "unit_id" => $request->unit_id,
            "sale_price" => $request->sale_price,
        ]);

        $product->categories()->attach($request->category_ids);

        $product->load(['unit', 'categories'])->loadSum('stockSupplyItems', 'quantity_remaining');

        return new ProductResource($product);
    }

    public function show(Product $product)
    {
        $product->load(['unit', 'categories', 'stockSupplyItems', 'stockSupplyItems.stockSupply', 'stockSupplyItems.stockSupply.suplier'])->loadSum('stockSupplyItems', 'quantity_remaining');
        return new DetailProductResource($product);
    }


    public function update(UpdateProductRequest $request, Product $product)
    {
        $data = $request->validated();

        if ($request->hasFile('image')) {

            if ($product->image && Storage::disk('public')->exists($product->image)) {
                Storage::disk('public')->delete($product->image);
            }

            $image = $request->file('image');

            $fileName = Str::uuid() . '.' . $image->getClientOriginalExtension();

            $image->storeAs('images/products', $fileName, 'public');

            $data['image'] = 'images/products/' . $fileName;
        }

        $product->update($data);

        $product->categories()->sync($request->category_ids);

        $product->load(['unit', 'categories'])->loadSum('stockSupplyItems', 'quantity_remaining');

        return new ProductResource($product);
    }

    public function destroy(Product $product)
    {
        if ($product->stockSupplyItems()->exists()) {
            return response()->json([
                'message' => 'Cannot delete product because it already has stock supply records. Please deactivate it instead.'
            ], 422);
        }

        if ($product->image && Storage::disk('public')->exists($product->image)) {
            Storage::disk('public')->delete($product->image);
        }

        $product->delete();

        return response()->json([
            'message' => 'Product deleted successfully.'
        ]);
    }


    public function toggleActive(Product $product){
        $product->active = !$product->active;
        $product->save();
        return new ProductResource($product);
    }

    public function bulkToggleActive(\Illuminate\Http\Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:products,id',
        ]);

        Product::whereIn('id', $request->ids)
            ->update(['active' => \Illuminate\Support\Facades\DB::raw('NOT active')]);

        return response()->json([
            'message' => 'Products status toggled successfully.'
        ]);
    }
}
