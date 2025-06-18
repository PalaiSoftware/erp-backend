<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Models\Vendor;
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

        // Check if the user's rid is one of 5, 6, 7, or 8
        if (!in_array($user->rid, [5, 6, 7, 8])) {
            return response()->json(['message' => 'Unauthorized to create a vendor'], 403);
        }

        // $validated = $request->validate([
        //     'vendor_name' => 'required|string|max:255',
        //     'contact_person' => 'nullable|string|max:255',
        //     'email' => 'nullable|email',
        //     'phone' => 'nullable|string|max:20',
        //     'address' => 'nullable|string',
        //     'gst_no' => 'nullable|string|max:255',
        //     'pan' => 'nullable|string|max:255',
        //     'uid' => 'nullable|integer',
        //     'cid' => 'required|integer',
        // ]);
 
        try {
            // Validate the request
            $validated = $request->validate([
                'vendor_name' => [
                    'required',
                    'string',
                    'max:255',
                    function ($attribute, $value, $fail) use ($request) {
                        if (Vendor::where('vendor_name', $value)->where('cid', $request->input('cid'))->exists()) {
                            $fail($value . ' has already been taken for this company.');
                        }
                    },
                ],
                'contact_person' => 'nullable|string|max:255',
                'email' => 'nullable|email',
                'phone' => 'nullable|string|max:20',
                'address' => 'nullable|string',
                'gst_no' => 'nullable|string|max:255',
                'pan' => 'nullable|string|max:255',
                'uid' => 'nullable|integer',
                'cid' => 'required|integer',
            ]);
        } catch (ValidationException $e) {
            // Customize validation error response
            $errors = $e->errors();
            if (isset($errors['vendor_name'])) {
                return response()->json(['message' => $errors['vendor_name'][0]], 422);
            }
            return response()->json(['errors' => $errors], 422);
        }

        $vendor = Vendor::create([
            'vendor_name' => $validated['vendor_name'],
            'contact_person' => $validated['contact_person'] ?? null,
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'address' => $validated['address'] ?? null,
            'gst_no' => $validated['gst_no'] ?? null,
            'pan' => $validated['pan'] ?? null,
            'uid' => $validated['uid'] ?? null,
            'cid' => $validated['cid'],
        ]);

        return response()->json([
            'message' => 'Vendor created successfully',
            'vendor' => $vendor
        ], 201);
    }
    // public function checkVendor(Request $request)
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
            
    //         $validated = $request->validate([
    //             'gstno' => 'required|string',
    //             'pannumber' => 'required|string',
    //             phone 
    //             email 
    //             name 

    //             'cid' => 'required|integer',
    //         ]);
        
            
    //         $vendor = Vendor::where('gst_no', $validated['gstno'])
    //                         ->where('pan', $validated['pannumber'])
    //                         ->first();
        
            
    //         if (!$vendor) {
    //             return response()->json([
    //                 'message' => 'Please add this vendor as this vendor is not present in the vendors table.'
    //             ], 404);
    //         }
        
            
    //         $cids = $vendor->cids ?? [];
        
    //         if (in_array($validated['cid'], $cids)) {
    //             return response()->json([
    //                 'message' => 'This vendor already exists in your company.'
    //             ], 200);
    //         } else {
    //             return response()->json([
    //                 'message' => 'Need to add this vendor to your company.'
    //             ], 200);
    //         }
    // }
    // public function checkVendor(Request $request)
    // {
    // // Authentication and authorization checks
    //         $user = Auth::user();
    //         if (!$user) return response()->json(['message' => 'Unauthorized'], 401);
    //         if ($user->rid < 5 || $user->rid > 10) return response()->json(['message' => 'Forbidden'], 403);

    //         // Validate the request
    //         $validated = $request->validate([
    //             'gstno' => 'nullable|string|required_without_all:pannumber,phone,email,name',
    //             'pannumber' => 'nullable|string|required_without_all:gstno,phone,email,name',
    //             'phone' => 'nullable|string|required_without_all:gstno,pannumber,email,name',
    //             'email' => 'nullable|email|required_without_all:gstno,pannumber,phone,name',
    //             'name' => 'nullable|string|required_without_all:gstno,pannumber,phone,email',
    //             'cid' => 'required|integer',
    //         ]);

    //         // Build the query dynamically
    //         $query = Vendor::query();

    //         if (isset($validated['gstno'])) {
    //             $query->where('gst_no', $validated['gstno']);
    //         }
    //         if (isset($validated['pannumber'])) {
    //             $query->where('pan', $validated['pannumber']);
    //         }
    //         if (isset($validated['phone'])) {
    //             $query->where('phone', $validated['phone']);
    //         }
    //         if (isset($validated['email'])) {
    //             $query->where('email', $validated['email']);
    //         }
    //         if (isset($validated['name'])) {
    //             $query->where('vendor_name', $validated['name']);
    //         }

    //         // Add the cid condition to the query
    //         $query->where('cid', $validated['cid']);

    //         $vendor = $query->get();

    //         // Return response based on vendor existence
    //         if ($vendor) {
    //             return response()->json([
    //                 'message' => 'Vendor found in your company.',
    //                 'vendor' => $vendor
    //             ], 200);
    //         } else {
    //             return response()->json([
    //                 'message' => 'Vendor not found in your company. Please add them.',
    //             ], 404);
    //         }
    // }
    public function checkVendor(Request $request)
    {
        // Authentication and authorization checks
        $user = Auth::user();
        if (!$user) return response()->json(['message' => 'Unauthorized'], 401);
        if ($user->rid < 5 || $user->rid > 10) return response()->json(['message' => 'Forbidden'], 403);
    
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
        $query = Vendor::query();
    
        // Add pattern matching conditions
        if (isset($validated['gstno'])) {
            $query->where('gst_no', 'LIKE', "%{$validated['gstno']}%");
        }
        if (isset($validated['pannumber'])) {
            $query->where('pan', 'LIKE', "%{$validated['pannumber']}%");
        }
        if (isset($validated['phone'])) {
            $query->where('phone', 'LIKE', "%{$validated['phone']}%");
        }
        if (isset($validated['email'])) {
            $query->where('email', 'LIKE', "%{$validated['email']}%");
        }
        if (isset($validated['name'])) {
            $query->where('vendor_name', 'LIKE', "%{$validated['name']}%");
        }
    
        // Exact match for company ID
        $query->where('cid', $validated['cid']);
    
        // Execute the query
        $vendors = $query->get();
    
        // Return response based on results
        return $vendors->isEmpty()
            ? response()->json(['message' => 'Vendor not found in your company. Please add them.'], 404)
            : response()->json([
                'message' => 'Vendor found in your company.',
                'vendors' => $vendors
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

                
                $vendors = Vendor::where('cid', $validated['cid'])
                                ->orderBy('id')
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

        public function update(Request $request, $id)
            {
                // Authentication and authorization checks
                $user = Auth::user();
                if (!$user) {
                    return response()->json(['message' => 'Unauthorized'], 401);
                }
                if (!in_array($user->rid, [5, 6, 7, 8, 9, 10])) {
                    return response()->json(['message' => 'Forbidden'], 403);
                }

                // Validate the request
                $validated = $request->validate([
                    'vendor_name' => 'required|string|max:255',
                    'contact_person' => 'nullable|string|max:255',
                    'email' => 'nullable|email|unique:vendors,email,' . $id, // Ignore current vendor's email
                    'phone' => 'nullable|string|max:20',
                    'address' => 'nullable|string',
                    'gst_no' => 'nullable|string|max:255',
                    'pan' => 'nullable|string|max:255',
                    'uid' => 'nullable|integer',
                    'cid' => 'required|integer', // Ensure cid is provided
                ]);

                // Find the vendor by ID
                $vendor = Vendor::find($id);
                if (!$vendor) {
                    return response()->json(['message' => 'Vendor not found'], 404);
                }

                // Update the vendor
                $vendor->update($validated);

                // Return response
                return response()->json([
                    'message' => 'Vendor updated successfully',
                    'vendor' => $vendor
                ], 200);
            } 
        public function getVendorById($vendorId)
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
            
                // Validate vendor_id (basic check since it's a route parameter)
                if (!is_numeric($vendorId) || $vendorId <= 0) {
                    return response()->json([
                        'message' => 'Validation failed',
                        'errors' => ['vendor_id' => ['The vendor_id must be a positive integer.']]
                    ], 422);
                }
            
                // Fetch vendor details
                $vendor = DB::table('vendors')
                    ->where('id', $vendorId)
                    ->select('id', 'vendor_name', 'contact_person', 'email', 'phone', 'address', 'gst_no', 'pan', 'uid', 'cid', 'created_at', 'updated_at')
                    ->first();
            
                if (!$vendor) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Vendor not found'
                    ], 404);
                }
            
                return response()->json([
                    'status' => 'success',
                    'data' => $vendor
                ], 200);
            }                
}