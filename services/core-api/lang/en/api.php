<?php

return [
    'errors' => [
        'unauthenticated' => 'Unauthenticated.',
        'forbidden' => 'This action is unauthorized.',
        'not_found' => 'Resource not found.',
        'too_many_requests' => 'Too many requests.',
        'server_error' => 'Server error.',
    ],

    'health' => [
        'ok' => 'core-api is healthy.',
    ],

    'validation' => [
        'timezone' => 'The timezone must be a valid IANA time zone identifier.',
        'ends_after_starts' => 'The end time must be after the start time.',
        'sales_ends_after_starts' => 'The sales end time must be after the sales start time.',
        'group_discount_fraction' => 'The group discount must be a fraction between 0 and 1 (e.g. 0.10).',
    ],

    'auth' => [
        'registered' => 'Registration successful.',
        'logged_in' => 'Login successful.',
        'logged_out' => 'Logout successful.',
        'me' => 'Authenticated user retrieved.',
        'invalid_credentials' => 'The provided credentials are incorrect.',
        'role_not_self_assignable' => 'You may only register as a vendor or an attendee.',
        'business_name_required' => 'A business name is required to register as a vendor.',
    ],

    'events' => [
        'listed' => 'Events retrieved.',
        'retrieved' => 'Event retrieved.',
        'created' => 'Event created.',
        'updated' => 'Event updated.',
        'deleted' => 'Event deleted.',
        'published' => 'Event published.',
        'cancelled' => 'Event cancelled.',
        'invalid_transition' => 'That event status transition is not allowed.',
        'not_verified_vendor' => 'Only verified vendors can publish events.',
        'capacity_below_allocated' => 'Capacity cannot be lower than the tickets already allocated.',
    ],

    'vendors' => [
        'kyc_submitted' => 'KYC submitted for review.',
        'pending_listed' => 'Vendors pending KYC review retrieved.',
        'kyc_verified' => 'Vendor KYC verified.',
        'kyc_rejected' => 'Vendor KYC rejected.',
        'invalid_kyc_transition' => 'That KYC status change is not allowed.',
        'already_submitted' => 'KYC has already been submitted and is awaiting review.',
    ],

    'orders' => [
        'created' => 'Order created. Your tickets are held for 15 minutes — complete payment to confirm.',
        'idempotency_key_required' => 'An Idempotency-Key header is required for checkout.',
        'idempotency_conflict' => 'This Idempotency-Key was already used with a different request.',
        'tickets_unavailable' => 'Only :available ticket(s) remain (you requested :requested).',
        'mixed_currency' => 'All tickets in one order must share the same currency.',
        'event_not_purchasable' => 'Tickets for this event are not on sale.',
        'sales_window_closed' => 'The sales window for this ticket type is not open.',
        'lock_unavailable' => 'This ticket type is busy; please retry in a moment.',
        'attendee_profile_required' => 'Your attendee profile is incomplete; please contact support.',
    ],

    'payments' => [
        'webhook_processed' => 'Payment result processed.',
        'webhook_amount_mismatch' => 'The webhook amount or currency does not match the order.',
    ],

    'ticket_types' => [
        'listed' => 'Ticket types retrieved.',
        'retrieved' => 'Ticket type retrieved.',
        'created' => 'Ticket type created.',
        'updated' => 'Ticket type updated.',
        'deleted' => 'Ticket type deleted.',
        'capacity_exceeded' => 'Ticket type inventory would exceed the event capacity.',
        'quantity_below_sold' => 'Quantity cannot be lower than the number already sold.',
    ],
];
