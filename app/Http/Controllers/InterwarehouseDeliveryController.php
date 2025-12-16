<?php

namespace App\Http\Controllers;

use App\Models\InterwarehouseRequest;
use App\Models\InterwarehouseDelivery;
use App\Models\InterwarehouseItem;
use App\Models\Transfer;
use App\Models\TransferDetail;
use App\Models\product_warehouse;
use App\Models\Setting;
use Carbon\Carbon;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PDF;

class InterwarehouseDeliveryController extends Controller
{
    /**
     * Display a listing of deliveries.
     */
    public function index(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', InterwarehouseDelivery::class);

        $perPage = $request->limit ?? 10;
        $pageStart = \Request::get('page', 1);
        $offSet = ($pageStart * $perPage) - $perPage;
        $order = $request->SortField ?? 'id';
        $dir = $request->SortType ?? 'desc';

        $query = InterwarehouseDelivery::with('request.requesterWarehouse', 'request.supplierWarehouse', 'user', 'transfer')
            ->when($request->filled('interwarehouse_request_id'), function ($q) use ($request) {
                return $q->where('interwarehouse_request_id', $request->interwarehouse_request_id);
            })
            ->when($request->filled('statut'), function ($q) use ($request) {
                return $q->where('statut', $request->statut);
            })
            ->when($request->filled('search'), function ($q) use ($request) {
                return $q->where('Ref', 'like', "{$request->search}%");
            });

        $totalRows = $query->count();
        if ($perPage == "-1") {
            $perPage = $totalRows;
        }

        $deliveries = $query->offset($offSet)
            ->limit($perPage)
            ->orderBy($order, $dir)
            ->get();

        $data = [];
        foreach ($deliveries as $delivery) {
            $data[] = [
                'id' => $delivery->id,
                'Ref' => $delivery->Ref,
                'date' => $delivery->date,
                'statut' => $delivery->statut,
                'request_ref' => $delivery->request->Ref ?? '',
                'from_warehouse' => $delivery->request->supplierWarehouse->name ?? '',
                'to_warehouse' => $delivery->request->requesterWarehouse->name ?? '',
                'transfer_ref' => $delivery->transfer->Ref ?? '',
                'user' => $delivery->user->username ?? '',
                'notes' => $delivery->notes,
            ];
        }

        return response()->json([
            'totalRows' => $totalRows,
            'deliveries' => $data,
        ]);
    }

    /**
     * Store a newly created delivery (initiate delivery).
     */
    public function store(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'create', InterwarehouseDelivery::class);

        request()->validate([
            'delivery.interwarehouse_request_id' => 'required|integer',
        ]);

        $iw_request = InterwarehouseRequest::findOrFail($request->delivery['interwarehouse_request_id']);

        // Check if request can be delivered
        $allowed_statuses = [
            InterwarehouseRequest::STATUS_VALIDATED,
            InterwarehouseRequest::STATUS_IN_PAYMENT,
            InterwarehouseRequest::STATUS_PAID,
        ];

        if (!in_array($iw_request->statut, $allowed_statuses)) {
            return response()->json([
                'success' => false,
                'message' => 'La livraison ne peut être initiée que pour les demandes validées ou payées'
            ], 403);
        }

        // Check payment threshold
        if (!$iw_request->canDeliver()) {
            $required = $iw_request->payment_threshold;
            $current = $iw_request->payment_percentage;
            return response()->json([
                'success' => false,
                'message' => "Paiement insuffisant. Requis: {$required}%, Actuel: {$current}%"
            ], 403);
        }

