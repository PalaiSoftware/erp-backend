<?php

namespace App\Http\Controllers;

use App\Models\PaymentMode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $request->headers->set('Accept', 'application/json');

        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        if (!in_array($user->rid, [1, 2, 3, 4])) {
            return response()->json(['message' => 'Unauthorized to view payment modes'], 403);
        }

        Log::info('Fetching all payment modes', ['user_id' => $user->id]);

        $paymentModes = PaymentMode::all();

        return response()->json([
            'message' => 'Payment modes retrieved successfully',
            'payment_modes' => $paymentModes,
        ], 200);
    }

}
