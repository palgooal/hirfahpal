{{--
    ORD-01D, shared since ORD-02A (step 2) by the orders list and the order details page: Accept and Reject ask first,
    in a native modal dialog as the media library's (the rest of the page is inert while it is open; Escape and Cancel
    close it and focus returns to the action button). The title's ":number" becomes the order number, isolated left to
    right, when it opens (resources/js/vendor-order-actions.js). Place it inside the element the action script is given
    as its root, once per page (the ids are fixed).
--}}
<dialog class="vendor-media-dialog vendor-order-dialog" aria-labelledby="vendor-order-accept-title" aria-describedby="vendor-order-accept-description" data-order-dialog="accept">
    <form class="vendor-order-dialog-form" novalidate>
        <div class="vendor-media-dialog-header">
            <h2 class="vendor-media-dialog-title" id="vendor-order-accept-title" data-order-dialog-title data-template="{{ t('vendor.Order_Accept_Title', 'Accept order :number?') }}"></h2>
        </div>
        <div class="vendor-order-dialog-body">
            <p id="vendor-order-accept-description">{{ t('vendor.Order_Accept_Description', 'The order will be accepted and the stock reserved for it will be deducted from your stock. You cannot undo this from your dashboard.') }}</p>
            <p class="vendor-field-error" role="alert" data-order-dialog-error hidden></p>
        </div>
        <div class="vendor-order-dialog-actions">
            <button type="button" class="btn btn-light-secondary" data-order-dialog-cancel>{{ t('vendor.Cancel', 'Cancel') }}</button>
            <button type="submit" class="btn btn-primary" data-order-dialog-confirm>{{ t('vendor.Order_Accept_Confirm', 'Confirm acceptance') }}</button>
        </div>
    </form>
</dialog>
<dialog class="vendor-media-dialog vendor-order-dialog" aria-labelledby="vendor-order-reject-title" aria-describedby="vendor-order-reject-description" data-order-dialog="reject">
    <form class="vendor-order-dialog-form" novalidate>
        <div class="vendor-media-dialog-header">
            <h2 class="vendor-media-dialog-title" id="vendor-order-reject-title" data-order-dialog-title data-template="{{ t('vendor.Order_Reject_Title', 'Reject order :number?') }}"></h2>
        </div>
        <div class="vendor-order-dialog-body">
            <p id="vendor-order-reject-description">{{ t('vendor.Order_Reject_Description', 'The order will be rejected and the stock reserved for it released. The reason is saved with the order.') }}</p>
            <div class="vendor-order-dialog-field">
                <label class="form-label" for="vendor-order-reject-reason">{{ t('vendor.Order_Reject_Reason', 'Reason for rejection') }}</label>
                <textarea class="form-control" id="vendor-order-reject-reason" name="rejection_reason" rows="4" maxlength="1000" required aria-required="true" aria-invalid="false" aria-describedby="vendor-order-reject-hint vendor-order-reject-error" data-order-reject-reason></textarea>
                <div class="vendor-order-dialog-meta">
                    <p id="vendor-order-reject-hint">{{ str_replace(':max', '1000', t('vendor.Order_Reject_Reason_Hint', 'Required. Up to :max characters.')) }}</p>
                    <p aria-hidden="true" data-order-reject-count>0 / 1000</p>
                </div>
                <p class="vendor-field-error" id="vendor-order-reject-error" data-order-reject-error hidden></p>
            </div>
            <p class="vendor-field-error" role="alert" data-order-dialog-error hidden></p>
        </div>
        <div class="vendor-order-dialog-actions">
            <button type="button" class="btn btn-light-secondary" data-order-dialog-cancel>{{ t('vendor.Cancel', 'Cancel') }}</button>
            <button type="submit" class="btn btn-danger" data-order-dialog-confirm>{{ t('vendor.Order_Reject_Confirm', 'Reject order') }}</button>
        </div>
    </form>
</dialog>
