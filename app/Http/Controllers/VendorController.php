<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Models\Vendor;
use Illuminate\Support\Facades\Auth;

class VendorController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function store(Request $request)
    {
        $user = Auth::user();

    // Check if the user is authenticated
    if (!$user) {
        return response()->json(['message' => 'Unauthenticated'], 401);
    }

    // Check if the user's rid is one of 5, 6, 7, or 8
    if (!in_array($user->rid, [5, 6, 7, 8])) {
        return response()->json(['message' => 'Unauthorized to create a vendor'], 403);
    }

    $validated = $request->validate([
        'vendor_name' => 'required|string|max:255',
        'contact_person' => 'nullable|string|max:255',
        'email' => 'nullable|email|unique:vendors,email',
        'phone' => 'nullable|string|max:20',
        'address' => 'nullable|string',
        'gst_no' => 'nullable|string|max:255',
        'pan' => 'nullable|string|max:255',
        'uid' => 'nullable|integer',
        'cid' => 'required|integer',
    ]);

    $vendor = Vendor::create([
        'vendor_name' => $validated['vendor_name'],
        'contact_person' => $validated['contact_person'],
        'email' => $validated['email'],
        'phone' => $validated['phone'],
        'address' => $validated['address'],
        'gst_no' => $validated['gst_no'],
        'pan' => $validated['pan'],
        'uid' => $validated['uid'],
        'cids' => [$validated['cid']],
    ]);

    return response()->json([
        'message' => 'Vendor created successfully',
        'vendor' => $vendor
    ], 201);
    }
    public function checkVendor(Request $request)
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
            'gstno' => 'required|string',
            'pannumber' => 'required|string',
            'cid' => 'required|integer',
        ]);
    
        
        $vendor = Vendor::where('gst_no', $validated['gstno'])
                        ->where('pan', $validated['pannumber'])
                        ->first();
    
        
        if (!$vendor) {
            return response()->json([
                'message' => 'Please add this vendor as this vendor is not present in the vendors table.'
            ], 404);
        }
    
        
        $cids = $vendor->cids ?? [];
    
        if (in_array($validated['cid'], $cids)) {
            return response()->json([
                'message' => 'This vendor already exists in your company.'
            ], 200);
        } else {
            return response()->json([
                'message' => 'Need to add this vendor to your company.'
            ], 200);
        }
    }

    public function addVendorToCompany(Request $request)
    {
        // Get the authenticated user
    $user = Auth::user();
    
    // Check if user is authenticated
    if (!$user) {
        return response()->json(['message' => 'Unauthenticated'], 401);
    }
    
    // Restrict to rid 5, 6, 7, or 8 only
    if (!in_array($user->rid, [5, 6, 7, 8])) {
        return response()->json(['message' => 'Unauthorized to add vendor to company'], 403);
    }
        $validated = $request->validate([
            'gstno' => 'required|string',
            'pannumber' => 'required|string',
            'cid' => 'required|integer',
        ]);
    
        $vendor = Vendor::where('gst_no', $validated['gstno'])
                        ->where('pan', $validated['pannumber'])
                        ->first();
    
        if (!$vendor) {
            return response()->json([
                'message' => 'Vendor not found.'
            ], 404);
        }
    
        $cids = $vendor->cids ?? []; 
    
        if (in_array($validated['cid'], $cids)) {
            return response()->json([
                'message' => 'Vendor already exists in your company.'
            ], 200);
        }
    
    
        $cids[] = $validated['cid'];
        $vendor->cids = $cids;
        $vendor->save();
    
        return response()->json([
            'message' => 'Vendor added to your company successfully.'
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

    $validated = $request->validate([
        'cid' => 'required|integer', 
    ]);

    
    $vendors = Vendor::whereJsonContains('cids', (int)$validated['cid'])
                     ->select(
                         'id',
                         'vendor_name',
                         'contact_person',
                         'email',
                         'phone',
                         'address',
                         'gst_no',
                         'pan'
                     )
                     ->get();

    return response()->json([
        'message' => 'Vendors retrieved successfully',
        'vendors' => $vendors,
    ], 200);
}
}