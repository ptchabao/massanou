<?php

namespace App\Http\Controllers;

use App\Models\InterwarehouseRequest;
use App\Models\InterwarehousePayment;
use App\Models\PaymentMethod;
use App\Models\Account;
use Carbon\Carbon;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InterwarehousePaymentController extends Controller
{
    /**
     * Display a listing of payments.
     */
    public function index(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', InterwarehousePayment::class);

        $perPage = $request->limit ?? 10;
        $pageStart = \Request::get('page', 1);
        $offSet = ($pageStart * $perPage) - $perPage;
        $order = $request->SortField ?? 'id';
        $dir = $request->SortType ?? 'desc';

        $query = InterwarehousePayment::with('request', 'paymentMethod', 'user', 'account')
            ->where('deleted_at', null)
            ->when($request->filled('interwarehouse_request_id'), function ($q) use ($request) {
                return $q->where('interwarehouse_request_id', $request->interwarehouse_request_id);
            })
            ->when($request->filled('search'), function ($q) use ($request) {
                return $q->where('Ref', 'like', "{$request->search}%");
            });

        $totalRows = $query->count();
        if ($perPage == "-1") {
            $perPage = $totalRows;
        }

        $payments = $query->offset($offSet)
            ->limit($perPage)
            ->orderBy($order, $dir)
            ->get();

        $data = [];
        foreach ($payments as $payment) {
            $data[] = [
                'id' => $payment->id,
                'Ref' => $payment->Ref,
                'date' => $payment->date,
                'montant' => number_format($payment->montant, 2, '.', ''),
                'request_ref' => $payment->request->Ref ?? '',
                'payment_method' => $payment->paymentMethod->name ?? '',
                'account' => $payment->account->name ?? '',
                'user' => $payment->user->username ?? '',
                'notes' => $payment->notes,
            ];
        }

        return response()->json([
            'totalRows' => $totalRows,
            'payments' => $data,
        ]);
    }

    /**
     * Store a newly created payment.
     */
    public function store(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'create', InterwarehousePayment::class);

        request()->validate([
            'payment.interwarehouse_request_id' => 'required|integer',
            'payment.montant' => 'required|numeric|min:0.01',
            'payment.payment_method_id' => 'required|integer',
        ]);

        $iw_request = InterwarehouseRequest::findOrFail($request->payment['interwarehouse_request_id']);

        // Check if request is in valid state for payment
        $allowed_statuses = [
            InterwarehouseRequest::STATUS_VALIDATED,
            InterwarehouseRequest::STATUS_IN_PAYMENT,
        ];

        if (!in_array($iw_request->statut, $allowed_statuses)) {
            return response()->json([
                'success' => false,
                'message' => 'Les paiements ne sont autorisés que pour les demandes validées'
            ], 403);
        }

        $remaining = $iw_request->remaining_amount;
        $montant = $request->payment['montant'];

        if ($montant > $remaining) {
            return response()->json([
                'success' => false,
                'message' => "Le montant dépasse le reste à payer ({$remaining})"
            ], 400);
        }

        \DB::transaction(function () use ($request, $iw_request, $montant) {
            $payment = new InterwarehousePayment;
            $payment->Ref = $this->getNumberOrder();
            $payment->interwarehouse_request_id = $iw_request->id;
            $payment->date = $request->payment['date'] ?? Carbon::now()->format('Y-m-d');
            $payment->montant = $montant;
            $payment->payment_method_id = $request->payment['payment_method_id'];
            $payment->account_id = $request->payment['account_id'] ?? null;
            $payment->user_id = Auth::user()->id;
            $payment->notes = $request->payment['notes'] ?? '';
            $payment->save();

            // Update request paid amount
            $iw_request->paid_amount += $montant;

            // Update status based on payment
            if ($iw_request->paid_amount >= $iw_request->GrandTotal) {
                $iw_request->statut = InterwarehouseRequest::STATUS_PAID;
            } else {
                $iw_request->statut = InterwarehouseRequest::STATUS_IN_PAYMENT;
            }

            $iw_request->save();
        }, 10);

        return response()->json(['success' => true, 'message' => 'Paiement enregistré avec succès']);
    }

    /**
     * Display the specified payment.
     */
    public function show(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'view', InterwarehousePayment::class);

        $payment = InterwarehousePayment::with('request', 'paymentMethod', 'user', 'account')
            ->findOrFail($id);

        return response()->json([
            'payment' => [
                'id' => $payment->id,
                'Ref' => $payment->Ref,
                'date' => $payment->date,
                'montant' => $payment->montant,
                'interwarehouse_request_id' => $payment->interwarehouse_request_id,
                'request_ref' => $payment->request->Ref ?? '',
                'payment_method_id' => $payment->payment_method_id,
                'payment_method' => $payment->paymentMethod->name ?? '',
                'account_id' => $payment->account_id,
                'account' => $payment->account->name ?? '',
                'user' => $payment->user->username ?? '',
                'notes' => $payment->notes,
            ]
        ]);
    }

    /**
     * Get payments for a specific request.
     */
    public function getPaymentsByRequest(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'view', InterwarehousePayment::class);

        $iw_request = InterwarehouseRequest::findOrFail($id);

        $payments = InterwarehousePayment::with('paymentMethod', 'user')
            ->where('interwarehouse_request_id', $id)
            ->where('deleted_at', null)
            ->orderBy('date', 'desc')
            ->get();

        $data = [];
        foreach ($payments as $payment) {
            $data[] = [
                'id' => $payment->id,
                'Ref' => $payment->Ref,
                'date' => $payment->date,
                'montant' => number_format($payment->montant, 2, '.', ''),
                'payment_method' => $payment->paymentMethod->name ?? '',
                'user' => $payment->user->username ?? '',
                'notes' => $payment->notes,
            ];
        }

        return response()->json([
            'payments' => $data,
            'request_info' => [
                'GrandTotal' => $iw_request->GrandTotal,
                'paid_amount' => $iw_request->paid_amount,
                'remaining_amount' => $iw_request->remaining_amount,
                'payment_percentage' => $iw_request->payment_percentage,
                'payment_threshold' => $iw_request->payment_threshold,
                'can_deliver' => $iw_request->canDeliver(),
            ]
        ]);
    }

    /**
     * Remove the specified payment.
     */
    public function destroy(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'delete', InterwarehousePayment::class);

        $payment = InterwarehousePayment::findOrFail($id);
        $iw_request = InterwarehouseRequest::findOrFail($payment->interwarehouse_request_id);

        // Cannot delete payment if already delivered
        if (
            in_array($iw_request->statut, [
                InterwarehouseRequest::STATUS_DELIVERED,
                InterwarehouseRequest::STATUS_CLOSED
            ])
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Impossible de supprimer le paiement - la livraison a déjà été effectuée'
            ], 403);
        }

        \DB::transaction(function () use ($payment, $iw_request) {
            // Deduct payment amount from request
            $iw_request->paid_amount -= $payment->montant;

            // Update status
            if ($iw_request->paid_amount <= 0) {
                $iw_request->statut = InterwarehouseRequest::STATUS_VALIDATED;
            } else {
                $iw_request->statut = InterwarehouseRequest::STATUS_IN_PAYMENT;
            }

            $iw_request->save();

            $payment->deleted_at = Carbon::now();
            $payment->save();
        }, 10);

        return response()->json(['success' => true, 'message' => 'Paiement supprimé avec succès']);
    }

    /**
     * Get creation data (payment methods, accounts).
     */
    public function create(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'create', InterwarehousePayment::class);

        $payment_methods = PaymentMethod::where('deleted_at', null)->get(['id', 'name']);
        $accounts = Account::where('deleted_at', null)->get(['id', 'name']);

        return response()->json([
            'payment_methods' => $payment_methods,
            'accounts' => $accounts,
        ]);
    }

    /**
     * Generate auto reference number.
     */
    private function getNumberOrder()
    {
        $last = InterwarehousePayment::withTrashed()->latest('id')->first();

        if ($last) {
            $item = $last->Ref;
            $n498 = explode("_", $item);
            $one = $n498[1] + 1;
            $number = 'IWP_' . str_pad($one, 5, '0', STR_PAD_LEFT);
        } else {
            $number = 'IWP_00001';
        }

        return $number;
    }
}
