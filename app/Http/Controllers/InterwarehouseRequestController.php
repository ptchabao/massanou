<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\product_warehouse;
use App\Models\Role;
use App\Models\Unit;
use App\Models\Setting;
use App\Models\InterwarehouseRequest;
use App\Models\InterwarehouseItem;
use App\Models\InterwarehousePayment;
use App\Models\InterwarehouseDelivery;
use App\Models\Transfer;
use App\Models\TransferDetail;
use App\Models\Warehouse;
use App\Models\User;
use App\Models\UserWarehouse;
use App\utils\helpers;
use Carbon\Carbon;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PDF;

class InterwarehouseRequestController extends Controller
{
    /**
     * Display a listing of the requests.
     */
    public function index(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', InterwarehouseRequest::class);

        $perPage = $request->limit;
        $pageStart = \Request::get('page', 1);
        $offSet = ($pageStart * $perPage) - $perPage;
        $order = $request->SortField ?? 'id';
        $dir = $request->SortType ?? 'desc';
        $helpers = new helpers();

        // Get user's warehouses
        $user_auth = auth()->user();
        $warehouses = $user_auth->is_all_warehouses
            ? Warehouse::where('deleted_at', null)->pluck('id')->toArray()
            : UserWarehouse::where('user_id', $user_auth->id)->pluck('warehouse_id')->toArray();

        $data = array();

        $query = InterwarehouseRequest::with('requesterWarehouse', 'supplierWarehouse', 'user', 'proformaUser')
            ->where('deleted_at', null)
            ->where(function ($q) use ($warehouses) {
                // Show requests where user's warehouse is either requester or supplier
                $q->whereIn('requester_warehouse_id', $warehouses)
                    ->orWhereIn('supplier_warehouse_id', $warehouses);
            })
            ->when($request->filled('search'), function ($query) use ($request) {
                return $query->where(function ($q) use ($request) {
                    $q->where('Ref', 'like', "{$request->search}%")
                        ->orWhere('statut', '=', $request->search);
                });
            })
            ->when($request->filled('statut'), function ($query) use ($request) {
                return $query->where('statut', $request->statut);
            })
            ->when($request->filled('requester_warehouse_id'), function ($query) use ($request) {
                return $query->where('requester_warehouse_id', $request->requester_warehouse_id);
            })
            ->when($request->filled('supplier_warehouse_id'), function ($query) use ($request) {
                return $query->where('supplier_warehouse_id', $request->supplier_warehouse_id);
            });

        $totalRows = $query->count();
        if ($perPage == "-1") {
            $perPage = $totalRows;
        }

        $requests = $query->offset($offSet)
            ->limit($perPage)
            ->orderBy($order, $dir)
            ->get();

        foreach ($requests as $iw_request) {
            $item['id'] = $iw_request->id;
            $item['date'] = $iw_request->date;
            $item['Ref'] = $iw_request->Ref;
            $item['statut'] = $iw_request->statut;
            $item['requester_warehouse'] = $iw_request->requesterWarehouse->name ?? '';
            $item['requester_warehouse_id'] = $iw_request->requester_warehouse_id;
            $item['supplier_warehouse'] = $iw_request->supplierWarehouse->name ?? '';
            $item['supplier_warehouse_id'] = $iw_request->supplier_warehouse_id;
            $item['GrandTotal'] = number_format($iw_request->GrandTotal, 2, '.', '');
            $item['paid_amount'] = number_format($iw_request->paid_amount, 2, '.', '');
            $item['remaining'] = number_format($iw_request->remaining_amount, 2, '.', '');
            $item['payment_percentage'] = $iw_request->payment_percentage;
            $item['payment_threshold'] = $iw_request->payment_threshold;
            $item['desired_date'] = $iw_request->desired_date;
            $item['items_count'] = $iw_request->items;
            $item['created_by'] = $iw_request->user->username ?? '';
            $item['proforma_by'] = $iw_request->proformaUser->username ?? '';
            $item['can_deliver'] = $iw_request->canDeliver();

            $data[] = $item;
        }

        return response()->json([
            'totalRows' => $totalRows,
            'interwarehouse_requests' => $data,
        ]);
    }

