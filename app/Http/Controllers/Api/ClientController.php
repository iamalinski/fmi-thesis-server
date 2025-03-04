<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    public function index(Request $request)
    {
        $clients = $request->user()->clients()
            ->when($request->filled('search'), function ($query) use ($request) {
                return $query->where('name', 'like', '%' . $request->search . '%')
                    ->orWhere('number', 'like', '%' . $request->search . '%')
                    ->orWhere('vat_number', 'like', '%' . $request->search . '%');
            })
            ->orderBy('name')
            ->paginate($request->per_page ?? 10);

        return response()->json($clients);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'number' => 'nullable|string|max:20',
            'vat_number' => 'nullable|string|max:20',
            'acc_person' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:255',
        ]);

        $client = $request->user()->clients()->create($request->all());

        return response()->json([
            'message' => 'Client created successfully',
            'client' => $client
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $client = $request->user()->clients()->findOrFail($id);

        return response()->json($client);
    }

    public function update(Request $request, $id)
    {
        $client = $request->user()->clients()->findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'number' => 'nullable|string|max:20',
            'vat_number' => 'nullable|string|max:20',
            'acc_person' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:255',
        ]);

        $client->update($request->all());

        return response()->json([
            'message' => 'Client updated successfully',
            'client' => $client
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $client = $request->user()->clients()->findOrFail($id);

        // Check if client has related sales or invoices
        if ($client->sales()->count() > 0 || $client->invoices()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete client with related sales or invoices'
            ], 422);
        }

        $client->delete();

        return response()->json([
            'message' => 'Client deleted successfully'
        ]);
    }
}
