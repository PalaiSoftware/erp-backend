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
     * Both rid=1 and rid=2 can view their company's types
     */
    public function index()
    {
        $user = Auth::user();

        $query = CustomerType::query();

        // If not global admin, restrict to their company
        if ($user->rid !== 1) {
            $query->where('cid', $user->cid);
        }

        $types = $query->select('id', 'name', 'description')
            ->orderBy('name')
            ->get();

        return response()->json([
            'message' => 'Customer types retrieved successfully',
            'types'   => $types
        ]);
    }

    /**
     * Create a new customer type
     * rid=1: can create for any company (via cid in request)
     * rid=2: can only create for their own company
     */
    public function store(Request $request)
    {
        $user = Auth::user();

        // Only rid=1 and rid=2 are allowed
        if (!in_array($user->rid, [1, 2])) {
            return response()->json([
                'message' => 'Unauthorized: Only Admin or Company Owner can create customer types'
            ], 403);
        }

        // Determine which company this type belongs to
        $targetCid = $user->cid; // default: own company

        if ($user->rid === 1) {
            // Global admin can optionally specify a company
            $targetCid = $request->input('cid', $user->cid);
        }

        // Limit: maximum 6 types per company
        $existingCount = CustomerType::where('cid', $targetCid)->count();
        if ($existingCount >= 6) {
            return response()->json([
                'message' => 'Maximum 6 customer types allowed per company'
            ], 422);
        }

        $validated = $request->validate([
            'name'        => 'required|string|max:100|unique:customer_types,name,NULL,id,cid,' . $targetCid,
            'description' => 'nullable|string|max:500',
            // Only global admin can send cid
            'cid'         => $user->rid === 1 ? 'sometimes|integer|exists:clients,id' : 'prohibited',
        ]);

        $type = CustomerType::create([
            'name'           => $validated['name'],
            'description'    => $validated['description'] ?? null,
            'cid'            => $targetCid,
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
     * rid=1: can update any
     * rid=2: only within their company
     */
    public function update(Request $request, $id)
    {
        $user = Auth::user();

        if (!in_array($user->rid, [1, 2])) {
            return response()->json([
                'message' => 'Unauthorized: Only Admin or Company Owner can update customer types'
            ], 403);
        }

        $type = CustomerType::query();

        if ($user->rid !== 1) {
            $type->where('cid', $user->cid);
        }

        $type = $type->findOrFail($id);

        $validated = $request->validate([
            'name'        => 'required|string|max:100|unique:customer_types,name,' . $id . ',id,cid,' . $type->cid,
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
     * rid=1: can delete any
     * rid=2: only within their company
     */
    public function destroy($id)
    {
        $user = Auth::user();

        if (!in_array($user->rid, [1, 2])) {
            return response()->json([
                'message' => 'Unauthorized: Only Admin or Company Owner can delete customer types'
            ], 403);
        }

        $type = CustomerType::query();

        if ($user->rid !== 1) {
            $type->where('cid', $user->cid);
        }

        $type = $type->findOrFail($id);
        $type->delete();

        return response()->json([
            'message' => 'Customer type deleted successfully'
        ]);
    }
}