    /**
     * Store a newly created request.
     */
    public function store(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'create', InterwarehouseRequest::class);

        request()->validate([
            'interwarehouse.supplier_warehouse_id' => 'required|integer',
        ]);

        \DB::transaction(function () use ($request) {
            $iw_request = new InterwarehouseRequest;

            $user = Auth::user();
            // Get user's primary warehouse as requester
            $user_warehouses = $user->is_all_warehouses
                ? Warehouse::where('deleted_at', null)->pluck('id')->first()
                : UserWarehouse::where('user_id', $user->id)->pluck('warehouse_id')->first();

            $iw_request->date = $request->interwarehouse['date'] ?? Carbon::now()->format('Y-m-d');
            $iw_request->Ref = $this->getNumberOrder();
            $iw_request->requester_warehouse_id = $request->interwarehouse['requester_warehouse_id'] ?? $user_warehouses;
            $iw_request->supplier_warehouse_id = $request->interwarehouse['supplier_warehouse_id'];
            $iw_request->items = sizeof($request['details']);
            $iw_request->tax_rate = $request->interwarehouse['tax_rate'] ?? 0;
            $iw_request->TaxNet = $request->interwarehouse['TaxNet'] ?? 0;
            $iw_request->discount = $request->interwarehouse['discount'] ?? 0;
            $iw_request->discount_type = $request->interwarehouse['discount_type'] ?? 'fixed';
            $iw_request->shipping = $request->interwarehouse['shipping'] ?? 0;
            $iw_request->GrandTotal = $request['GrandTotal'] ?? 0;
            $iw_request->desired_date = $request->interwarehouse['desired_date'] ?? null;
            $iw_request->notes = $request->interwarehouse['notes'] ?? '';
            $iw_request->statut = $request->interwarehouse['send_now'] ? InterwarehouseRequest::STATUS_SENT : InterwarehouseRequest::STATUS_DRAFT;
            $iw_request->user_id = $user->id;
            $iw_request->save();

            // Save items
            $data = $request['details'];
            foreach ($data as $value) {
                $item = new InterwarehouseItem;
                $item->interwarehouse_request_id = $iw_request->id;
                $item->product_id = $value['product_id'];
                $item->product_variant_id = $value['product_variant_id'] ?? null;
                $item->unit_id = $value['unit_id'] ?? null;
                $item->quantity = $value['quantity'];
                $item->cost = $value['Unit_cost'] ?? 0;
                $item->TaxNet = $value['tax_percent'] ?? 0;
                $item->tax_method = $value['tax_method'] ?? '1';
                $item->discount = $value['discount'] ?? 0;
                $item->discount_method = $value['discount_method'] ?? '1';
                $item->total = $value['subtotal'] ?? 0;
                $item->save();
            }
        }, 10);

