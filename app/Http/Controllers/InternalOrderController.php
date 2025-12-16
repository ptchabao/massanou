<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\product_warehouse;
use App\Models\Role;
use App\Models\Unit;
use App\Models\Setting;
use App\Models\InternalOrder;
use App\Models\InternalOrderDetail;
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

class InternalOrderController extends BaseController
{

    //------------- Get All Internal Orders --------------\\

    public function index(request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', InternalOrder::class);

        // How many items do you want to display.
        $perPage = $request->limit;
        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;
        $order = $request->SortField;
        $dir = $request->SortType;
        $helpers = new helpers();

        // Filter fields With Params to retrieve
        $param = array(
            0 => 'like',
            1 => '=',
            2 => '=',
            3 => '=',
        );
        $columns = array(
            0 => 'Ref',
            1 => 'statut',
            2 => 'from_warehouse_id',
            3 => 'to_warehouse_id',
        );
        $data = array();

        // Check If User Has Permission View All Records
        $InternalOrders = InternalOrder::with('fromWarehouse', 'toWarehouse', 'user')
            ->where('deleted_at', '=', null)
            ->where(function ($query) use ($request, $param, $columns) {
                return $query->when($request->filled('search'), function ($query) use ($request, $param, $columns) {
                    return $query->where(function ($query) use ($request, $param, $columns) {
                        for ($i = 0; $i < count($param); $i++) {
                            if ($i == 0) {
                                $query->where($columns[$i], $param[$i], "{$request->search}%");
                            } else {
                                $query->orWhere($columns[$i], $param[$i], "{$request->search}");
                            }
                        }
                    });
                });
            });

        $totalRows = $InternalOrders->count();
        if ($perPage == "-1") {
            $perPage = $totalRows;
        }
        $InternalOrders = $InternalOrders->offset($offSet)
            ->limit($perPage)
            ->orderBy($order, $dir)
            ->get();

        foreach ($InternalOrders as $InternalOrder) {

            $item['id'] = $InternalOrder->id;
            $item['date'] = $InternalOrder->date;
            $item['Ref'] = $InternalOrder->Ref;
            $item['statut'] = $InternalOrder->statut;
            $item['from_warehouse'] = $InternalOrder['fromWarehouse']->name;
            $item['to_warehouse'] = $InternalOrder['toWarehouse']->name;
            $item['GrandTotal'] = number_format($InternalOrder->GrandTotal, 2, '.', '');
            $item['discount'] = $InternalOrder->discount;
            $item['discount_type'] = $InternalOrder->discount_type;
            $item['shipping'] = $InternalOrder->shipping;
            $item['TaxNet'] = $InternalOrder->TaxNet;
            $item['items'] = $InternalOrder->items;
            $item['approved_by'] = $InternalOrder->approvedBy ? $InternalOrder->approvedBy->username : null;
            $item['approved_at'] = $InternalOrder->approved_at;

            $data[] = $item;
        }

        return response()->json([
            'totalRows' => $totalRows,
            'internal_orders' => $data,
        ]);
    }

    //------------- Store New Internal Order --------------\\

    public function store(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'create', InternalOrder::class);

        request()->validate([
            'internal_order.from_warehouse' => 'required',
            'internal_order.to_warehouse' => 'required',
        ]);

