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
        $this->middleware('auth:sanctum'); // Protect these routes
    }

    /**
     * Store a newly created or updated customer.
     *
     * Only users with a specific role (e.g., admin) are allowed.
     * If a customer with the same phone exists, it checks the name:
     * - If the name is different, update the customer name.
     * - If the name is the same, return a message that the data already exists.
     */
    public function store(Request $request)
    {
        $user = Auth::user();

        // Only allow users with a specific role (e.g., admin)
        if ($user->rid !== 1) {
            return response()->json(['message' => 'Unauthorized to create a customer'], 403);
        }

        try {
            // Validate the request (note: email uniqueness removed)
            $validated = $request->validate([
                'cid'     => 'required|integer',
                'name'    => 'required|string|max:255',
                'email'   => 'nullable|email',
                'phone'   => 'required|string|max:20', // No unique rule here
                'address' => 'nullable|string',
            ]);
        } catch (ValidationException $e) {
            // Return JSON response with validation errors and a 422 status code.
            return response()->json([
                'message' => 'Validation failed',
                'errors'  => $e->errors(),
            ], 422);
        }

        // Check if a customer with the given phone already exists
        $existingCustomer = Customer::where('phone', $validated['phone'])->first();

        if ($existingCustomer) {
            // If the names differ, update the customer's name (and optionally other fields)
            if ($existingCustomer->name !== $validated['name']) {
                $existingCustomer->update(['name' => $validated['name']]);
                return response()->json([
                    'message'  => 'Customer updated successfully',
                    'customer' => $existingCustomer,
                ], 200);
            } else {
                // If the name is the same, return that the customer already exists
                return response()->json([
                    'message'  => 'Customer data already exists',
                    'customer' => $existingCustomer,
                ], 200);
            }
        } else {
            // Otherwise, create a new customer record
            $customer = Customer::create($validated);
            return response()->json([
                'message'  => 'Customer created successfully',
                'customer' => $customer,
            ], 201);
        }
    }

    /**
     * Display a listing of the customers.
     */
    public function index()
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $customers = Customer::all();

        return response()->json([
            'message'   => 'Customers retrieved successfully',
            'customers' => $customers,
        ], 200);
    }
}
