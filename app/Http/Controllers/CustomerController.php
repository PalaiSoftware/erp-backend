<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SalesClient;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon; 
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
        
        // Restrict to rid 1, 2, 3 or 4 only
        if (!in_array($user->rid, [1, 2, 3, 4,5])) {
            return response()->json(['message' => 'Unauthorized to add sales client'], 403);
        }
            
        $validated = $request->validate([
            'cid' => 'required|integer',
            'name' => 'required|string|max:255',
            'email' => 'nullable|email',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'gst_no' => 'nullable|string',
            'pan' => 'nullable|string|max:20',
        ]);

        $salesClient = SalesClient::create([
            'cid' => $validated['cid'],
            'uid' => $user->id,
            'name' => $validated['name'],
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'address' => $validated['address'] ?? null,
            'gst_no' => $validated['gst_no'] ?? null,
            'pan' => $validated['pan'] ?? null,
        ]);
    
        return response()->json([
            'message' => 'Sales client created successfully',
            'sales_client' => $salesClient
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
        if ($user->rid < 1 || $user->rid > 5) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
    
        // Validate the request input
        $validated = $request->validate([
            'cid' => 'required|integer',
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email',
            'phone' => 'sometimes|string|max:20',
            'gst_no' => 'sometimes|string',
            'pan' => 'sometimes|string|max:20',
        ]);
    
        // Define the possible search fields
        $searchFields = ['name', 'email', 'phone', 'gst_no', 'pan'];
        
        // Check if at least one search field is provided
        $providedSearchFields = array_intersect_key($validated, array_flip($searchFields));
        if (empty($providedSearchFields)) {
            return response()->json(['message' => 'At least one search field is required'], 422);
        }
    
        // Build the query
        $query = SalesClient::where('cid', $validated['cid']);
        foreach ($providedSearchFields as $field => $value) {
            // Use LIKE with wildcards for pattern matching
            $query->where($field, 'LIKE', "%{$value}%");
        }
    
        // Execute the query
        $salesClients = $query->get();
    
        // Return the response
        if ($salesClients->isEmpty()) {
            return response()->json(['message' => 'No sales clients found matching the criteria'], 404);
        }
    
        return response()->json([
            'message' => 'Sales clients found',
            'sales_clients' => $salesClients
        ], 200);
    }
    // public function index(Request $request)
    // {
    //     // Get the authenticated user
    //     $user = Auth::user();
    //     if (!$user) {
    //         return response()->json(['message' => 'Unauthorized'], 401);
    //     }
    
    //     // Restrict access to users with rid between 5 and 10 inclusive
    //     if ($user->rid < 5 || $user->rid > 10) {
    //         return response()->json(['message' => 'Forbidden'], 403);
    //     }
        
    //     // Validate the request
    //     $validated = $request->validate([
    //         'cid' => 'required|integer',
    //     ]);
    
    //     // Retrieve customers where cid matches the provided value
    //     $customers = Customer::where('cid', $validated['cid'])
    //                         ->get()
    //                         ->map(function ($customer) {
    //                             return [
    //                                 'id' => $customer->id,
    //                                 'name' => $customer->first_name . ($customer->last_name ? ' ' . $customer->last_name : ''),
    //                                 'email' => $customer->email,
    //                                 'phone' => $customer->phone,
    //                                 'address' => $customer->address,
    //                                 'pan' => $customer->pan,
    //                                 'gst' => $customer->gst,
    //                             ];
    //                         });
    
    //     return response()->json([
    //         'message' => 'Customers retrieved successfully',
    //         'customers' => $customers,
    //     ], 200);
    // }

    public function index(Request $request)
    {
        // Get the authenticated user
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
    
        // Restrict access to users with rid between 1 and 5 inclusive
        if ($user->rid < 1 || $user->rid > 5) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        
        // Validate the request
        $validated = $request->validate([
            'cid' => 'required|integer',
        ]);
    
        // Retrieve sales clients where cid matches the provided value
        $salesClients = SalesClient::where('cid', $validated['cid'])
                            ->orderBy('id','desc')
                            ->get()
                            ->map(function ($salesClient) {
                                return [
                                    'id' => $salesClient->id,
                                    'name' => $salesClient->name,
                                    'email' => $salesClient->email,
                                    'phone' => $salesClient->phone,
                                    'address' => $salesClient->address,
                                    'pan' => $salesClient->pan,
                                    'gst_no' => $salesClient->gst_no,
                                ];
                            });
    
        return response()->json([
            'message' => 'Sales clients retrieved successfully',
            'sales_clients' => $salesClients,
        ], 200);
    }

public function getCustomer($customerId)
{
    Log::info('Get sales client API endpoint reached', ['sales_client_id' => $customerId]);

    // Get the authenticated user
    $user = Auth::user();
    if (!$user) {
        return response()->json(['message' => 'Unauthenticated'], 401);
    }

    // Restrict to rid 1, 2, 3, 4, 5 only
    if (!in_array($user->rid, [1, 2, 3, 4, 5])) {
        return response()->json(['message' => 'Unauthorized to view sales client data'], 403);
    }

    try {
        // Fetch the sales client by ID
        $salesClient = SalesClient::where('id', $customerId)->first();

        if (!$salesClient) {
            Log::warning("Sales client not found", ['sales_client_id' => $customerId]);
            return response()->json(['message' => 'Sales client not found'], 404);
        }

        // Prepare sales client data
        $salesClientData = [
            'id' => $salesClient->id,
            'name' => $salesClient->name,
            'email' => $salesClient->email ?? null,
            'phone' => $salesClient->phone ?? null,
            'address' => $salesClient->address ?? null,
            'gst_no' => $salesClient->gst_no ?? null,
            'pan' => $salesClient->pan ?? null,
            'uid' => $salesClient->uid,
            'cid' => $salesClient->cid,
            'created_at' => Carbon::parse($salesClient->created_at)->format('Y-m-d H:i:s'),
            'updated_at' => $salesClient->updated_at ? Carbon::parse($salesClient->updated_at)->format('Y-m-d H:i:s') : null,
        ];

        Log::info('Sales client data retrieved successfully', ['sales_client_id' => $customerId]);
        return response()->json($salesClientData, 200);

    } catch (\Exception $e) {
        Log::error('Failed to fetch sales client data', [
            'sales_client_id' => $customerId,
            'error' => $e->getMessage()
        ]);
        return response()->json([
            'message' => 'Failed to fetch sales client data',
            'error' => $e->getMessage()
        ], 500);
    }
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
    if (!in_array($user->rid, [1, 2, 3, 4, 5])) {
        return response()->json(['message' => 'Unauthorized to update sales client'], 403);
    }
    
    // Check if the sales client exists
    if (!SalesClient::where('id', $id)->exists()) {
        return response()->json(['message' => 'Sales client not found'], 404);
    }
    
    // Validate the request data, cid is required
    $validated = $request->validate([
        'cid' => 'required|integer',
        'name' => 'sometimes|string|max:255',
        'email' => 'nullable|email',
        'phone' => 'nullable|string|max:20',
        'address' => 'nullable|string',
        'gst_no' => 'nullable|string',
        'pan' => 'nullable|string|max:20',
    ]);
    
    // Update the sales client directly in the database
    SalesClient::where('id', $id)->update($validated);
    
    // Retrieve the updated sales client
    $salesClient = SalesClient::find($id);
    
    return response()->json([
        'message' => 'Sales client updated successfully',
        'sales_client' => $salesClient
    ], 200);
}
}