        \DB::transaction(function () use ($request) {
            $order = new InternalOrder;

            $order->date = $request->internal_order['date'];
            $order->Ref = $this->getNumberOrder();
            $order->from_warehouse_id = $request->internal_order['from_warehouse'];
            $order->to_warehouse_id = $request->internal_order['to_warehouse'];
            $order->items = sizeof($request['details']);
            $order->tax_rate = $request->internal_order['tax_rate'] ? $request->internal_order['tax_rate'] : 0;
            $order->TaxNet = $request->internal_order['TaxNet'] ? $request->internal_order['TaxNet'] : 0;
            $order->discount = $request->internal_order['discount'] ? $request->internal_order['discount'] : 0;
            $order->discount_type = $request->internal_order['discount_type'] ? $request->internal_order['discount_type'] : 'fixed';
            $order->shipping = $request->internal_order['shipping'] ? $request->internal_order['shipping'] : 0;
            $order->statut = 'pending'; // Always start as pending
            $order->notes = $request->internal_order['notes'];
            $order->GrandTotal = $request['GrandTotal'];
            $order->user_id = Auth::user()->id;
            $order->save();

            $data = $request['details'];

            foreach ($data as $key => $value) {
                $orderDetails = new InternalOrderDetail;
                $orderDetails->internal_order_id = $order->id;
                $orderDetails->quantity = $value['quantity'];
                $orderDetails->cost = $value['Unit_cost'];
                $orderDetails->TaxNet = $value['tax_percent'];
                $orderDetails->tax_method = $value['tax_method'];
                $orderDetails->discount = $value['discount'];
                $orderDetails->discount_method = $value['discount_method'];
                $orderDetails->total = $value['subtotal'];
                $orderDetails->purchase_unit_id = $value['purchase_unit_id'] ? $value['purchase_unit_id'] : NULL;
                $orderDetails->product_id = $value['product_id'];
                $orderDetails->product_variant_id = $value['product_variant_id'] ? $value['product_variant_id'] : NULL;
                $orderDetails->save();
            }

        }, 10);

