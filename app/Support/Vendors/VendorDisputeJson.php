<?php

namespace App\Support\Vendors;

use App\Models\Customer;
use App\Models\Dispute;
use App\Models\Order;
use App\Models\VendorOrder;
use App\Support\Orders\VendorOrderJson;
use Illuminate\Support\Arr;

/**
 * E2-MVP (VEN-BE-023, the vendor disputes list and detail): a dispute on one of the vendor's orders, as the vendor's
 * JSON publishes it. What the customer raised (type, subject, description), its status, the admin's resolution (the
 * shareable outcome, required when a dispute is resolved or closed) and when it was resolved, the customer's name, the
 * parent order's id and number, and the vendor's own order with its items, quantities and amounts (VendorOrderJson).
 * Never the resolving admin's identity, the customer's account, or the parent order's totals, notes or payment data.
 */
class VendorDisputeJson
{
    private const DISPUTE = ['id', 'vendor_order_id', 'type', 'status', 'subject', 'description', 'resolution', 'resolved_at', 'created_at', 'updated_at'];

    /** @return array<string, mixed> */
    public static function dispute(Dispute $dispute): array
    {
        $data = Arr::only($dispute->attributesToArray(), self::DISPUTE);

        if ($dispute->relationLoaded('order')) {
            $data['order'] = $dispute->order instanceof Order ? ['id' => $dispute->order->id, 'number' => $dispute->order->number] : null;
        }
        if ($dispute->relationLoaded('vendorOrder')) {
            $data['vendor_order'] = $dispute->vendorOrder instanceof VendorOrder ? VendorOrderJson::vendorOrder($dispute->vendorOrder) : null;
        }
        if ($dispute->relationLoaded('customer')) {
            $data['customer'] = $dispute->customer instanceof Customer ? ['name' => $dispute->customer->name] : null;
        }

        return $data;
    }
}
