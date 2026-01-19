<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Models\PurchaseClient;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB; // Added this line
class VendorController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function store(Request $request)
{
    // Force JSON response
    $request->headers->set('Accept', 'application/json');

    $user = Auth::user();

    // Check if the user is authenticated
    if (!$user) {
        return response()->json(['message' => 'Unauthenticated'], 401);
    }

    // Check if the user's rid is one of 1, 2, 3,
    if (!in_array($user->rid, [1, 2])) {
        return response()->json(['message' => 'Unauthorized to create a purchase client'], 403);
    }
    // ✅ CRITICAL FIX: Define $cid BEFORE validation
    $cid = (int)$user->cid;
    
    // ✅ EXTRA SAFETY: Check if cid is valid
    if ($cid <= 0) {
        return response()->json(['message' => 'Invalid company ID'], 400);
    }

    try {
        // Validate the request
         // Validate the request
         $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                // ✅ CORRECT WAY: Use $cid that's already defined
                function ($attribute, $value, $fail) use ($cid, $user) {
                    $normalizedValue = trim(strtolower($value));
                    
                    if (PurchaseClient::whereRaw('TRIM(LOWER(name)) = ?', [$normalizedValue])
                        ->where('cid', $cid)
                        ->exists()) {
                        $fail($value . ' has already been taken for this company.');
                    }
                },
            ],
            'email' => 'nullable|email',
            'phone' => 'required|string|max:20',
            'address' => 'nullable|string',
            'gst_no' => 'nullable|string|max:255',
            'pan' => 'nullable|string|max:255',
            // 'uid' => 'nullable|integer',
            // 'cid' => 'required|integer',
        ]);
    } catch (ValidationException $e) {
        // Customize validation error response
        $errors = $e->errors();
        if (isset($errors['name'])) {
            return response()->json(['message' => $errors['name'][0]], 422);
        }
        return response()->json(['errors' => $errors], 422);
    }

    $purchaseClient = PurchaseClient::create([
        'name' => $validated['name'],
        'email' => $validated['email'] ?? null,
        'phone' => $validated['phone'],
        'address' => $validated['address'] ?? null,
        'gst_no' => $validated['gst_no'] ?? null,
        'pan' => $validated['pan'] ?? null,
        'uid' => $user->id,
        'cid' => $user->cid,
    ]);

    return response()->json([
        'message' => 'Purchase Client created successfully',
        'purchase_client' => $purchaseClient
    ], 201);
}

public function checkVendor(Request $request)
{
    // Authentication and authorization checks
    $user = Auth::user();
    if (!$user) return response()->json(['message' => 'Unauthorized'], 401);
    if ($user->rid < 1 || $user->rid > 5) return response()->json(['message' => 'Forbidden'], 403);

    // Validate the request
    $validated = $request->validate([
        'gstno' => 'nullable|string|required_without_all:pannumber,phone,email,name',
        'pannumber' => 'nullable|string|required_without_all:gstno,phone,email,name',
        'phone' => 'nullable|string|required_without_all:gstno,pannumber,email,name',
        'email' => 'nullable|email|required_without_all:gstno,pannumber,phone,name',
        'name' => 'nullable|string|required_without_all:gstno,pannumber,phone,email',
        'cid' => 'required|integer',
    ]);

    // Build the query dynamically with pattern matching
    $query = PurchaseClient::query();

    // Add pattern matching conditions
    if (isset($validated['name'])) {
        $query->where('name', 'LIKE', "%{$validated['name']}%");
    }
    if (isset($validated['email'])) {
        $query->where('email', 'LIKE', "%{$validated['email']}%");
    }
    if (isset($validated['phone'])) {
        $query->where('phone', 'LIKE', "%{$validated['phone']}%");
    }
    if (isset($validated['gstno'])) {
        $query->where('gst_no', 'LIKE', "%{$validated['gstno']}%");
    }
    if (isset($validated['pannumber'])) {
        $query->where('pan', 'LIKE', "%{$validated['pannumber']}%");
    }
    // Exact match for company ID
    $query->where('cid', $validated['cid']);

    // Execute the query
    $purchaseClients = $query->get();

    // Return response based on results
    return $purchaseClients->isEmpty()
        ? response()->json(['message' => 'Purchase client not found in your company. Please add them.'], 404)
        : response()->json([
            'message' => 'Purchase client found in your company.',
            'purchase_clients' => $purchaseClients
        ], 200);
}
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
    
        $validated = $request->validate([
            'cid' => 'required|integer',
        ]);
        // Extract the validated 'cid' for clarity (optional but improves readability)
        $cid = $validated['cid'];

         // Check if the user belongs to the requested company
         if ($user->cid != $cid) {
            return response()->json(['message' => 'Forbidden: You do not have access to this company\'s data'], 403);
         }
    
         $purchaseClients = PurchaseClient::where('purchase_clients.cid', $validated['cid'])
         ->leftJoin('users', 'purchase_clients.uid', '=', 'users.id')
         ->select(
             'purchase_clients.id',
             'purchase_clients.name',
             'purchase_clients.email',
             'purchase_clients.phone',
             'purchase_clients.address',
             'purchase_clients.gst_no',
             'purchase_clients.pan',
             'purchase_clients.uid',
             'users.name as created_by'  // Added: Get the user's name
         )
         ->orderBy('purchase_clients.id', 'desc')
         ->get();
         
        return response()->json([
            'message' => 'Purchase clients retrieved successfully',
            'purchase_clients' => $purchaseClients,
        ], 200);
}

