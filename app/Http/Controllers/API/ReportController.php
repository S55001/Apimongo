<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    /** GET /api/reports/sales?from=YYYY-MM-DD&to=YYYY-MM-DD&group=day|week|month&user_id= */
    public function sales(Request $request)
    {
        $request->validate([
            'from'  => 'required|date',
            'to'    => 'required|date|after_or_equal:from',
            'group' => 'nullable|in:day,week,month',
            'user_id' => 'nullable|string'
        ]);

        $group = $request->query('group', 'day');

        $q = Sale::query()
            ->whereBetween('created_at', [$request->from, $request->to])
            ->where('status', 'completed');

        if ($uid = $request->user_id) {
            $q->where('user_id', (string)$uid);
        }

        $sales = $q->get(['total', 'created_at', 'products']);

        $fmt = match ($group) {
            'week'  => 'o-\WW',
            'month' => 'Y-m',
            default => 'Y-m-d',
        };

        $agg = [];
        foreach ($sales as $s) {
            $k = $s->created_at->format($fmt);
            if (!isset($agg[$k])) {
                $agg[$k] = ['count' => 0, 'amount' => 0.0, 'items' => 0];
            }
            $agg[$k]['count']  += 1;
            $agg[$k]['amount'] += (float)$s->total;
            $agg[$k]['items']  += collect($s->products)->sum('quantity');
        }

        $series = collect($agg)->map(fn($v, $k) => ['period' => $k] + $v)
                               ->sortBy('period')
                               ->values();

        return response()->json([
            'from'   => $request->from,
            'to'     => $request->to,
            'group'  => $group,
            'series' => $series,
            'total'  => [
                'sales' => (int)$series->sum('count'),
                'amount'=> (float)$series->sum('amount'),
                'items' => (int)$series->sum('items'),
            ],
        ]);
    }
}
