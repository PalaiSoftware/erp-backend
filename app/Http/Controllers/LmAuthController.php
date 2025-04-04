<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use App\Models\Company;
use Illuminate\Support\Facades\Auth; 
use App\Models\UidCid;
class LmAuthController extends Controller
{
public function Lmnewuser(Request $request)
{
    $user = Auth::user();
    
    // Check if user has permission to create new users based on their role
    if (!in_array($user->rid, [1, 2, 3])) {
        return response()->json([
            'message' => 'You are not allowed to create a lm new user'
        ], 403);
    }

    $validator = Validator::make($request->all(), [
        'name' => 'required|string|max:255',
        'email' => 'required|string|email|max:255|unique:users',
        'mobile' => 'required|string|max:255|unique:users',
        'country' => 'required|string|max:255',
        'password' => 'required|string|min:6',
        'rid' => 'required|integer|between:2,4',
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
    $allowedRoles = range($user->rid + 1, 4);

    // Check if the requested rid is allowed for this user
    if (!in_array($request->rid, $allowedRoles)) {
        return response()->json([
            'message' => 'You are not allowed to create a lm user with this role ID'
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

    return response()->json(['message' => ' Lm User registered successfully'], 201);
}

public function getLmUsersByRole(Request $request)
{
    // Get the authenticated user
    $currentUser = Auth::user();

    if (!$currentUser) {
        return response()->json(['message' => 'Unauthorized'], 401);
    }

    // Define the role hierarchy logic
    $currentRid = $currentUser->rid;

    
    if (!in_array($currentRid, [1, 2, 3])) {
        return response()->json([
            'message' => 'You are not authorized to view lm users with lower roles',
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
        'message' => 'Lm Users retrieved successfully (both blocked and unblocked)',
        'users' => $users
    ], 200);
}
public function LmUserBlockUnblock(Request $request)
{
    // Get the authenticated user
    $currentUser = Auth::user();

    if (!$currentUser) {
        return response()->json(['message' => 'Unauthorized'], 401);
    }

    if (!in_array($currentUser->rid, [1, 2,3])) {
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

public function LmUserPromoteDemote(Request $request)
{
    // Get the authenticated user
    $currentUser = Auth::user();

    if (!$currentUser) {
        return response()->json(['message' => 'Unauthorized'], 401);
    }

    // Check if current user's rid is 5, 6, 7, or 8 (only these can promote/demote)
    if (!in_array($currentUser->rid, [1, 2,])) {
        return response()->json([
            'message' => 'You are not authorized to promote or demote lm users'
        ], 403);
    }

    $validator = Validator::make($request->all(), [
        'user_id' => 'required|integer|exists:users,id',
        'rid' => 'required|integer|min:1|max:4'
    ], [
        'rid.max' => 'Cannot demote beyond the lowest role (rid=4).'
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
            'message' => 'You cannot modify lm users with equal or higher roles'
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
