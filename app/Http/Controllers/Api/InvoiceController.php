<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $invoices = $request->user()->invoices()
            ->with('client')
            ->when($request->filled('search'), function ($query) use ($request) {
                return $query->where('invoice_number', 'like', '%' . $request->search . '%');
            })
            ->when($request->filled('status') && $request->status !== 'all', function ($query) use ($request) {
                return $query->where('status', $request->status);
            })
            ->when($request->filled('client'), function ($query) use ($request) {
                return $query->where('client_id', $request->client);
            })
            ->orderBy('date', 'desc')
            ->paginate($request->per_page ?? 10);

        return response()->json($invoices);
    }

    public function store(Request $request)
    {
        $request->validate([
            'client_id' => 'required|exists:clients,id',
            'sale_id' => 'nullable|exists:sales,id',
            'date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:date',
            'amount' => 'required|numeric|min:0',
            'status' => 'required|in:paid,pending,overdue',
            'notes' => 'nullable|string',
        ]);

        // Generate invoice number
        $latestInvoice = Invoice::latest()->first();
        $invoiceNumber = $latestInvoice
            ? 'INV-' . date('Y') . str_pad((intval(substr($latestInvoice->invoice_number, 8)) + 1), 4, '0', STR_PAD_LEFT)
            : 'INV-' . date('Y') . '0001';

        $invoice = $request->user()->invoices()->create([
            'client_id' => $request->client_id,
            'sale_id' => $request->sale_id,
            'invoice_number' => $invoiceNumber,
            'date' => $request->date,
            'due_date' => $request->due_date,
            'amount' => $request->amount,
            'status' => $request->status,
            'notes' => $request->notes,
        ]);

        return response()->json([
            'message' => 'Invoice created successfully',
            'invoice' => $invoice->load('client', 'sale.items.article')
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $invoice = $request->user()->invoices()->with('client', 'sale.items.article')->findOrFail($id);

        return response()->json($invoice);
    }

    public function update(Request $request, $id)
    {
        $invoice = $request->user()->invoices()->findOrFail($id);

        $request->validate([
            'client_id' => 'required|exists:clients,id',
            'sale_id' => 'nullable|exists:sales,id',
            'date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:date',
            'amount' => 'required|numeric|min:0',
            'status' => 'required|in:paid,pending,overdue',
            'notes' => 'nullable|string',
        ]);

        $invoice->update($request->all());

        return response()->json([
            'message' => 'Invoice updated successfully',
            'invoice' => $invoice->fresh()->load('client', 'sale.items.article')
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $invoice = $request->user()->invoices()->findOrFail($id);

        $invoice->delete();

        return response()->json([
            'message' => 'Invoice deleted successfully'
        ]);
    }
}
