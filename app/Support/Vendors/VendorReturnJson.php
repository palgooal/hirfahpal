<?php

namespace App\Support\Vendors;

use App\Models\Customer;
use App\Models\Order;
use App\Models\ReturnRequest;
use App\Models\VendorOrder;
use App\Support\Orders\VendorOrderJson;
use Illuminate\Support\Arr;

/**
 * E2-MVP (VEN-BE-023, the vendor returns list and detail): a return request on one of the vendor's orders, as the
 * vendor's JSON publishes it. The request (its status, the customer's reason and when the admin decided), the
 * customer's name, the parent order's id and number, and the vendor's own order with its items, quantities and amounts
 * (VendorOrderJson). Never the admin's note or identity, the customer's account, or the parent order's totals, notes or
 * payment data.
 */
class VendorReturnJson
{
    private const RETURN_REQUEST = ['id', 'vendor_order_id', 'status', 'reason', 'reviewed_at', 'created_at', 'updated_at'];

    /** @return array<string, mixed> */
    public static function returnRequest(ReturnRequest $returnRequest): array
    {
        $data = Arr::only($returnRequest->attributesToArray(), self::RETURN_REQUEST);

        if ($returnRequest->relationLoaded('order')) {
            $data['order'] = $returnRequest->order instanceof Order ? ['id' => $returnRequest->order->id, 'number' => $returnRequest->order->number] : null;
        }
        if ($returnRequest->relationLoaded('vendorOrder')) {
            $data['vendor_order'] = $returnRequest->vendorOrder instanceof VendorOrder ? VendorOrderJson::vendorOrder($returnRequest->vendorOrder) : null;
        }
        if ($returnRequest->relationLoaded('customer')) {
            $data['customer'] = $returnRequest->customer instanceof Customer ? ['name' => $returnRequest->customer->name] : null;
        }

        return $data;
    }
}
