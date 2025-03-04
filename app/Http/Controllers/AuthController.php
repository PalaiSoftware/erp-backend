<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use App\Models\Company;
use Illuminate\Support\Facades\Auth; 

class AuthController extends Controller
{

public function register(Request $request)
{
    $validator = Validator::make($request->all(), [
        'name' => 'required|string|max:255',
        'email' => 'required|string|email|max:255|unique:users',
        'mobile' => 'required|string|max:255|unique:users',
        'country' => 'required|string|max:255',
        'password' => 'required|string|min:6',
        'rid' => 'required|integer|between:1,6', // Role ID between 1 and 6
        'company_name' => 'required|string|max:255',
        'company_address' => 'nullable|string',
        'company_phone' => 'nullable|string|max:20',
        'gst_no' => 'nullable|string|max:255',
        'pan' => 'nullable|string|max:20',
    ]);

    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }

    \DB::beginTransaction(); // Start transaction

    try {
        // Create user first
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'mobile' => $request->mobile,
            'country' => $request->country,
            'password' => Hash::make($request->password),
            'rid' => $request->rid,
            'blocked' => 0,
        ]);

        if (!$user) {
            throw new \Exception("User creation failed.");
        }

        // Ensure $user->id exists before creating company
        $company = new Company();
        $company->name = $request->company_name;
        $company->uid = $user->id; // Explicitly setting uid
        $company->address = $request->company_address;
        $company->phone = $request->company_phone;
        $company->gst_no = $request->gst_no;
        $company->pan = $request->pan;
        $company->cuid = null;
        $company->blocked = 0;
        $company->save(); // Save instead of create()

        // Update user with company ID
        $user->cid = $company->id;
        $user->save();

        \DB::commit(); // Commit transaction

        return response()->json([
            'message' => 'User and company registered successfully',
            'user' => $user,
            'company' => $company
        ], 201);
    } catch (\Exception $e) {
        \DB::rollBack(); // Rollback transaction on failure
        return response()->json([
            'message' => 'Registration failed',
            'error' => $e->getMessage()
        ], 500);
    }
}


    public function newuser(Request $request)
    {
        $user = Auth::user();
    
        if ($user->rid !== 1) {
            return response()->json([
                'message' => 'You are not allowed to create a new user for your company'
            ], 403);
        }
    
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'mobile' => 'required|string|max:255|unique:users',
            'country' => 'required|string|max:255',
            'password' => 'required|string|min:6',
            'rid' => 'required|integer|between:1,6', // Role ID between 1 and 6
        ]);
    
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
    
        // Get `cid` from the request headers
        $cid = $request->header('cid');
    
        if (!$cid) {
            return response()->json(['message' => 'Company ID is required'], 400);
        }
    
        // Create the new user with `cid`
        $newUser = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'mobile' => $request->mobile,
            'country' => $request->country,
            'password' => Hash::make($request->password),
            'rid' => $request->rid, // Assign role (must be predefined)
            'cid' => $cid, // Assign the company ID from the header
            'blocked' => 0,
        ]);
    
        return response()->json(['message' => 'User registered successfully'], 201);
    }
    

public function login(Request $request)
{
    $credentials = $request->validate([
        'email' => 'required|string|email',
        'password' => 'required|string',
    ]);

    $user = User::where('email', $credentials['email'])->first();

    if (!$user || !Hash::check($credentials['password'], $user->password)) {
        return response()->json(['message' => 'Invalid credentials'], 401);
    }

    if ($user->blocked) {
        return response()->json(['message' => 'User is blocked'], 403);
    }

    $company = Company::find($user->cid);
    // if ($company && $company->blocked) {
    //     return response()->json(['message' => 'Company is blocked'], 403);
    // }

    // Generate Token
    $token = $user->createToken('auth_token')->plainTextToken;

    return response()->json([
        'message' => 'Login successful',
        'user' => $user,
        'token' => $token,
        'company' => $company,
    ]);
}
}

