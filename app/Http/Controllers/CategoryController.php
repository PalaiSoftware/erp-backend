<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CategoryController extends Controller
{
    /**
     * Add a new category
     */
    public function addCategory(Request $request)
    {
        // Validate the request
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:categories,name'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Create the category
        $category = Category::create([
            'name' => $request->name
        ]);

        return response()->json([
            'message' => 'Category added successfully',
            'category' => $category
        ], 201);
    }

    /**
     * Get all categories
     */
    public function getCategories()
    {
        // Fetch all categories
        $categories = Category::all();

        if ($categories->isEmpty()) {
            return response()->json([
                'message' => 'No categories found',
                'categories' => []
            ], 200);
        }

        return response()->json([
            'message' => 'Categories retrieved successfully',
            'categories' => $categories
        ], 200);
    }
}