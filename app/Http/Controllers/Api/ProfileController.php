<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function updatePersonalInfo(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                Rule::unique('users')->ignore($user->id),
            ],
        ]);

        $user->update($request->only('first_name', 'last_name', 'email'));

        return response()->json([
            'message' => 'Personal information updated successfully',
            'user' => $user
        ]);
    }

    public function updateCompanyInfo(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'name' => 'required|string|max:255',
            'eik' => 'required|string|max:20',
            'vat_number' => 'nullable|string|max:20',
            'address' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'bank_name' => 'nullable|string|max:255',
            'bank_account' => 'nullable|string|max:50',
            'mol' => 'nullable|string|max:255',
        ]);

        $company = $user->company;

        if ($company) {
            $company->update($request->all());
        } else {
            $company = Company::create([
                'user_id' => $user->id,
                'name' => $request->name,
                'eik' => $request->eik,
                'vat_number' => $request->vat_number,
                'address' => $request->address,
                'phone' => $request->phone,
                'email' => $request->email,
                'bank_name' => $request->bank_name,
                'bank_account' => $request->bank_account,
                'mol' => $request->mol,
            ]);
        }

        return response()->json([
            'message' => 'Company information updated successfully',
            'company' => $company
        ]);
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'new_password' => 'required|min:8|confirmed',
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'Current password is incorrect'
            ], 422);
        }

        $user->update([
            'password' => Hash::make($request->new_password)
        ]);

        return response()->json([
            'message' => 'Password changed successfully'
        ]);
    }
}
