<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Models\Vendor;
use Illuminate\Support\Facades\Auth;

class VendorController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum'); // Protect this route
    }

    public function store(Request $request)
    {
        $user = Auth::user();

        // Only allow users with a specific role (e.g., admin)
        if ($user->rid !== 1) {
            return response()->json(['message' => 'Unauthorized to create a vendor'], 403);
        }

        // Validate request
        $request->validate([
            'vendor_name' => 'required|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'email' => 'nullable|email|unique:vendors,email',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'gst_no' => 'nullable|string|max:255',
            'pan' => 'nullable|string|max:20',
            'uid' => 'required|integer',

        ]);

        // Create a new vendor
        $vendor = Vendor::create([
            'vendor_name' => $request->vendor_name,
            'contact_person' => $request->contact_person,
            'email' => $request->email,
            'phone' => $request->phone,
            'address' => $request->address,
            'gst_no' => $request->gst_no,
            'pan' => $request->pan,
            'uid' => $request->uid,

        ]);

        return response()->json([
            'message' => 'Vendor created successfully',
            'vendor' => $vendor
        ], 201);
    }
    public function index()
    {
        $user = Auth::user(); //Get authenticated user

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $vendors = Vendor::all();

        return response()->json([
            'message' => 'Vendors retrieved successfully',
            'vendors' => $vendors
        ], 200);
    }
}
