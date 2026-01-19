<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Client;
use Illuminate\Support\Facades\Auth; 
use App\Models\PendingRegistration;


class AuthController extends Controller
{

// public function register(Request $request)
// {
//     $validator = Validator::make($request->all(), [
//         'name' => 'required|string|max:255',
//         // 'email' => 'required|string|email|max:255|unique:users',
//         'email' => [
//             'required',
//             'string',
//             'email',
//             'max:255',
//             function ($attribute, $value, $fail) {
//                 if (User::whereRaw('LOWER(email) = LOWER(?)', [strtolower($value)])->exists()) {
//                     $fail('The email has already been taken.');
//                 }
//             },
//         ],
//         'mobile' => 'required|string|max:255',
//         'country' => 'required|string|max:255',
//         'password' => 'required|string|min:6',
//         'rid' => 'required|integer|between:1,5',
//         'client_name' => 'required|string|max:255',
//         'client_address' => 'nullable|string',
//         'client_phone' => 'nullable|string|max:20',
//         'gst_no' => 'nullable|string|max:255',
//         'pan' => 'nullable|string|max:20',
//     ]);

//     if ($validator->fails()) {
//         return response()->json(['errors' => $validator->errors()], 422);
//     }

//     DB::beginTransaction();

//     try {
//         // Create Client first
//         $client = Client::create([
//             'name' => $request->client_name,
//             'address' => $request->client_address,
//             'phone' => $request->client_phone,
//             'gst_no' => $request->gst_no,
//             'pan' => $request->pan,
//             'blocked' => 0,
//         ]);

//         if (!$client) {
//             throw new \Exception("Client creation failed.");
//         }

//         // Create User with cid set to client’s ID
//         $user = User::create([
//             'name' => $request->name,
//             'email' => $request->email,
//             'mobile' => $request->mobile,
//             'country' => $request->country,
//             'password' => Hash::make($request->password),
//             //'rid' => 3,
//             'rid' => $request->rid,
//             'blocked' => 0,
//             'cid' => $client->id, // Set cid during creation
//         ]);

//         if (!$user) {
//             throw new \Exception("User creation failed.");
//         }
//         DB::commit();

//         return response()->json([
//             'message' => 'User and client registered successfully',
//             'user' => $user,
//             'client' => $client
//         ], 201);

//     } catch (\Exception $e) {
//         DB::rollBack();
//         return response()->json([
//             'message' => 'Registration failed',
//             'error' => $e->getMessage()
//         ], 500);
//     }
// }

public function login(Request $request)
{
    $credentials = $request->validate([
        'email' => 'required|string|email',
        'password' => 'required|string',
    ]);

    $pending = PendingRegistration::where('email', $credentials['email'])->first();

    if ($pending && !$pending->approved) {
        return response()->json([
            'message' => 'Your registration is still pending approval. Please wait for admin approval.'
        ], 403);
    }

    $user = User::where('email', $credentials['email'])->first();

    if (!$user) {
        return response()->json([
            'message' => 'Wrong email address. Please check your email.'
        ], 404);
    }

    if (!Hash::check($credentials['password'], $user->password)) {
        return response()->json(['message' => 'Invalid credentials , wrong password'], 401);
    }

    if ($user->blocked) {
        return response()->json(['message' => 'User is blocked'], 403);
    }

    $user->touch(); // Update last login time

    // Fetch client (company) details using user's cid
    $client = Client::find($user->cid);

    if (!$client) {
        return response()->json(['message' => 'Client not found'], 404);
    }

    if ($client->blocked) {
        return response()->json(['message' => 'Client is blocked'], 403);
    }

    // Generate token
    $token = $user->createToken('auth_token')->plainTextToken;

    return response()->json([
        'message' => 'Login successful',
        'user' => $user,
        'token' => $token,
        'client' => $client,
        'previous_login' => $user->updated_at,
    ]);
}


public function newuser(Request $request)
{
    $user = Auth::user();
    
    // Check if user has permission to create new users based on their role
    if (!in_array($user->rid, [1, 2])) {
        return response()->json([
            'message' => 'You are not allowed to create a new user for your company'
        ], 403);
    }
    $validator = Validator::make($request->all(), [
        'name' => 'required|string|max:255',
        'email' => [
            'required',
            'string',
            'email',
            'max:255',
            function ($attribute, $value, $fail) {
                if (User::whereRaw('LOWER(email) = LOWER(?)', [strtolower($value)])->exists()) {
                    $fail('The email has already been taken.');
                }
            },
        ],
        'mobile' => 'required|string|max:255',
        'country' => 'required|string|max:255',
        'password' => 'required|string|min:6',
        'rid' => 'required|integer|between:1,5',
    ]);

    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }

