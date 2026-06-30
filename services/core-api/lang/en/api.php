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
        'sales_end_before_event_start' => 'Ticket sales must close by the time the event starts.',
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
        'listed' => 'Orders retrieved.',
        'retrieved' => 'Order retrieved.',
        'created' => 'Order created. Your tickets are held for 15 minutes — complete payment to confirm.',
        'idempotency_key_required' => 'An Idempotency-Key header is required for checkout.',
        'idempotency_conflict' => 'This Idempotency-Key was already used with a different request.',
        'tickets_unavailable' => 'Only :available ticket(s) remain (you requested :requested).',
        'mixed_currency' => 'All tickets in one order must share the same currency.',
        'event_not_purchasable' => 'Tickets for this event are not on sale.',
        'sales_window_closed' => 'The sales window for this ticket type is not open.',
        'lock_unavailable' => 'This ticket type is busy; please retry in a moment.',
        'attendee_profile_required' => 'Your attendee profile is incomplete; please contact support.',
        'payment_initiated' => 'Payment initiated. Your order will be confirmed shortly.',
        'not_payable' => 'This order is not in a payable state.',
        'validation' => [
            'status_invalid' => 'The status must be a valid order status.',
            'per_page_integer' => 'The per_page value must be a whole number.',
            'per_page_min' => 'The per_page value must be at least 1.',
            'per_page_max' => 'The per_page value may not exceed 100.',
        ],
    ],

    'payments' => [
        'webhook_processed' => 'Payment result processed.',
        'webhook_amount_mismatch' => 'The webhook amount or currency does not match the order.',
    ],

    'refunds' => [
        'requested' => 'Refund requested. It will be processed shortly.',
        'webhook_processed' => 'Refund result processed.',
        'webhook_amount_mismatch' => 'The refund webhook amount or currency does not match the open refund.',
        'not_allowed' => 'This order cannot be refunded.',
        'no_payment' => 'No completed payment was found for this order.',
        'not_eligible_window' => 'This request is outside the refund window, so no automatic refund applies.',
        'dispute_opened' => 'Your request is outside the automatic refund window. A dispute has been opened and an admin will review it.',
        'already_refunded' => 'This order has already been fully refunded.',
        'invalid_item' => 'A selected ticket does not belong to this order.',
        'invalid_quantity' => 'The selected quantity exceeds the tickets on this order.',
        'checked_in' => 'This ticket has already been checked in and cannot be refunded.',
    ],

    'disputes' => [
        'listed' => 'Disputes retrieved.',
        'resolved' => 'Dispute resolved. Refund has been queued.',
        'rejected' => 'Dispute rejected.',
        'resolution_required' => 'A resolution note is required when rejecting a dispute.',
    ],

    'payouts' => [
        'listed' => 'Payouts retrieved.',
        'built' => ':count payout(s) built for this batch.',
        'requested' => 'Payout request created. An admin will process it shortly.',
        'preview_ready' => 'Payout preview calculated.',
        'nothing_eligible' => 'No eligible settled orders meet the minimum payout threshold.',
        'vendor_not_found' => 'The specified vendor does not exist.',
        'execution_queued' => 'Payout execution queued. The result will arrive via webhook.',
        'not_executable' => 'Payout must be pending or approved to execute.',
        'webhook_processed' => 'Payout result processed.',
        'webhook_amount_mismatch' => 'The payout webhook amount does not match the recorded payable amount.',
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