        // Check if there's already a pending delivery
        $existing = InterwarehouseDelivery::where('interwarehouse_request_id', $iw_request->id)
            ->whereIn('statut', [InterwarehouseDelivery::STATUS_PENDING, InterwarehouseDelivery::STATUS_SHIPPED])
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'Une livraison est déjà en cours pour cette demande'
            ], 403);
        }

        $delivery = new InterwarehouseDelivery;
        $delivery->Ref = $this->getNumberOrder();
        $delivery->interwarehouse_request_id = $iw_request->id;
        $delivery->date = $request->delivery['date'] ?? Carbon::now()->format('Y-m-d');
        $delivery->user_id = Auth::user()->id;
        $delivery->statut = InterwarehouseDelivery::STATUS_PENDING;
        $delivery->notes = $request->delivery['notes'] ?? '';
        $delivery->save();

        return response()->json(['success' => true, 'message' => 'Livraison initiée avec succès', 'delivery_id' => $delivery->id]);
    }

    /**
     * Display the specified delivery.
     */
    public function show(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'view', InterwarehouseDelivery::class);

        $delivery = InterwarehouseDelivery::with([
            'request.requesterWarehouse',
            'request.supplierWarehouse',
            'request.details.product',
            'user',
            'transfer'
        ])->findOrFail($id);

        return response()->json([
            'delivery' => [
                'id' => $delivery->id,
                'Ref' => $delivery->Ref,
                'date' => $delivery->date,
                'statut' => $delivery->statut,
                'request_ref' => $delivery->request->Ref ?? '',
                'from_warehouse' => $delivery->request->supplierWarehouse->name ?? '',
                'to_warehouse' => $delivery->request->requesterWarehouse->name ?? '',
                'transfer_ref' => $delivery->transfer->Ref ?? '',
                'transfer_id' => $delivery->transfer_id,
                'user' => $delivery->user->username ?? '',
                'notes' => $delivery->notes,
            ],
            'items' => $delivery->request->details->map(function ($item) {
                return [
                    'product_name' => $item->product->name ?? '',
                    'product_code' => $item->product->code ?? '',
                    'quantity' => $item->quantity,
                ];
            }),
        ]);
    }

    /**
     * Ship the delivery (supplier action) - creates Transfer and deducts stock.
     */
    public function ship(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'update', InterwarehouseDelivery::class);

        $delivery = InterwarehouseDelivery::findOrFail($id);
        $iw_request = InterwarehouseRequest::with('details')->findOrFail($delivery->interwarehouse_request_id);

        if ($delivery->statut !== InterwarehouseDelivery::STATUS_PENDING) {
            return response()->json([
                'success' => false,
                'message' => "Seules les livraisons en attente peuvent être expédiées"
            ], 403);
        }

        \DB::transaction(function () use ($delivery, $iw_request) {
            // Create Transfer record
            $transfer = new Transfer;
            $transfer->Ref = $this->getTransferNumberOrder();
            $transfer->date = Carbon::now()->format('Y-m-d');
            $transfer->from_warehouse_id = $iw_request->supplier_warehouse_id;
            $transfer->to_warehouse_id = $iw_request->requester_warehouse_id;
            $transfer->items = $iw_request->items; // This is the float column 'items' (count), which is correct for Transfer model which also has 'items' float column
            $transfer->tax_rate = $iw_request->tax_rate;
            $transfer->TaxNet = $iw_request->TaxNet;
            $transfer->discount = $iw_request->discount;
            $transfer->shipping = $iw_request->shipping;
            $transfer->GrandTotal = $iw_request->GrandTotal;
            $transfer->statut = 'sent'; // Transfer is sent (in transit)
            $transfer->notes = "Livraison Inter-Entrepôt: {$iw_request->Ref}";
            $transfer->user_id = Auth::user()->id;
            $transfer->save();

            // Create transfer details and update stock
            foreach ($iw_request->details as $item) {
                // Create transfer detail
                $transferDetail = new TransferDetail;
                $transferDetail->transfer_id = $transfer->id;
                $transferDetail->product_id = $item->product_id;
                $transferDetail->product_variant_id = $item->product_variant_id;
                $transferDetail->quantity = $item->quantity;
                $transferDetail->cost = $item->cost;
                $transferDetail->TaxNet = $item->TaxNet;
                $transferDetail->tax_method = $item->tax_method;
                $transferDetail->discount = $item->discount;
                $transferDetail->discount_method = $item->discount_method;
                $transferDetail->total = $item->total;
                $transferDetail->purchase_unit_id = $item->unit_id;
                $transferDetail->save();

                // Deduct stock from supplier warehouse
                $product_warehouse = product_warehouse::where('warehouse_id', $iw_request->supplier_warehouse_id)
                    ->where('product_id', $item->product_id)
                    ->where('product_variant_id', $item->product_variant_id)
                    ->first();

                if ($product_warehouse) {
                    $product_warehouse->qte -= $item->quantity;
                    $product_warehouse->save();
                }
            }

            // Update delivery
            $delivery->transfer_id = $transfer->id;
            $delivery->statut = InterwarehouseDelivery::STATUS_SHIPPED;
            $delivery->save();

        }, 10);

        return response()->json(['success' => true, 'message' => 'Livraison expédiée avec succès']);
    }

    /**
     * Receive the delivery (requester action) - adds stock to requester warehouse.
     */
    public function receive(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'update', InterwarehouseDelivery::class);

        $delivery = InterwarehouseDelivery::findOrFail($id);
        $iw_request = InterwarehouseRequest::with('details')->findOrFail($delivery->interwarehouse_request_id);

        if ($delivery->statut !== InterwarehouseDelivery::STATUS_SHIPPED) {
            return response()->json([
                'success' => false,
                'message' => "Seules les livraisons expédiées peuvent être réceptionnées"
            ], 403);
        }

        \DB::transaction(function () use ($delivery, $iw_request) {
            // Add stock to requester warehouse
            foreach ($iw_request->details as $item) {
                $product_warehouse = product_warehouse::where('warehouse_id', $iw_request->requester_warehouse_id)
                    ->where('product_id', $item->product_id)
                    ->where('product_variant_id', $item->product_variant_id)
                    ->first();

                if ($product_warehouse) {
                    $product_warehouse->qte += $item->quantity;
                    $product_warehouse->save();
                } else {
                    // Create new product_warehouse record
                    product_warehouse::create([
                        'warehouse_id' => $iw_request->requester_warehouse_id,
                        'product_id' => $item->product_id,
                        'product_variant_id' => $item->product_variant_id,
                        'qte' => $item->quantity,
                    ]);
                }
            }

            // Update transfer status
            if ($delivery->transfer) {
                $delivery->transfer->statut = 'completed';
                $delivery->transfer->save();
            }

            // Update delivery
            $delivery->statut = InterwarehouseDelivery::STATUS_RECEIVED;
            $delivery->save();

            // Update request status
            $iw_request->statut = InterwarehouseRequest::STATUS_DELIVERED;
            $iw_request->save();

        }, 10);

        return response()->json(['success' => true, 'message' => 'Livraison réceptionnée avec succès']);
    }

    /**
     * Generate delivery note PDF.
     */
    public function deliveryNotePdf(Request $request, $id)
    {
        $delivery = InterwarehouseDelivery::with([
            'request.requesterWarehouse',
            'request.supplierWarehouse',
            'request.details.product',
            'request.details.unit',
            'user'
        ])->findOrFail($id);

        $settings = Setting::where('deleted_at', null)->first();

        $details = [];
        foreach ($delivery->request->details as $item) {
            $details[] = [
                'code' => $item->product->code ?? '',
                'name' => $item->product->name ?? '',
                'unit' => $item->unit->ShortName ?? '',
                'quantity' => $item->quantity,
            ];
        }

        $pdf_data = [
            'title' => 'BON DE LIVRAISON',
            'delivery' => $delivery,
            'details' => $details,
            'setting' => $settings,
        ];

        $pdf = PDF::loadView('pdf.interwarehouse_delivery', $pdf_data);

        return $pdf->download("BL_{$delivery->Ref}.pdf");
    }

    /**
     * Generate auto reference number for delivery.
     */
    private function getNumberOrder()
    {
        $last = InterwarehouseDelivery::latest('id')->first();

        if ($last) {
            $item = $last->Ref;
            $n498 = explode("_", $item);
            $one = $n498[1] + 1;
            $number = 'IWD_' . str_pad($one, 5, '0', STR_PAD_LEFT);
        } else {
            $number = 'IWD_00001';
        }

        return $number;
    }

    /**
     * Generate auto reference number for transfer.
     */
    private function getTransferNumberOrder()
    {
        $last = Transfer::latest('id')->first();

        if ($last) {
            $item = $last->Ref;
            $n498 = explode("_", $item);
            $one = $n498[1] + 1;
            $number = 'TR_' . str_pad($one, 5, '0', STR_PAD_LEFT);
        } else {
            $number = 'TR_00001';
        }

        return $number;
    }
}
