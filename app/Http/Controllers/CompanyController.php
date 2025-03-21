<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Company;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Models\UidCid;

class CompanyController extends Controller
{
    public function index(Request $request){
        $uid = $request->header('uid');
        \Log::info('Received uid from header: ' . $uid);
        if (!$uid) {
            return response()->json(['message' => 'User ID is required in headers.'], 400);
        }
    
        // Get the authenticated user's rid from the token
        $user = auth()->user();
    
        if (!$user) {
            return response()->json(['message' => 'Unauthorized. Token invalid or missing.'], 401);
        }
        $companies = Company::where('uid', $uid)->get();

        return response()->json([
            'companies' => $companies
        ]);
    }
    public function updateRecentCompany(Request $request)
    {
        // Get values from headers
        $uid = $request->header('uid');
        $cid = $request->header('cid'); // Get cid from header
    
        // Input validation
        if (!$uid) {
            return response()->json(['error' => 'User ID is required in headers'], 400);
        }
        if (!$cid) {
            return response()->json(['error' => 'Company ID is required in headers'], 400);
        }
    
        // Authentication check
        $user = auth()->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    
        // Verify company exists
        $company = Company::find($cid);
        if (!$company) {
            return response()->json(['error' => 'Company not found'], 404);
        }
    
        // Optional: Verify user-company association (if needed)
        // if (!$user->companies->contains($cid)) {
        //     return response()->json(['error' => 'User not associated with this company'], 403);
        // }
    
        // Update or create entry
        $entry = UidCid::updateOrCreate(
            ['uid' => $uid],
            ['cid' => $cid]
        );
    
        // Logging
        \Log::info("User $uid updated recent company to $cid", [
            'old_cid' => $entry->wasRecentlyCreated ? null : $entry->getOriginal('cid'),
            'new_cid' => $cid
        ]);
    
        return response()->json([
            'message' => 'Company updated successfully',
            'uid' => $uid,
            'cid' => $cid
        ], 200);
    }
    // Debugging: Log uid to check if it's received
    public function createCompany(Request $request)
    {
        // Get uid from headers
        $uid = $request->header('uid');
    
        // Debugging: Log uid to check if it's received
        \Log::info('Received uid from header: ' . $uid);
    
        // Check if uid is present
        if (!$uid) {
            return response()->json(['message' => 'User ID is required in headers.'], 400);
        }
    
        // Get the authenticated user's rid from the token
        $user = auth()->user();
    
        if (!$user) {
            return response()->json(['message' => 'Unauthorized. Token invalid or missing.'], 401);
        }
    
        // Check if the user has rid == 1
        if ($user->rid !== 1) {
            return response()->json(['message' => 'Unauthorized. Only role ID 1 can create companies.'], 403);
        }
    
        // Validate request data
        $validator = Validator::make($request->all(), [
            'company_name' => 'required|string|max:255',
            'company_address' => 'nullable|string',
            'company_phone' => 'nullable|string|max:20',
            'gst_no' => 'nullable|string|max:255',
            'pan' => 'nullable|string|max:20',
        ]);
    
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
    
        // Create the company with the given uid
        $company = Company::create([
            'name' => $request->company_name,
            'uid' => $uid, // Using uid from headers
            'address' => $request->company_address,
            'phone' => $request->company_phone,
            'gst_no' => $request->gst_no,
            'pan' => $request->pan,
            'cuid' => null, // Can be updated later
            'blocked' => 0, // Default to not blocked
        ]);
    // Debug the operation
    $existing = UidCid::where('uid', $uid)->first();
    if ($existing) {
        \Log::info('Before: uid ' . $uid . ' exists with cid ' . $existing->cid);
    } else {
        \Log::info('Before: uid ' . $uid . ' not found');
    }

    UidCid::updateOrCreate(
        ['uid' => $uid],
        ['cid' => $company->id]
    );

    $updated = UidCid::where('uid', $uid)->first();
    \Log::info('After: uid ' . $uid . ' has cid ' . $updated->cid);
        return response()->json(['message' => 'Company created successfully', 'company' => $company], 201);
    }
    

}
