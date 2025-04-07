<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Customer;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class CustomerController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }
    public function store(Request $request)
    {
        // Get the authenticated user
        $user = Auth::user();
        
        // Check if user is authenticated
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }
        
        // Restrict to rid 5, 6, 7, 8 or 9 only
        if (!in_array($user->rid, [5, 6, 7, 8, 9])) {
            return response()->json(['message' => 'Unauthorized to add customer'], 403);
        }
            
        $validated = $request->validate([
            'cid' => 'required|integer',
            'first_name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'email' => 'nullable|email',
            'phone' => 'nullable|string|max:20',
            'gst' => 'nullable|string',
            'pan' => 'nullable|string',
            'address' => 'nullable|string',
        ]);


       $customer = Customer::create([
        'cid' => $validated['cid'],
        'first_name' => $validated['first_name'],
        'last_name' => $validated['last_name'] ?? null,
        'email' => $validated['email'] ?? null,
        'phone' => $validated['phone'] ?? null,
        'gst' => $validated['gst'] ?? null,
        'pan' => $validated['pan'] ?? null,
        'address' => $validated['address'] ?? null,
    ]);
    
        return response()->json([
            'message' => 'Customer created successfully',
            'customer' => $customer
        ], 201);
    }

    public function checkCustomer(Request $request)
    {
        // Check if the user is authenticated
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Check if the user's role ID (rid) is between 5 and 10
        if ($user->rid < 5 || $user->rid > 10) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // Validate the request input
        $validated = $request->validate([
            'cid' => 'required|integer',
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|max:20',
            'gst' => 'sometimes|string',
            'pan' => 'sometimes|string',
        ]);

        // Define the possible search fields
        $searchFields = ['first_name', 'last_name', 'phone', 'gst', 'pan'];
        
        // Check if at least one search field is provided
        $providedSearchFields = array_intersect_key($validated, array_flip($searchFields));
        if (empty($providedSearchFields)) {
            return response()->json(['message' => 'At least one search field is required'], 422);
        }

        // Build the query
        $query = Customer::where('cid', $validated['cid']);
        foreach ($providedSearchFields as $field => $value) {
            $query->where($field, $value);
        }

        // Execute the query
        $customers = $query->get();

        // Return the response
        if ($customers->isEmpty()) {
            return response()->json(['message' => 'No customers found matching the criteria'], 404);
        }

        return response()->json([
            'message' => 'Customers found',
            'customers' => $customers
        ], 200);
    }

    public function index(Request $request)
    {
        // Get the authenticated user
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
    
        // Restrict access to users with rid between 5 and 10 inclusive
        if ($user->rid < 5 || $user->rid > 10) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        
        // Validate the request
        $validated = $request->validate([
            'cid' => 'required|integer',
        ]);
    
        // Retrieve customers where cid matches the provided value
        $customers = Customer::where('cid', $validated['cid'])
                            ->get()
                            ->map(function ($customer) {
                                return [
                                    'id' => $customer->id,
                                    'name' => $customer->first_name . ($customer->last_name ? ' ' . $customer->last_name : ''),
                                    'email' => $customer->email,
                                    'phone' => $customer->phone,
                                    'address' => $customer->address,
                                    'pan' => $customer->pan,
                                    'gst' => $customer->gst,
                                ];
                            });
    
        return response()->json([
            'message' => 'Customers retrieved successfully',
            'customers' => $customers,
        ], 200);
    }

    public function update(Request $request, $id)
    {
        // Get the authenticated user
        $user = Auth::user();
        
        // Check if user is authenticated
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }
        
        // Restrict to rid 5, 6, 7, 8 or 9 only
        if (!in_array($user->rid, [5, 6, 7, 8, 9])) {
            return response()->json(['message' => 'Unauthorized to update customer'], 403);
        }
        
        // Check if the customer exists
        if (!Customer::where('id', $id)->exists()) {
            return response()->json(['message' => 'Customer not found'], 404);
        }
        
        // Validate the request data, cid is required
        $validated = $request->validate([
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'email' => 'nullable|email',
            'phone' => 'nullable|string|max:20',
            'gst' => 'nullable|string',
            'pan' => 'nullable|string',
            'address' => 'nullable|string',
        ]);
        
        // Update the customer directly in the database
        Customer::where('id', $id)->update($validated);
        
        // Retrieve the updated customer
        $customer = Customer::find($id);
        
        return response()->json([
            'message' => 'Customer updated successfully',
            'customer' => $customer
        ], 200);
    }
}
