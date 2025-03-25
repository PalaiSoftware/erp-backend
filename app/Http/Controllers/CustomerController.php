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
    
    // Restrict to rid 5, 6, 7,8 or 9 only
    if (!in_array($user->rid, [5, 6, 7, 8,9])) {
        return response()->json(['message' => 'Unauthorized to add customer'], 403);
    }
        
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|',
            'phone' => 'required|string|max:20',
            'address' => 'nullable|string',
            'cid' => 'required|integer',
        ]);

        
        $customer = Customer::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'address' => $validated['address'],
            'cids' => [$validated['cid']],
        ]);

        
        return response()->json([
            'message' => 'Customer created successfully',
            'customer' => $customer
        ], 201);
    }
   
    public function checkCustomer(Request $request)
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
        
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'cid' => 'required|integer',
        ]);

        
        $customer = Customer::where('name', $validated['name'])
                           ->where('phone', $validated['phone'])
                           ->first();

        
        if ($customer) {
            $cids = $customer->cids ?? [];
            if (in_array($validated['cid'], $cids)) {
                return response()->json([
                    'message' => 'This customer already exists in your company.'
                ], 200);
            } else {
                return response()->json([
                    'message' => 'Need to add this customer to your company.'
                ], 200);
            }
        }

        
        $phoneMatch = Customer::where('phone', $validated['phone'])
                             ->where('name', '!=', $validated['name'])
                             ->first();

        if ($phoneMatch) {
            return response()->json([
                'message' => 'Phone number matches an existing customer but name does not.New customer creat with new name.,'
            ], 200);
        }

        
        return response()->json([
            'message' => 'Please add this customer as this customer is not present in the customers table.'
        ], 404);
    }
    public function addCustomerToCompany(Request $request)
    {
        // Get the authenticated user
    $user = Auth::user();
    
    // Check if user is authenticated
    if (!$user) {
        return response()->json(['message' => 'Unauthenticated'], 401);
    }
    
    // Restrict to rid 5, 6, 7,8 or 9 only
    if (!in_array($user->rid, [5, 6, 7, 8,9])) {
        return response()->json(['message' => 'Unauthorized to add customer to company'], 403);
    }
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'cid' => 'required|integer',
        ]);

    
        $customer = Customer::where('name', $validated['name'])
                           ->where('phone', $validated['phone'])
                           ->first();

        
        if (!$customer) {
            return response()->json([
                'message' => 'Customer not found.'
            ], 404);
        }

        
        $cids = $customer->cids ?? [];

        if (in_array($validated['cid'], $cids)) {
            return response()->json([
                'message' => 'Customer already exists in your company.'
            ], 200);
        }

    
        $cids[] = $validated['cid'];
        $customer->cids = $cids;
        $customer->save();

        return response()->json([
            'message' => 'Customer added to your company successfully.'
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
        
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        
        $validated = $request->validate([
            'cid' => 'required|integer',
        ]);

        
        $customers = Customer::whereJsonContains('cids', (int)$validated['cid'])
                            ->select(
                                'id',
                                'name',
                                'email',
                                'phone',
                                'address'
                            )
                            ->get();

        return response()->json([
            'message' => 'Customers retrieved successfully',
            'customers' => $customers,
        ], 200);
    }
}
