<?php

namespace App\Http\Controllers;

use App\Http\Requests\CategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;

class CategoryController extends Controller
{
    public function index()
    {
        $search = request()->query('search');
        if ($search) {
            $categories = Category::select("id", "name")->where('name', 'like', '%' . $search . '%')->limit(5)->get();
        } else {
            $categories = Category::select("id", "name")->limit(5)->get();
        }
        return CategoryResource::collection($categories);
    }

    public function store(CategoryRequest $request)
    {
        return new CategoryResource(Category::create($request->validated()));
    }


    public function destroy(Category $category)
    {
        $category->delete();

        return response()->json();
    }
}
