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
        'created' => 'Event created.',
        'updated' => 'Event updated.',
        'deleted' => 'Event deleted.',
        'published' => 'Event published.',
        'cancelled' => 'Event cancelled.',
        'invalid_transition' => 'That event status transition is not allowed.',
        'not_verified_vendor' => 'Only verified vendors can publish events.',
    ],

    'ticket_types' => [
        'created' => 'Ticket type created.',
        'updated' => 'Ticket type updated.',
        'deleted' => 'Ticket type deleted.',
        'capacity_exceeded' => 'Ticket type inventory would exceed the event capacity.',
    ],
];
