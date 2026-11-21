<?php

namespace App\Support\Vendors;

use App\Models\Commission;
use App\Models\Order;
use App\Models\VendorOrder;
use Illuminate\Support\Arr;

/**
 * E2-MVP (VEN-BE-023, the vendor commissions list): a commission charged on one of the vendor's orders, as the vendor's
 * JSON publishes it. The record as calculated (the rate, the base amount and the commission amount, its status and when
 * it was earned) and the vendor's own order amounts, so the vendor can check it; the parent order by id and number only.
 * Never the parent order's totals (the other vendors' share), notes or payment data, the customer's address reference,
 * or the vendor id. Nothing is calculated here: no net amount and no payout.
 */
class VendorCommissionJson
{
    private const COMMISSION = ['id', 'vendor_order_id', 'rate', 'base_amount', 'amount', 'status', 'earned_at', 'created_at', 'updated_at'];

    private const VENDOR_ORDER = ['id', 'number', 'status', 'subtotal', 'delivery_fee', 'total'];

    /** @return array<string, mixed> */
    public static function commission(Commission $commission): array
    {
        $data = Arr::only($commission->attributesToArray(), self::COMMISSION);

        if ($commission->relationLoaded('vendorOrder')) {
            $vendorOrder = $commission->vendorOrder;
            $data['vendor_order'] = $vendorOrder instanceof VendorOrder ? self::vendorOrder($vendorOrder) : null;
        }

        return $data;
    }

    /** @return array<string, mixed> */
    private static function vendorOrder(VendorOrder $vendorOrder): array
    {
        $data = Arr::only($vendorOrder->attributesToArray(), self::VENDOR_ORDER);

        if ($vendorOrder->relationLoaded('order')) {
            $data['order'] = $vendorOrder->order instanceof Order ? ['id' => $vendorOrder->order->id, 'number' => $vendorOrder->order->number] : null;
        }

        return $data;
    }
}
