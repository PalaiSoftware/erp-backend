<?php

namespace App\Http\Controllers;

use App\Models\CustomerType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CustomerTypeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * Get all customer types for the current user's company
     * Both Admin (rid=1) and Superuser (rid=2) can view
     */
    public function index()
    {
        $user = Auth::user();

        $types = CustomerType::where('cid', $user->cid)
            ->select('id', 'name', 'description')
            ->orderBy('name')
            ->get();

        return response()->json([
            'message' => 'Customer types retrieved successfully',
            'types'   => $types
        ]);
    }

    /**
     * Create a new customer type
     * ONLY main Admin (rid=1) can do this
     */
    public function store(Request $request)
    {
        $user = Auth::user();

        // Only rid=1 (main Admin) can create types
        if ($user->rid !== 1) {
            return response()->json([
                'message' => 'Unauthorized: Only main Admin can create customer types'
            ], 403);
        }

        // Limit: maximum 6 types per company
        $existingCount = CustomerType::where('cid', $user->cid)->count();
        if ($existingCount >= 6) {
            return response()->json([
                'message' => 'Maximum 6 customer types allowed per company'
            ], 422);
        }

        $validated = $request->validate([
            'name'        => 'required|string|max:100|unique:customer_types,name,NULL,id,cid,' . $user->cid,
            'description' => 'nullable|string|max:500',
        ]);

        $type = CustomerType::create([
            'name'           => $validated['name'],
            'description'    => $validated['description'] ?? null,
            'cid'            => $user->cid,
            'created_by'     => $user->id,
            'created_by_rid' => $user->rid,
        ]);

        return response()->json([
            'message' => 'Customer type created successfully',
            'type'    => $type
        ], 201);
    }

    /**
     * Update a customer type
     * ONLY main Admin (rid=1) can do this
     */
    public function update(Request $request, $id)
    {
        $user = Auth::user();

        if ($user->rid !== 1) {
            return response()->json([
                'message' => 'Unauthorized: Only main Admin can update customer types'
            ], 403);
        }

        $type = CustomerType::where('cid', $user->cid)->findOrFail($id);

        $validated = $request->validate([
            'name'        => 'required|string|max:100|unique:customer_types,name,' . $id . ',id,cid,' . $user->cid,
            'description' => 'nullable|string|max:500',
        ]);

        $type->update($validated);

        return response()->json([
            'message' => 'Customer type updated successfully',
            'type'    => $type
        ]);
    }

    /**
     * Delete a customer type
     * ONLY main Admin (rid=1) can do this
     */
    public function destroy($id)
    {
        $user = Auth::user();

        if ($user->rid !== 1) {
            return response()->json([
                'message' => 'Unauthorized: Only main Admin can delete customer types'
            ], 403);
        }

        $type = CustomerType::where('cid', $user->cid)->findOrFail($id);
        $type->delete();

        return response()->json([
            'message' => 'Customer type deleted successfully'
        ]);
    }
}