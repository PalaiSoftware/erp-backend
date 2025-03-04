<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Company;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;


class CompanyController extends Controller
{
    // public function store(Request $request)
    // {
    //     // Get the authenticated user
    //     $user = Auth::user();

    //     // Check if the user has role ID 1 (allowed to create a company)
    //     if ($user->rid !== 1) {
    //         return response()->json([
    //             'message' => 'You are not allowed to create a company'
    //         ], 403);
    //     }

    //     // Validate request data
    //     $request->validate([
    //         'name' => 'required|string|max:255',
    //         'address' => 'nullable|string',
    //         'phone' => 'nullable|string|max:20',
    //         'gst_no' => 'nullable|string',
    //         'pan' => 'nullable|string|max:20',
    //     ]);

    //     // Create the company
    //     $company = Company::create([
    //         'user_id' => $user->id, // Owner ID
    //         'name' => $request->name,
    //         'address' => $request->address,
    //         'phone' => $request->phone,
    //         'gst_no' => $request->gst_no,
    //         'pan' => $request->pan,
    //         'cuid' => null, // Can be updated later
    //     ]);

    //     return response()->json([
    //         'message' => 'Company created successfully',
    //         'company' => $company
    //     ], 201);
    // }
    // public function createCompany(Request $request)
    // {
    //     $validator = Validator::make($request->all(), [
    //         'email' => 'required|string|email|exists:users,email', // Ensure user exists
    //         'company_name' => 'required|string|max:255',
    //         'company_address' => 'nullable|string',
    //         'company_phone' => 'nullable|string|max:20',
    //         'gst_no' => 'nullable|string|max:255',
    //         'pan' => 'nullable|string|max:20',
    //     ]);
    
    //     if ($validator->fails()) {
    //         return response()->json(['errors' => $validator->errors()], 422);
    //     }
    
    //     \DB::beginTransaction();
    
    //     try {
    //         // Fetch user by email
    //         $user = User::where('email', $request->email)->first();
    
    //         // Check if user has rid == 1
    //         if ($user->rid != 1) {
    //             return response()->json(['message' => 'User is not authorized to create a company'], 403);
    //         }
    
    //         // Create a new company linked to this user
    //         $company = Company::create([
    //             'name' => $request->company_name,
    //             'uid' => $user->id,
    //             'address' => $request->company_address,
    //             'phone' => $request->company_phone,
    //             'gst_no' => $request->gst_no,
    //             'pan' => $request->pan,
    //             'cuid' => null,
    //             'blocked' => 0,
    //         ]);
    
    //         \DB::commit();
    
    //         return response()->json([
    //             'message' => 'Company created successfully',
    //             'company' => $company
    //         ], 201);
    
    //     } catch (\Exception $e) {
    //         \DB::rollBack();
    //         return response()->json([
    //             'message' => 'Company creation failed',
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }
//     public function createCompany(Request $request)
// {
//     // Get the authenticated user from token
//     $user = auth()->user();

//     // Check if the user has `rid == 1` (Admin Role)
//     if ($user->rid != 1) {
//         return response()->json(['message' => 'User is not authorized to create a company'], 403);
//     }

//     // Validate the company data
//     $validator = Validator::make($request->all(), [
//         'company_name' => 'required|string|max:255',
//         'company_address' => 'nullable|string',
//         'company_phone' => 'nullable|string|max:20',
//         'gst_no' => 'nullable|string|max:255',
//         'pan' => 'nullable|string|max:20',
//     ]);

//     if ($validator->fails()) {
//         return response()->json(['errors' => $validator->errors()], 422);
//     }

//     // Create a new company with the authenticated user as `uid`
//     $company = Company::create([
//         'name' => $request->company_name,
//         'uid' => $user->id,  // Assign current user ID
//         'address' => $request->company_address,
//         'phone' => $request->company_phone,
//         'gst_no' => $request->gst_no,
//         'pan' => $request->pan,
//         'cuid' => null, // Can be updated later
//         'blocked' => 0, // Default to not blocked
//     ]);

//     return response()->json([
//         'message' => 'Company created successfully',
//         'company' => $company
//     ], 201);
// }
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
    
        return response()->json(['message' => 'Company created successfully', 'company' => $company], 201);
    }
    

}