    // Get `cid` from the request headers
    $cid = $request->header('cid');
    
    if (!$cid) {
        return response()->json(['message' => 'Company ID is required'], 400);
    }

    // Define allowed role IDs based on current user's rid
    $allowedRoles = range($user->rid + 1, 5);

    // Check if the requested rid is allowed for this user
    if (!in_array($request->rid, $allowedRoles)) {
        return response()->json([
            'message' => 'You are not allowed to create a user with this role ID'
        ], 403);
    }

    // Create the new user with cid
    $newUser = User::create([
        'name' => $request->name,
        'email' => $request->email,
        'mobile' => $request->mobile,
        'country' => $request->country,
        'password' => Hash::make($request->password),
        'rid' => $request->rid,
        'cid' => $cid,
        'blocked' => 0,
    ]);

    return response()->json(['message' => 'User registered successfully'], 201);
}

//Add the new function here for get UsersByRole
public function getUsersByRole(Request $request)
{
    // Get the authenticated user
    $currentUser = Auth::user();

    if (!$currentUser) {
        return response()->json(['message' => 'Unauthorized'], 401);
    }

    // Define the role hierarchy logic
    $currentRid = $currentUser->rid;

    // Check if current user's rid is 1, 2, 3, or 4
    if (!in_array($currentRid, [1, 2, 3, 4])) {
        return response()->json([
            'message' => 'You are not authorized to view users with lower roles',
            'users' => []
        ], 403);
    }
            
    // Validate the cid from the request body
    try {
        $request->validate([
            'cid' => 'required|integer|exists:clients,id', // Ensure cid is provided and exists
        ]);
    } catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json([
            'message' => 'Validation failed',
            'errors' => $e->errors(),
        ], 422);
    }

    // Get the cid from the request
    $cid = $request->input('cid');

    // Check if the user belongs to the requested company
    if ( $currentUser->cid != $cid) {
        return response()->json(['message' => 'Forbidden: You do not have access to this company\'s data'], 403);
    }

      // Fetch users with rid greater than the current user's rid, within the same company
      // No filter on 'blocked' to include both blocked and unblocked users
        $users = User::where('cid', $cid) // Use provided cid
            ->where('rid', '>', $currentRid) // Only users with lower roles (higher rid numbers)
            ->select('id', 'name', 'email', 'mobile', 'country', 'rid', 'blocked') // Select relevant fields
            ->get();

        if ($users->isEmpty()) {
             return response()->json([
               'message' => 'No users found with lower roles for the provided company ID',
               'users' => []
             ], 200);
        }

        return response()->json([
           'message' => 'Users retrieved successfully (both blocked and unblocked)',
           'users' => $users
        ], 200);

}

public function userBlockUnblock(Request $request)
{
    // Get the authenticated user
    $currentUser = Auth::user();

    if (!$currentUser) {
        return response()->json(['message' => 'Unauthorized'], 401);
    }

    // Check if current user's rid is 1, 2
    if (!in_array($currentUser->rid, [1, 2])) {
        return response()->json([
            'message' => 'You are not authorized to block/unblock users'
        ], 403);
    }

    // Validate request
    $validator = Validator::make($request->all(), [
        'user_id' => 'required|integer|exists:users,id',
        'block' => 'required|boolean' // 1 to block, 0 to unblock
    ]);

    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }

    // Find the target user
    $targetUser = User::where('id', $request->user_id)
                     ->where('cid', $currentUser->cid) // Same company
                     ->first();

    if (!$targetUser) {
        return response()->json([
            'message' => 'User not found in your company'
        ], 404);
    }

    // Check if current user has permission to block/unblock target user
    if ($targetUser->rid <= $currentUser->rid) {
        return response()->json([
            'message' => 'You cannot block/unblock users with equal or higher roles'
        ], 403);
    }

    // Update block status
    $targetUser->blocked = $request->block;
    $targetUser->save();

    $action = $request->block ? 'blocked' : 'unblocked';
    return response()->json([
        'message' => "User {$action} successfully",
        'user' => [
            'id' => $targetUser->id,
            'name' => $targetUser->name,
            'rid' => $targetUser->rid,
            'blocked' => $targetUser->blocked
        ]
    ], 200);
}