public function getVendorById($vendorId)
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

    // Validate vendor_id (basic check since it's a route parameter)
    if (!is_numeric($vendorId) || $vendorId <= 0) {
        return response()->json([
            'message' => 'Validation failed',
            'errors' => ['vendor_id' => ['The vendor_id must be a positive integer.']]
        ], 422);
    }

    // Fetch purchase client details
    $purchaseClient = PurchaseClient::select('id', 'name', 'email', 'phone', 'address', 'gst_no', 'pan','uid')
        ->find($vendorId);

    if (!$purchaseClient) {
        return response()->json([
            'status' => 'error',
            'message' => 'Purchase client not found'
        ], 404);
    }
    // Check if the current user is the one who created this vendor
if ($purchaseClient->uid != $user->id) {
    \Log::warning('Unauthorized vendor access attempt', [
        'vendor_id' => $vendorId,
        'user_id' => $user->id,
        'vendor_creator_id' => $purchaseClient->uid
    ]);
    return response()->json([
        'message' => 'Forbidden: You do not have permission to view this vendor'
    ], 403);
}

    return response()->json([
        'status' => 'success',
        'data' => $purchaseClient
    ], 200);
}

public function update(Request $request, $id)
{
    // Force JSON response
    $request->headers->set('Accept', 'application/json');

    // Authentication and authorization checks
    $user = Auth::user();
    if (!$user) {
        return response()->json(['message' => 'Unauthorized'], 401);
    }
    if (!in_array($user->rid, [1, 2, 3])) {
        return response()->json(['message' => 'Forbidden'], 403);
    }

    // ✅ 1. Find vendor FIRST (before validation)
    $purchaseClient = PurchaseClient::find($id);
    if (!$purchaseClient) {
        return response()->json(['message' => 'Purchase Client not found'], 404);
    }
    // Check if the current user is the one who created this vendor
if ($purchaseClient->uid != $user->id) {
    \Log::warning('Unauthorized vendor update attempt', [
        'vendor_id' => $id,
        'user_id' => $user->id,
        'vendor_creator_id' => $purchaseClient->uid
    ]);
    return response()->json([
        'message' => 'Forbidden: You do not have permission to update this vendor'
    ], 403);
}

    // ✅ 2. Get company ID from EXISTING vendor (not request)
    $cid = (int)$purchaseClient->cid;

    try {
        // ✅ 3. Validate the request with CUSTOM name validation
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                // ✅ CRITICAL: Custom validation for same company
                function ($attribute, $value, $fail) use ($cid, $id) {
                    $normalizedValue = trim(strtolower($value));
                    
                    // Check if name exists for OTHER vendors in SAME company
                    $exists = PurchaseClient::whereRaw('TRIM(LOWER(name)) = ?', [$normalizedValue])
                        ->where('cid', $cid)
                        ->where('id', '!=', $id) // ✅ Exclude current vendor
                        ->exists();
                    
                    if ($exists) {
                        $fail($value . ' has already been taken for this company.');
                    }
                },
            ],
            'email' => 'nullable|email|unique:purchase_clients,email,' . $id,
            'phone' => 'required|string|max:20',
            'address' => 'nullable|string',
            'gst_no' => 'nullable|string|max:255',
            'pan' => 'nullable|string|max:255',
        ]);
    } catch (ValidationException $e) {
        $errors = $e->errors();
        if (isset($errors['name'])) {
            return response()->json(['message' => $errors['name'][0]], 422);
        }
        return response()->json(['errors' => $errors], 422);
    }

    // ✅ 4. Update vendor (CID automatically stays same)
    $purchaseClient->update($validated);

    return response()->json([
        'message' => 'Purchase Client updated successfully',
        'purchase_client' => $purchaseClient
    ], 200);
}
}