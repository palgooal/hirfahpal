{{--
    ORD-01D, shared since ORD-02A (step 2) by the orders list and the order details page: the outcome of an order
    action (resources/js/vendor-order-actions.js), announced: a success politely, a failure at once. Both stay in the
    page (empty when there is nothing to say) so that a new text is read out. Place it inside the element the action
    script is given as its root.
--}}
<p class="vendor-form-status vendor-order-feedback" data-tone="success" role="status" data-order-success></p>
<p class="vendor-form-status vendor-order-feedback" data-tone="error" role="alert" data-order-failure></p>
