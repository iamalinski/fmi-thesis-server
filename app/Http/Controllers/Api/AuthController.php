<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    // Shared validation rules
    private function getValidationRules($includeCompany = true)
    {
        $rules = [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'password_confirmation' => 'required|string|min:8',
        ];

        // Add company rules if needed
        if ($includeCompany) {
            $rules = array_merge($rules, [
                'company.name' => 'required|string|max:255',
                'company.eik' => 'required|string|max:20',
                'company.vat_number' => 'nullable|string|max:20',
                'company.address' => 'required|string|max:255',
                'company.phone' => 'nullable|string|max:20',
                'company.email' => 'nullable|email|max:255',
                'company.bank_name' => 'nullable|string|max:255',
                'company.bank_account' => 'nullable|string|max:50',
                'company.mol' => 'required|string|max:255',
            ]);
        }

        return $rules;
    }

    public function registerCheckUserData(Request $request)
    {
        // Use only the user validation rules
        $request->validate($this->getValidationRules(false));

        // Return success if validation passes
        return response()->json(['success' => true, 'message' => 'Validation successful'], 201);
    }

    public function register(Request $request)
    {
        // Validate using all rules including company
        $request->validate($this->getValidationRules(true));

        // Create user
        $user = User::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        // Create company if data is provided
        if ($request->has('company') && is_array($request->company) && !empty($request->company['name'])) {
            // Prepare company data
            $companyData = $request->company;
            $companyData['user_id'] = $user->id;

            Company::create($companyData);
        }

        // Generate token
        $token = $user->createToken('auth-token')->plainTextToken;

        // Return response with user (including company) and token
        return response()->json([
            'user' => $user->load('company'),
            'token' => $token
        ], 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (!Auth::attempt($request->only('email', 'password'))) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $user = User::where('email', $request->email)->first();
        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'user' => $user->load('company'),
            'token' => $token
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Successfully logged out']);
    }

    public function user(Request $request)
    {
        return response()->json($request->user()->load('company'));
    }
}
