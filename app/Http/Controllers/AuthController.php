<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use App\Models\Company;
use Illuminate\Support\Facades\Auth; 
use App\Models\UidCid;
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
        // Debug the operation
        $uid=$user->id;
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
    
    // Check if user has permission to create new users based on their role
    if (!in_array($user->rid, [1, 2, 3, 4])) {
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
        'rid' => 'required|integer|between:1,6',
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
    $allowedRoles = [];
    switch ($user->rid) {
        case 1:
            $allowedRoles = [2, 3, 4, 5, 6];
            break;
        case 2:
            $allowedRoles = [3, 4, 5,6];
            break;
        case 3:
            $allowedRoles = [4, 5,6];
            break;
        case 4:
            $allowedRoles = [5,6];
            break;
        case 5:
            $allowedRoles = [];
            break;
    }

    // Check if the requested rid is allowed for this user
    if (!in_array($request->rid, $allowedRoles)) {
        return response()->json([
            'message' => 'You are not allowed to create a user with this role ID'
        ], 403);
    }

    // Create the new user with `cid`
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

    $uid = $user->id;
    $previousLogin = $user->updated_at;

    // $user->touch(); // Update last login time

    // Get initial CID (prioritize uid_cid over user's default cid)
    $uidCidEntry = UidCid::where('uid', $uid)->first();
    $cid = $uidCidEntry?->cid ?? $user->cid;

    // Check company status
    $company = Company::find($cid);
    if (!$company) {
        return response()->json(['message' => 'Company not found'], 404);
    }

    // Main logic: Check if initial company is blocked

    if ($company->blocked) {
        // Get the first unblocked company associated with the user
        $availableCompany = Company::where('uid', $uid)
            ->where('blocked', 0)
            ->first();
    
        if (!$availableCompany) {
            return response()->json(['message' => 'All associated companies are blocked'], 403);
        }
    
        // Update uid_cid with the unblocked company
        $uidCidEntry = UidCid::firstOrNew(['uid' => $uid]);
        $uidCidEntry->cid = $availableCompany->id;
        $uidCidEntry->save();
    
        // Update company reference
        $cid = $availableCompany->id;
        $company = $availableCompany;
    }
    // Generate token
    $user->touch();

    $token = $user->createToken('auth_token')->plainTextToken;

    return response()->json([
        'message' => 'Login successful',
        'user' => $user,
        'token' => $token,
        'company' => $company,
        'previous_login' => $user->updated_at,
    ]);
}

// Add the new function here for get UsersByRole
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

    // Fetch users with rid greater than the current user's rid, within the same company
    // No filter on 'blocked' to include both blocked and unblocked users
    $users = User::where('cid', $currentUser->cid) // Same company
                 ->where('rid', '>', $currentRid) // Only users with lower roles (higher rid numbers)
                 ->select('id', 'name', 'email', 'mobile', 'country', 'rid', 'blocked') // Select relevant fields
                 ->get();

    if ($users->isEmpty()) {
        return response()->json([
            'message' => 'No users found with lower roles in your company',
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

    // Check if current user's rid is 1, 2, 3, or 4
    if (!in_array($currentUser->rid, [1, 2, 3, 4])) {
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

    // Check if current user's rid is 1, 2, 3, or 4 (only these can promote/demote)
    if (!in_array($currentUser->rid, [1, 2, 3, 4])) {
        return response()->json([
            'message' => 'You are not authorized to promote or demote users'
        ], 403);
    }

    // Validate request
    $validator = Validator::make($request->all(), [
        'user_id' => 'required|integer|exists:users,id',
        'rid' => 'required|integer|min:1' // New rid must be a positive integer
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