public function UserPromoteDemote(Request $request)
{
    // Get the authenticated user
    $currentUser = Auth::user();

    if (!$currentUser) {
        return response()->json(['message' => 'Unauthorized'], 401);
    }

    // Check if current user's rid is 1, 2,(only these can promote/demote)
    if (!in_array($currentUser->rid, [1, 2])) {
        return response()->json([
            'message' => 'You are not authorized to promote or demote users'
        ], 403);
    }
    $validator = Validator::make($request->all(), [
        'user_id' => 'required|integer|exists:users,id',
        'rid' => 'required|integer|min:1|max:5'
    ], [
        'rid.max' => 'Cannot demote beyond the lowest role (rid=5).'
    ]);

    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }

    // Find the target user
    $targetUser = User::where('id', $request->user_id)
                     ->where('cid', $currentUser->cid) // Same company
                     ->first();

    if (!$targetUser) {
        return response()->json([
            'message' => 'User not found in your company'
        ], 404);
    }
    // Check if the target user is blocked
    if ($targetUser->blocked == 1) {
        return response()->json([
            'message' => 'Cannot promote or demote a blocked user'
        ], 403);
    }

    // Define minimum rid that can be modified based on current user's rid
    $minRidToModify = $currentUser->rid + 1; // One level below current user

    // Prevent modifying users with equal or higher roles
    if ($targetUser->rid < $minRidToModify) {
        return response()->json([
            'message' => 'You cannot modify users with equal or higher roles'
        ], 403);
    }

    // Yeh modified condition hai
    if ($request->rid <= $currentUser->rid) {
        return response()->json([
            'message' => 'You cannot set a role equal to or higher than yours'
        ], 400);
    }

    // Check if the new rid is the same as the current rid
    if ($targetUser->rid == $request->rid) {
        return response()->json([
            'message' => 'New role ID must be different from the current role ID'
        ], 400);
    }

    // Determine if it's a promotion or demotion
    $action = $request->rid < $targetUser->rid ? 'promoted' : 'demoted';
    $oldRid = $targetUser->rid;
    $newRid = $request->rid;

    // Update the user's role
    $targetUser->rid = $newRid;
    $targetUser->save();

    return response()->json([
        'message' => "User $action successfully from rid=$oldRid to rid=$newRid",
        'user' => [
            'id' => $targetUser->id,
            'name' => $targetUser->name,
            'rid' => $targetUser->rid,
            'blocked' => $targetUser->blocked
        ]
    ], 200);
}
public function getCompanyDetail(Request $request, $cid)
{
    // Get the authenticated user
    $user = Auth::user();
    if (!$user) {
        return response()->json(['message' => 'Unauthorized'], 401);
    }

    // Restrict access to users with rid 1, 2, or 3
    if (!in_array($user->rid, [1, 2, 3])) {
        return response()->json([
            'message' => 'Forbidden: Only Admin, Superuser, and Moderator can view company details'
        ], 403);
    }

    // Validate CID is a positive integer
    if (!is_numeric($cid) || $cid <= 0) {
        return response()->json([
            'message' => 'Validation failed',
            'errors' => ['cid' => ['The cid must be a positive integer.']]
        ], 422);
    }

    // Check if the user belongs to the requested company
    if ($user->cid != $cid) {
        return response()->json([
            'message' => 'Forbidden: You do not have access to get this company data'
        ], 403);
    }

    try {
        // Fetch client details
        $client = DB::table('clients')
            ->where('id', $cid)
            ->first();

        if (!$client) {
            Log::info('Client not found', ['cid' => $cid]);
            return response()->json([
                'status' => 'error',
                'message' => 'Client not found'
            ], 404);
        }
        // Determine company status based on blocked field
        $companyStatus = $client->blocked == 0 ? 'active' : 'blocked';

        // Return client details
        return response()->json([
            'status' => 'success',
            'data' => [
                'id' => $client->id,
                'name' => $client->name,
                'address' => $client->address,
                'phone' => $client->phone,
                'gst_no' => $client->gst_no,
                'pan' => $client->pan,
                'company_status' => $companyStatus,
            ]
        ], 200);
    } catch (\Exception $e) {
        Log::error('Failed to fetch client details', ['cid' => $cid, 'error' => $e->getMessage()]);
        return response()->json([
            'message' => 'Failed to fetch client details',
            'error' => $e->getMessage()
        ], 500);
    }
}

