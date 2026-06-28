<?php

return [

    'payments' => [
        'created' => 'Charge created and is being processed.',
        'idempotency_conflict' => 'This Idempotency-Key was already used with a different request.',
        'idempotency_key_required' => 'An Idempotency-Key header is required for this request.',
    ],

    'errors' => [
        'too_many_requests' => 'Too many requests. Please retry later.',
    ],

];
