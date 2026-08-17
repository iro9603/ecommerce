<?php

return [
    /*
     * Automatic approval is still opt-in per store. Disabling this switch
     * forces every otherwise eligible submission into the manual queue.
     */
    'automatic_approval_enabled' => (bool) env('PRODUCT_AUTOMATIC_APPROVAL_ENABLED', true),

    'maximum_automatic_risk_score' => (int) env('PRODUCT_MAXIMUM_AUTOMATIC_RISK_SCORE', 20),
];
