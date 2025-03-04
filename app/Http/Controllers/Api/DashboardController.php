<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        
        // Total revenue
        $totalRevenue = $user->sales()->sum('total');
        
        // Invoice count
        $invoiceCount = $user->invoices()->count();
        
        // Active articles count
        $activeArticlesCount = $user->articles()->where('status', 'active')->count();
        
        // Monthly revenue change
        $currentMonthSales = $user->sales()
            ->whereYear('date', now()->year)
            ->whereMonth('date', now()->month)
            ->sum('total');
            
        $previousMonthSales = $user->sales()
            ->whereYear('date', now()->subMonth()->year)
            ->whereMonth('date', now()->subMonth()->month)
            ->sum('total');
            
        $revenueChange = $previousMonthSales > 0 
            ? (($currentMonthSales - $previousMonthSales) / $previousMonthSales) * 100 
            : 0;
        
        // Top clients
        $topClients = Client::select('clients.*', DB::raw('SUM(sales.total) as total_spent'), DB::raw('COUNT(sales.id) as orders_count'))
            ->join('sales', 'clients.id', '=', 'sales.client_id')
            ->where('clients.user_id', $user->id)
            ->groupBy('clients.id')
            ->orderBy('total_spent', 'desc')
            ->limit(5)
            ->get();
        
        // Best sales
        $bestSales = $user->sales()
            ->with('client')
            ->orderBy('total', 'desc')
            ->limit(4)
            ->get()
            ->map(function ($sale) {
                return [
                    'id' => $sale->sale_number,
                    'client' => $sale->client->name,
                    'amount' => number_format($sale->total, 2),
                    'items' => $sale->items->count(),
                    'date' => $sale->date
                ];
            });
        
        // Latest sales
        $latestSales = $user->sales()
            ->with('client')
            ->orderBy('date', 'desc')
            ->limit(4)
            ->get()
            ->map(function ($sale) {
                return [
                    'id' => $sale->sale_number,
                    'client' => $sale->client->name,
                    'amount' => number_format($sale->total, 2),
                    'items' => $sale->items->count(),
                    'date' => $sale->date
                ];
            });
        
        // Recent invoices
        $recentInvoices = $user->invoices()
            ->with('client')
            ->orderBy('date', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($invoice) {
                return [
                    'id' => $invoice->invoice_number,
                    'client' => $invoice->client->name,
                    'amount' => number_format($invoice->amount, 2),
                    'status' => $invoice->status
                ];
            });
        
        // Top products (articles)
        $topProducts = Article::select(
                'articles.id', 
                'articles.name', 
                DB::raw('SUM(sale_items.quantity) as sales'),
                DB::raw('SUM(sale_items.quantity) / (SELECT SUM(quantity) FROM sale_items) * 100 as progress')
            )
            ->join('sale_items', 'articles.id', '=', 'sale_items.article_id')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('articles.user_id', $user->id)
            ->groupBy('articles.id', 'articles.name')
            ->orderBy('sales', 'desc')
            ->limit(5)
            ->get();
            
        return response()->json([
            'totalRevenue' => number_format($totalRevenue, 2),
            'invoiceCount' => $invoiceCount,
            'activeArticlesCount' => $activeArticlesCount,
            'revenueChange' => number_format($revenueChange, 1),
            'topClients' => $topClients->map(function ($client) {
                return [
                    'id' => $client->id,
                    'name' => $client->name,
                    'totalSpent' => number_format($client->total_spent, 2),
                    'ordersCount' => $client->orders_count,
                    'avatarColor' => $this->getRandomColor($client->id)
                ];
            }),
            'bestSales' => $bestSales,
            'latestSales' => $latestSales,
            'recentInvoices' => $recentInvoices,
            'topProducts' => $topProducts
        ]);
    }
    
    private function getRandomColor($seed) {
        // Array of nice colors for avatars
        $colors = [
            '#3f51b5', '#f44336', '#4caf50', '#ff9800', '#9c27b0',
            '#2196f3', '#009688', '#ffeb3b', '#795548', '#607d8b'
        ];
        
        // Use the seed to get a consistent color
        return $colors[$seed % count($colors)];
    }
}