<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SaleController extends Controller
{
    /** GET /api/sales?user_id=&date_from=&date_to=&status= */
    public function index(Request $request)
    {
        $q = Sale::query();

        // admin ve todas; vendedor puede que vea propias (puedes reforzarlo aquí si quieres)
        if ($userId = $request->query('user_id')) {
            $q->where('user_id', (string)$userId);
        }

        if ($status = $request->query('status')) {
            $q->where('status', $status); // completed|cancelled
        }

        if ($request->filled(['date_from','date_to'])) {
            $q->whereBetween('created_at', [
                $request->query('date_from'),
                $request->query('date_to'),
            ]);
        }

        return response()->json(
            $q->orderBy('created_at','desc')->paginate(15)
        );
    }

    /** POST /api/sales */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'products' => 'required|array|min:1',
            'products.*.product_id' => 'required|integer|min:1',
            'products.*.name' => 'required|string|max:100',
            'products.*.unit_price' => 'required|numeric|min:0',
            'products.*.quantity' => 'required|integer|min:1',
            'payment_method' => 'required|in:efectivo,tarjeta,transferencia',
            'customer_info' => 'nullable|array',
        ]);

        $user = $request->user();
        if (!$user || !in_array($user->role, ['vendedor','admin'])) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $total = collect($validated['products'])
            ->sum(fn($p) => $p['unit_price'] * $p['quantity']);

        DB::connection('mongodb')->beginTransaction();
        try {
            $sale = Sale::create([
                'user_id'        => (string)$user->_id,
                'products'       => $validated['products'],
                'total'          => round($total, 2),
                'payment_method' => $validated['payment_method'],
                'status'         => 'completed',
                'customer_info'  => $validated['customer_info'] ?? null,
            ]);

            DB::connection('mongodb')->commit();

            return response()->json($sale, 201);
        } catch (\Throwable $e) {
            DB::connection('mongodb')->rollBack();
            return response()->json([
                'message' => 'Error al registrar la venta',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /** GET /api/sales/{id} */
    public function show($id)
    {
        $sale = Sale::find($id);
        return $sale
            ? response()->json($sale)
            : response()->json(['message' => 'Venta no encontrada'], 404);
    }

    /** POST /api/sales/{id}/cancel */
    public function cancel($id, Request $request)
    {
        $user = $request->user();
        if (!$user || !in_array($user->role, ['vendedor','admin'])) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        DB::connection('mongodb')->beginTransaction();
        try {
            $sale = Sale::findOrFail($id);

            if ($sale->status === 'cancelled') {
                return response()->json(['message' => 'La venta ya está cancelada'], 400);
            }

            if ($user->role === 'vendedor' && (string)$sale->user_id !== (string)$user->_id) {
                return response()->json(['message' => 'No autorizado a cancelar esta venta'], 403);
            }

            $sale->update(['status' => 'cancelled']);
            DB::connection('mongodb')->commit();

            return response()->json([
                'message'  => 'Venta cancelada correctamente',
                'products' => collect($sale->products)->map(fn($p) => [
                    'product_id' => $p['product_id'],
                    'quantity'   => $p['quantity'],
                ])->values()
            ]);

        } catch (\Throwable $e) {
            DB::connection('mongodb')->rollBack();
            return response()->json([
                'message' => 'Error al cancelar la venta',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}