        return response()->json(['success' => true, 'message' => 'Demande créée avec succès']);
    }

    /**
     * Display the specified request.
     */
    public function show(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'view', InterwarehouseRequest::class);

        $iw_request = InterwarehouseRequest::with([
            'requesterWarehouse',
            'supplierWarehouse',
            'user',
            'proformaUser',
            'validatedUser',
            'details.product',
            'details.productVariant',
            'details.unit',
            'payments.paymentMethod',
            'payments.user',
            'deliveries.user'
        ])->findOrFail($id);

        $details = [];
        foreach ($iw_request->details as $item) {
            $details[] = [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product->name ?? '',
                'product_code' => $item->product->code ?? '',
                'product_variant_id' => $item->product_variant_id,
                'variant_name' => $item->productVariant->name ?? '',
                'unit_id' => $item->unit_id,
                'unit_name' => $item->unit->ShortName ?? '',
                'quantity' => $item->quantity,
                'cost' => $item->cost,
                'TaxNet' => $item->TaxNet,
                'tax_method' => $item->tax_method,
                'discount' => $item->discount,
                'discount_method' => $item->discount_method,
                'total' => $item->total,
            ];
        }

        $payments = [];
        foreach ($iw_request->payments as $payment) {
            $payments[] = [
                'id' => $payment->id,
                'Ref' => $payment->Ref,
                'date' => $payment->date,
                'montant' => $payment->montant,
                'payment_method' => $payment->paymentMethod->name ?? '',
                'user' => $payment->user->username ?? '',
                'notes' => $payment->notes,
            ];
        }

        $deliveries = [];
        foreach ($iw_request->deliveries as $delivery) {
            $deliveries[] = [
                'id' => $delivery->id,
                'Ref' => $delivery->Ref,
                'date' => $delivery->date,
                'statut' => $delivery->statut,
                'user' => $delivery->user->username ?? '',
                'notes' => $delivery->notes,
            ];
        }

        return response()->json([
            'interwarehouse_request' => [
                'id' => $iw_request->id,
                'Ref' => $iw_request->Ref,
                'date' => $iw_request->date,
                'statut' => $iw_request->statut,
                'requester_warehouse_id' => $iw_request->requester_warehouse_id,
                'requester_warehouse' => $iw_request->requesterWarehouse->name ?? '',
                'supplier_warehouse_id' => $iw_request->supplier_warehouse_id,
                'supplier_warehouse' => $iw_request->supplierWarehouse->name ?? '',
                'items_count' => $iw_request->items,
                'tax_rate' => $iw_request->tax_rate,
                'TaxNet' => $iw_request->TaxNet,
                'discount' => $iw_request->discount,
                'discount_type' => $iw_request->discount_type,
                'shipping' => $iw_request->shipping,
                'GrandTotal' => $iw_request->GrandTotal,
                'paid_amount' => $iw_request->paid_amount,
                'remaining_amount' => $iw_request->remaining_amount,
                'payment_percentage' => $iw_request->payment_percentage,
                'payment_threshold' => $iw_request->payment_threshold,
                'can_deliver' => $iw_request->canDeliver(),
                'desired_date' => $iw_request->desired_date,
                'notes' => $iw_request->notes,
                'proforma_notes' => $iw_request->proforma_notes,
                'proforma_by' => $iw_request->proformaUser->username ?? '',
                'proforma_at' => $iw_request->proforma_at,
                'validated_by' => $iw_request->validatedUser->username ?? '',
                'validated_at' => $iw_request->validated_at,
                'created_by' => $iw_request->user->username ?? '',
                'created_at' => $iw_request->created_at,
            ],
            'details' => $details,
            'payments' => $payments,
            'deliveries' => $deliveries,
        ]);
    }

    /**
     * Show the form for creating a new request.
     */
    public function create(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'create', InterwarehouseRequest::class);

        $user_auth = auth()->user();
        $warehouses_user = $user_auth->is_all_warehouses
            ? Warehouse::where('deleted_at', null)->get(['id', 'name'])
            : Warehouse::whereIn('id', UserWarehouse::where('user_id', $user_auth->id)->pluck('warehouse_id'))
                ->where('deleted_at', null)
                ->get(['id', 'name']);

        $all_warehouses = Warehouse::where('deleted_at', null)->get(['id', 'name']);

        return response()->json([
            'user_warehouses' => $warehouses_user,
            'all_warehouses' => $all_warehouses,
        ]);
    }

    /**
     * Show the form for editing the specified request.
     */
    public function edit(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'update', InterwarehouseRequest::class);

        $iw_request = InterwarehouseRequest::with('details.product', 'details.productVariant', 'details.unit')
            ->findOrFail($id);

        // Only allow editing drafts
        if ($iw_request->statut !== InterwarehouseRequest::STATUS_DRAFT) {
            return response()->json(['success' => false, 'message' => 'Seuls les brouillons peuvent être modifiés'], 403);
        }

        $user_auth = auth()->user();
        $warehouses_user = $user_auth->is_all_warehouses
            ? Warehouse::where('deleted_at', null)->get(['id', 'name'])
            : Warehouse::whereIn('id', UserWarehouse::where('user_id', $user_auth->id)->pluck('warehouse_id'))
                ->where('deleted_at', null)
                ->get(['id', 'name']);

        $all_warehouses = Warehouse::where('deleted_at', null)->get(['id', 'name']);

        $details = [];
        foreach ($iw_request->details as $item) {
            $unit = $item->unit;
            $details[] = [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'name' => $item->product->name ?? '',
                'code' => $item->product->code ?? '',
                'product_variant_id' => $item->product_variant_id,
                'unit_id' => $item->unit_id,
                'Unit_cost' => $item->cost,
                'quantity' => $item->quantity,
                'tax_percent' => $item->TaxNet,
                'tax_method' => $item->tax_method,
                'discount' => $item->discount,
                'discount_method' => $item->discount_method,
                'subtotal' => $item->total,
            ];
        }

        return response()->json([
            'interwarehouse_request' => [
                'id' => $iw_request->id,
                'date' => $iw_request->date,
                'requester_warehouse_id' => $iw_request->requester_warehouse_id,
                'supplier_warehouse_id' => $iw_request->supplier_warehouse_id,
                'tax_rate' => $iw_request->tax_rate,
                'TaxNet' => $iw_request->TaxNet,
                'discount' => $iw_request->discount,
                'discount_type' => $iw_request->discount_type,
                'shipping' => $iw_request->shipping,
                'desired_date' => $iw_request->desired_date,
                'notes' => $iw_request->notes,
            ],
            'details' => $details,
            'user_warehouses' => $warehouses_user,
            'all_warehouses' => $all_warehouses,
        ]);
    }

    /**
     * Update the specified request.
     */
    public function update(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'update', InterwarehouseRequest::class);

        $iw_request = InterwarehouseRequest::findOrFail($id);

        // Only allow updating drafts
        if ($iw_request->statut !== InterwarehouseRequest::STATUS_DRAFT) {
            return response()->json(['success' => false, 'message' => 'Seuls les brouillons peuvent être modifiés'], 403);
        }

        \DB::transaction(function () use ($request, $iw_request) {
            // Delete old items
            InterwarehouseItem::where('interwarehouse_request_id', $iw_request->id)->delete();

            $iw_request->date = $request->interwarehouse['date'] ?? $iw_request->date;
            $iw_request->supplier_warehouse_id = $request->interwarehouse['supplier_warehouse_id'];
            $iw_request->items = sizeof($request['details']);
            $iw_request->tax_rate = $request->interwarehouse['tax_rate'] ?? 0;
            $iw_request->TaxNet = $request->interwarehouse['TaxNet'] ?? 0;
            $iw_request->discount = $request->interwarehouse['discount'] ?? 0;
            $iw_request->discount_type = $request->interwarehouse['discount_type'] ?? 'fixed';
            $iw_request->shipping = $request->interwarehouse['shipping'] ?? 0;
            $iw_request->GrandTotal = $request['GrandTotal'] ?? 0;
            $iw_request->desired_date = $request->interwarehouse['desired_date'] ?? null;
            $iw_request->notes = $request->interwarehouse['notes'] ?? '';

            if ($request->interwarehouse['send_now']) {
                $iw_request->statut = InterwarehouseRequest::STATUS_SENT;
            }

            $iw_request->save();

            // Save new items
            foreach ($request['details'] as $value) {
                $item = new InterwarehouseItem;
                $item->interwarehouse_request_id = $iw_request->id;
                $item->product_id = $value['product_id'];
                $item->product_variant_id = $value['product_variant_id'] ?? null;
                $item->unit_id = $value['unit_id'] ?? null;
                $item->quantity = $value['quantity'];
                $item->cost = $value['Unit_cost'] ?? 0;
                $item->TaxNet = $value['tax_percent'] ?? 0;
                $item->tax_method = $value['tax_method'] ?? '1';
                $item->discount = $value['discount'] ?? 0;
                $item->discount_method = $value['discount_method'] ?? '1';
                $item->total = $value['subtotal'] ?? 0;
                $item->save();
            }
        }, 10);

        return response()->json(['success' => true, 'message' => 'Demande mise à jour avec succès']);
    }

    /**
     * Remove the specified request.
     */
    public function destroy(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'delete', InterwarehouseRequest::class);

        $iw_request = InterwarehouseRequest::findOrFail($id);

        // Only allow deleting drafts or rejected
        if (!in_array($iw_request->statut, [InterwarehouseRequest::STATUS_DRAFT, InterwarehouseRequest::STATUS_REJECTED])) {
            return response()->json(['success' => false, 'message' => 'Seuls les brouillons ou les demandes refusées peuvent être supprimés'], 403);
        }

        $iw_request->deleted_at = Carbon::now();
        $iw_request->save();

        return response()->json(['success' => true, 'message' => 'Demande supprimée avec succès']);
    }

    /**
     * Delete multiple requests by selection.
     */
    public function delete_by_selection(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'delete', InterwarehouseRequest::class);

        $selectedIds = $request->selectedIds;

        foreach ($selectedIds as $id) {
            $iw_request = InterwarehouseRequest::findOrFail($id);
            if (in_array($iw_request->statut, [InterwarehouseRequest::STATUS_DRAFT, InterwarehouseRequest::STATUS_REJECTED])) {
                $iw_request->deleted_at = Carbon::now();
                $iw_request->save();
            }
        }

        return response()->json(['success' => true, 'message' => 'Demandes supprimées avec succès']);
    }

    /**
     * Send the request to supplier warehouse.
     */
    public function sendRequest(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'update', InterwarehouseRequest::class);

        $iw_request = InterwarehouseRequest::findOrFail($id);

        if ($iw_request->statut !== InterwarehouseRequest::STATUS_DRAFT) {
            return response()->json(['success' => false, 'message' => 'Seuls les brouillons peuvent être envoyés'], 403);
        }

        $iw_request->statut = InterwarehouseRequest::STATUS_SENT;
        $iw_request->save();

        return response()->json(['success' => true, 'message' => 'Demande envoyée avec succès']);
    }

    /**
     * Create proforma (supplier action).
     */
    public function createProforma(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'update', InterwarehouseRequest::class);

        $iw_request = InterwarehouseRequest::findOrFail($id);

        if ($iw_request->statut !== InterwarehouseRequest::STATUS_SENT) {
            return response()->json(['success' => false, 'message' => 'Le proforma ne peut être créé que pour les demandes envoyées'], 403);
        }

        \DB::transaction(function () use ($request, $iw_request) {
            // Update items with prices
            foreach ($request['details'] as $value) {
                $item = InterwarehouseItem::find($value['id']);
                if ($item) {
                    $item->cost = $value['Unit_cost'] ?? 0;
                    $item->TaxNet = $value['tax_percent'] ?? 0;
                    $item->tax_method = $value['tax_method'] ?? '1';
                    $item->discount = $value['discount'] ?? 0;
                    $item->discount_method = $value['discount_method'] ?? '1';
                    $item->total = $value['subtotal'] ?? 0;
                    $item->save();
                }
            }

            $iw_request->tax_rate = $request->interwarehouse['tax_rate'] ?? 0;
            $iw_request->TaxNet = $request->interwarehouse['TaxNet'] ?? 0;
            $iw_request->discount = $request->interwarehouse['discount'] ?? 0;
            $iw_request->discount_type = $request->interwarehouse['discount_type'] ?? 'fixed';
            $iw_request->shipping = $request->interwarehouse['shipping'] ?? 0;
            $iw_request->GrandTotal = $request['GrandTotal'] ?? 0;
            $iw_request->proforma_notes = $request->interwarehouse['proforma_notes'] ?? '';
            $iw_request->proforma_by = Auth::user()->id;
            $iw_request->proforma_at = Carbon::now();
            $iw_request->statut = InterwarehouseRequest::STATUS_PROFORMA_SENT;
            $iw_request->save();
        }, 10);

        return response()->json(['success' => true, 'message' => 'Proforma créé avec succès']);
    }

    /**
     * Accept proforma (requester action).
     */
    public function acceptProforma(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'update', InterwarehouseRequest::class);

        $iw_request = InterwarehouseRequest::findOrFail($id);

        if ($iw_request->statut !== InterwarehouseRequest::STATUS_PROFORMA_SENT) {
            return response()->json(['success' => false, 'message' => 'Seuls les proformas peuvent être acceptés'], 403);
        }

        $iw_request->validated_by = Auth::user()->id;
        $iw_request->validated_at = Carbon::now();
        $iw_request->statut = InterwarehouseRequest::STATUS_VALIDATED;
        $iw_request->save();

        return response()->json(['success' => true, 'message' => 'Proforma accepté avec succès']);
    }

    /**
     * Reject proforma (requester action).
     */
    public function rejectProforma(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'update', InterwarehouseRequest::class);

        $iw_request = InterwarehouseRequest::findOrFail($id);

        if (!in_array($iw_request->statut, [InterwarehouseRequest::STATUS_PROFORMA_SENT, InterwarehouseRequest::STATUS_SENT])) {
            return response()->json(['success' => false, 'message' => 'Action non autorisée pour ce statut'], 403);
        }

        $iw_request->statut = InterwarehouseRequest::STATUS_REJECTED;
        $iw_request->notes = $iw_request->notes . "\n[Rejeté] " . ($request->reason ?? '');
        $iw_request->save();

        return response()->json(['success' => true, 'message' => 'Demande refusée']);
    }

    /**
     * Generate auto reference number.
     */
    public function getNumberOrder()
    {
        $last = InterwarehouseRequest::latest('id')->first();

        if ($last) {
            $item = $last->Ref;
            $n498 = explode("_", $item);
            $one = $n498[1] + 1;
            $number = 'IW_' . str_pad($one, 5, '0', STR_PAD_LEFT);
        } else {
            $number = 'IW_00001';
        }

        return $number;
    }

    /**
     * Generate PDF for the request.
     */
    public function generatePdf(Request $request, $id)
    {
        $iw_request = InterwarehouseRequest::with([
            'requesterWarehouse',
            'supplierWarehouse',
            'details.product',
            'details.unit'
        ])->findOrFail($id);

        $settings = Setting::where('deleted_at', null)->first();

        $details = [];
        foreach ($iw_request->details as $item) {
            $details[] = [
                'code' => $item->product->code ?? '',
                'name' => $item->product->name ?? '',
                'unit' => $item->unit->ShortName ?? '',
                'quantity' => $item->quantity,
                'cost' => number_format($item->cost, 2, '.', ''),
                'total' => number_format($item->total, 2, '.', ''),
            ];
        }

        $pdf_data = [
            'title' => $iw_request->statut === InterwarehouseRequest::STATUS_PROFORMA_SENT ? 'PROFORMA' : 'DEMANDE DE DEVIS',
            'request' => $iw_request,
            'details' => $details,
            'setting' => $settings,
        ];

        $pdf = PDF::loadView('pdf.interwarehouse_request', $pdf_data);

        return $pdf->download("InterWarehouse_{$iw_request->Ref}.pdf");
    }
}
