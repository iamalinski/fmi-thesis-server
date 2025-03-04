<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SaleController extends Controller
{
    public function index(Request $request)
    {
        $sales = $request->user()->sales()
            ->with('client')
            ->when($request->filled('search'), function ($query) use ($request) {
                return $query->where('sale_number', 'like', '%' . $request->search . '%');
            })
            ->orderBy('date', 'desc')
            ->paginate($request->per_page ?? 10);

        return response()->json($sales);
    }

    public function store(Request $request)
    {
        $request->validate([
            'client_id' => 'required|exists:clients,id',
            'date' => 'required|date',
            'subtotal' => 'required|numeric|min:0',
            'discount' => 'required|numeric|min:0',
            'total' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.article_id' => 'required|exists:articles,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.total' => 'required|numeric|min:0',
        ]);

        // Generate sale number
        $latestSale = Sale::latest()->first();
        $saleNumber = $latestSale
            ? 'SALE-' . str_pad((intval(substr($latestSale->sale_number, 5)) + 1), 4, '0', STR_PAD_LEFT)
            : 'SALE-0001';

        DB::beginTransaction();

        try {
            // Create the sale
            $sale = $request->user()->sales()->create([
                'client_id' => $request->client_id,
                'sale_number' => $saleNumber,
                'date' => $request->date,
                'subtotal' => $request->subtotal,
                'discount' => $request->discount,
                'total' => $request->total,
                'notes' => $request->notes,
            ]);

            // Create sale items
            foreach ($request->items as $item) {
                SaleItem::create([
                    'sale_id' => $sale->id,
                    'article_id' => $item['article_id'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'total' => $item['total'],
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Sale created successfully',
                'sale' => $sale->load('items.article', 'client')
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to create sale',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show(Request $request, $id)
    {
        $sale = $request->user()->sales()->with('items.article', 'client')->findOrFail($id);

        return response()->json($sale);
    }

    public function update(Request $request, $id)
    {
        $sale = $request->user()->sales()->findOrFail($id);

        $request->validate([
            'client_id' => 'required|exists:clients,id',
            'date' => 'required|date',
            'subtotal' => 'required|numeric|min:0',
            'discount' => 'required|numeric|min:0',
            'total' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.id' => 'nullable|exists:sale_items,id',
            'items.*.article_id' => 'required|exists:articles,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.total' => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();

        try {
            // Update sale
            $sale->update([
                'client_id' => $request->client_id,
                'date' => $request->date,
                'subtotal' => $request->subtotal,
                'discount' => $request->discount,
                'total' => $request->total,
                'notes' => $request->notes,
            ]);

            // Get existing item IDs
            $existingItemIds = $sale->items->pluck('id')->toArray();
            $updatedItemIds = collect($request->items)->pluck('id')->filter()->toArray();

            // Delete removed items
            $itemsToDelete = array_diff($existingItemIds, $updatedItemIds);
            SaleItem::whereIn('id', $itemsToDelete)->delete();

            // Update or create items
            foreach ($request->items as $item) {
                if (isset($item['id'])) {
                    // Update existing item
                    SaleItem::where('id', $item['id'])->update([
                        'article_id' => $item['article_id'],
                        'quantity' => $item['quantity'],
                        'price' => $item['price'],
                        'total' => $item['total'],
                    ]);
                } else {
                    // Create new item
                    SaleItem::create([
                        'sale_id' => $sale->id,
                        'article_id' => $item['article_id'],
                        'quantity' => $item['quantity'],
                        'price' => $item['price'],
                        'total' => $item['total'],
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Sale updated successfully',
                'sale' => $sale->fresh()->load('items.article', 'client')
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to update sale',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        $sale = $request->user()->sales()->findOrFail($id);

        // Check if sale has related invoice
        if ($sale->invoice) {
            return response()->json([
                'message' => 'Cannot delete sale with related invoice'
            ], 422);
        }

        DB::beginTransaction();

        try {
            // Delete sale items
            $sale->items()->delete();

            // Delete sale
            $sale->delete();

            DB::commit();

            return response()->json([
                'message' => 'Sale deleted successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to delete sale',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
