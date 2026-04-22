<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use App\Models\OtpCode;
use Carbon\Carbon;

class AuthController extends Controller
{
    // LOGIN  (email | mobile | username  +  password)
    public function login(Request $request)
    {
        $request->validate([
            'identifier' => 'required|string',
            'password'   => 'required|string',
        ]);

        $user = $this->findUser(trim($request->input('identifier')));

        if (!$user || !Hash::check($request->input('password'), $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $user->tokens()->where('name', 'mobile')->delete();
        $token = $user->createToken('mobile')->plainTextToken;

        return response()->json([
            'token'  => $token,
            'userId' => $user->id,
            'user'   => $this->userPayload($user),
        ]);
    }

    // REGISTER
    public function register(Request $request)
    {
        $v = Validator::make($request->all(), [
            'name'     => 'required|string|max:255',
            'username' => 'required|string|max:64|unique:users,username|alpha_dash',
            'email'    => 'nullable|email|max:255',
            'mobile'   => 'nullable|string|max:20',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($v->fails()) {
            return response()->json(['errors' => $v->errors()], 422);
        }

        if (empty($request->email) && empty($request->mobile)) {
            return response()->json([
                'errors' => ['contact' => ['Please provide at least an email address or a mobile number.']],
            ], 422);
        }

        if (!empty($request->email) && User::where('email', $request->email)->exists()) {
            return response()->json(['errors' => ['email' => ['This email is already registered.']]], 422);
        }
        if (!empty($request->mobile) && User::where('mobile', $request->mobile)->exists()) {
            return response()->json(['errors' => ['mobile' => ['This mobile number is already registered.']]], 422);
        }

        $user = User::create([
            'name'     => $request->name,
            'username' => $request->username,
            'email'    => $request->email ?: null,
            'mobile'   => $request->mobile ?: null,
            'password' => Hash::make($request->password),
        ]);

        $token = $user->createToken('mobile')->plainTextToken;

        return response()->json([
            'token'  => $token,
            'userId' => $user->id,
            'user'   => $this->userPayload($user),
        ], 201);
    }

    // GET CURRENT USER
    public function user(Request $request)
    {
        return response()->json($this->userPayload($request->user()));
    }

    // UPDATE PROFILE
    public function updateProfile(Request $request)
    {
        $u = $request->user();

        $v = Validator::make($request->all(), [
            'name'     => 'sometimes|string|max:255',
            'username' => "sometimes|string|max:64|alpha_dash|unique:users,username,{$u->id}",
            'email'    => "sometimes|nullable|email|max:255|unique:users,email,{$u->id}",
            'mobile'   => "sometimes|nullable|string|max:20|unique:users,mobile,{$u->id}",
        ]);

        if ($v->fails()) {
            return response()->json(['errors' => $v->errors()], 422);
        }

        $fields = collect($request->only(['name', 'username', 'email', 'mobile']))
            ->reject(fn($v) => is_null($v))
            ->toArray();

        $u->fill($fields)->save();

        return response()->json(['user' => $this->userPayload($u)]);
    }

    // CHANGE PASSWORD
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'password'         => 'required|string|min:8|confirmed',
        ]);

        $u = $request->user();

        if (!Hash::check($request->current_password, $u->password)) {
            return response()->json(['message' => 'Current password is incorrect'], 422);
        }

        $u->password = Hash::make($request->password);
        $u->save();

        return response()->json(['message' => 'Password updated successfully']);
    }

    // OTP: SEND
    public function sendOtp(Request $request)
    {
        $request->validate(['identifier' => 'required|string']);

        $id   = trim($request->input('identifier'));
        $user = $this->findUser($id);

        if ($user) {
            OtpCode::where('identifier', $id)->where('used', false)->update(['used' => true]);

            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            OtpCode::create([
                'identifier' => $id,
                'type'       => str_contains($id, '@') ? 'email' : 'sms',
                'code'       => $code,
                'expires_at' => Carbon::now()->addMinutes(10),
            ]);

            // TODO: Integrate SMS gateway (MSG91 / Twilio) here
            \Log::info("[OTP] identifier={$id} code={$code}");
        }

        // Always return generic to prevent user enumeration
        return response()->json(['message' => 'If an account exists, an OTP has been sent.']);
    }

    // OTP: VERIFY + LOGIN
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'identifier' => 'required|string',
            'code'       => 'required|string|size:6',
        ]);

        $id  = trim($request->input('identifier'));
        $otp = OtpCode::where('identifier', $id)
            ->where('code', trim($request->input('code')))
            ->where('used', false)
            ->where('expires_at', '>', Carbon::now())
            ->latest()
            ->first();

        if (!$otp) {
            return response()->json(['message' => 'Invalid or expired OTP'], 401);
        }

        $otp->update(['used' => true]);

        $user = $this->findUser($id);
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $user->tokens()->where('name', 'mobile')->delete();
        $token = $user->createToken('mobile')->plainTextToken;

        return response()->json([
            'token'  => $token,
            'userId' => $user->id,
            'user'   => $this->userPayload($user),
        ]);
    }

    // SUBSCRIPTION / PURCHASE STATUS
    public function subscriptionStatus(Request $request)
    {
        $u = $request->user();

        $subscriptions = [];
        $purchases     = [];

        if (method_exists($u, 'subscriptions')) {
            $subscriptions = $u->subscriptions()
                ->orderByDesc('created_at')->get()
                ->map(fn($s) => [
                    'id'         => $s->id,
                    'plan'       => $s->plan_name ?? $s->plan_id ?? 'Subscription',
                    'status'     => $s->status,
                    'started_at' => $s->created_at?->toDateString(),
                    'expires_at' => $s->expires_at?->toDateString(),
                ])->toArray();
        }

        $rel = null;
        if (method_exists($u, 'purchases')) $rel = $u->purchases();
        elseif (method_exists($u, 'orders'))   $rel = $u->orders();

        if ($rel) {
            $purchases = $rel->orderByDesc('created_at')->get()
                ->map(fn($p) => [
                    'id'           => $p->id,
                    'item'         => $p->product_name ?? $p->item_name ?? 'Product',
                    'amount'       => $p->amount ?? $p->total ?? 0,
                    'currency'     => $p->currency ?? 'USD',
                    'status'       => $p->status ?? 'completed',
                    'purchased_at' => $p->created_at?->toDateString(),
                ])->toArray();
        }

        return response()->json([
            'user'          => $this->userPayload($u),
            'subscriptions' => $subscriptions,
            'purchases'     => $purchases,
        ]);
    }

    // HELPERS
    private function findUser(string $identifier): ?User
    {
        if (str_contains($identifier, '@')) {
            return User::where('email', $identifier)->first();
        }
        if (preg_match('/^\+?[0-9]{7,15}$/', $identifier)) {
            return User::where('mobile', $identifier)->first();
        }
        return User::where('username', $identifier)->orWhere('name', $identifier)->first();
    }

    private function userPayload(User $u): array
    {
        return [
            'id'       => $u->id,
            'name'     => $u->name,
            'username' => $u->username,
            'email'    => $u->email,
            'mobile'   => $u->mobile,
            'role'     => $u->role,
        ];
    }
}
