<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Sale;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class ReportController extends Controller
{
    /**
     * GET /api/reports/sales
     * Query params:
     * - date_from (YYYY-MM-DD)
     * - date_to   (YYYY-MM-DD)
     * - group_by: daily|weekly|monthly (default: daily)
     * - user_id (opcional)
     *
     * Respuesta:
     * {
     *   "period": {...},
     *   "totals": { "sales_count", "items_sold", "total_amount" },
     *   "by_day":     [ { label, date_from, date_to, sales, items, total } ],
     *   "by_user":    [ { user_id, sales, items, total } ],
     *   "by_product": [ { product_id, name, quantity, revenue } ]
     * }
     */
    public function sales(Request $request)
    {
        $groupBy = $request->query('group_by', 'daily'); // daily|weekly|monthly
        $userId  = $request->query('user_id');

        // Rango por defecto últimos 7 días
        $dateFrom = $request->query('date_from') ? Carbon::parse($request->query('date_from'))->startOfDay() : now()->subDays(6)->startOfDay();
        $dateTo   = $request->query('date_to')   ? Carbon::parse($request->query('date_to'))->endOfDay()   : now()->endOfDay();

        $q = Sale::query()
            ->where('status', '!=', 'cancelled')
            ->whereBetween('created_at', [$dateFrom, $dateTo]);

        if ($userId) {
            $q->where('user_id', (string)$userId);
        }

        $sales = $q->get([
            '_id', 'user_id', 'products', 'total', 'payment_method', 'status', 'created_at', 'customer_info'
        ]);

        // Totales globales
        $salesCount = $sales->count();
        $itemsSold  = $sales->reduce(function ($acc, $s) {
            $c = 0;
            if (is_array($s->products)) {
                foreach ($s->products as $p) {
                    $c += (int)($p['quantity'] ?? 0);
                }
            }
            return $acc + $c;
        }, 0);
        $totalAmount = round($sales->sum('total'), 2);

        // Agrupación por periodo
        $buckets = $this->buildBuckets($dateFrom, $dateTo, $groupBy);
        foreach ($sales as $s) {
            $bucketKey = $this->bucketKeyForDate($s->created_at, $groupBy);
            if (!isset($buckets[$bucketKey])) continue;

            $buckets[$bucketKey]['sales'] += 1;

            $items = 0;
            if (is_array($s->products)) {
                foreach ($s->products as $p) $items += (int)($p['quantity'] ?? 0);
            }
            $buckets[$bucketKey]['items'] += $items;
            $buckets[$bucketKey]['total'] += (float)$s->total;
        }
        // Formato final
        $byDay = array_values(array_map(function ($b) {
            $b['total'] = round($b['total'], 2);
            return $b;
        }, $buckets));

        // Por usuario
        $byUserMap = [];
        foreach ($sales as $s) {
            $uid = (string)($s->user_id ?? '—');
            if (!isset($byUserMap[$uid])) {
                $byUserMap[$uid] = ['user_id' => $uid, 'sales' => 0, 'items' => 0, 'total' => 0.0];
            }
            $byUserMap[$uid]['sales'] += 1;
            if (is_array($s->products)) {
                foreach ($s->products as $p) {
                    $byUserMap[$uid]['items'] += (int)($p['quantity'] ?? 0);
                }
            }
            $byUserMap[$uid]['total'] += (float)$s->total;
        }
        $byUser = array_values(array_map(function ($r) {
            $r['total'] = round($r['total'], 2);
            return $r;
        }, $byUserMap));

        // Por producto
        $byProductMap = [];
        foreach ($sales as $s) {
            if (!is_array($s->products)) continue;
            foreach ($s->products as $p) {
                $pid   = (int)($p['product_id'] ?? 0);
                $name  = (string)($p['name'] ?? ('Producto #'.$pid));
                $qty   = (int)($p['quantity'] ?? 0);
                $price = (float)($p['unit_price'] ?? $p['price'] ?? 0);
                if (!isset($byProductMap[$pid])) {
                    $byProductMap[$pid] = [
                        'product_id' => $pid,
                        'name'       => $name,
                        'quantity'   => 0,
                        'revenue'    => 0.0,
                    ];
                }
                $byProductMap[$pid]['quantity'] += $qty;
                $byProductMap[$pid]['revenue']  += $qty * $price;
            }
        }
        $byProduct = array_values(array_map(function ($r) {
            $r['revenue'] = round($r['revenue'], 2);
            return $r;
        }, $byProductMap));

        return response()->json([
            'period' => [
                'from'     => $dateFrom->toDateString(),
                'to'       => $dateTo->toDateString(),
                'group_by' => $groupBy,
                'user_id'  => $userId,
            ],
            'totals' => [
                'sales_count' => $salesCount,
                'items_sold'  => $itemsSold,
                'total_amount'=> round($totalAmount, 2),
            ],
            'by_day'     => $byDay,
            'by_user'    => $byUser,
            'by_product' => $byProduct,
        ]);
    }

    private function buildBuckets(Carbon $from, Carbon $to, string $groupBy): array
    {
        $buckets = [];

        if ($groupBy === 'monthly') {
            $cursor = $from->copy()->startOfMonth();
            $end    = $to->copy()->endOfMonth();
            while ($cursor <= $end) {
                $key = $cursor->format('Y-m');
                $buckets[$key] = [
                    'label'     => $cursor->isoFormat('MMMM YYYY'),
                    'date_from' => $cursor->copy()->startOfMonth()->toDateString(),
                    'date_to'   => $cursor->copy()->endOfMonth()->toDateString(),
                    'sales'     => 0,
                    'items'     => 0,
                    'total'     => 0.0,
                ];
                $cursor->addMonthNoOverflow()->startOfMonth();
            }
            return $buckets;
        }

        if ($groupBy === 'weekly') {
            $cursor = $from->copy()->startOfWeek();
            $end    = $to->copy()->endOfWeek();
            while ($cursor <= $end) {
                $weekStart = $cursor->copy()->startOfWeek();
                $weekEnd   = $cursor->copy()->endOfWeek();
                $key = $weekStart->format('o-\WW'); // Año ISO + semana
                $buckets[$key] = [
                    'label'     => 'Semana ' . $weekStart->format('W') . ' (' . $weekStart->format('d M') . ' - ' . $weekEnd->format('d M') . ')',
                    'date_from' => $weekStart->toDateString(),
                    'date_to'   => $weekEnd->toDateString(),
                    'sales'     => 0,
                    'items'     => 0,
                    'total'     => 0.0,
                ];
                $cursor->addWeek();
            }
            return $buckets;
        }

        // daily
        foreach (CarbonPeriod::create($from->copy()->startOfDay(), '1 day', $to->copy()->endOfDay()) as $d) {
            $key = $d->format('Y-m-d');
            $buckets[$key] = [
                'label'     => $d->isoFormat('DD MMM'),
                'date_from' => $d->toDateString(),
                'date_to'   => $d->toDateString(),
                'sales'     => 0,
                'items'     => 0,
                'total'     => 0.0,
            ];
        }
        return $buckets;
    }

    private function bucketKeyForDate($date, string $groupBy): string
    {
        $c = Carbon::parse($date);
        return match ($groupBy) {
            'monthly' => $c->format('Y-m'),
            'weekly'  => $c->copy()->startOfWeek()->format('o-\WW'),
            default   => $c->format('Y-m-d'),
        };
    }
}
