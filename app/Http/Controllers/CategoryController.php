<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class CategoryController extends Controller
{
    
    public function addCategory(Request $request)
    {
        // // Force JSON response
        // $request->headers->set('Accept', 'application/json');

        // Auth check
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }
        //Role restriction
        if (!in_array($user->rid, [1, 2, 3, 4])) {
            return response()->json(['message' => 'Unauthorized to add unit'], 403);
        }
        // Validate the request
        $validator = Validator::make($request->all(), [
            'name' => [
                'required',
                'string',
                'max:255',
                function ($attribute, $value, $fail) {
                    $exists = Category::whereRaw('LOWER(name) = ?', [trim(strtolower($value))])
                        ->exists();
                    if ($exists) {
                        $fail('The category name already exists.');
                    }
                },
            ],
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
            'category' => [
                'id' => $category->id,
                'name' => $category->name,
            ]
        ], 201);
    }

    public function getCategories()
    {    
        // // Force JSON response
        // $request->headers->set('Accept', 'application/json');

        // Authentication and authorization checks
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        if ($user->rid < 1 || $user->rid > 5) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
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