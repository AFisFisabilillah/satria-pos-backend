<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $period = $request->query('period', 'today');
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $now = Carbon::now();
        $start = null;
        $end = null;

        switch ($period) {
            case 'week':
                $start = $now->copy()->subDays(6)->startOfDay();
                $end = $now->copy()->endOfDay();
                break;
            case 'month':
                $start = $now->copy()->subDays(29)->startOfDay();
                $end = $now->copy()->endOfDay();
                break;
            case 'year':
                $start = $now->copy()->subDays(364)->startOfDay();
                $end = $now->copy()->endOfDay();
                break;
            case 'custom':
                if ($startDate) {
                    $start = Carbon::parse($startDate)->startOfDay();
                }
                if ($endDate) {
                    $end = Carbon::parse($endDate)->endOfDay();
                }
                break;
            case 'all':
                break;
            case 'today':
            default:
                $start = $now->copy()->startOfDay();
                $end = $now->copy()->endOfDay();
                break;
        }

        $applyDateFilter = function ($query, $column = 'created_at') use ($start, $end) {
            if ($start) {
                $query->where($column, '>=', $start);
            }
            if ($end) {
                $query->where($column, '<=', $end);
            }
            return $query;
        };

        // 1. Sales & Laba Kotor
        $totalTransactionsQuery = DB::table("transactions");
        $applyDateFilter($totalTransactionsQuery, 'created_at');
        $totalTransactions = $totalTransactionsQuery->count();

        $totalSalesQuery = DB::table("transactions");
        $applyDateFilter($totalSalesQuery, 'created_at');
        $totalSales = $totalSalesQuery->sum("total_price");

        $totalCogsQuery = DB::table("transaction_item_cogs")
            ->join("transaction_items", "transaction_item_cogs.transaction_item_id", "=", "transaction_items.id")
            ->join("transactions", "transaction_items.transaction_id", "=", "transactions.id");
        $applyDateFilter($totalCogsQuery, 'transactions.created_at');
        $totalCogs = $totalCogsQuery->sum(DB::raw("transaction_item_cogs.quantity_deducted * transaction_item_cogs.purchase_price"));

        $labaKotor = $totalSales - $totalCogs;

        // 2. Top Products
        $topProductsQuery = DB::table("transaction_items")
            ->join("transactions", "transaction_items.transaction_id", "=", "transactions.id")
            ->join("products", "transaction_items.product_id", "=", "products.id");
        $applyDateFilter($topProductsQuery, 'transactions.created_at');
        $topProducts = $topProductsQuery
            ->select("products.id", "products.name", DB::raw("SUM(transaction_items.quantity) as total_sold"))
            ->groupBy("products.id", "products.name")
            ->orderByDesc("total_sold")
            ->limit(10)
            ->get();

        // 3. Members Stats
        $todayDate = Carbon::today()->toDateString();
        $memberStats = DB::table("members")
            ->selectRaw("
                COUNT(*) as total_members,
                COUNT(CASE WHEN active = 1 THEN 1 END) as active_members,
                COUNT(CASE WHEN DATE(created_at) = ? THEN 1 END) as new_members_today
            ", [$todayDate])
            ->first();

        $newMembersPeriodQuery = DB::table("members");
        $applyDateFilter($newMembersPeriodQuery, 'created_at');
        $newMembersPeriod = $newMembersPeriodQuery->count();

        $topMembers = DB::table("members")
            ->orderByDesc("total_spent")
            ->limit(10)
            ->get(["id", "name", "member_code", "total_spent"]);

        // 4. Inventory Stats (Snapshot Realtime)
        $inventoryStats = DB::table("stock_supply_items")
            ->selectRaw("
                SUM(quantity_remaining) as total_stock,
                SUM(quantity_remaining * purchase_price) as total_inventory_value
            ")
            ->first();

        $totalProduct = DB::table("products")->count("id");

        // 5. Supplier Returns
        $supplierReturnQuery = DB::table("supplier_return_items")
            ->join("supplier_returns", "supplier_return_items.supplier_return_id", "=", "supplier_returns.id")
            ->join("stock_supply_items", "supplier_return_items.stock_supply_item_id", "=", "stock_supply_items.id");
        $applyDateFilter($supplierReturnQuery, 'supplier_returns.created_at');
        $supplierReturnStats = $supplierReturnQuery
            ->selectRaw("
                SUM(supplier_return_items.quantity) as total_return_stock,
                SUM(supplier_return_items.quantity * stock_supply_items.purchase_price) as total_return_value
            ")
            ->first();

        // 6. Stock Adjustment Loss
        $adjustmentLossQuery = DB::table("stock_adjustments")
            ->join("stock_supply_items", "stock_adjustments.stock_supply_item_id", "=", "stock_supply_items.id");
        $applyDateFilter($adjustmentLossQuery, 'stock_adjustments.created_at');
        $adjustmentLoss = $adjustmentLossQuery->sum(DB::raw("stock_adjustments.quantity * stock_supply_items.purchase_price"));

        // 7. Voucher Stats
        $voucherStats = DB::table("vouchers")
            ->selectRaw("
                COUNT(*) as total_vouchers,
                COUNT(CASE WHEN active = 1 AND (expired_at IS NULL OR expired_at >= ?) AND quota > 0 THEN 1 END) as active_vouchers
            ", [$todayDate])
            ->first();

        $voucherUsageQuery = DB::table("transaction_voucher")
            ->join("transactions", "transaction_voucher.transaction_id", "=", "transactions.id");
        $applyDateFilter($voucherUsageQuery, 'transactions.created_at');
        $voucherUsagePeriod = $voucherUsageQuery
            ->selectRaw("
                COUNT(transaction_voucher.id) as total_used,
                COALESCE(SUM(transaction_voucher.discount_applied), 0) as total_discount
            ")
            ->first();

       return  response()->json([
            "filter" => [
                "period" => $period,
                "start_date" => $start ? $start->toDateTimeString() : null,
                "end_date" => $end ? $end->toDateTimeString() : null,
            ],
            "sales" => [
                "total_transactions" => $totalTransactions,
                "total_sales" => $totalSales,
                "total_cogs" => $totalCogs,
                "laba_kotor" => $labaKotor,
            ],
            "total_product" => $totalProduct,
            "top_products" => $topProducts,
            "top_members" => $topMembers,
            "member_stats" => [
                "total_members" => $memberStats->total_members,
                "active_members" => $memberStats->active_members,
                "new_members_today" => $memberStats->new_members_today,
                "new_members_period" => $newMembersPeriod,
            ],
            "inventory_stats" => $inventoryStats,
            "supplier_return_stats" => $supplierReturnStats,
            "adjustment_loss" => $adjustmentLoss,
            "voucher_stats" => $voucherStats,
            "voucher_usage_period" => $voucherUsagePeriod,
        ]);
    }
}
