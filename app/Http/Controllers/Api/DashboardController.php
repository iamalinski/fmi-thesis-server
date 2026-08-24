<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Total revenue = sum of paid invoices, with month-over-month change.
     */
    public function totalRevenue(Request $request)
    {
        $user = $request->user();
        $prev = now()->subMonthNoOverflow();

        $value = (float) $user->invoices()->where('status', 'paid')->sum('amount');

        $thisMonth = (float) $user->invoices()
            ->where('status', 'paid')
            ->whereYear('date', now()->year)
            ->whereMonth('date', now()->month)
            ->sum('amount');

        $prevMonth = (float) $user->invoices()
            ->where('status', 'paid')
            ->whereYear('date', $prev->year)
            ->whereMonth('date', $prev->month)
            ->sum('amount');

        return response()->json([
            'value' => round($value, 2),
            'change' => $this->percentChange($thisMonth, $prevMonth),
        ]);
    }

    /**
     * Total number of invoices, with month-over-month change.
     */
    public function invoicesCount(Request $request)
    {
        $user = $request->user();
        $prev = now()->subMonthNoOverflow();

        $value = $user->invoices()->count();

        $thisMonth = $user->invoices()
            ->whereYear('date', now()->year)
            ->whereMonth('date', now()->month)
            ->count();

        $prevMonth = $user->invoices()
            ->whereYear('date', $prev->year)
            ->whereMonth('date', $prev->month)
            ->count();

        return response()->json([
            'value' => $value,
            'change' => $this->percentChange($thisMonth, $prevMonth),
        ]);
    }

    /**
     * Count of active articles, compared to the catalogue at the start of the month.
     */
    public function activeArticles(Request $request)
    {
        $user = $request->user();

        $value = $user->articles()->where('status', 'active')->count();

        $previous = $user->articles()
            ->where('status', 'active')
            ->where('created_at', '<', now()->startOfMonth())
            ->count();

        return response()->json([
            'value' => $value,
            'change' => $this->percentChange($value, $previous),
        ]);
    }

    /**
     * Top clients by total invoiced amount.
     */
    public function topClients(Request $request)
    {
        $user = $request->user();

        $clients = Client::query()
            ->select(
                'clients.id',
                'clients.name',
                DB::raw('SUM(invoices.amount) as total_spent'),
                DB::raw('COUNT(invoices.id) as invoices_count')
            )
            ->join('invoices', 'clients.id', '=', 'invoices.client_id')
            ->where('clients.user_id', $user->id)
            ->groupBy('clients.id', 'clients.name')
            ->orderByDesc('total_spent')
            ->limit(5)
            ->get();

        return response()->json(
            $clients->map(fn ($client) => [
                'id' => $client->id,
                'name' => $client->name,
                'totalSpent' => round((float) $client->total_spent, 2),
                'invoicesCount' => (int) $client->invoices_count,
                'avatarColor' => $this->colorForSeed($client->id),
            ])
        );
    }

    /**
     * The five most recent invoices.
     */
    public function recentInvoices(Request $request)
    {
        $invoices = $request->user()->invoices()
            ->with('client')
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        return response()->json(
            $invoices->map(fn ($invoice) => [
                'id' => $invoice->id,
                'number' => $invoice->invoice_number,
                'client' => $invoice->client->name ?? '—',
                'amount' => round((float) $invoice->amount, 2),
                'status' => $invoice->status,
            ])
        );
    }

    /**
     * Best-selling articles by quantity across invoice line items.
     */
    public function topProducts(Request $request)
    {
        $user = $request->user();

        $rows = DB::table('invoice_items')
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->join('articles', 'articles.id', '=', 'invoice_items.article_id')
            ->where('invoices.user_id', $user->id)
            ->groupBy('articles.id', 'articles.name')
            ->select(
                'articles.id',
                'articles.name',
                DB::raw('SUM(invoice_items.quantity) as sales')
            )
            ->orderByDesc('sales')
            ->limit(5)
            ->get();

        $max = (float) ($rows->max('sales') ?: 1);

        return response()->json(
            $rows->map(fn ($row) => [
                'id' => $row->id,
                'name' => $row->name,
                'sales' => (float) $row->sales,
                'progress' => (int) round(((float) $row->sales / $max) * 100),
            ])
        );
    }

    /**
     * Percentage change from a previous to a current value.
     */
    private function percentChange(float $current, float $previous): float
    {
        if ($previous <= 0) {
            return 0.0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    /**
     * Deterministic avatar colour derived from an id.
     */
    private function colorForSeed(int $seed): string
    {
        $colors = [
            '#3f51b5', '#f44336', '#4caf50', '#ff9800', '#9c27b0',
            '#2196f3', '#009688', '#ffb300', '#795548', '#607d8b',
        ];

        return $colors[$seed % count($colors)];
    }
}
