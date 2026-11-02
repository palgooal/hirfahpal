<?php

namespace App\Support\VendorDashboard;

/**
 * ORD-02A (step 2): the vendor order texts the orders list and the order details page share, so both use the same
 * translation keys and wording (each t() call is the one the list made before; there are no new keys). Display only:
 * the statuses are the values the orders endpoint returns, and nothing here defines or changes the order lifecycle.
 */
final class VendorOrderTexts
{
    /**
     * Status labels by stored status value (ORD-01B / ORD-01C; the dashboard home's labels).
     *
     * @return array<string, string>
     */
    public static function statuses(): array
    {
        return [
            'pending' => t('vendor.Order_Status_Pending', 'Pending'),
            'accepted' => t('vendor.Order_Status_Accepted', 'Accepted'),
            'preparing' => t('vendor.Order_Status_Preparing', 'Preparing'),
            'ready_for_delivery' => t('vendor.Order_Status_Ready_For_Delivery', 'Ready for delivery'),
            'assigned' => t('vendor.Order_Status_Assigned', 'Assigned to a driver'),
            'out_for_delivery' => t('vendor.Order_Status_Out_For_Delivery', 'Out for delivery'),
            'delivered' => t('vendor.Order_Status_Delivered', 'Delivered'),
            'completed' => t('vendor.Order_Status_Completed', 'Completed'),
            'rejected' => t('vendor.Order_Status_Rejected', 'Rejected'),
            'cancelled' => t('vendor.Order_Status_Cancelled', 'Cancelled'),
        ];
    }

    /**
     * Each order action's label and accessible name (ORD-01C; ":number" is the order number).
     *
     * @return array<string, array{label: string, named: string}>
     */
    public static function actions(): array
    {
        return [
            'accept' => ['label' => t('vendor.Order_Action_Accept', 'Accept'), 'named' => t('vendor.Order_Action_Accept_Named', 'Accept order :number')],
            'reject' => ['label' => t('vendor.Order_Action_Reject', 'Reject'), 'named' => t('vendor.Order_Action_Reject_Named', 'Reject order :number')],
            'preparing' => ['label' => t('vendor.Order_Action_Preparing', 'Preparing'), 'named' => t('vendor.Order_Action_Preparing_Named', 'Mark order :number as preparing')],
            'ready' => ['label' => t('vendor.Order_Action_Ready', 'Ready'), 'named' => t('vendor.Order_Action_Ready_Named', 'Mark order :number as ready for delivery')],
        ];
    }

    /** The generic text of an order action that failed (ORD-01C). */
    public static function actionError(): string
    {
        return t('vendor.Order_Action_Error', 'The order could not be updated. Please try again.');
    }

    /**
     * The action outcomes for vendor-order-actions.js (ORD-01D; ":number" is the order number, ":max" the reason limit).
     * A server's own message is never shown: each outcome has its text here.
     *
     * @return array{success: array<string, string>, errors: array<string, string>, reason: array<string, string>}
     */
    public static function outcomes(): array
    {
        return [
            'success' => [
                'accept' => t('vendor.Order_Action_Success_Accept', 'Order :number was accepted.'),
                'reject' => t('vendor.Order_Action_Success_Reject', 'Order :number was rejected.'),
                'preparing' => t('vendor.Order_Action_Success_Preparing', 'Order :number is now being prepared.'),
                'ready' => t('vendor.Order_Action_Success_Ready', 'Order :number is ready for delivery.'),
            ],
            'errors' => [
                'conflict' => t('vendor.Order_Action_Conflict', 'This order could not be updated because it has changed or is no longer available. The list has been refreshed.'),
                'conflictAccept' => t('vendor.Order_Action_Conflict_Accept', 'This order could not be accepted. It may have changed, or the stock reserved for it may no longer be available. The list has been refreshed.'),
                'session' => t('vendor.Order_Action_Session', 'Your session has ended. Sign in again, then try again.'),
                'forbidden' => t('vendor.Order_Action_Forbidden', 'You cannot update this order.'),
                'network' => t('vendor.Order_Action_Network', 'The connection failed. Check your connection and try again.'),
                'failed' => self::actionError(),
            ],
            'reason' => [
                'required' => t('vendor.Order_Reject_Reason_Required', 'Enter the reason for rejecting this order.'),
                'too_long' => t('vendor.Order_Reject_Reason_Too_Long', 'The reason must be :max characters or fewer.'),
                'invalid' => t('vendor.Order_Reject_Reason_Invalid', 'The reason was not accepted. Check it and try again.'),
            ],
        ];
    }
}
