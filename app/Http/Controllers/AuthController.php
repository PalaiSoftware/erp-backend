<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Client;
use Illuminate\Support\Facades\Auth; 
class AuthController extends Controller
{

public function register(Request $request)
{
    $validator = Validator::make($request->all(), [
        'name' => 'required|string|max:255',
        'email' => 'required|string|email|max:255|unique:users',
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

    DB::beginTransaction();

    try {
        // Create Client first
        $client = Client::create([
            'name' => $request->client_name,
            'address' => $request->client_address,
            'phone' => $request->client_phone,
            'gst_no' => $request->gst_no,
            'pan' => $request->pan,
            'blocked' => 0,
        ]);

        if (!$client) {
            throw new \Exception("Client creation failed.");
        }

        // Create User with cid set to client’s ID
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'mobile' => $request->mobile,
            'country' => $request->country,
            'password' => Hash::make($request->password),
            'rid' => $request->rid,
            'blocked' => 0,
            'cid' => $client->id, // Set cid during creation
        ]);

        if (!$user) {
            throw new \Exception("User creation failed.");
        }
        DB::commit();

        return response()->json([
            'message' => 'User and client registered successfully',
            'user' => $user,
            'client' => $client
        ], 201);

    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'message' => 'Registration failed',
            'error' => $e->getMessage()
        ], 500);
    }
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
    if (!in_array($user->rid, [1, 2, 3])) {
        return response()->json([
            'message' => 'You are not allowed to create a new user for your company'
        ], 403);
    }

    $validator = Validator::make($request->all(), [
        'name' => 'required|string|max:255',
        'email' => 'required|string|email|max:255|unique:users',
        'mobile' => 'required|string|max:255',
        'country' => 'required|string|max:255',
        'password' => 'required|string|min:6',
        'rid' => 'required|integer|between:2,5',
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

    // Check if current user's rid is 1, 2, or 3
    if (!in_array($currentUser->rid, [1, 2, 3])) {
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

    // Check if current user's rid is 1, 2, or 3 (only these can promote/demote)
    if (!in_array($currentUser->rid, [1, 2, 3])) {
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

    // Prevent promoting to equal or higher roles than current user (except for rid=1)
    if ($currentUser->rid > 1 && $request->rid <= $currentUser->rid) {
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
}