public function updateCompanyDetails(Request $request, $cid)
{
    // Get the authenticated user
    $user = Auth::user();
    if (!$user) {
        return response()->json(['message' => 'Unauthorized'], 401);
    }

    // Restrict access to users with rid 1, 2
    if (!in_array($user->rid, [1, 2])) {
        return response()->json([
            'message' => 'Forbidden: Only Admin, Superuser can update company details'
        ], 403);
    }

    // Validate CID is a positive integer
    if (!is_numeric($cid) || $cid <= 0) {
        return response()->json([
            'message' => 'Validation failed',
            'errors' => ['cid' => ['The cid must be a positive integer.']]
        ], 422);
    }

    // Check if the user belongs to the requested company
    if ($user->cid != $cid) {
        return response()->json([
            'message' => 'Forbidden: You do not have permission to update this company\'s details'
        ], 403);
    }

    try {
        // Validate the request data
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'address' => 'sometimes|string|max:500',
            'phone' => 'sometimes|string|max:20',
            'gst_no' => 'sometimes|string|max:20',
            'pan' => 'sometimes|string|max:20',
        ]);

        // Check if the client exists
        $client = DB::table('clients')->where('id', $cid)->first();
        if (!$client) {
            Log::info('Client not found for update', ['cid' => $cid]);
            return response()->json([
                'status' => 'error',
                'message' => 'Client not found'
            ], 404);
        }
        //   blocked = 0 means company is UNBLOCKED (active) - CAN BE UPDATED
        //   blocked = 1 means company is BLOCKED - CANNOT BE UPDATED
        if ($client->blocked == 1) {
            Log::warning('Attempt to update blocked company', ['cid' => $cid]);
            return response()->json([
                'status' => 'error',
                'message' => 'Cannot update details for a blocked company'
            ], 403);
        }

        // Update the client details
        DB::table('clients')
            ->where('id', $cid)
            ->update(array_merge($validated, ['updated_at' => now()]));

        // Fetch the updated client details
        $updatedClient = DB::table('clients')->where('id', $cid)->first();

        return response()->json([
            'status' => 'success',
            'message' => 'Company details updated successfully',
        ], 200);
    } catch (\Illuminate\Validation\ValidationException $e) {
        Log::error('Validation failed for company update', [
            'cid' => $cid,
            'errors' => $e->errors()
        ]);
        return response()->json([
            'message' => 'Validation failed',
            'errors' => $e->errors(),
        ], 422);
    } catch (\Exception $e) {
        Log::error('Failed to update company details', ['cid' => $cid, 'error' => $e->getMessage()]);
        return response()->json([
            'message' => 'Failed to update company details',
            'error' => $e->getMessage()
        ], 500);
    }
}

public function getUserDetailsById(Request $request, $userId)
{
    // Get the authenticated user
    $currentUser = Auth::user();
    if (!$currentUser) {
        return response()->json(['message' => 'Unauthorized'], 401);
    }

    // Validate user ID is a positive integer
    if (!is_numeric($userId) || $userId <= 0) {
        return response()->json([
            'message' => 'Validation failed',
            'errors' => ['user_id' => ['The user_id must be a positive integer.']]
        ], 422);
    }

    //  Fetch user with company name =====
    $targetUser = DB::table('users')
        ->leftJoin('clients', 'users.cid', '=', 'clients.id')
        ->where('users.id', $userId)
        ->select(
            'users.*',
            'clients.name as company_name'
        )
        ->first();
    
    
    if (!$targetUser) {
        return response()->json([
            'status' => 'error',
            'message' => 'User not found'
        ], 404);
    }

    // Check if users belong to the same company
    if ($currentUser->cid != $targetUser->cid) {
        return response()->json([
            'message' => 'Forbidden: You do not have access to this user\'s data'
        ], 403);
    }

    // Check access permissions based on current user's rid
    if ($currentUser->id != $userId) { // If not requesting own details
        // Only users with rid 1, 2, or 3 can view other users in the same company
        if (!in_array($currentUser->rid, [1, 2, 3])) {
            return response()->json([
                'message' => 'Forbidden: You do not have permission to view other users\' details'
            ], 403);
        }
    }

    // Fetch role name from roles table
    $roleName = DB::table('roles')->where('id', $targetUser->rid)->value('role') ?? 'Unknown';
    
    // Determine user status based on blocked field
    $userStatus = $targetUser->blocked == 0 ? 'active' : 'blocked';
    
    // Return user details (excluding sensitive information)
    return response()->json([
        'status' => 'success',
        'data' => [
            'id' => $targetUser->id,
            'name' => $targetUser->name,
            'email' => $targetUser->email,
            'mobile' => $targetUser->mobile,
            'country' => $targetUser->country,
            'role' => $roleName,
            'company_name' => $targetUser->company_name,  // Added company name
            'user_status' => $userStatus,
        ]
    ], 200);
}



