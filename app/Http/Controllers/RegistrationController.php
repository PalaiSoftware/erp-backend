<?php

namespace App\Http\Controllers;

use App\Models\PendingRegistration;
use App\Models\User;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth; 

class RegistrationController extends Controller
{
    // Register → save into pending_registrations
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                function ($attribute, $value, $fail) {
                    if (
                        User::whereRaw('LOWER(email) = LOWER(?)', [strtolower($value)])->exists() ||
                        PendingRegistration::whereRaw('LOWER(email) = LOWER(?)', [strtolower($value)])->exists()
                    ) {
                        $fail('The email has already been taken.');
                    }
                },
            ],
            'mobile' => 'required|string|max:255',
            'country' => 'required|string|max:255',
            'password' => 'required|string|min:6',
            'rid' => 'required|integer|between:1,5',
            'client_name' => 'required|string|max:255',
            'client_address' => 'nullable|string',
            'client_phone' => 'nullable|string|max:20',
            'gst_no' => 'nullable|string|max:255',
            'pan' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $pending = PendingRegistration::create([
            'name' => $request->name,
            'email' => $request->email,
            'mobile' => $request->mobile,
            'country' => $request->country,
            'password' => Hash::make($request->password),
            'rid' => $request->rid,
            'client_name' => $request->client_name,
            'client_address' => $request->client_address,
            'client_phone' => $request->client_phone,
            'gst_no' => $request->gst_no,
            'pan' => $request->pan,
        ]);

        return response()->json([
            'message' => 'Registration submitted for approval',
            'data' => $pending
        ], 201);
    }

    
public function pendingList(Request $request)
{
    $user = $request->user(); // more reliable than Auth::user() in some setups

    if (!$user) {
        return response()->json(['message' => 'Unauthenticated'], 401);
    }

    if (!in_array($user->rid, [1, 2])) {
        return response()->json(['message' => 'Unauthorized to Access pending users'], 403);
    }

    $pending = PendingRegistration::where('approved', false)->get();
    return response()->json($pending, 200);
}

    //only single pending user 
    public function getUserById($id)
{
     // Get the authenticated user
     $user = Auth::user();

     // Check if user is authenticated
     if (!$user) {
         return response()->json(['message' => 'Unauthenticated'], 401);
     }

     // Restrict to rid 1, 2, 3,4
     if (!in_array($user->rid, [1, 2])) {
         return response()->json(['message' => 'Unauthorized to Access pending user'], 403);
     }
    try {
        $user = PendingRegistration::find($id);

        if (!$user) {
            return response()->json([
                'message' => 'User not found'
            ], 404);
        }

        return response()->json([
            'message' => 'User retrieved successfully',
            'user' => $user
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Failed to fetch user',
            'error' => $e->getMessage()
        ], 500);
    }
}

public function approve(Request $request)
{
     // Get the authenticated user
     $user = Auth::user();

     // Check if user is authenticated
     if (!$user) {
         return response()->json(['message' => 'Unauthenticated'], 401);
     }

     // Restrict to rid 1, 2, 3,4
     if (!in_array($user->rid, [1, 2])) {
         return response()->json(['message' => 'Unauthorized to approve pending users'], 403);
     }
    // Create a Validator instance so we can check ->fails() and return JSON ourselves
    $validator = Validator::make($request->all(), [
        'id' => 'required|integer|exists:pending_registrations,id',
        'name' => 'required|string|max:255',
        'email' => [
            'required',
            'string',
            'email',
            'max:255',
            function ($attribute, $value, $fail) use ($request) {
                // 1) Check against users
                $emailExistsInUsers = User::whereRaw('LOWER(email) = LOWER(?)', [strtolower($value)])->exists();
                if ($emailExistsInUsers) {
                    $fail('The email has already been taken by an existing user.');
                    return;
                }

                // 2) Also check pending registrations (another pending record with same email)
                $query = PendingRegistration::whereRaw('LOWER(email) = LOWER(?)', [strtolower($value)]);
                if ($request->has('id')) {
                    // exclude the current pending record (so editing the same record's email is allowed)
                    $query->where('id', '<>', $request->id);
                }
                if ($query->exists()) {
                    $fail('The email has already been taken by another pending registration.');
                }
            }
        ],
        'mobile' => 'required|string|max:20',
        'country' => 'required|string|max:255',
        'rid' => 'required|integer|between:1,5',
        'client_name' => 'required|string|max:255',
        'client_address' => 'nullable|string',
        'client_phone' => 'nullable|string|max:20',
        'gst_no' => 'nullable|string|max:255',
        'pan' => 'nullable|string|max:20',
    ]);

    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }

    $pending = PendingRegistration::findOrFail($request->id);

    DB::beginTransaction();
    try {
        // Create or update Client
        $client = Client::create([
            'name'    => $request->client_name,
            'address' => $request->client_address,
            'phone'   => $request->client_phone,
            'gst_no'  => $request->gst_no,
            'pan'     => $request->pan,
            'blocked' => 0,
        ]);

        // Create User (using pending hashed password)
        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'mobile'   => $request->mobile,
            'country'  => $request->country,
            'password' => $pending->password, // already hashed in pending
            'rid'      => $request->rid,
            'blocked'  => 0,
            'cid'      => $client->id,
        ]);

        // Mark pending registration as approved (or you can delete)
        $pending->approved = true;
        $pending->save();

        DB::commit();

        return response()->json([
            'message' => 'User approved with modifications successfully',
            'user'    => $user,
            'client'  => $client,
        ], 200);

    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'message' => 'Approval failed',
            'error'   => $e->getMessage(),
        ], 500);
    }
}


}