        return response()->json(['success' => true, 'message' => 'Internal Order Created Successfully']);
    }

    //------------ function show -----------\\

    public function show(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'view', InternalOrder::class);

        $InternalOrder = InternalOrder::with('details.product.unitPurchase')
            ->where('deleted_at', '=', null)
            ->findOrFail($id);

        $details = array();

        foreach ($InternalOrder['details'] as $detail) {

            if ($detail->product_variant_id) {
                $productsVariants = ProductVariant::where('product_id', $detail->product_id)
                    ->where('id', $detail->product_variant_id)->first();

                $data['code'] = $productsVariants->name . '-' . $detail['product']['code'];
                $data['name'] = '[' . $productsVariants->name . ']' . $detail['product']['name'];

            } else {
                $data['code'] = $detail['product']['code'];
                $data['name'] = $detail['product']['name'];
            }

            $data['quantity'] = $detail->quantity;
            $data['total'] = $detail->total;
            $data['unit_cost'] = $detail->cost;
            $data['discount'] = $detail->discount;
            $data['discount_method'] = $detail->discount_method;
            $data['TaxNet'] = $detail->TaxNet;
            $data['tax_method'] = $detail->tax_method;
            $data['unit_purchase'] = $detail['product']['unitPurchase'] ? $detail['product']['unitPurchase']->ShortName : '';

            $details[] = $data;
        }

        $company = Setting::where('deleted_at', '=', null)->first();

        return response()->json([
            'internal_order' => $InternalOrder,
            'details' => $details,
            'company' => $company,
        ]);

    }

    //------------ function edit -----------\\

    public function edit(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'update', InternalOrder::class);

        $InternalOrder = InternalOrder::with('details.product.unitPurchase')
            ->where('deleted_at', '=', null)
            ->where('statut', '=', 'pending') // Only allow editing pending orders
            ->findOrFail($id);

        $details = array();

        foreach ($InternalOrder['details'] as $detail) {

            if ($detail->product_variant_id) {
                $item_product = product_warehouse::where('product_id', $detail->product_id)
                    ->where('deleted_at', '=', null)
                    ->where('warehouse_id', $InternalOrder->to_warehouse_id)
                    ->where('product_variant_id', $detail->product_variant_id)
                    ->first();

                $productsVariants = ProductVariant::where('product_id', $detail->product_id)
                    ->where('id', $detail->product_variant_id)
                    ->first();

                $item_product ? $data['del'] = 0 : $data['del'] = 1;
                $data['product_variant_id'] = $detail->product_variant_id;
                $data['code'] = $productsVariants->name . '-' . $detail['product']['code'];
                $data['name'] = '[' . $productsVariants->name . ']' . $detail['product']['name'];
                $data['Variant'] = $productsVariants->name;

                $data['stock'] = $item_product ? $item_product->qte : 0;

            } else {
                $item_product = product_warehouse::where('product_id', $detail->product_id)
                    ->where('deleted_at', '=', null)
                    ->where('warehouse_id', $InternalOrder->to_warehouse_id)
                    ->where('product_variant_id', '=', null)
                    ->first();

                $item_product ? $data['del'] = 0 : $data['del'] = 1;
                $data['product_variant_id'] = null;
                $data['Variant'] = null;
                $data['code'] = $detail['product']['code'];
                $data['name'] = $detail['product']['name'];
                $data['stock'] = $item_product ? $item_product->qte : 0;
            }

            $data['id'] = $detail->id;
            $data['detail_id'] = $detail->id;
            $data['quantity'] = $detail->quantity;
            $data['product_id'] = $detail->product_id;
            $data['total'] = $detail->total;
            $data['etat'] = 'current';
            $data['unitPurchase'] = $detail['product']['unitPurchase']->ShortName;
            $data['purchase_unit_id'] = $detail['product']['unitPurchase']->id;
            $data['Unit_cost'] = $detail->cost;
            $data['discount'] = $detail->discount;
            $data['discount_Method'] = $detail->discount_method;
            $data['tax_percent'] = $detail->TaxNet;
            $data['tax_method'] = $detail->tax_method;
            $data['subtotal'] = $detail->total;

            $details[] = $data;
        }

        //get warehouses assigned to user
        $user_auth = auth()->user();
        if ($user_auth->is_all_warehouses) {
            $warehouses = Warehouse::where('deleted_at', '=', null)->get(['id', 'name']);
        } else {
            $warehouses_id = UserWarehouse::where('user_id', $user_auth->id)->pluck('warehouse_id')->toArray();
            $warehouses = Warehouse::where('deleted_at', '=', null)->whereIn('id', $warehouses_id)->get(['id', 'name']);
        }

        $to_warehouses = Warehouse::where('deleted_at', '=', null)->get(['id', 'name']);

        return response()->json([
            'details' => $details,
            'internal_order' => $InternalOrder,
            'warehouses' => $warehouses,
            'to_warehouses' => $to_warehouses,
        ]);
    }

    //------------ function update -----------\\

    public function update(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'update', InternalOrder::class);

        request()->validate([
            'internal_order.from_warehouse' => 'required',
            'internal_order.to_warehouse' => 'required',
        ]);

        \DB::transaction(function () use ($request, $id) {
            $InternalOrder = InternalOrder::findOrFail($id);

            // Only allow updating if status is pending
            if ($InternalOrder->statut !== 'pending') {
                throw new \Exception('Cannot update internal order that is not in pending status');
            }

            $current_InternalOrder = InternalOrder::findOrFail($id);

            // Delete old details
            $old_details_id = InternalOrderDetail::where('internal_order_id', $id)->pluck('id');
            InternalOrderDetail::whereIn('id', $old_details_id)->delete();

            // Update order
            $current_InternalOrder->date = $request->internal_order['date'];
            $current_InternalOrder->from_warehouse_id = $request->internal_order['from_warehouse'];
            $current_InternalOrder->to_warehouse_id = $request->internal_order['to_warehouse'];
            $current_InternalOrder->items = sizeof($request['details']);
            $current_InternalOrder->tax_rate = $request->internal_order['tax_rate'] ? $request->internal_order['tax_rate'] : 0;
            $current_InternalOrder->TaxNet = $request->internal_order['TaxNet'] ? $request->internal_order['TaxNet'] : 0;
            $current_InternalOrder->discount = $request->internal_order['discount'] ? $request->internal_order['discount'] : 0;
            $current_InternalOrder->discount_type = $request->internal_order['discount_type'] ? $request->internal_order['discount_type'] : 'fixed';
            $current_InternalOrder->shipping = $request->internal_order['shipping'] ? $request->internal_order['shipping'] : 0;
            $current_InternalOrder->notes = $request->internal_order['notes'];
            $current_InternalOrder->GrandTotal = $request['GrandTotal'];
            $current_InternalOrder->save();

            // Add new details
            $data = $request['details'];
            foreach ($data as $key => $value) {
                $orderDetails = new InternalOrderDetail;
                $orderDetails->internal_order_id = $id;
                $orderDetails->quantity = $value['quantity'];
                $orderDetails->cost = $value['Unit_cost'];
                $orderDetails->TaxNet = $value['tax_percent'];
                $orderDetails->tax_method = $value['tax_method'];
                $orderDetails->discount = $value['discount'];
                $orderDetails->discount_method = $value['discount_method'];
                $orderDetails->total = $value['subtotal'];
                $orderDetails->purchase_unit_id = $value['purchase_unit_id'] ? $value['purchase_unit_id'] : NULL;
                $orderDetails->product_id = $value['product_id'];
                $orderDetails->product_variant_id = $value['product_variant_id'] ? $value['product_variant_id'] : NULL;
                $orderDetails->save();
            }

        }, 10);

        return response()->json(['success' => true, 'message' => 'Internal Order Updated Successfully']);
    }

    //------------ Delete Internal Order -----------\\

    public function destroy(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'delete', InternalOrder::class);

        \DB::transaction(function () use ($id, $request) {
            $InternalOrder = InternalOrder::findOrFail($id);

            // Only allow deleting if status is pending
            if ($InternalOrder->statut !== 'pending') {
                throw new \Exception('Cannot delete internal order that is not in pending status');
            }

            $InternalOrder->details()->delete();
            $InternalOrder->update([
                'deleted_at' => Carbon::now(),
            ]);

        }, 10);

        return response()->json(['success' => true, 'message' => 'Internal Order Deleted Successfully']);
    }

    //-------------- Delete by selection  ---------------\\

    public function delete_by_selection(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'delete', InternalOrder::class);

        $selectedIds = $request->selectedIds;

        foreach ($selectedIds as $id) {
            $InternalOrder = InternalOrder::findOrFail($id);

            // Only allow deleting if status is pending
            if ($InternalOrder->statut === 'pending') {
                $InternalOrder->details()->delete();
                $InternalOrder->update([
                    'deleted_at' => Carbon::now(),
                ]);
            }
        }

        return response()->json(['success' => true]);
    }

    //------------- get Number Order  -----------\\

    public function getNumberOrder()
    {
        $last = DB::table('internal_orders')->latest('id')->first();

        if ($last) {
            $item = $last->Ref;
            $nwMsg = explode("_", $item);
            $inMsg = $nwMsg[1] + 1;
            $code = $nwMsg[0] . '_' . $inMsg;
        } else {
            $code = 'IO_1111';
        }
        return $code;
    }

    //------------- Create Internal Order (Get Data) -----------\\

    public function create(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'create', InternalOrder::class);

        // Get all warehouses for both dropdowns
        // Users can order from any warehouse to any warehouse
        $warehouses = Warehouse::where('deleted_at', '=', null)->get(['id', 'name']);
        $to_warehouses = Warehouse::where('deleted_at', '=', null)->get(['id', 'name']);

        return response()->json(['warehouses' => $warehouses, 'to_warehouses' => $to_warehouses]);
    }

    //------------- Approve Internal Order -----------\\

    public function approve(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'approve', InternalOrder::class);

        \DB::transaction(function () use ($request, $id) {
            $InternalOrder = InternalOrder::findOrFail($id);

            // Only allow approving if status is pending
            if ($InternalOrder->statut !== 'pending') {
                throw new \Exception('Cannot approve internal order that is not in pending status');
            }

            // Apply discount if provided
            if ($request->has('discount')) {
                $InternalOrder->discount = $request->discount;
                $InternalOrder->discount_type = $request->discount_type ?? 'fixed';

                // Recalculate GrandTotal
                $subtotal = 0;
                foreach ($InternalOrder->details as $detail) {
                    $subtotal += $detail->total;
                }

                $discount_amount = 0;
                if ($InternalOrder->discount_type === 'percent') {
                    $discount_amount = ($subtotal * $InternalOrder->discount) / 100;
                } else {
                    $discount_amount = $InternalOrder->discount;
                }

                $InternalOrder->GrandTotal = ($subtotal + $InternalOrder->TaxNet + $InternalOrder->shipping) - $discount_amount;
            }

            $InternalOrder->statut = 'approved';
            $InternalOrder->approved_by = Auth::user()->id;
            $InternalOrder->approved_at = Carbon::now();
            $InternalOrder->save();

        }, 10);

        return response()->json(['success' => true, 'message' => 'Internal Order Approved Successfully']);
    }

    //------------- Reject Internal Order -----------\\

    public function reject(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'approve', InternalOrder::class);

        \DB::transaction(function () use ($request, $id) {
            $InternalOrder = InternalOrder::findOrFail($id);

            // Only allow rejecting if status is pending
            if ($InternalOrder->statut !== 'pending') {
                throw new \Exception('Cannot reject internal order that is not in pending status');
            }

            $InternalOrder->statut = 'rejected';
            $InternalOrder->approved_by = Auth::user()->id;
            $InternalOrder->approved_at = Carbon::now();

            // Add rejection note if provided
            if ($request->has('rejection_note')) {
                $InternalOrder->notes = ($InternalOrder->notes ? $InternalOrder->notes . "\n\n" : '') .
                    "REJECTED: " . $request->rejection_note;
            }

            $InternalOrder->save();

        }, 10);

        return response()->json(['success' => true, 'message' => 'Internal Order Rejected Successfully']);
    }

    //------------- Ship Internal Order (Create Transfer) -----------\\

    public function ship(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'ship', InternalOrder::class);

        \DB::transaction(function () use ($request, $id) {
            $InternalOrder = InternalOrder::findOrFail($id);

            // Only allow shipping if status is approved
            if ($InternalOrder->statut !== 'approved') {
                throw new \Exception('Cannot ship internal order that is not approved');
            }

            // Create a new Transfer
            $transfer = new Transfer;
            $transfer->date = Carbon::now()->format('Y-m-d');
            $transfer->Ref = $this->getTransferNumberOrder();
            $transfer->from_warehouse_id = $InternalOrder->to_warehouse_id; // Main warehouse
            $transfer->to_warehouse_id = $InternalOrder->from_warehouse_id; // Requesting warehouse
            $transfer->items = $InternalOrder->items;
            $transfer->tax_rate = $InternalOrder->tax_rate;
            $transfer->TaxNet = $InternalOrder->TaxNet;
            $transfer->discount = $InternalOrder->discount;
            $transfer->shipping = $InternalOrder->shipping;
            $transfer->statut = 'completed'; // Auto-complete the transfer
            $transfer->notes = "Auto-generated from Internal Order: " . $InternalOrder->Ref;
            $transfer->GrandTotal = $InternalOrder->GrandTotal;
            $transfer->user_id = Auth::user()->id;
            $transfer->save();

            // Copy details from internal order to transfer
            foreach ($InternalOrder->details as $detail) {
                $transferDetail = new TransferDetail;
                $transferDetail->transfer_id = $transfer->id;
                $transferDetail->product_id = $detail->product_id;
                $transferDetail->product_variant_id = $detail->product_variant_id;
                $transferDetail->purchase_unit_id = $detail->purchase_unit_id;
                $transferDetail->quantity = $detail->quantity;
                $transferDetail->cost = $detail->cost;
                $transferDetail->TaxNet = $detail->TaxNet;
                $transferDetail->tax_method = $detail->tax_method;
                $transferDetail->discount = $detail->discount;
                $transferDetail->discount_method = $detail->discount_method;
                $transferDetail->total = $detail->total;
                $transferDetail->save();

                // Update stock quantities
                $unit = Unit::find($detail->purchase_unit_id);

                if ($detail->product_variant_id) {
                    // Handle variant products
                    // Decrease stock from main warehouse (to_warehouse)
                    $product_warehouse_from = product_warehouse::where('deleted_at', '=', null)
                        ->where('warehouse_id', $InternalOrder->to_warehouse_id)
                        ->where('product_id', $detail->product_id)
                        ->where('product_variant_id', $detail->product_variant_id)
                        ->first();

                    if ($unit && $product_warehouse_from) {
                        if ($unit->operator == '/') {
                            $product_warehouse_from->qte -= $detail->quantity / $unit->operator_value;
                        } else {
                            $product_warehouse_from->qte -= $detail->quantity * $unit->operator_value;
                        }
                        $product_warehouse_from->save();
                    }

                    // Increase stock in requesting warehouse (from_warehouse)
                    $product_warehouse_to = product_warehouse::where('deleted_at', '=', null)
                        ->where('warehouse_id', $InternalOrder->from_warehouse_id)
                        ->where('product_id', $detail->product_id)
                        ->where('product_variant_id', $detail->product_variant_id)
                        ->first();

                    if ($unit && $product_warehouse_to) {
                        if ($unit->operator == '/') {
                            $product_warehouse_to->qte += $detail->quantity / $unit->operator_value;
                        } else {
                            $product_warehouse_to->qte += $detail->quantity * $unit->operator_value;
                        }
                        $product_warehouse_to->save();
                    }

                } else {
                    // Handle non-variant products
                    // Decrease stock from main warehouse
                    $product_warehouse_from = product_warehouse::where('deleted_at', '=', null)
                        ->where('warehouse_id', $InternalOrder->to_warehouse_id)
                        ->where('product_id', $detail->product_id)
                        ->first();

                    if ($unit && $product_warehouse_from) {
                        if ($unit->operator == '/') {
                            $product_warehouse_from->qte -= $detail->quantity / $unit->operator_value;
                        } else {
                            $product_warehouse_from->qte -= $detail->quantity * $unit->operator_value;
                        }
                        $product_warehouse_from->save();
                    }

                    // Increase stock in requesting warehouse
                    $product_warehouse_to = product_warehouse::where('deleted_at', '=', null)
                        ->where('warehouse_id', $InternalOrder->from_warehouse_id)
                        ->where('product_id', $detail->product_id)
                        ->first();

                    if ($unit && $product_warehouse_to) {
                        if ($unit->operator == '/') {
                            $product_warehouse_to->qte += $detail->quantity / $unit->operator_value;
                        } else {
                            $product_warehouse_to->qte += $detail->quantity * $unit->operator_value;
                        }
                        $product_warehouse_to->save();
                    }
                }
            }

            // Update internal order status and link to transfer
            $InternalOrder->statut = 'shipped';
            $InternalOrder->transfer_id = $transfer->id;
            $InternalOrder->save();

        }, 10);

        return response()->json(['success' => true, 'message' => 'Internal Order Shipped Successfully']);
    }

    //------------- Receive Internal Order -----------\\

    public function receive(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'receive', InternalOrder::class);

        \DB::transaction(function () use ($request, $id) {
            $InternalOrder = InternalOrder::findOrFail($id);

            // Only allow receiving if status is shipped
            if ($InternalOrder->statut !== 'shipped') {
                throw new \Exception('Cannot receive internal order that is not shipped');
            }

            $InternalOrder->statut = 'received';
            $InternalOrder->save();

        }, 10);

        return response()->json(['success' => true, 'message' => 'Internal Order Received Successfully']);
    }

    //------------- Get Transfer Number Order -----------\\

    private function getTransferNumberOrder()
    {
        $last = DB::table('transfers')->latest('id')->first();

        if ($last) {
            $item = $last->Ref;
            $nwMsg = explode("_", $item);
            $inMsg = $nwMsg[1] + 1;
            $code = $nwMsg[0] . '_' . $inMsg;
        } else {
            $code = 'TR_1111';
        }
        return $code;
    }

    //------------- Generate PDF -----------\\

    public function internal_order_pdf(Request $request, $id)
    {
        $InternalOrder = InternalOrder::with('details.product.unitPurchase')
            ->where('deleted_at', '=', null)
            ->findOrFail($id);

        $details = array();

        foreach ($InternalOrder['details'] as $detail) {

            if ($detail->product_variant_id) {
                $productsVariants = ProductVariant::where('product_id', $detail->product_id)
                    ->where('id', $detail->product_variant_id)->first();

                $data['code'] = $productsVariants->name . '-' . $detail['product']['code'];
                $data['name'] = '[' . $productsVariants->name . ']' . $detail['product']['name'];

            } else {
                $data['code'] = $detail['product']['code'];
                $data['name'] = $detail['product']['name'];
            }

            $data['quantity'] = $detail->quantity;
            $data['total'] = $detail->total;
            $data['unit_cost'] = $detail->cost;
            $data['discount'] = $detail->discount;
            $data['discount_method'] = $detail->discount_method;
            $data['TaxNet'] = $detail->TaxNet;
            $data['tax_method'] = $detail->tax_method;
            $data['unit_purchase'] = $detail['product']['unitPurchase'] ? $detail['product']['unitPurchase']->ShortName : '';

            $details[] = $data;
        }

        $company = Setting::where('deleted_at', '=', null)->first();

        $pdf = PDF::loadView('pdf.internal_order_pdf', [
            'internal_order' => $InternalOrder,
            'details' => $details,
            'company' => $company,
        ]);

        return $pdf->download('internal_order_' . $InternalOrder->Ref . '.pdf');
    }

}
