<?php

return [

    'payments' => [
        'created' => 'Charge created and is being processed.',
        'idempotency_conflict' => 'This Idempotency-Key was already used with a different request.',
        'idempotency_key_required' => 'An Idempotency-Key header is required for this request.',
    ],

    'refunds' => [
        'created' => 'Refund created and is being processed.',
        'idempotency_key_required' => 'An Idempotency-Key header is required for this request.',
        'exceeds_charge' => 'A refund cannot exceed the original charge amount.',
        'charge_not_refundable' => 'The original charge is not in a refundable (succeeded) state.',
        'currency_mismatch' => 'The refund currency must match the original charge currency.',
    ],

    'errors' => [
        'too_many_requests' => 'Too many requests. Please retry later.',
    ],

];