public function updateUserDetails(Request $request, $userId)
{
    // Get the authenticated user
    $currentUser = Auth::user();
    if (!$currentUser) {
        return response()->json(['message' => 'Unauthorized'], 401);
    }

    // Validate user ID is a positive integer
    if (!is_numeric($userId) || $userId <= 0) {
        return response()->json([
            'message' => 'Validation failed',
            'errors' => ['user_id' => ['The user_id must be a positive integer.']]
        ], 422);
    }

    // Fetch the target user details
    $targetUser = DB::table('users')->where('id', $userId)->first();
    
    if (!$targetUser) {
        return response()->json([
            'status' => 'error',
            'message' => 'User not found'
        ], 404);
    }
    
    // Check if the user is blocked
    if ($targetUser->blocked == 1) {
        \Log::warning('Attempt to update blocked user', [
            'user_id' => $userId,
            'current_user' => $currentUser->id
        ]);
        return response()->json([
            'status' => 'error',
            'message' => 'Cannot update details for a blocked user'
        ], 403);
    }
   
    // Check if users belong to the same company
    if ($currentUser->cid != $targetUser->cid) {
        return response()->json([
            'message' => 'Forbidden: You do not have access to update this user\'s data'
        ], 403);
    }

    // ====== UPDATED PERMISSION LOGIC ======
    // Check access permissions based on role hierarchy
    if ($currentUser->id != $userId) { // If not updating own details
        // Role hierarchy validation:
        // - rid=1 can update all lower roles (2,3,4,5)
        // - rid=2 can update rid 3,4,5 (but NOT other rid=2)
        // - rid=3 can update rid 4,5 (but NOT other rid=2/3)
        // - rid=4/5 can ONLY update self (handled by this if-block)
        
        if ($currentUser->rid == 1) {
            // Super Admin: Allow if target has higher rid number (lower role)
            if ($targetUser->rid <= 1) {
                return response()->json([
                    'message' => 'Forbidden: Admin can only update lower roles'
                ], 403);
            }
        } 
        elseif ($currentUser->rid == 2) {
            // Admin: Allow only for rid 3,4,5
            if ($targetUser->rid <= 2) {
                return response()->json([
                    'message' => 'Forbidden: SuperUser can only update lower roles'
                ], 403);
            }
        } 
        elseif ($currentUser->rid == 3) {
            // Manager: Allow only for rid 4,5
            if ($targetUser->rid <= 3) {
                return response()->json([
                    'message' => 'Forbidden: Moderator can only update Staff roles'
                ], 403);
            }
        } 
        else {
            // Staff (rid=4/5): Cannot update others
            return response()->json([
                'message' => 'Forbidden: Staff members can only update their own details'
            ], 403);
        }
    }
    // ====== END UPDATED PERMISSION LOGIC ======

    try {
        // Validate the request data
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $userId,
            'mobile' => 'sometimes|string|max:20',
            'country' => 'sometimes|string|max:50',
        ]);

        // Update the user details
        DB::table('users')
            ->where('id', $userId)
            ->update(array_merge($validated, ['updated_at' => now()]));

        // Fetch the updated user details with company name and role
        $updatedUser = DB::table('users')
            ->leftJoin('clients', 'users.cid', '=', 'clients.id')
            ->leftJoin('roles', 'users.rid', '=', 'roles.id')
            ->where('users.id', $userId)
            ->select(
                'users.*',
                'clients.name as company_name',
                'roles.role as role_name'
            )
            ->first();

        return response()->json([
            'status' => 'success',
            'message' => 'User details updated successfully',
        ], 200);
    } catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json([
            'message' => 'Validation failed',
            'errors' => $e->errors(),
        ], 422);
    } catch (\Exception $e) {
        \Log::error('Failed to update user details', [
            'user_id' => $userId, 
            'error' => $e->getMessage()
        ]);
        return response()->json([
            'message' => 'Failed to update user details',
            'error' => $e->getMessage()
        ], 500);
    }
}
public function changeUserPassword(Request $request, $userId)
{
    // Get the authenticated user
    $currentUser = Auth::user();
    if (!$currentUser) {
        return response()->json(['message' => 'Unauthorized'], 401);
    }

    // Validate user ID is a positive integer
    if (!is_numeric($userId) || $userId <= 0) {
        return response()->json([
            'message' => 'Validation failed',
            'errors' => ['user_id' => ['The user_id must be a positive integer.']]
        ], 422);
    }

    // Fetch the target user details
    $targetUser = DB::table('users')->where('id', $userId)->first();
    
    if (!$targetUser) {
        return response()->json([
            'status' => 'error',
            'message' => 'User not found'
        ], 404);
    }

    // Check if the user is blocked
    if ($targetUser->blocked == 1) {
        \Log::warning('Attempt to change password for blocked user', [
            'user_id' => $userId,
            'current_user' => $currentUser->id
        ]);
        return response()->json([
            'status' => 'error',
            'message' => 'Cannot change password for a blocked user'
        ], 403);
    }

    // Check if users belong to the same company
    if ($currentUser->cid != $targetUser->cid) {
        return response()->json([
            'message' => 'Forbidden: You do not have access to change this user\'s password'
        ], 403);
    }

    // ====== UPDATED PERMISSION LOGIC ======
    // Check access permissions based on role hierarchy
    if ($currentUser->id != $userId) { // If not changing own password
        // Role hierarchy validation:
        // - rid=1 can change self + all lower roles (rid 2,3,4,5)
        // - rid=2 can change self + rid 3,4,5 (but NOT other rid=2)
        // - rid=3 can change self + rid 4,5 (but NOT other rid=2/3)
        // - rid=4/5 can ONLY change self
        
        if ($currentUser->rid == 1) {
            // Super Admin: Block if target has same or higher role (rid<=1)
            if ($targetUser->rid <= 1) {
                return response()->json([
                    'message' => 'Forbidden: Admin can only change lower roles password'
                ], 403);
            }
        } 
        elseif ($currentUser->rid == 2) {
            // Admin: Block if target has same or higher role (rid<=2)
            if ($targetUser->rid <= 2) {
                return response()->json([
                    'message' => 'Forbidden: SuperUser can only change lower roles password'
                ], 403);
            }
        } 
        elseif ($currentUser->rid == 3) {
            // Manager: Block if target has same or higher role (rid<=3)
            if ($targetUser->rid <= 3) {
                return response()->json([
                    'message' => 'Forbidden: Moderator can only change Staff password'
                ], 403);
            }
        } 
        else {
            // Staff (rid=4/5): Cannot change others
            return response()->json([
                'message' => 'Forbidden: Staff members can only change their own password'
            ], 403);
        }
    }
    // ====== END UPDATED PERMISSION LOGIC ======

    try {
        // Validation rules
        $rules = [
            'new_password' => 'required|string|confirmed',
            'new_password_confirmation' => 'required|string',
            'email' => 'required|email'  // Required for email verification
        ];
        
        // Only require old_password for self
        if ($currentUser->id == $userId) {
            $rules['old_password'] = 'required|string';
        }
        
        // Validate the request data
        $validated = $request->validate($rules);

        // Verify email matches the user's email
        if ($validated['email'] !== $targetUser->email) {
            return response()->json([
                'message' => 'The provided email does not match the user\'s email'
            ], 422);
        }

        // Verify old password for self
        if ($currentUser->id == $userId) {
            if (!Hash::check($validated['old_password'], $targetUser->password)) {
                return response()->json([
                    'message' => 'The provided old password is incorrect'
                ], 422);
            }
        }

        // Update the password
        $newPasswordHash = Hash::make($validated['new_password']);
        
        DB::table('users')
            ->where('id', $userId)
            ->update([
                'password' => $newPasswordHash,
                'updated_at' => now()
            ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Password updated successfully'
        ], 200);
    } catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json([
            'message' => 'Validation failed',
            'errors' => $e->errors(),
        ], 422);
    } catch (\Exception $e) {
        \Log::error('Failed to update password', [
            'user_id' => $userId, 
            'error' => $e->getMessage()
        ]);
        return response()->json([
            'message' => 'Failed to update password',
            'error' => $e->getMessage()
        ], 500);
    }
}
}