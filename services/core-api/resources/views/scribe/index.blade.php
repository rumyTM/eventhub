<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta content="IE=edge,chrome=1" http-equiv="X-UA-Compatible">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <title>EventHub Core API</title>

    <link href="https://fonts.googleapis.com/css?family=Open+Sans&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="{{ asset("/vendor/scribe/css/theme-default.style.css") }}" media="screen">
    <link rel="stylesheet" href="{{ asset("/vendor/scribe/css/theme-default.print.css") }}" media="print">

    <script src="https://cdn.jsdelivr.net/npm/lodash@4.17.10/lodash.min.js"></script>

    <link rel="stylesheet"
          href="https://unpkg.com/@highlightjs/cdn-assets@11.6.0/styles/obsidian.min.css">
    <script src="https://unpkg.com/@highlightjs/cdn-assets@11.6.0/highlight.min.js"></script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jets/0.14.1/jets.min.js"></script>

    <style id="language-style">
        /* starts out as display none and is replaced with js later  */
                    body .content .bash-example code { display: none; }
                    body .content .javascript-example code { display: none; }
                    body .content .php-example code { display: none; }
            </style>

    <script>
        var tryItOutBaseUrl = "http://localhost:8000";
        var useCsrf = Boolean();
        var csrfUrl = "/sanctum/csrf-cookie";
    </script>
    <script src="{{ asset("/vendor/scribe/js/tryitout-5.11.0.js") }}"></script>

    <script src="{{ asset("/vendor/scribe/js/theme-default-5.11.0.js") }}"></script>

</head>

<body data-languages="[&quot;bash&quot;,&quot;javascript&quot;,&quot;php&quot;]">

<a href="#" id="nav-button">
    <span>
        MENU
        <img src="{{ asset("/vendor/scribe/images/navbar.png") }}" alt="navbar-image"/>
    </span>
</a>
<div class="tocify-wrapper">
    
            <div class="lang-selector">
                                            <button type="button" class="lang-button" data-language-name="bash">bash</button>
                                            <button type="button" class="lang-button" data-language-name="javascript">javascript</button>
                                            <button type="button" class="lang-button" data-language-name="php">php</button>
                    </div>
    
    <div class="search">
        <input type="text" class="search" id="input-search" placeholder="Search">
    </div>

    <div id="toc">
                    <ul id="tocify-header-introduction" class="tocify-header">
                <li class="tocify-item level-1" data-unique="introduction">
                    <a href="#introduction">Introduction</a>
                </li>
                                    <ul id="tocify-subheader-introduction" class="tocify-subheader">
                                                    <li class="tocify-item level-2" data-unique="authentication">
                                <a href="#authentication">Authentication</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="demo-credentials-seeded">
                                <a href="#demo-credentials-seeded">Demo credentials (seeded)</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="idempotency">
                                <a href="#idempotency">Idempotency</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="response-envelope">
                                <a href="#response-envelope">Response envelope</a>
                            </li>
                                                                        </ul>
                            </ul>
                    <ul id="tocify-header-authenticating-requests" class="tocify-header">
                <li class="tocify-item level-1" data-unique="authenticating-requests">
                    <a href="#authenticating-requests">Authenticating requests</a>
                </li>
                            </ul>
                    <ul id="tocify-header-public" class="tocify-header">
                <li class="tocify-item level-1" data-unique="public">
                    <a href="#public">Public</a>
                </li>
                                    <ul id="tocify-subheader-public" class="tocify-subheader">
                                                    <li class="tocify-item level-2" data-unique="public-auth">
                                <a href="#public-auth">Auth</a>
                            </li>
                                                            <ul id="tocify-subheader-public-auth" class="tocify-subheader">
                                                                            <li class="tocify-item level-3" data-unique="public-POSTapi-v1-auth-register">
                                            <a href="#public-POSTapi-v1-auth-register">Register</a>
                                        </li>
                                                                            <li class="tocify-item level-3" data-unique="public-POSTapi-v1-auth-login">
                                            <a href="#public-POSTapi-v1-auth-login">Login</a>
                                        </li>
                                                                    </ul>
                                                                                <li class="tocify-item level-2" data-unique="public-events">
                                <a href="#public-events">Events</a>
                            </li>
                                                            <ul id="tocify-subheader-public-events" class="tocify-subheader">
                                                                            <li class="tocify-item level-3" data-unique="public-GETapi-v1-events">
                                            <a href="#public-GETapi-v1-events">List events</a>
                                        </li>
                                                                            <li class="tocify-item level-3" data-unique="public-GETapi-v1-events--id-">
                                            <a href="#public-GETapi-v1-events--id-">Get event</a>
                                        </li>
                                                                    </ul>
                                                                                <li class="tocify-item level-2" data-unique="public-ticket-types">
                                <a href="#public-ticket-types">Ticket Types</a>
                            </li>
                                                            <ul id="tocify-subheader-public-ticket-types" class="tocify-subheader">
                                                                            <li class="tocify-item level-3" data-unique="public-GETapi-v1-events--event_id--ticket-types">
                                            <a href="#public-GETapi-v1-events--event_id--ticket-types">List ticket types</a>
                                        </li>
                                                                            <li class="tocify-item level-3" data-unique="public-GETapi-v1-events--event_id--ticket-types--ticketType_id-">
                                            <a href="#public-GETapi-v1-events--event_id--ticket-types--ticketType_id-">Get ticket type</a>
                                        </li>
                                                                    </ul>
                                                                                <li class="tocify-item level-2" data-unique="public-GETapi-v1-health">
                                <a href="#public-GETapi-v1-health">Health check</a>
                            </li>
                                                                        </ul>
                            </ul>
                    <ul id="tocify-header-auth" class="tocify-header">
                <li class="tocify-item level-1" data-unique="auth">
                    <a href="#auth">Auth</a>
                </li>
                                    <ul id="tocify-subheader-auth" class="tocify-subheader">
                                                    <li class="tocify-item level-2" data-unique="auth-POSTapi-v1-auth-logout">
                                <a href="#auth-POSTapi-v1-auth-logout">Logout</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="auth-GETapi-v1-auth-me">
                                <a href="#auth-GETapi-v1-auth-me">Current user</a>
                            </li>
                                                                        </ul>
                            </ul>
                    <ul id="tocify-header-vendor" class="tocify-header">
                <li class="tocify-item level-1" data-unique="vendor">
                    <a href="#vendor">Vendor</a>
                </li>
                                    <ul id="tocify-subheader-vendor" class="tocify-subheader">
                                                    <li class="tocify-item level-2" data-unique="vendor-events">
                                <a href="#vendor-events">Events</a>
                            </li>
                                                            <ul id="tocify-subheader-vendor-events" class="tocify-subheader">
                                                                            <li class="tocify-item level-3" data-unique="vendor-POSTapi-v1-events">
                                            <a href="#vendor-POSTapi-v1-events">Create event</a>
                                        </li>
                                                                            <li class="tocify-item level-3" data-unique="vendor-PUTapi-v1-events--id-">
                                            <a href="#vendor-PUTapi-v1-events--id-">Update event</a>
                                        </li>
                                                                            <li class="tocify-item level-3" data-unique="vendor-DELETEapi-v1-events--id-">
                                            <a href="#vendor-DELETEapi-v1-events--id-">Delete event</a>
                                        </li>
                                                                    </ul>
                                                                                <li class="tocify-item level-2" data-unique="vendor-ticket-types">
                                <a href="#vendor-ticket-types">Ticket Types</a>
                            </li>
                                                            <ul id="tocify-subheader-vendor-ticket-types" class="tocify-subheader">
                                                                            <li class="tocify-item level-3" data-unique="vendor-POSTapi-v1-events--event_id--ticket-types">
                                            <a href="#vendor-POSTapi-v1-events--event_id--ticket-types">Create ticket type</a>
                                        </li>
                                                                            <li class="tocify-item level-3" data-unique="vendor-PUTapi-v1-events--event_id--ticket-types--ticketType_id-">
                                            <a href="#vendor-PUTapi-v1-events--event_id--ticket-types--ticketType_id-">Update ticket type</a>
                                        </li>
                                                                            <li class="tocify-item level-3" data-unique="vendor-DELETEapi-v1-events--event_id--ticket-types--ticketType_id-">
                                            <a href="#vendor-DELETEapi-v1-events--event_id--ticket-types--ticketType_id-">Delete ticket type</a>
                                        </li>
                                                                    </ul>
                                                                                <li class="tocify-item level-2" data-unique="vendor-kyc">
                                <a href="#vendor-kyc">KYC</a>
                            </li>
                                                            <ul id="tocify-subheader-vendor-kyc" class="tocify-subheader">
                                                                            <li class="tocify-item level-3" data-unique="vendor-POSTapi-v1-vendor-kyc">
                                            <a href="#vendor-POSTapi-v1-vendor-kyc">Submit KYC (vendor)</a>
                                        </li>
                                                                    </ul>
                                                                        </ul>
                            </ul>
                    <ul id="tocify-header-attendee" class="tocify-header">
                <li class="tocify-item level-1" data-unique="attendee">
                    <a href="#attendee">Attendee</a>
                </li>
                                    <ul id="tocify-subheader-attendee" class="tocify-subheader">
                                                    <li class="tocify-item level-2" data-unique="attendee-orders">
                                <a href="#attendee-orders">Orders</a>
                            </li>
                                                            <ul id="tocify-subheader-attendee-orders" class="tocify-subheader">
                                                                            <li class="tocify-item level-3" data-unique="attendee-POSTapi-v1-orders">
                                            <a href="#attendee-POSTapi-v1-orders">Checkout</a>
                                        </li>
                                                                            <li class="tocify-item level-3" data-unique="attendee-POSTapi-v1-orders--order_id--pay">
                                            <a href="#attendee-POSTapi-v1-orders--order_id--pay">Pay order</a>
                                        </li>
                                                                    </ul>
                                                                                <li class="tocify-item level-2" data-unique="attendee-refunds">
                                <a href="#attendee-refunds">Refunds</a>
                            </li>
                                                            <ul id="tocify-subheader-attendee-refunds" class="tocify-subheader">
                                                                            <li class="tocify-item level-3" data-unique="attendee-POSTapi-v1-orders--order_id--refund">
                                            <a href="#attendee-POSTapi-v1-orders--order_id--refund">Request refund (attendee)</a>
                                        </li>
                                                                    </ul>
                                                                        </ul>
                            </ul>
                    <ul id="tocify-header-admin" class="tocify-header">
                <li class="tocify-item level-1" data-unique="admin">
                    <a href="#admin">Admin</a>
                </li>
                                    <ul id="tocify-subheader-admin" class="tocify-subheader">
                                                    <li class="tocify-item level-2" data-unique="admin-vendors">
                                <a href="#admin-vendors">Vendors</a>
                            </li>
                                                            <ul id="tocify-subheader-admin-vendors" class="tocify-subheader">
                                                                            <li class="tocify-item level-3" data-unique="admin-GETapi-v1-admin-vendors">
                                            <a href="#admin-GETapi-v1-admin-vendors">List pending vendors (admin)</a>
                                        </li>
                                                                            <li class="tocify-item level-3" data-unique="admin-POSTapi-v1-admin-vendors--vendor_id--verify">
                                            <a href="#admin-POSTapi-v1-admin-vendors--vendor_id--verify">Verify vendor (admin)</a>
                                        </li>
                                                                            <li class="tocify-item level-3" data-unique="admin-POSTapi-v1-admin-vendors--vendor_id--reject">
                                            <a href="#admin-POSTapi-v1-admin-vendors--vendor_id--reject">Reject vendor (admin)</a>
                                        </li>
                                                                    </ul>
                                                                                <li class="tocify-item level-2" data-unique="admin-refunds">
                                <a href="#admin-refunds">Refunds</a>
                            </li>
                                                            <ul id="tocify-subheader-admin-refunds" class="tocify-subheader">
                                                                            <li class="tocify-item level-3" data-unique="admin-POSTapi-v1-admin-orders--order_id--refund">
                                            <a href="#admin-POSTapi-v1-admin-orders--order_id--refund">Initiate refund (admin)</a>
                                        </li>
                                                                    </ul>
                                                                                <li class="tocify-item level-2" data-unique="admin-GETapi-v1-admin-disputes">
                                <a href="#admin-GETapi-v1-admin-disputes">List open disputes</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="admin-POSTapi-v1-admin-disputes--dispute_id--resolve">
                                <a href="#admin-POSTapi-v1-admin-disputes--dispute_id--resolve">Resolve dispute (approve refund)</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="admin-POSTapi-v1-admin-disputes--dispute_id--reject">
                                <a href="#admin-POSTapi-v1-admin-disputes--dispute_id--reject">Reject dispute</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="admin-payouts">
                                <a href="#admin-payouts">Payouts</a>
                            </li>
                                                            <ul id="tocify-subheader-admin-payouts" class="tocify-subheader">
                                                                            <li class="tocify-item level-3" data-unique="admin-GETapi-v1-admin-payouts">
                                            <a href="#admin-GETapi-v1-admin-payouts">List payouts (admin)</a>
                                        </li>
                                                                            <li class="tocify-item level-3" data-unique="admin-POSTapi-v1-admin-payouts-build">
                                            <a href="#admin-POSTapi-v1-admin-payouts-build">Build payout batch (admin)</a>
                                        </li>
                                                                            <li class="tocify-item level-3" data-unique="admin-POSTapi-v1-admin-payouts--payout_id--execute">
                                            <a href="#admin-POSTapi-v1-admin-payouts--payout_id--execute">Execute payout</a>
                                        </li>
                                                                    </ul>
                                                                                <li class="tocify-item level-2" data-unique="admin-system">
                                <a href="#admin-system">System</a>
                            </li>
                                                            <ul id="tocify-subheader-admin-system" class="tocify-subheader">
                                                                            <li class="tocify-item level-3" data-unique="admin-GETapi-v1-admin-ping">
                                            <a href="#admin-GETapi-v1-admin-ping">Admin ping</a>
                                        </li>
                                                                    </ul>
                                                                        </ul>
                            </ul>
                    <ul id="tocify-header-orders" class="tocify-header">
                <li class="tocify-item level-1" data-unique="orders">
                    <a href="#orders">Orders</a>
                </li>
                                    <ul id="tocify-subheader-orders" class="tocify-subheader">
                                                    <li class="tocify-item level-2" data-unique="orders-GETapi-v1-orders">
                                <a href="#orders-GETapi-v1-orders">List orders</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="orders-GETapi-v1-orders--id-">
                                <a href="#orders-GETapi-v1-orders--id-">Get order</a>
                            </li>
                                                                        </ul>
                            </ul>
                    <ul id="tocify-header-payouts" class="tocify-header">
                <li class="tocify-item level-1" data-unique="payouts">
                    <a href="#payouts">Payouts</a>
                </li>
                                    <ul id="tocify-subheader-payouts" class="tocify-subheader">
                                                    <li class="tocify-item level-2" data-unique="payouts-GETapi-v1-payouts">
                                <a href="#payouts-GETapi-v1-payouts">My payouts (vendor)</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="payouts-GETapi-v1-payouts-preview">
                                <a href="#payouts-GETapi-v1-payouts-preview">Preview next payout (vendor)</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="payouts-POSTapi-v1-payouts-request">
                                <a href="#payouts-POSTapi-v1-payouts-request">Request payout (vendor)</a>
                            </li>
                                                                        </ul>
                            </ul>
            </div>

    <ul class="toc-footer" id="toc-footer">
                    <li style="padding-bottom: 5px;"><a href="{{ route("scribe.postman") }}">View Postman collection</a></li>
                            <li style="padding-bottom: 5px;"><a href="{{ route("scribe.openapi") }}">View OpenAPI spec</a></li>
                <li><a href="http://github.com/knuckleswtf/scribe">Documentation powered by Scribe ✍</a></li>
    </ul>

    <ul class="toc-footer" id="last-updated">
        <li>Last updated: June 30, 2026</li>
    </ul>
</div>

<div class="page-wrapper">
    <div class="dark-box"></div>
    <div class="content">
        <h1 id="introduction">Introduction</h1>
<p>REST API for the EventHub multi-vendor event ticketing and payout platform. Vendors create events and sell tickets; attendees browse and buy; admins approve vendors, manage refunds, and disburse payouts. Every financial operation is auditable, idempotent, and resilient to partial failure.</p>
<aside>
    <strong>Base URL</strong>: <code>http://localhost:8000</code>
</aside>
<h2 id="authentication">Authentication</h2>
<p>Most write endpoints and all admin endpoints require a Sanctum <strong>bearer token</strong>.
Obtain one by calling <strong>POST /api/v1/auth/login</strong> (or <strong>register</strong>); include it as:</p>
<pre><code>Authorization: Bearer {YOUR_AUTH_KEY}</code></pre>
<p>Public read endpoints (event catalog, ticket types) work without a token.</p>
<h2 id="demo-credentials-seeded">Demo credentials (seeded)</h2>
<table>
<thead>
<tr>
<th>Role</th>
<th>Email</th>
<th>Password</th>
</tr>
</thead>
<tbody>
<tr>
<td>Admin</td>
<td>admin@eventhub.test</td>
<td>password</td>
</tr>
<tr>
<td>Vendor</td>
<td>vendor@eventhub.test</td>
<td>password</td>
</tr>
<tr>
<td>Attendee</td>
<td>attendee@eventhub.test</td>
<td>password</td>
</tr>
</tbody>
</table>
<h2 id="idempotency">Idempotency</h2>
<p>Money-moving endpoints (checkout, refund, payout-execute) are idempotent.
Pass a unique <code>Idempotency-Key</code> header on the checkout request; duplicate calls return the
original result without re-executing the side effect.</p>
<h2 id="response-envelope">Response envelope</h2>
<p>Every response uses the same shape:</p>
<pre><code class="language-json">{
  "success": true,
  "message": "Human-readable summary",
  "data": {},
  "errors": null
}</code></pre>
<p>Validation failures return HTTP 422 with <code>errors</code> as a field → messages map.
Rate-limited responses return HTTP 429 with <code>data.retry_after</code> (seconds).</p>
<aside>Internal webhook endpoints (<code>/api/v1/internal/payments/*</code>) are excluded from
these docs — they are called only by the payment-service and are protected by a shared-secret
HMAC signature, never by user tokens.</aside>

        <h1 id="authenticating-requests">Authenticating requests</h1>
<p>To authenticate requests, include an <strong><code>Authorization</code></strong> header with the value <strong><code>"Bearer {YOUR_BEARER_TOKEN}"</code></strong>.</p>
<p>All authenticated endpoints are marked with a <code>requires authentication</code> badge in the documentation below.</p>
<p>Obtain a token via <strong>POST /api/v1/auth/login</strong>. Include it in every authenticated request: <code>Authorization: Bearer {token}</code>.</p>

        <h1 id="public">Public</h1>

    

                        <h2 id="public-auth">Auth</h2>
                                                    <h2 id="public-POSTapi-v1-auth-register">Register</h2>

<p>
</p>

<p>Create a new vendor or attendee account. Returns a Sanctum bearer token on success.
Admin accounts are provisioned via seeder only — the <code>admin</code> role is not self-assignable.</p>

<span id="example-requests-POSTapi-v1-auth-register">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://localhost:8000/api/v1/auth/register" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"name\": \"Alice Smith\",
    \"email\": \"alice@example.com\",
    \"password\": \"password123\",
    \"role\": \"vendor\",
    \"business_name\": \"Acme Events Ltd\",
    \"phone\": \"+8801711000000\",
    \"password_confirmation\": \"password123\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/auth/register"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "name": "Alice Smith",
    "email": "alice@example.com",
    "password": "password123",
    "role": "vendor",
    "business_name": "Acme Events Ltd",
    "phone": "+8801711000000",
    "password_confirmation": "password123"
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>


<div class="php-example">
    <pre><code class="language-php">$client = new \GuzzleHttp\Client();
$url = 'http://localhost:8000/api/v1/auth/register';
$response = $client-&gt;post(
    $url,
    [
        'headers' =&gt; [
            'Content-Type' =&gt; 'application/json',
            'Accept' =&gt; 'application/json',
        ],
        'json' =&gt; [
            'name' =&gt; 'Alice Smith',
            'email' =&gt; 'alice@example.com',
            'password' =&gt; 'password123',
            'role' =&gt; 'vendor',
            'business_name' =&gt; 'Acme Events Ltd',
            'phone' =&gt; '+8801711000000',
            'password_confirmation' =&gt; 'password123',
        ],
    ]
);
$body = $response-&gt;getBody();
print_r(json_decode((string) $body));</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-auth-register">
            <blockquote>
            <p>Example response (201, Vendor registered):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;success&quot;: true,
    &quot;message&quot;: &quot;Account created successfully.&quot;,
    &quot;data&quot;: {
        &quot;user&quot;: {
            &quot;id&quot;: &quot;01J0000000000000000VENDOR&quot;,
            &quot;name&quot;: &quot;Acme Events Ltd&quot;,
            &quot;email&quot;: &quot;vendor@eventhub.test&quot;,
            &quot;role&quot;: {
                &quot;value&quot;: &quot;vendor&quot;,
                &quot;label&quot;: &quot;Vendor&quot;
            },
            &quot;created_at&quot;: &quot;2026-06-30T10:00:00Z&quot;
        },
        &quot;token&quot;: &quot;[PLACEHOLDER_TOKEN]&quot;
    },
    &quot;errors&quot;: null
}</code>
 </pre>
    </span>
<span id="execution-results-POSTapi-v1-auth-register" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-auth-register"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-auth-register"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-auth-register" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-auth-register">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-auth-register" data-method="POST"
      data-path="api/v1/auth/register"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-auth-register', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-v1-auth-register"
                    onclick="tryItOut('POSTapi-v1-auth-register');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-v1-auth-register"
                    onclick="cancelTryOut('POSTapi-v1-auth-register');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-v1-auth-register"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/auth/register</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-auth-register"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-auth-register"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>name</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="name"                data-endpoint="POSTapi-v1-auth-register"
               value="Alice Smith"
               data-component="body">
    <br>
<p>Full name. Example: <code>Alice Smith</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>email</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="email"                data-endpoint="POSTapi-v1-auth-register"
               value="alice@example.com"
               data-component="body">
    <br>
<p>Email address. Example: <code>alice@example.com</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>password</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="password"                data-endpoint="POSTapi-v1-auth-register"
               value="password123"
               data-component="body">
    <br>
<p>Min 8 characters. Example: <code>password123</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>role</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="role"                data-endpoint="POSTapi-v1-auth-register"
               value="vendor"
               data-component="body">
    <br>
<p><code>vendor</code> or <code>attendee</code>. Example: <code>vendor</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>business_name</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="business_name"                data-endpoint="POSTapi-v1-auth-register"
               value="Acme Events Ltd"
               data-component="body">
    <br>
<p>if role is vendor. Example: <code>Acme Events Ltd</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>phone</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="phone"                data-endpoint="POSTapi-v1-auth-register"
               value="+8801711000000"
               data-component="body">
    <br>
<p>optional Attendee contact phone. Example: <code>+8801711000000</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>password_confirmation</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="password_confirmation"                data-endpoint="POSTapi-v1-auth-register"
               value="password123"
               data-component="body">
    <br>
<p>Must match password. Example: <code>password123</code></p>
        </div>
        </form>

                    <h2 id="public-POSTapi-v1-auth-login">Login</h2>

<p>
</p>

<p>Authenticate and receive a Sanctum bearer token. Use the returned token in an
<code>Authorization: Bearer {token}</code> header on subsequent requests.</p>

<span id="example-requests-POSTapi-v1-auth-login">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://localhost:8000/api/v1/auth/login" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"email\": \"vendor@eventhub.test\",
    \"password\": \"password\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/auth/login"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "email": "vendor@eventhub.test",
    "password": "password"
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>


<div class="php-example">
    <pre><code class="language-php">$client = new \GuzzleHttp\Client();
$url = 'http://localhost:8000/api/v1/auth/login';
$response = $client-&gt;post(
    $url,
    [
        'headers' =&gt; [
            'Content-Type' =&gt; 'application/json',
            'Accept' =&gt; 'application/json',
        ],
        'json' =&gt; [
            'email' =&gt; 'vendor@eventhub.test',
            'password' =&gt; 'password',
        ],
    ]
);
$body = $response-&gt;getBody();
print_r(json_decode((string) $body));</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-auth-login">
            <blockquote>
            <p>Example response (200, Success):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;success&quot;: true,
    &quot;message&quot;: &quot;Logged in successfully.&quot;,
    &quot;data&quot;: {
        &quot;user&quot;: {
            &quot;id&quot;: &quot;01J0000000000000000VENDOR&quot;,
            &quot;name&quot;: &quot;Acme Events Ltd&quot;,
            &quot;email&quot;: &quot;vendor@eventhub.test&quot;,
            &quot;role&quot;: {
                &quot;value&quot;: &quot;vendor&quot;,
                &quot;label&quot;: &quot;Vendor&quot;
            },
            &quot;created_at&quot;: &quot;2026-06-30T10:00:00Z&quot;
        },
        &quot;token&quot;: &quot;[PLACEHOLDER_TOKEN]&quot;
    },
    &quot;errors&quot;: null
}</code>
 </pre>
            <blockquote>
            <p>Example response (401, Bad credentials):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;success&quot;: false,
    &quot;message&quot;: &quot;The provided credentials are incorrect.&quot;,
    &quot;data&quot;: null,
    &quot;errors&quot;: null
}</code>
 </pre>
    </span>
<span id="execution-results-POSTapi-v1-auth-login" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-auth-login"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-auth-login"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-auth-login" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-auth-login">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-auth-login" data-method="POST"
      data-path="api/v1/auth/login"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-auth-login', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-v1-auth-login"
                    onclick="tryItOut('POSTapi-v1-auth-login');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-v1-auth-login"
                    onclick="cancelTryOut('POSTapi-v1-auth-login');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-v1-auth-login"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/auth/login</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-auth-login"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-auth-login"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>email</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="email"                data-endpoint="POSTapi-v1-auth-login"
               value="vendor@eventhub.test"
               data-component="body">
    <br>
<p>Must be a valid email address. Example: <code>vendor@eventhub.test</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>password</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="password"                data-endpoint="POSTapi-v1-auth-login"
               value="password"
               data-component="body">
    <br>
<p>Example: <code>password</code></p>
        </div>
        </form>

                                <h2 id="public-events">Events</h2>
                                                    <h2 id="public-GETapi-v1-events">List events</h2>

<p>
</p>

<p>Returns published events for unauthenticated callers. Vendors additionally see their own
draft/ongoing events; admins see all events regardless of status.</p>

<span id="example-requests-GETapi-v1-events">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:8000/api/v1/events" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/events"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>


<div class="php-example">
    <pre><code class="language-php">$client = new \GuzzleHttp\Client();
$url = 'http://localhost:8000/api/v1/events';
$response = $client-&gt;get(
    $url,
    [
        'headers' =&gt; [
            'Content-Type' =&gt; 'application/json',
            'Accept' =&gt; 'application/json',
        ],
    ]
);
$body = $response-&gt;getBody();
print_r(json_decode((string) $body));</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-events">
            <blockquote>
            <p>Example response (200, Success):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;success&quot;: true,
    &quot;message&quot;: &quot;Events retrieved.&quot;,
    &quot;data&quot;: {
        &quot;events&quot;: [
            {
                &quot;id&quot;: &quot;01JWXYZ0000000000000EVENT1&quot;,
                &quot;vendor_id&quot;: &quot;01JWXYZ0000000000000VENDOR&quot;,
                &quot;title&quot;: &quot;Summer Music Festival 2026&quot;,
                &quot;description&quot;: &quot;An evening of live music at the Dhaka Convention Centre.&quot;,
                &quot;timezone&quot;: &quot;Asia/Dhaka&quot;,
                &quot;starts_at&quot;: &quot;2026-09-20T12:00:00+00:00&quot;,
                &quot;ends_at&quot;: &quot;2026-09-20T16:00:00+00:00&quot;,
                &quot;capacity&quot;: 500,
                &quot;status&quot;: {
                    &quot;value&quot;: &quot;published&quot;,
                    &quot;label&quot;: &quot;Published&quot;
                },
                &quot;ticket_types&quot;: [],
                &quot;created_at&quot;: &quot;2026-06-30T09:00:00+00:00&quot;,
                &quot;updated_at&quot;: &quot;2026-06-30T09:00:00+00:00&quot;
            }
        ],
        &quot;pagination&quot;: {
            &quot;current_page&quot;: 1,
            &quot;per_page&quot;: 15,
            &quot;total&quot;: 1,
            &quot;last_page&quot;: 1
        }
    },
    &quot;errors&quot;: null
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-events" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-events"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-events"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-events" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-events">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-events" data-method="GET"
      data-path="api/v1/events"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-events', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-events"
                    onclick="tryItOut('GETapi-v1-events');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-events"
                    onclick="cancelTryOut('GETapi-v1-events');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-events"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/events</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-events"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-events"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                    <h2 id="public-GETapi-v1-events--id-">Get event</h2>

<p>
</p>

<p>Retrieve a single event with its ticket types. Published events are public;
draft/cancelled events are visible only to their owner vendor or an admin.</p>

<span id="example-requests-GETapi-v1-events--id-">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:8000/api/v1/events/architecto" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/events/architecto"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>


<div class="php-example">
    <pre><code class="language-php">$client = new \GuzzleHttp\Client();
$url = 'http://localhost:8000/api/v1/events/architecto';
$response = $client-&gt;get(
    $url,
    [
        'headers' =&gt; [
            'Content-Type' =&gt; 'application/json',
            'Accept' =&gt; 'application/json',
        ],
    ]
);
$body = $response-&gt;getBody();
print_r(json_decode((string) $body));</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-events--id-">
            <blockquote>
            <p>Example response (200, Success):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;success&quot;: true,
    &quot;message&quot;: &quot;Event retrieved.&quot;,
    &quot;data&quot;: {
        &quot;event&quot;: {
            &quot;id&quot;: &quot;01JWXYZ0000000000000EVENT1&quot;,
            &quot;vendor_id&quot;: &quot;01JWXYZ0000000000000VENDOR&quot;,
            &quot;title&quot;: &quot;Summer Music Festival 2026&quot;,
            &quot;description&quot;: &quot;An evening of live music at the Dhaka Convention Centre.&quot;,
            &quot;timezone&quot;: &quot;Asia/Dhaka&quot;,
            &quot;starts_at&quot;: &quot;2026-09-20T12:00:00+00:00&quot;,
            &quot;ends_at&quot;: &quot;2026-09-20T16:00:00+00:00&quot;,
            &quot;capacity&quot;: 500,
            &quot;status&quot;: {
                &quot;value&quot;: &quot;published&quot;,
                &quot;label&quot;: &quot;Published&quot;
            },
            &quot;ticket_types&quot;: [
                {
                    &quot;id&quot;: &quot;01JWXYZ000000000000TICKET1&quot;,
                    &quot;event_id&quot;: &quot;01JWXYZ0000000000000EVENT1&quot;,
                    &quot;kind&quot;: {
                        &quot;value&quot;: &quot;general&quot;,
                        &quot;label&quot;: &quot;General&quot;
                    },
                    &quot;price&quot;: 50000,
                    &quot;currency&quot;: &quot;BDT&quot;,
                    &quot;quantity_total&quot;: 200,
                    &quot;quantity_sold&quot;: 12,
                    &quot;group_size&quot;: null,
                    &quot;group_discount&quot;: null,
                    &quot;sales_start&quot;: &quot;2026-08-01T00:00:00+06:00&quot;,
                    &quot;sales_end&quot;: &quot;2026-09-19T23:59:59+06:00&quot;,
                    &quot;created_at&quot;: &quot;2026-06-30T09:00:00+00:00&quot;,
                    &quot;updated_at&quot;: &quot;2026-06-30T09:00:00+00:00&quot;
                }
            ],
            &quot;created_at&quot;: &quot;2026-06-30T09:00:00+00:00&quot;,
            &quot;updated_at&quot;: &quot;2026-06-30T09:00:00+00:00&quot;
        }
    },
    &quot;errors&quot;: null
}</code>
 </pre>
            <blockquote>
            <p>Example response (403, Draft event (not owner)):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;success&quot;: false,
    &quot;message&quot;: &quot;This action is unauthorized.&quot;,
    &quot;data&quot;: null,
    &quot;errors&quot;: null
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-events--id-" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-events--id-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-events--id-"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-events--id-" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-events--id-">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-events--id-" data-method="GET"
      data-path="api/v1/events/{id}"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-events--id-', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-events--id-"
                    onclick="tryItOut('GETapi-v1-events--id-');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-events--id-"
                    onclick="cancelTryOut('GETapi-v1-events--id-');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-events--id-"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/events/{id}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-events--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-events--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="id"                data-endpoint="GETapi-v1-events--id-"
               value="architecto"
               data-component="url">
    <br>
<p>The ID of the event. Example: <code>architecto</code></p>
            </div>
                    </form>

                                <h2 id="public-ticket-types">Ticket Types</h2>
                                                    <h2 id="public-GETapi-v1-events--event_id--ticket-types">List ticket types</h2>

<p>
</p>

<p>Returns all active ticket types for the given event, with pricing and availability.
Requires the same visibility as the parent event (published events are public).</p>

<span id="example-requests-GETapi-v1-events--event_id--ticket-types">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:8000/api/v1/events/architecto/ticket-types" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/events/architecto/ticket-types"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>


<div class="php-example">
    <pre><code class="language-php">$client = new \GuzzleHttp\Client();
$url = 'http://localhost:8000/api/v1/events/architecto/ticket-types';
$response = $client-&gt;get(
    $url,
    [
        'headers' =&gt; [
            'Content-Type' =&gt; 'application/json',
            'Accept' =&gt; 'application/json',
        ],
    ]
);
$body = $response-&gt;getBody();
print_r(json_decode((string) $body));</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-events--event_id--ticket-types">
            <blockquote>
            <p>Example response (200, Success):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;success&quot;: true,
    &quot;message&quot;: &quot;Ticket types retrieved.&quot;,
    &quot;data&quot;: {
        &quot;ticket_types&quot;: [
            {
                &quot;id&quot;: &quot;01JWXYZ000000000000TICKET1&quot;,
                &quot;event_id&quot;: &quot;01JWXYZ0000000000000EVENT1&quot;,
                &quot;kind&quot;: {
                    &quot;value&quot;: &quot;general&quot;,
                    &quot;label&quot;: &quot;General&quot;
                },
                &quot;price&quot;: 50000,
                &quot;currency&quot;: &quot;BDT&quot;,
                &quot;quantity_total&quot;: 200,
                &quot;quantity_sold&quot;: 12,
                &quot;group_size&quot;: null,
                &quot;group_discount&quot;: null,
                &quot;sales_start&quot;: &quot;2026-08-01T00:00:00+06:00&quot;,
                &quot;sales_end&quot;: &quot;2026-09-19T23:59:59+06:00&quot;,
                &quot;created_at&quot;: &quot;2026-06-30T09:00:00+00:00&quot;,
                &quot;updated_at&quot;: &quot;2026-06-30T09:00:00+00:00&quot;
            }
        ],
        &quot;pagination&quot;: {
            &quot;current_page&quot;: 1,
            &quot;per_page&quot;: 25,
            &quot;total&quot;: 1,
            &quot;last_page&quot;: 1
        }
    },
    &quot;errors&quot;: null
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-events--event_id--ticket-types" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-events--event_id--ticket-types"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-events--event_id--ticket-types"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-events--event_id--ticket-types" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-events--event_id--ticket-types">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-events--event_id--ticket-types" data-method="GET"
      data-path="api/v1/events/{event_id}/ticket-types"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-events--event_id--ticket-types', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-events--event_id--ticket-types"
                    onclick="tryItOut('GETapi-v1-events--event_id--ticket-types');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-events--event_id--ticket-types"
                    onclick="cancelTryOut('GETapi-v1-events--event_id--ticket-types');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-events--event_id--ticket-types"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/events/{event_id}/ticket-types</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-events--event_id--ticket-types"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-events--event_id--ticket-types"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>event_id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="event_id"                data-endpoint="GETapi-v1-events--event_id--ticket-types"
               value="architecto"
               data-component="url">
    <br>
<p>The ID of the event. Example: <code>architecto</code></p>
            </div>
                    </form>

                    <h2 id="public-GETapi-v1-events--event_id--ticket-types--ticketType_id-">Get ticket type</h2>

<p>
</p>

<p>Retrieve a single ticket type including group-bundle rules if applicable.</p>

<span id="example-requests-GETapi-v1-events--event_id--ticket-types--ticketType_id-">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:8000/api/v1/events/architecto/ticket-types/architecto" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/events/architecto/ticket-types/architecto"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>


<div class="php-example">
    <pre><code class="language-php">$client = new \GuzzleHttp\Client();
$url = 'http://localhost:8000/api/v1/events/architecto/ticket-types/architecto';
$response = $client-&gt;get(
    $url,
    [
        'headers' =&gt; [
            'Content-Type' =&gt; 'application/json',
            'Accept' =&gt; 'application/json',
        ],
    ]
);
$body = $response-&gt;getBody();
print_r(json_decode((string) $body));</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-events--event_id--ticket-types--ticketType_id-">
            <blockquote>
            <p>Example response (200, Success):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;success&quot;: true,
    &quot;message&quot;: &quot;Ticket type retrieved.&quot;,
    &quot;data&quot;: {
        &quot;ticket_type&quot;: {
            &quot;id&quot;: &quot;01JWXYZ000000000000TICKET1&quot;,
            &quot;event_id&quot;: &quot;01JWXYZ0000000000000EVENT1&quot;,
            &quot;kind&quot;: {
                &quot;value&quot;: &quot;general&quot;,
                &quot;label&quot;: &quot;General&quot;
            },
            &quot;price&quot;: 50000,
            &quot;currency&quot;: &quot;BDT&quot;,
            &quot;quantity_total&quot;: 200,
            &quot;quantity_sold&quot;: 12,
            &quot;group_size&quot;: null,
            &quot;group_discount&quot;: null,
            &quot;sales_start&quot;: &quot;2026-08-01T00:00:00+06:00&quot;,
            &quot;sales_end&quot;: &quot;2026-09-19T23:59:59+06:00&quot;,
            &quot;created_at&quot;: &quot;2026-06-30T09:00:00+00:00&quot;,
            &quot;updated_at&quot;: &quot;2026-06-30T09:00:00+00:00&quot;
        }
    },
    &quot;errors&quot;: null
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-events--event_id--ticket-types--ticketType_id-" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-events--event_id--ticket-types--ticketType_id-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-events--event_id--ticket-types--ticketType_id-"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-events--event_id--ticket-types--ticketType_id-" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-events--event_id--ticket-types--ticketType_id-">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-events--event_id--ticket-types--ticketType_id-" data-method="GET"
      data-path="api/v1/events/{event_id}/ticket-types/{ticketType_id}"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-events--event_id--ticket-types--ticketType_id-', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-events--event_id--ticket-types--ticketType_id-"
                    onclick="tryItOut('GETapi-v1-events--event_id--ticket-types--ticketType_id-');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-events--event_id--ticket-types--ticketType_id-"
                    onclick="cancelTryOut('GETapi-v1-events--event_id--ticket-types--ticketType_id-');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-events--event_id--ticket-types--ticketType_id-"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/events/{event_id}/ticket-types/{ticketType_id}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-events--event_id--ticket-types--ticketType_id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-events--event_id--ticket-types--ticketType_id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>event_id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="event_id"                data-endpoint="GETapi-v1-events--event_id--ticket-types--ticketType_id-"
               value="architecto"
               data-component="url">
    <br>
<p>The ID of the event. Example: <code>architecto</code></p>
            </div>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>ticketType_id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="ticketType_id"                data-endpoint="GETapi-v1-events--event_id--ticket-types--ticketType_id-"
               value="architecto"
               data-component="url">
    <br>
<p>The ID of the ticketType. Example: <code>architecto</code></p>
            </div>
                    </form>

                                        <h2 id="public-GETapi-v1-health">Health check</h2>

<p>
</p>

<p>Returns the service name and a liveness status. No authentication required. Safe to poll.</p>

<span id="example-requests-GETapi-v1-health">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:8000/api/v1/health" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/health"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>


<div class="php-example">
    <pre><code class="language-php">$client = new \GuzzleHttp\Client();
$url = 'http://localhost:8000/api/v1/health';
$response = $client-&gt;get(
    $url,
    [
        'headers' =&gt; [
            'Content-Type' =&gt; 'application/json',
            'Accept' =&gt; 'application/json',
        ],
    ]
);
$body = $response-&gt;getBody();
print_r(json_decode((string) $body));</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-health">
            <blockquote>
            <p>Example response (200, Service is up.):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;success&quot;: true,
    &quot;message&quot;: &quot;core-api is healthy.&quot;,
    &quot;data&quot;: {
        &quot;service&quot;: &quot;core-api&quot;,
        &quot;status&quot;: &quot;ok&quot;
    },
    &quot;errors&quot;: null
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-health" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-health"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-health"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-health" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-health">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-health" data-method="GET"
      data-path="api/v1/health"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-health', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-health"
                    onclick="tryItOut('GETapi-v1-health');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-health"
                    onclick="cancelTryOut('GETapi-v1-health');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-health"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/health</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-health"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-health"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                <h1 id="auth">Auth</h1>

    

                                <h2 id="auth-POSTapi-v1-auth-logout">Logout</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Revoke the current Sanctum token. The token becomes immediately invalid.</p>

<span id="example-requests-POSTapi-v1-auth-logout">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://localhost:8000/api/v1/auth/logout" \
    --header "Authorization: Bearer {YOUR_BEARER_TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/auth/logout"
);

const headers = {
    "Authorization": "Bearer {YOUR_BEARER_TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "POST",
    headers,
}).then(response =&gt; response.json());</code></pre></div>


<div class="php-example">
    <pre><code class="language-php">$client = new \GuzzleHttp\Client();
$url = 'http://localhost:8000/api/v1/auth/logout';
$response = $client-&gt;post(
    $url,
    [
        'headers' =&gt; [
            'Authorization' =&gt; 'Bearer {YOUR_BEARER_TOKEN}',
            'Content-Type' =&gt; 'application/json',
            'Accept' =&gt; 'application/json',
        ],
    ]
);
$body = $response-&gt;getBody();
print_r(json_decode((string) $body));</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-auth-logout">
</span>
<span id="execution-results-POSTapi-v1-auth-logout" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-auth-logout"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-auth-logout"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-auth-logout" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-auth-logout">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-auth-logout" data-method="POST"
      data-path="api/v1/auth/logout"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-auth-logout', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-v1-auth-logout"
                    onclick="tryItOut('POSTapi-v1-auth-logout');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-v1-auth-logout"
                    onclick="cancelTryOut('POSTapi-v1-auth-logout');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-v1-auth-logout"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/auth/logout</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="POSTapi-v1-auth-logout"
               value="Bearer {YOUR_BEARER_TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_BEARER_TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-auth-logout"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-auth-logout"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                    <h2 id="auth-GETapi-v1-auth-me">Current user</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Return the authenticated user along with their vendor or attendee profile.</p>

<span id="example-requests-GETapi-v1-auth-me">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:8000/api/v1/auth/me" \
    --header "Authorization: Bearer {YOUR_BEARER_TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/auth/me"
);

const headers = {
    "Authorization": "Bearer {YOUR_BEARER_TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>


<div class="php-example">
    <pre><code class="language-php">$client = new \GuzzleHttp\Client();
$url = 'http://localhost:8000/api/v1/auth/me';
$response = $client-&gt;get(
    $url,
    [
        'headers' =&gt; [
            'Authorization' =&gt; 'Bearer {YOUR_BEARER_TOKEN}',
            'Content-Type' =&gt; 'application/json',
            'Accept' =&gt; 'application/json',
        ],
    ]
);
$body = $response-&gt;getBody();
print_r(json_decode((string) $body));</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-auth-me">
            <blockquote>
            <p>Example response (200, Success):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;success&quot;: true,
    &quot;message&quot;: &quot;Authenticated user retrieved.&quot;,
    &quot;data&quot;: {
        &quot;user&quot;: {
            &quot;id&quot;: &quot;01JWXYZ0000000000000VENDOR&quot;,
            &quot;name&quot;: &quot;Acme Events Ltd&quot;,
            &quot;email&quot;: &quot;vendor@eventhub.test&quot;,
            &quot;role&quot;: {
                &quot;value&quot;: &quot;vendor&quot;,
                &quot;label&quot;: &quot;Vendor&quot;
            },
            &quot;created_at&quot;: &quot;2026-06-30T10:00:00+00:00&quot;
        }
    },
    &quot;errors&quot;: null
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-auth-me" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-auth-me"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-auth-me"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-auth-me" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-auth-me">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-auth-me" data-method="GET"
      data-path="api/v1/auth/me"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-auth-me', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-auth-me"
                    onclick="tryItOut('GETapi-v1-auth-me');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-auth-me"
                    onclick="cancelTryOut('GETapi-v1-auth-me');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-auth-me"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/auth/me</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-v1-auth-me"
               value="Bearer {YOUR_BEARER_TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_BEARER_TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-auth-me"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-auth-me"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                <h1 id="vendor">Vendor</h1>

    

                        <h2 id="vendor-events">Events</h2>
                                                    <h2 id="vendor-POSTapi-v1-events">Create event</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Create a new event draft owned by the authenticated vendor.
The vendor must be KYC-verified before the event can be published.</p>

<span id="example-requests-POSTapi-v1-events">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://localhost:8000/api/v1/events" \
    --header "Authorization: Bearer {YOUR_BEARER_TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"title\": \"Summer Music Festival 2026\",
    \"description\": \"An evening of live music at the Dhaka Convention Centre.\",
    \"timezone\": \"Asia\\/Dhaka\",
    \"starts_at\": \"2026-09-20T18:00:00+06:00\",
    \"ends_at\": \"2026-09-20T22:00:00+06:00\",
    \"capacity\": 500
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/events"
);

const headers = {
    "Authorization": "Bearer {YOUR_BEARER_TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "title": "Summer Music Festival 2026",
    "description": "An evening of live music at the Dhaka Convention Centre.",
    "timezone": "Asia\/Dhaka",
    "starts_at": "2026-09-20T18:00:00+06:00",
    "ends_at": "2026-09-20T22:00:00+06:00",
    "capacity": 500
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>


<div class="php-example">
    <pre><code class="language-php">$client = new \GuzzleHttp\Client();
$url = 'http://localhost:8000/api/v1/events';
$response = $client-&gt;post(
    $url,
    [
        'headers' =&gt; [
            'Authorization' =&gt; 'Bearer {YOUR_BEARER_TOKEN}',
            'Content-Type' =&gt; 'application/json',
            'Accept' =&gt; 'application/json',
        ],
        'json' =&gt; [
            'title' =&gt; 'Summer Music Festival 2026',
            'description' =&gt; 'An evening of live music at the Dhaka Convention Centre.',
            'timezone' =&gt; 'Asia/Dhaka',
            'starts_at' =&gt; '2026-09-20T18:00:00+06:00',
            'ends_at' =&gt; '2026-09-20T22:00:00+06:00',
            'capacity' =&gt; 500,
        ],
    ]
);
$body = $response-&gt;getBody();
print_r(json_decode((string) $body));</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-events">
</span>
<span id="execution-results-POSTapi-v1-events" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-events"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-events"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-events" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-events">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-events" data-method="POST"
      data-path="api/v1/events"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-events', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-v1-events"
                    onclick="tryItOut('POSTapi-v1-events');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-v1-events"
                    onclick="cancelTryOut('POSTapi-v1-events');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-v1-events"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/events</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="POSTapi-v1-events"
               value="Bearer {YOUR_BEARER_TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_BEARER_TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-events"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-events"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>title</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="title"                data-endpoint="POSTapi-v1-events"
               value="Summer Music Festival 2026"
               data-component="body">
    <br>
<p>Must not be greater than 255 characters. Example: <code>Summer Music Festival 2026</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>description</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="description"                data-endpoint="POSTapi-v1-events"
               value="An evening of live music at the Dhaka Convention Centre."
               data-component="body">
    <br>
<p>Must not be greater than 5000 characters. Example: <code>An evening of live music at the Dhaka Convention Centre.</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>timezone</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="timezone"                data-endpoint="POSTapi-v1-events"
               value="Asia/Dhaka"
               data-component="body">
    <br>
<p>Example: <code>Asia/Dhaka</code></p>
Must be one of:
<ul style="list-style-type: square;"><li><code>Africa/Abidjan</code></li> <li><code>Africa/Accra</code></li> <li><code>Africa/Addis_Ababa</code></li> <li><code>Africa/Algiers</code></li> <li><code>Africa/Asmara</code></li> <li><code>Africa/Bamako</code></li> <li><code>Africa/Bangui</code></li> <li><code>Africa/Banjul</code></li> <li><code>Africa/Bissau</code></li> <li><code>Africa/Blantyre</code></li> <li><code>Africa/Brazzaville</code></li> <li><code>Africa/Bujumbura</code></li> <li><code>Africa/Cairo</code></li> <li><code>Africa/Casablanca</code></li> <li><code>Africa/Ceuta</code></li> <li><code>Africa/Conakry</code></li> <li><code>Africa/Dakar</code></li> <li><code>Africa/Dar_es_Salaam</code></li> <li><code>Africa/Djibouti</code></li> <li><code>Africa/Douala</code></li> <li><code>Africa/El_Aaiun</code></li> <li><code>Africa/Freetown</code></li> <li><code>Africa/Gaborone</code></li> <li><code>Africa/Harare</code></li> <li><code>Africa/Johannesburg</code></li> <li><code>Africa/Juba</code></li> <li><code>Africa/Kampala</code></li> <li><code>Africa/Khartoum</code></li> <li><code>Africa/Kigali</code></li> <li><code>Africa/Kinshasa</code></li> <li><code>Africa/Lagos</code></li> <li><code>Africa/Libreville</code></li> <li><code>Africa/Lome</code></li> <li><code>Africa/Luanda</code></li> <li><code>Africa/Lubumbashi</code></li> <li><code>Africa/Lusaka</code></li> <li><code>Africa/Malabo</code></li> <li><code>Africa/Maputo</code></li> <li><code>Africa/Maseru</code></li> <li><code>Africa/Mbabane</code></li> <li><code>Africa/Mogadishu</code></li> <li><code>Africa/Monrovia</code></li> <li><code>Africa/Nairobi</code></li> <li><code>Africa/Ndjamena</code></li> <li><code>Africa/Niamey</code></li> <li><code>Africa/Nouakchott</code></li> <li><code>Africa/Ouagadougou</code></li> <li><code>Africa/Porto-Novo</code></li> <li><code>Africa/Sao_Tome</code></li> <li><code>Africa/Tripoli</code></li> <li><code>Africa/Tunis</code></li> <li><code>Africa/Windhoek</code></li> <li><code>America/Adak</code></li> <li><code>America/Anchorage</code></li> <li><code>America/Anguilla</code></li> <li><code>America/Antigua</code></li> <li><code>America/Araguaina</code></li> <li><code>America/Argentina/Buenos_Aires</code></li> <li><code>America/Argentina/Catamarca</code></li> <li><code>America/Argentina/Cordoba</code></li> <li><code>America/Argentina/Jujuy</code></li> <li><code>America/Argentina/La_Rioja</code></li> <li><code>America/Argentina/Mendoza</code></li> <li><code>America/Argentina/Rio_Gallegos</code></li> <li><code>America/Argentina/Salta</code></li> <li><code>America/Argentina/San_Juan</code></li> <li><code>America/Argentina/San_Luis</code></li> <li><code>America/Argentina/Tucuman</code></li> <li><code>America/Argentina/Ushuaia</code></li> <li><code>America/Aruba</code></li> <li><code>America/Asuncion</code></li> <li><code>America/Atikokan</code></li> <li><code>America/Bahia</code></li> <li><code>America/Bahia_Banderas</code></li> <li><code>America/Barbados</code></li> <li><code>America/Belem</code></li> <li><code>America/Belize</code></li> <li><code>America/Blanc-Sablon</code></li> <li><code>America/Boa_Vista</code></li> <li><code>America/Bogota</code></li> <li><code>America/Boise</code></li> <li><code>America/Cambridge_Bay</code></li> <li><code>America/Campo_Grande</code></li> <li><code>America/Cancun</code></li> <li><code>America/Caracas</code></li> <li><code>America/Cayenne</code></li> <li><code>America/Cayman</code></li> <li><code>America/Chicago</code></li> <li><code>America/Chihuahua</code></li> <li><code>America/Ciudad_Juarez</code></li> <li><code>America/Costa_Rica</code></li> <li><code>America/Coyhaique</code></li> <li><code>America/Creston</code></li> <li><code>America/Cuiaba</code></li> <li><code>America/Curacao</code></li> <li><code>America/Danmarkshavn</code></li> <li><code>America/Dawson</code></li> <li><code>America/Dawson_Creek</code></li> <li><code>America/Denver</code></li> <li><code>America/Detroit</code></li> <li><code>America/Dominica</code></li> <li><code>America/Edmonton</code></li> <li><code>America/Eirunepe</code></li> <li><code>America/El_Salvador</code></li> <li><code>America/Fort_Nelson</code></li> <li><code>America/Fortaleza</code></li> <li><code>America/Glace_Bay</code></li> <li><code>America/Goose_Bay</code></li> <li><code>America/Grand_Turk</code></li> <li><code>America/Grenada</code></li> <li><code>America/Guadeloupe</code></li> <li><code>America/Guatemala</code></li> <li><code>America/Guayaquil</code></li> <li><code>America/Guyana</code></li> <li><code>America/Halifax</code></li> <li><code>America/Havana</code></li> <li><code>America/Hermosillo</code></li> <li><code>America/Indiana/Indianapolis</code></li> <li><code>America/Indiana/Knox</code></li> <li><code>America/Indiana/Marengo</code></li> <li><code>America/Indiana/Petersburg</code></li> <li><code>America/Indiana/Tell_City</code></li> <li><code>America/Indiana/Vevay</code></li> <li><code>America/Indiana/Vincennes</code></li> <li><code>America/Indiana/Winamac</code></li> <li><code>America/Inuvik</code></li> <li><code>America/Iqaluit</code></li> <li><code>America/Jamaica</code></li> <li><code>America/Juneau</code></li> <li><code>America/Kentucky/Louisville</code></li> <li><code>America/Kentucky/Monticello</code></li> <li><code>America/Kralendijk</code></li> <li><code>America/La_Paz</code></li> <li><code>America/Lima</code></li> <li><code>America/Los_Angeles</code></li> <li><code>America/Lower_Princes</code></li> <li><code>America/Maceio</code></li> <li><code>America/Managua</code></li> <li><code>America/Manaus</code></li> <li><code>America/Marigot</code></li> <li><code>America/Martinique</code></li> <li><code>America/Matamoros</code></li> <li><code>America/Mazatlan</code></li> <li><code>America/Menominee</code></li> <li><code>America/Merida</code></li> <li><code>America/Metlakatla</code></li> <li><code>America/Mexico_City</code></li> <li><code>America/Miquelon</code></li> <li><code>America/Moncton</code></li> <li><code>America/Monterrey</code></li> <li><code>America/Montevideo</code></li> <li><code>America/Montserrat</code></li> <li><code>America/Nassau</code></li> <li><code>America/New_York</code></li> <li><code>America/Nome</code></li> <li><code>America/Noronha</code></li> <li><code>America/North_Dakota/Beulah</code></li> <li><code>America/North_Dakota/Center</code></li> <li><code>America/North_Dakota/New_Salem</code></li> <li><code>America/Nuuk</code></li> <li><code>America/Ojinaga</code></li> <li><code>America/Panama</code></li> <li><code>America/Paramaribo</code></li> <li><code>America/Phoenix</code></li> <li><code>America/Port-au-Prince</code></li> <li><code>America/Port_of_Spain</code></li> <li><code>America/Porto_Velho</code></li> <li><code>America/Puerto_Rico</code></li> <li><code>America/Punta_Arenas</code></li> <li><code>America/Rankin_Inlet</code></li> <li><code>America/Recife</code></li> <li><code>America/Regina</code></li> <li><code>America/Resolute</code></li> <li><code>America/Rio_Branco</code></li> <li><code>America/Santarem</code></li> <li><code>America/Santiago</code></li> <li><code>America/Santo_Domingo</code></li> <li><code>America/Sao_Paulo</code></li> <li><code>America/Scoresbysund</code></li> <li><code>America/Sitka</code></li> <li><code>America/St_Barthelemy</code></li> <li><code>America/St_Johns</code></li> <li><code>America/St_Kitts</code></li> <li><code>America/St_Lucia</code></li> <li><code>America/St_Thomas</code></li> <li><code>America/St_Vincent</code></li> <li><code>America/Swift_Current</code></li> <li><code>America/Tegucigalpa</code></li> <li><code>America/Thule</code></li> <li><code>America/Tijuana</code></li> <li><code>America/Toronto</code></li> <li><code>America/Tortola</code></li> <li><code>America/Vancouver</code></li> <li><code>America/Whitehorse</code></li> <li><code>America/Winnipeg</code></li> <li><code>America/Yakutat</code></li> <li><code>Antarctica/Casey</code></li> <li><code>Antarctica/Davis</code></li> <li><code>Antarctica/DumontDUrville</code></li> <li><code>Antarctica/Macquarie</code></li> <li><code>Antarctica/Mawson</code></li> <li><code>Antarctica/McMurdo</code></li> <li><code>Antarctica/Palmer</code></li> <li><code>Antarctica/Rothera</code></li> <li><code>Antarctica/Syowa</code></li> <li><code>Antarctica/Troll</code></li> <li><code>Antarctica/Vostok</code></li> <li><code>Arctic/Longyearbyen</code></li> <li><code>Asia/Aden</code></li> <li><code>Asia/Almaty</code></li> <li><code>Asia/Amman</code></li> <li><code>Asia/Anadyr</code></li> <li><code>Asia/Aqtau</code></li> <li><code>Asia/Aqtobe</code></li> <li><code>Asia/Ashgabat</code></li> <li><code>Asia/Atyrau</code></li> <li><code>Asia/Baghdad</code></li> <li><code>Asia/Bahrain</code></li> <li><code>Asia/Baku</code></li> <li><code>Asia/Bangkok</code></li> <li><code>Asia/Barnaul</code></li> <li><code>Asia/Beirut</code></li> <li><code>Asia/Bishkek</code></li> <li><code>Asia/Brunei</code></li> <li><code>Asia/Chita</code></li> <li><code>Asia/Colombo</code></li> <li><code>Asia/Damascus</code></li> <li><code>Asia/Dhaka</code></li> <li><code>Asia/Dili</code></li> <li><code>Asia/Dubai</code></li> <li><code>Asia/Dushanbe</code></li> <li><code>Asia/Famagusta</code></li> <li><code>Asia/Gaza</code></li> <li><code>Asia/Hebron</code></li> <li><code>Asia/Ho_Chi_Minh</code></li> <li><code>Asia/Hong_Kong</code></li> <li><code>Asia/Hovd</code></li> <li><code>Asia/Irkutsk</code></li> <li><code>Asia/Jakarta</code></li> <li><code>Asia/Jayapura</code></li> <li><code>Asia/Jerusalem</code></li> <li><code>Asia/Kabul</code></li> <li><code>Asia/Kamchatka</code></li> <li><code>Asia/Karachi</code></li> <li><code>Asia/Kathmandu</code></li> <li><code>Asia/Khandyga</code></li> <li><code>Asia/Kolkata</code></li> <li><code>Asia/Krasnoyarsk</code></li> <li><code>Asia/Kuala_Lumpur</code></li> <li><code>Asia/Kuching</code></li> <li><code>Asia/Kuwait</code></li> <li><code>Asia/Macau</code></li> <li><code>Asia/Magadan</code></li> <li><code>Asia/Makassar</code></li> <li><code>Asia/Manila</code></li> <li><code>Asia/Muscat</code></li> <li><code>Asia/Nicosia</code></li> <li><code>Asia/Novokuznetsk</code></li> <li><code>Asia/Novosibirsk</code></li> <li><code>Asia/Omsk</code></li> <li><code>Asia/Oral</code></li> <li><code>Asia/Phnom_Penh</code></li> <li><code>Asia/Pontianak</code></li> <li><code>Asia/Pyongyang</code></li> <li><code>Asia/Qatar</code></li> <li><code>Asia/Qostanay</code></li> <li><code>Asia/Qyzylorda</code></li> <li><code>Asia/Riyadh</code></li> <li><code>Asia/Sakhalin</code></li> <li><code>Asia/Samarkand</code></li> <li><code>Asia/Seoul</code></li> <li><code>Asia/Shanghai</code></li> <li><code>Asia/Singapore</code></li> <li><code>Asia/Srednekolymsk</code></li> <li><code>Asia/Taipei</code></li> <li><code>Asia/Tashkent</code></li> <li><code>Asia/Tbilisi</code></li> <li><code>Asia/Tehran</code></li> <li><code>Asia/Thimphu</code></li> <li><code>Asia/Tokyo</code></li> <li><code>Asia/Tomsk</code></li> <li><code>Asia/Ulaanbaatar</code></li> <li><code>Asia/Urumqi</code></li> <li><code>Asia/Ust-Nera</code></li> <li><code>Asia/Vientiane</code></li> <li><code>Asia/Vladivostok</code></li> <li><code>Asia/Yakutsk</code></li> <li><code>Asia/Yangon</code></li> <li><code>Asia/Yekaterinburg</code></li> <li><code>Asia/Yerevan</code></li> <li><code>Atlantic/Azores</code></li> <li><code>Atlantic/Bermuda</code></li> <li><code>Atlantic/Canary</code></li> <li><code>Atlantic/Cape_Verde</code></li> <li><code>Atlantic/Faroe</code></li> <li><code>Atlantic/Madeira</code></li> <li><code>Atlantic/Reykjavik</code></li> <li><code>Atlantic/South_Georgia</code></li> <li><code>Atlantic/St_Helena</code></li> <li><code>Atlantic/Stanley</code></li> <li><code>Australia/Adelaide</code></li> <li><code>Australia/Brisbane</code></li> <li><code>Australia/Broken_Hill</code></li> <li><code>Australia/Darwin</code></li> <li><code>Australia/Eucla</code></li> <li><code>Australia/Hobart</code></li> <li><code>Australia/Lindeman</code></li> <li><code>Australia/Lord_Howe</code></li> <li><code>Australia/Melbourne</code></li> <li><code>Australia/Perth</code></li> <li><code>Australia/Sydney</code></li> <li><code>Europe/Amsterdam</code></li> <li><code>Europe/Andorra</code></li> <li><code>Europe/Astrakhan</code></li> <li><code>Europe/Athens</code></li> <li><code>Europe/Belgrade</code></li> <li><code>Europe/Berlin</code></li> <li><code>Europe/Bratislava</code></li> <li><code>Europe/Brussels</code></li> <li><code>Europe/Bucharest</code></li> <li><code>Europe/Budapest</code></li> <li><code>Europe/Busingen</code></li> <li><code>Europe/Chisinau</code></li> <li><code>Europe/Copenhagen</code></li> <li><code>Europe/Dublin</code></li> <li><code>Europe/Gibraltar</code></li> <li><code>Europe/Guernsey</code></li> <li><code>Europe/Helsinki</code></li> <li><code>Europe/Isle_of_Man</code></li> <li><code>Europe/Istanbul</code></li> <li><code>Europe/Jersey</code></li> <li><code>Europe/Kaliningrad</code></li> <li><code>Europe/Kirov</code></li> <li><code>Europe/Kyiv</code></li> <li><code>Europe/Lisbon</code></li> <li><code>Europe/Ljubljana</code></li> <li><code>Europe/London</code></li> <li><code>Europe/Luxembourg</code></li> <li><code>Europe/Madrid</code></li> <li><code>Europe/Malta</code></li> <li><code>Europe/Mariehamn</code></li> <li><code>Europe/Minsk</code></li> <li><code>Europe/Monaco</code></li> <li><code>Europe/Moscow</code></li> <li><code>Europe/Oslo</code></li> <li><code>Europe/Paris</code></li> <li><code>Europe/Podgorica</code></li> <li><code>Europe/Prague</code></li> <li><code>Europe/Riga</code></li> <li><code>Europe/Rome</code></li> <li><code>Europe/Samara</code></li> <li><code>Europe/San_Marino</code></li> <li><code>Europe/Sarajevo</code></li> <li><code>Europe/Saratov</code></li> <li><code>Europe/Simferopol</code></li> <li><code>Europe/Skopje</code></li> <li><code>Europe/Sofia</code></li> <li><code>Europe/Stockholm</code></li> <li><code>Europe/Tallinn</code></li> <li><code>Europe/Tirane</code></li> <li><code>Europe/Ulyanovsk</code></li> <li><code>Europe/Vaduz</code></li> <li><code>Europe/Vatican</code></li> <li><code>Europe/Vienna</code></li> <li><code>Europe/Vilnius</code></li> <li><code>Europe/Volgograd</code></li> <li><code>Europe/Warsaw</code></li> <li><code>Europe/Zagreb</code></li> <li><code>Europe/Zurich</code></li> <li><code>Indian/Antananarivo</code></li> <li><code>Indian/Chagos</code></li> <li><code>Indian/Christmas</code></li> <li><code>Indian/Cocos</code></li> <li><code>Indian/Comoro</code></li> <li><code>Indian/Kerguelen</code></li> <li><code>Indian/Mahe</code></li> <li><code>Indian/Maldives</code></li> <li><code>Indian/Mauritius</code></li> <li><code>Indian/Mayotte</code></li> <li><code>Indian/Reunion</code></li> <li><code>Pacific/Apia</code></li> <li><code>Pacific/Auckland</code></li> <li><code>Pacific/Bougainville</code></li> <li><code>Pacific/Chatham</code></li> <li><code>Pacific/Chuuk</code></li> <li><code>Pacific/Easter</code></li> <li><code>Pacific/Efate</code></li> <li><code>Pacific/Fakaofo</code></li> <li><code>Pacific/Fiji</code></li> <li><code>Pacific/Funafuti</code></li> <li><code>Pacific/Galapagos</code></li> <li><code>Pacific/Gambier</code></li> <li><code>Pacific/Guadalcanal</code></li> <li><code>Pacific/Guam</code></li> <li><code>Pacific/Honolulu</code></li> <li><code>Pacific/Kanton</code></li> <li><code>Pacific/Kiritimati</code></li> <li><code>Pacific/Kosrae</code></li> <li><code>Pacific/Kwajalein</code></li> <li><code>Pacific/Majuro</code></li> <li><code>Pacific/Marquesas</code></li> <li><code>Pacific/Midway</code></li> <li><code>Pacific/Nauru</code></li> <li><code>Pacific/Niue</code></li> <li><code>Pacific/Norfolk</code></li> <li><code>Pacific/Noumea</code></li> <li><code>Pacific/Pago_Pago</code></li> <li><code>Pacific/Palau</code></li> <li><code>Pacific/Pitcairn</code></li> <li><code>Pacific/Pohnpei</code></li> <li><code>Pacific/Port_Moresby</code></li> <li><code>Pacific/Rarotonga</code></li> <li><code>Pacific/Saipan</code></li> <li><code>Pacific/Tahiti</code></li> <li><code>Pacific/Tarawa</code></li> <li><code>Pacific/Tongatapu</code></li> <li><code>Pacific/Wake</code></li> <li><code>Pacific/Wallis</code></li> <li><code>UTC</code></li></ul>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>starts_at</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="starts_at"                data-endpoint="POSTapi-v1-events"
               value="2026-09-20T18:00:00+06:00"
               data-component="body">
    <br>
<p>Must be a valid date. Example: <code>2026-09-20T18:00:00+06:00</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>ends_at</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="ends_at"                data-endpoint="POSTapi-v1-events"
               value="2026-09-20T22:00:00+06:00"
               data-component="body">
    <br>
<p>Must be a valid date. Must be a date after <code>starts_at</code>. Example: <code>2026-09-20T22:00:00+06:00</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>capacity</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="capacity"                data-endpoint="POSTapi-v1-events"
               value="500"
               data-component="body">
    <br>
<p>Must be at least 1. Example: <code>500</code></p>
        </div>
        </form>

                    <h2 id="vendor-PUTapi-v1-events--id-">Update event</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Update a vendor's own event. Status transitions (e.g. draft → published) are enforced by
the event lifecycle policy (e.g. vendor must be KYC-verified to publish).</p>

<span id="example-requests-PUTapi-v1-events--id-">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request PUT \
    "http://localhost:8000/api/v1/events/architecto" \
    --header "Authorization: Bearer {YOUR_BEARER_TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"title\": \"Summer Music Festival 2026 (Updated)\",
    \"description\": \"Doors open at 17:30. Standing and seated areas available.\",
    \"timezone\": \"Asia\\/Dhaka\",
    \"starts_at\": \"2026-09-20T18:00:00+06:00\",
    \"ends_at\": \"2026-09-20T23:00:00+06:00\",
    \"capacity\": 600,
    \"status\": \"published\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/events/architecto"
);

const headers = {
    "Authorization": "Bearer {YOUR_BEARER_TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "title": "Summer Music Festival 2026 (Updated)",
    "description": "Doors open at 17:30. Standing and seated areas available.",
    "timezone": "Asia\/Dhaka",
    "starts_at": "2026-09-20T18:00:00+06:00",
    "ends_at": "2026-09-20T23:00:00+06:00",
    "capacity": 600,
    "status": "published"
};

fetch(url, {
    method: "PUT",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>


<div class="php-example">
    <pre><code class="language-php">$client = new \GuzzleHttp\Client();
$url = 'http://localhost:8000/api/v1/events/architecto';
$response = $client-&gt;put(
    $url,
    [
        'headers' =&gt; [
            'Authorization' =&gt; 'Bearer {YOUR_BEARER_TOKEN}',
            'Content-Type' =&gt; 'application/json',
            'Accept' =&gt; 'application/json',
        ],
        'json' =&gt; [
            'title' =&gt; 'Summer Music Festival 2026 (Updated)',
            'description' =&gt; 'Doors open at 17:30. Standing and seated areas available.',
            'timezone' =&gt; 'Asia/Dhaka',
            'starts_at' =&gt; '2026-09-20T18:00:00+06:00',
            'ends_at' =&gt; '2026-09-20T23:00:00+06:00',
            'capacity' =&gt; 600,
            'status' =&gt; 'published',
        ],
    ]
);
$body = $response-&gt;getBody();
print_r(json_decode((string) $body));</code></pre></div>

</span>

<span id="example-responses-PUTapi-v1-events--id-">
</span>
<span id="execution-results-PUTapi-v1-events--id-" hidden>
    <blockquote>Received response<span
                id="execution-response-status-PUTapi-v1-events--id-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-PUTapi-v1-events--id-"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-PUTapi-v1-events--id-" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-PUTapi-v1-events--id-">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-PUTapi-v1-events--id-" data-method="PUT"
      data-path="api/v1/events/{id}"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('PUTapi-v1-events--id-', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-PUTapi-v1-events--id-"
                    onclick="tryItOut('PUTapi-v1-events--id-');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-PUTapi-v1-events--id-"
                    onclick="cancelTryOut('PUTapi-v1-events--id-');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-PUTapi-v1-events--id-"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-darkblue">PUT</small>
            <b><code>api/v1/events/{id}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="PUTapi-v1-events--id-"
               value="Bearer {YOUR_BEARER_TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_BEARER_TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="PUTapi-v1-events--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="PUTapi-v1-events--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="id"                data-endpoint="PUTapi-v1-events--id-"
               value="architecto"
               data-component="url">
    <br>
<p>The ID of the event. Example: <code>architecto</code></p>
            </div>
                            <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>title</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="title"                data-endpoint="PUTapi-v1-events--id-"
               value="Summer Music Festival 2026 (Updated)"
               data-component="body">
    <br>
<p>Must not be greater than 255 characters. Example: <code>Summer Music Festival 2026 (Updated)</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>description</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="description"                data-endpoint="PUTapi-v1-events--id-"
               value="Doors open at 17:30. Standing and seated areas available."
               data-component="body">
    <br>
<p>Must not be greater than 5000 characters. Example: <code>Doors open at 17:30. Standing and seated areas available.</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>timezone</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="timezone"                data-endpoint="PUTapi-v1-events--id-"
               value="Asia/Dhaka"
               data-component="body">
    <br>
<p>Example: <code>Asia/Dhaka</code></p>
Must be one of:
<ul style="list-style-type: square;"><li><code>Africa/Abidjan</code></li> <li><code>Africa/Accra</code></li> <li><code>Africa/Addis_Ababa</code></li> <li><code>Africa/Algiers</code></li> <li><code>Africa/Asmara</code></li> <li><code>Africa/Bamako</code></li> <li><code>Africa/Bangui</code></li> <li><code>Africa/Banjul</code></li> <li><code>Africa/Bissau</code></li> <li><code>Africa/Blantyre</code></li> <li><code>Africa/Brazzaville</code></li> <li><code>Africa/Bujumbura</code></li> <li><code>Africa/Cairo</code></li> <li><code>Africa/Casablanca</code></li> <li><code>Africa/Ceuta</code></li> <li><code>Africa/Conakry</code></li> <li><code>Africa/Dakar</code></li> <li><code>Africa/Dar_es_Salaam</code></li> <li><code>Africa/Djibouti</code></li> <li><code>Africa/Douala</code></li> <li><code>Africa/El_Aaiun</code></li> <li><code>Africa/Freetown</code></li> <li><code>Africa/Gaborone</code></li> <li><code>Africa/Harare</code></li> <li><code>Africa/Johannesburg</code></li> <li><code>Africa/Juba</code></li> <li><code>Africa/Kampala</code></li> <li><code>Africa/Khartoum</code></li> <li><code>Africa/Kigali</code></li> <li><code>Africa/Kinshasa</code></li> <li><code>Africa/Lagos</code></li> <li><code>Africa/Libreville</code></li> <li><code>Africa/Lome</code></li> <li><code>Africa/Luanda</code></li> <li><code>Africa/Lubumbashi</code></li> <li><code>Africa/Lusaka</code></li> <li><code>Africa/Malabo</code></li> <li><code>Africa/Maputo</code></li> <li><code>Africa/Maseru</code></li> <li><code>Africa/Mbabane</code></li> <li><code>Africa/Mogadishu</code></li> <li><code>Africa/Monrovia</code></li> <li><code>Africa/Nairobi</code></li> <li><code>Africa/Ndjamena</code></li> <li><code>Africa/Niamey</code></li> <li><code>Africa/Nouakchott</code></li> <li><code>Africa/Ouagadougou</code></li> <li><code>Africa/Porto-Novo</code></li> <li><code>Africa/Sao_Tome</code></li> <li><code>Africa/Tripoli</code></li> <li><code>Africa/Tunis</code></li> <li><code>Africa/Windhoek</code></li> <li><code>America/Adak</code></li> <li><code>America/Anchorage</code></li> <li><code>America/Anguilla</code></li> <li><code>America/Antigua</code></li> <li><code>America/Araguaina</code></li> <li><code>America/Argentina/Buenos_Aires</code></li> <li><code>America/Argentina/Catamarca</code></li> <li><code>America/Argentina/Cordoba</code></li> <li><code>America/Argentina/Jujuy</code></li> <li><code>America/Argentina/La_Rioja</code></li> <li><code>America/Argentina/Mendoza</code></li> <li><code>America/Argentina/Rio_Gallegos</code></li> <li><code>America/Argentina/Salta</code></li> <li><code>America/Argentina/San_Juan</code></li> <li><code>America/Argentina/San_Luis</code></li> <li><code>America/Argentina/Tucuman</code></li> <li><code>America/Argentina/Ushuaia</code></li> <li><code>America/Aruba</code></li> <li><code>America/Asuncion</code></li> <li><code>America/Atikokan</code></li> <li><code>America/Bahia</code></li> <li><code>America/Bahia_Banderas</code></li> <li><code>America/Barbados</code></li> <li><code>America/Belem</code></li> <li><code>America/Belize</code></li> <li><code>America/Blanc-Sablon</code></li> <li><code>America/Boa_Vista</code></li> <li><code>America/Bogota</code></li> <li><code>America/Boise</code></li> <li><code>America/Cambridge_Bay</code></li> <li><code>America/Campo_Grande</code></li> <li><code>America/Cancun</code></li> <li><code>America/Caracas</code></li> <li><code>America/Cayenne</code></li> <li><code>America/Cayman</code></li> <li><code>America/Chicago</code></li> <li><code>America/Chihuahua</code></li> <li><code>America/Ciudad_Juarez</code></li> <li><code>America/Costa_Rica</code></li> <li><code>America/Coyhaique</code></li> <li><code>America/Creston</code></li> <li><code>America/Cuiaba</code></li> <li><code>America/Curacao</code></li> <li><code>America/Danmarkshavn</code></li> <li><code>America/Dawson</code></li> <li><code>America/Dawson_Creek</code></li> <li><code>America/Denver</code></li> <li><code>America/Detroit</code></li> <li><code>America/Dominica</code></li> <li><code>America/Edmonton</code></li> <li><code>America/Eirunepe</code></li> <li><code>America/El_Salvador</code></li> <li><code>America/Fort_Nelson</code></li> <li><code>America/Fortaleza</code></li> <li><code>America/Glace_Bay</code></li> <li><code>America/Goose_Bay</code></li> <li><code>America/Grand_Turk</code></li> <li><code>America/Grenada</code></li> <li><code>America/Guadeloupe</code></li> <li><code>America/Guatemala</code></li> <li><code>America/Guayaquil</code></li> <li><code>America/Guyana</code></li> <li><code>America/Halifax</code></li> <li><code>America/Havana</code></li> <li><code>America/Hermosillo</code></li> <li><code>America/Indiana/Indianapolis</code></li> <li><code>America/Indiana/Knox</code></li> <li><code>America/Indiana/Marengo</code></li> <li><code>America/Indiana/Petersburg</code></li> <li><code>America/Indiana/Tell_City</code></li> <li><code>America/Indiana/Vevay</code></li> <li><code>America/Indiana/Vincennes</code></li> <li><code>America/Indiana/Winamac</code></li> <li><code>America/Inuvik</code></li> <li><code>America/Iqaluit</code></li> <li><code>America/Jamaica</code></li> <li><code>America/Juneau</code></li> <li><code>America/Kentucky/Louisville</code></li> <li><code>America/Kentucky/Monticello</code></li> <li><code>America/Kralendijk</code></li> <li><code>America/La_Paz</code></li> <li><code>America/Lima</code></li> <li><code>America/Los_Angeles</code></li> <li><code>America/Lower_Princes</code></li> <li><code>America/Maceio</code></li> <li><code>America/Managua</code></li> <li><code>America/Manaus</code></li> <li><code>America/Marigot</code></li> <li><code>America/Martinique</code></li> <li><code>America/Matamoros</code></li> <li><code>America/Mazatlan</code></li> <li><code>America/Menominee</code></li> <li><code>America/Merida</code></li> <li><code>America/Metlakatla</code></li> <li><code>America/Mexico_City</code></li> <li><code>America/Miquelon</code></li> <li><code>America/Moncton</code></li> <li><code>America/Monterrey</code></li> <li><code>America/Montevideo</code></li> <li><code>America/Montserrat</code></li> <li><code>America/Nassau</code></li> <li><code>America/New_York</code></li> <li><code>America/Nome</code></li> <li><code>America/Noronha</code></li> <li><code>America/North_Dakota/Beulah</code></li> <li><code>America/North_Dakota/Center</code></li> <li><code>America/North_Dakota/New_Salem</code></li> <li><code>America/Nuuk</code></li> <li><code>America/Ojinaga</code></li> <li><code>America/Panama</code></li> <li><code>America/Paramaribo</code></li> <li><code>America/Phoenix</code></li> <li><code>America/Port-au-Prince</code></li> <li><code>America/Port_of_Spain</code></li> <li><code>America/Porto_Velho</code></li> <li><code>America/Puerto_Rico</code></li> <li><code>America/Punta_Arenas</code></li> <li><code>America/Rankin_Inlet</code></li> <li><code>America/Recife</code></li> <li><code>America/Regina</code></li> <li><code>America/Resolute</code></li> <li><code>America/Rio_Branco</code></li> <li><code>America/Santarem</code></li> <li><code>America/Santiago</code></li> <li><code>America/Santo_Domingo</code></li> <li><code>America/Sao_Paulo</code></li> <li><code>America/Scoresbysund</code></li> <li><code>America/Sitka</code></li> <li><code>America/St_Barthelemy</code></li> <li><code>America/St_Johns</code></li> <li><code>America/St_Kitts</code></li> <li><code>America/St_Lucia</code></li> <li><code>America/St_Thomas</code></li> <li><code>America/St_Vincent</code></li> <li><code>America/Swift_Current</code></li> <li><code>America/Tegucigalpa</code></li> <li><code>America/Thule</code></li> <li><code>America/Tijuana</code></li> <li><code>America/Toronto</code></li> <li><code>America/Tortola</code></li> <li><code>America/Vancouver</code></li> <li><code>America/Whitehorse</code></li> <li><code>America/Winnipeg</code></li> <li><code>America/Yakutat</code></li> <li><code>Antarctica/Casey</code></li> <li><code>Antarctica/Davis</code></li> <li><code>Antarctica/DumontDUrville</code></li> <li><code>Antarctica/Macquarie</code></li> <li><code>Antarctica/Mawson</code></li> <li><code>Antarctica/McMurdo</code></li> <li><code>Antarctica/Palmer</code></li> <li><code>Antarctica/Rothera</code></li> <li><code>Antarctica/Syowa</code></li> <li><code>Antarctica/Troll</code></li> <li><code>Antarctica/Vostok</code></li> <li><code>Arctic/Longyearbyen</code></li> <li><code>Asia/Aden</code></li> <li><code>Asia/Almaty</code></li> <li><code>Asia/Amman</code></li> <li><code>Asia/Anadyr</code></li> <li><code>Asia/Aqtau</code></li> <li><code>Asia/Aqtobe</code></li> <li><code>Asia/Ashgabat</code></li> <li><code>Asia/Atyrau</code></li> <li><code>Asia/Baghdad</code></li> <li><code>Asia/Bahrain</code></li> <li><code>Asia/Baku</code></li> <li><code>Asia/Bangkok</code></li> <li><code>Asia/Barnaul</code></li> <li><code>Asia/Beirut</code></li> <li><code>Asia/Bishkek</code></li> <li><code>Asia/Brunei</code></li> <li><code>Asia/Chita</code></li> <li><code>Asia/Colombo</code></li> <li><code>Asia/Damascus</code></li> <li><code>Asia/Dhaka</code></li> <li><code>Asia/Dili</code></li> <li><code>Asia/Dubai</code></li> <li><code>Asia/Dushanbe</code></li> <li><code>Asia/Famagusta</code></li> <li><code>Asia/Gaza</code></li> <li><code>Asia/Hebron</code></li> <li><code>Asia/Ho_Chi_Minh</code></li> <li><code>Asia/Hong_Kong</code></li> <li><code>Asia/Hovd</code></li> <li><code>Asia/Irkutsk</code></li> <li><code>Asia/Jakarta</code></li> <li><code>Asia/Jayapura</code></li> <li><code>Asia/Jerusalem</code></li> <li><code>Asia/Kabul</code></li> <li><code>Asia/Kamchatka</code></li> <li><code>Asia/Karachi</code></li> <li><code>Asia/Kathmandu</code></li> <li><code>Asia/Khandyga</code></li> <li><code>Asia/Kolkata</code></li> <li><code>Asia/Krasnoyarsk</code></li> <li><code>Asia/Kuala_Lumpur</code></li> <li><code>Asia/Kuching</code></li> <li><code>Asia/Kuwait</code></li> <li><code>Asia/Macau</code></li> <li><code>Asia/Magadan</code></li> <li><code>Asia/Makassar</code></li> <li><code>Asia/Manila</code></li> <li><code>Asia/Muscat</code></li> <li><code>Asia/Nicosia</code></li> <li><code>Asia/Novokuznetsk</code></li> <li><code>Asia/Novosibirsk</code></li> <li><code>Asia/Omsk</code></li> <li><code>Asia/Oral</code></li> <li><code>Asia/Phnom_Penh</code></li> <li><code>Asia/Pontianak</code></li> <li><code>Asia/Pyongyang</code></li> <li><code>Asia/Qatar</code></li> <li><code>Asia/Qostanay</code></li> <li><code>Asia/Qyzylorda</code></li> <li><code>Asia/Riyadh</code></li> <li><code>Asia/Sakhalin</code></li> <li><code>Asia/Samarkand</code></li> <li><code>Asia/Seoul</code></li> <li><code>Asia/Shanghai</code></li> <li><code>Asia/Singapore</code></li> <li><code>Asia/Srednekolymsk</code></li> <li><code>Asia/Taipei</code></li> <li><code>Asia/Tashkent</code></li> <li><code>Asia/Tbilisi</code></li> <li><code>Asia/Tehran</code></li> <li><code>Asia/Thimphu</code></li> <li><code>Asia/Tokyo</code></li> <li><code>Asia/Tomsk</code></li> <li><code>Asia/Ulaanbaatar</code></li> <li><code>Asia/Urumqi</code></li> <li><code>Asia/Ust-Nera</code></li> <li><code>Asia/Vientiane</code></li> <li><code>Asia/Vladivostok</code></li> <li><code>Asia/Yakutsk</code></li> <li><code>Asia/Yangon</code></li> <li><code>Asia/Yekaterinburg</code></li> <li><code>Asia/Yerevan</code></li> <li><code>Atlantic/Azores</code></li> <li><code>Atlantic/Bermuda</code></li> <li><code>Atlantic/Canary</code></li> <li><code>Atlantic/Cape_Verde</code></li> <li><code>Atlantic/Faroe</code></li> <li><code>Atlantic/Madeira</code></li> <li><code>Atlantic/Reykjavik</code></li> <li><code>Atlantic/South_Georgia</code></li> <li><code>Atlantic/St_Helena</code></li> <li><code>Atlantic/Stanley</code></li> <li><code>Australia/Adelaide</code></li> <li><code>Australia/Brisbane</code></li> <li><code>Australia/Broken_Hill</code></li> <li><code>Australia/Darwin</code></li> <li><code>Australia/Eucla</code></li> <li><code>Australia/Hobart</code></li> <li><code>Australia/Lindeman</code></li> <li><code>Australia/Lord_Howe</code></li> <li><code>Australia/Melbourne</code></li> <li><code>Australia/Perth</code></li> <li><code>Australia/Sydney</code></li> <li><code>Europe/Amsterdam</code></li> <li><code>Europe/Andorra</code></li> <li><code>Europe/Astrakhan</code></li> <li><code>Europe/Athens</code></li> <li><code>Europe/Belgrade</code></li> <li><code>Europe/Berlin</code></li> <li><code>Europe/Bratislava</code></li> <li><code>Europe/Brussels</code></li> <li><code>Europe/Bucharest</code></li> <li><code>Europe/Budapest</code></li> <li><code>Europe/Busingen</code></li> <li><code>Europe/Chisinau</code></li> <li><code>Europe/Copenhagen</code></li> <li><code>Europe/Dublin</code></li> <li><code>Europe/Gibraltar</code></li> <li><code>Europe/Guernsey</code></li> <li><code>Europe/Helsinki</code></li> <li><code>Europe/Isle_of_Man</code></li> <li><code>Europe/Istanbul</code></li> <li><code>Europe/Jersey</code></li> <li><code>Europe/Kaliningrad</code></li> <li><code>Europe/Kirov</code></li> <li><code>Europe/Kyiv</code></li> <li><code>Europe/Lisbon</code></li> <li><code>Europe/Ljubljana</code></li> <li><code>Europe/London</code></li> <li><code>Europe/Luxembourg</code></li> <li><code>Europe/Madrid</code></li> <li><code>Europe/Malta</code></li> <li><code>Europe/Mariehamn</code></li> <li><code>Europe/Minsk</code></li> <li><code>Europe/Monaco</code></li> <li><code>Europe/Moscow</code></li> <li><code>Europe/Oslo</code></li> <li><code>Europe/Paris</code></li> <li><code>Europe/Podgorica</code></li> <li><code>Europe/Prague</code></li> <li><code>Europe/Riga</code></li> <li><code>Europe/Rome</code></li> <li><code>Europe/Samara</code></li> <li><code>Europe/San_Marino</code></li> <li><code>Europe/Sarajevo</code></li> <li><code>Europe/Saratov</code></li> <li><code>Europe/Simferopol</code></li> <li><code>Europe/Skopje</code></li> <li><code>Europe/Sofia</code></li> <li><code>Europe/Stockholm</code></li> <li><code>Europe/Tallinn</code></li> <li><code>Europe/Tirane</code></li> <li><code>Europe/Ulyanovsk</code></li> <li><code>Europe/Vaduz</code></li> <li><code>Europe/Vatican</code></li> <li><code>Europe/Vienna</code></li> <li><code>Europe/Vilnius</code></li> <li><code>Europe/Volgograd</code></li> <li><code>Europe/Warsaw</code></li> <li><code>Europe/Zagreb</code></li> <li><code>Europe/Zurich</code></li> <li><code>Indian/Antananarivo</code></li> <li><code>Indian/Chagos</code></li> <li><code>Indian/Christmas</code></li> <li><code>Indian/Cocos</code></li> <li><code>Indian/Comoro</code></li> <li><code>Indian/Kerguelen</code></li> <li><code>Indian/Mahe</code></li> <li><code>Indian/Maldives</code></li> <li><code>Indian/Mauritius</code></li> <li><code>Indian/Mayotte</code></li> <li><code>Indian/Reunion</code></li> <li><code>Pacific/Apia</code></li> <li><code>Pacific/Auckland</code></li> <li><code>Pacific/Bougainville</code></li> <li><code>Pacific/Chatham</code></li> <li><code>Pacific/Chuuk</code></li> <li><code>Pacific/Easter</code></li> <li><code>Pacific/Efate</code></li> <li><code>Pacific/Fakaofo</code></li> <li><code>Pacific/Fiji</code></li> <li><code>Pacific/Funafuti</code></li> <li><code>Pacific/Galapagos</code></li> <li><code>Pacific/Gambier</code></li> <li><code>Pacific/Guadalcanal</code></li> <li><code>Pacific/Guam</code></li> <li><code>Pacific/Honolulu</code></li> <li><code>Pacific/Kanton</code></li> <li><code>Pacific/Kiritimati</code></li> <li><code>Pacific/Kosrae</code></li> <li><code>Pacific/Kwajalein</code></li> <li><code>Pacific/Majuro</code></li> <li><code>Pacific/Marquesas</code></li> <li><code>Pacific/Midway</code></li> <li><code>Pacific/Nauru</code></li> <li><code>Pacific/Niue</code></li> <li><code>Pacific/Norfolk</code></li> <li><code>Pacific/Noumea</code></li> <li><code>Pacific/Pago_Pago</code></li> <li><code>Pacific/Palau</code></li> <li><code>Pacific/Pitcairn</code></li> <li><code>Pacific/Pohnpei</code></li> <li><code>Pacific/Port_Moresby</code></li> <li><code>Pacific/Rarotonga</code></li> <li><code>Pacific/Saipan</code></li> <li><code>Pacific/Tahiti</code></li> <li><code>Pacific/Tarawa</code></li> <li><code>Pacific/Tongatapu</code></li> <li><code>Pacific/Wake</code></li> <li><code>Pacific/Wallis</code></li> <li><code>UTC</code></li></ul>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>starts_at</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="starts_at"                data-endpoint="PUTapi-v1-events--id-"
               value="2026-09-20T18:00:00+06:00"
               data-component="body">
    <br>
<p>Must be a valid date. Example: <code>2026-09-20T18:00:00+06:00</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>ends_at</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="ends_at"                data-endpoint="PUTapi-v1-events--id-"
               value="2026-09-20T23:00:00+06:00"
               data-component="body">
    <br>
<p>Must be a valid date. Example: <code>2026-09-20T23:00:00+06:00</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>capacity</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="capacity"                data-endpoint="PUTapi-v1-events--id-"
               value="600"
               data-component="body">
    <br>
<p>Must be at least 1. Example: <code>600</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>status</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="status"                data-endpoint="PUTapi-v1-events--id-"
               value="published"
               data-component="body">
    <br>
<p>Example: <code>published</code></p>
Must be one of:
<ul style="list-style-type: square;"><li><code>draft</code></li> <li><code>published</code></li> <li><code>ongoing</code></li> <li><code>completed</code></li> <li><code>cancelled</code></li></ul>
        </div>
        </form>

                    <h2 id="vendor-DELETEapi-v1-events--id-">Delete event</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Soft-delete a vendor's own draft event. Published or ongoing events cannot be deleted
(cancel them instead via the status transition).</p>

<span id="example-requests-DELETEapi-v1-events--id-">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request DELETE \
    "http://localhost:8000/api/v1/events/architecto" \
    --header "Authorization: Bearer {YOUR_BEARER_TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/events/architecto"
);

const headers = {
    "Authorization": "Bearer {YOUR_BEARER_TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "DELETE",
    headers,
}).then(response =&gt; response.json());</code></pre></div>


<div class="php-example">
    <pre><code class="language-php">$client = new \GuzzleHttp\Client();
$url = 'http://localhost:8000/api/v1/events/architecto';
$response = $client-&gt;delete(
    $url,
    [
        'headers' =&gt; [
            'Authorization' =&gt; 'Bearer {YOUR_BEARER_TOKEN}',
            'Content-Type' =&gt; 'application/json',
            'Accept' =&gt; 'application/json',
        ],
    ]
);
$body = $response-&gt;getBody();
print_r(json_decode((string) $body));</code></pre></div>

</span>

<span id="example-responses-DELETEapi-v1-events--id-">
</span>
<span id="execution-results-DELETEapi-v1-events--id-" hidden>
    <blockquote>Received response<span
                id="execution-response-status-DELETEapi-v1-events--id-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-DELETEapi-v1-events--id-"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-DELETEapi-v1-events--id-" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-DELETEapi-v1-events--id-">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-DELETEapi-v1-events--id-" data-method="DELETE"
      data-path="api/v1/events/{id}"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('DELETEapi-v1-events--id-', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-DELETEapi-v1-events--id-"
                    onclick="tryItOut('DELETEapi-v1-events--id-');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-DELETEapi-v1-events--id-"
                    onclick="cancelTryOut('DELETEapi-v1-events--id-');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-DELETEapi-v1-events--id-"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-red">DELETE</small>
            <b><code>api/v1/events/{id}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="DELETEapi-v1-events--id-"
               value="Bearer {YOUR_BEARER_TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_BEARER_TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="DELETEapi-v1-events--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="DELETEapi-v1-events--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="id"                data-endpoint="DELETEapi-v1-events--id-"
               value="architecto"
               data-component="url">
    <br>
<p>The ID of the event. Example: <code>architecto</code></p>
            </div>
                    </form>

                                <h2 id="vendor-ticket-types">Ticket Types</h2>
                                                    <h2 id="vendor-POSTapi-v1-events--event_id--ticket-types">Create ticket type</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Add a ticket type (general, VIP, early-bird, or group-bundle) to the vendor's own event.</p>

<span id="example-requests-POSTapi-v1-events--event_id--ticket-types">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://localhost:8000/api/v1/events/architecto/ticket-types" \
    --header "Authorization: Bearer {YOUR_BEARER_TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"kind\": \"general\",
    \"price\": 50000,
    \"currency\": \"BDT\",
    \"quantity_total\": 200,
    \"sales_start\": \"2026-08-01T00:00:00+06:00\",
    \"sales_end\": \"2026-09-19T23:59:59+06:00\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/events/architecto/ticket-types"
);

const headers = {
    "Authorization": "Bearer {YOUR_BEARER_TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "kind": "general",
    "price": 50000,
    "currency": "BDT",
    "quantity_total": 200,
    "sales_start": "2026-08-01T00:00:00+06:00",
    "sales_end": "2026-09-19T23:59:59+06:00"
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>


<div class="php-example">
    <pre><code class="language-php">$client = new \GuzzleHttp\Client();
$url = 'http://localhost:8000/api/v1/events/architecto/ticket-types';
$response = $client-&gt;post(
    $url,
    [
        'headers' =&gt; [
            'Authorization' =&gt; 'Bearer {YOUR_BEARER_TOKEN}',
            'Content-Type' =&gt; 'application/json',
            'Accept' =&gt; 'application/json',
        ],
        'json' =&gt; [
            'kind' =&gt; 'general',
            'price' =&gt; 50000,
            'currency' =&gt; 'BDT',
            'quantity_total' =&gt; 200,
            'sales_start' =&gt; '2026-08-01T00:00:00+06:00',
            'sales_end' =&gt; '2026-09-19T23:59:59+06:00',
        ],
    ]
);
$body = $response-&gt;getBody();
print_r(json_decode((string) $body));</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-events--event_id--ticket-types">
</span>
<span id="execution-results-POSTapi-v1-events--event_id--ticket-types" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-events--event_id--ticket-types"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-events--event_id--ticket-types"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-events--event_id--ticket-types" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-events--event_id--ticket-types">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-events--event_id--ticket-types" data-method="POST"
      data-path="api/v1/events/{event_id}/ticket-types"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-events--event_id--ticket-types', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-v1-events--event_id--ticket-types"
                    onclick="tryItOut('POSTapi-v1-events--event_id--ticket-types');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-v1-events--event_id--ticket-types"
                    onclick="cancelTryOut('POSTapi-v1-events--event_id--ticket-types');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-v1-events--event_id--ticket-types"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/events/{event_id}/ticket-types</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="POSTapi-v1-events--event_id--ticket-types"
               value="Bearer {YOUR_BEARER_TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_BEARER_TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-events--event_id--ticket-types"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-events--event_id--ticket-types"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>event_id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="event_id"                data-endpoint="POSTapi-v1-events--event_id--ticket-types"
               value="architecto"
               data-component="url">
    <br>
<p>The ID of the event. Example: <code>architecto</code></p>
            </div>
                            <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>kind</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="kind"                data-endpoint="POSTapi-v1-events--event_id--ticket-types"
               value="general"
               data-component="body">
    <br>
<p>Example: <code>general</code></p>
Must be one of:
<ul style="list-style-type: square;"><li><code>early_bird</code></li> <li><code>vip</code></li> <li><code>general</code></li></ul>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>price</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="price"                data-endpoint="POSTapi-v1-events--event_id--ticket-types"
               value="50000"
               data-component="body">
    <br>
<p>Must be at least 0. Example: <code>50000</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>currency</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="currency"                data-endpoint="POSTapi-v1-events--event_id--ticket-types"
               value="BDT"
               data-component="body">
    <br>
<p>Must be 3 characters. Example: <code>BDT</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>quantity_total</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="quantity_total"                data-endpoint="POSTapi-v1-events--event_id--ticket-types"
               value="200"
               data-component="body">
    <br>
<p>Must be at least 1. Example: <code>200</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>group_size</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="group_size"                data-endpoint="POSTapi-v1-events--event_id--ticket-types"
               value=""
               data-component="body">
    <br>
<p>Must be at least 2.</p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>group_discount</code></b>&nbsp;&nbsp;
<small>number</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="group_discount"                data-endpoint="POSTapi-v1-events--event_id--ticket-types"
               value=""
               data-component="body">
    <br>
<p>This field is required when <code>group_size</code> is present. Must be at least 0.</p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>sales_start</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="sales_start"                data-endpoint="POSTapi-v1-events--event_id--ticket-types"
               value="2026-08-01T00:00:00+06:00"
               data-component="body">
    <br>
<p>Must be a valid date. Example: <code>2026-08-01T00:00:00+06:00</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>sales_end</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="sales_end"                data-endpoint="POSTapi-v1-events--event_id--ticket-types"
               value="2026-09-19T23:59:59+06:00"
               data-component="body">
    <br>
<p>Must be a valid date. Must be a date after <code>sales_start</code>. Example: <code>2026-09-19T23:59:59+06:00</code></p>
        </div>
        </form>

                    <h2 id="vendor-PUTapi-v1-events--event_id--ticket-types--ticketType_id-">Update ticket type</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-PUTapi-v1-events--event_id--ticket-types--ticketType_id-">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request PUT \
    "http://localhost:8000/api/v1/events/architecto/ticket-types/architecto" \
    --header "Authorization: Bearer {YOUR_BEARER_TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"kind\": \"vip\",
    \"price\": 150000,
    \"currency\": \"BDT\",
    \"quantity_total\": 50,
    \"sales_start\": \"2026-08-01T00:00:00+06:00\",
    \"sales_end\": \"2026-09-19T23:59:59+06:00\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/events/architecto/ticket-types/architecto"
);

const headers = {
    "Authorization": "Bearer {YOUR_BEARER_TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "kind": "vip",
    "price": 150000,
    "currency": "BDT",
    "quantity_total": 50,
    "sales_start": "2026-08-01T00:00:00+06:00",
    "sales_end": "2026-09-19T23:59:59+06:00"
};

fetch(url, {
    method: "PUT",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>


<div class="php-example">
    <pre><code class="language-php">$client = new \GuzzleHttp\Client();
$url = 'http://localhost:8000/api/v1/events/architecto/ticket-types/architecto';
$response = $client-&gt;put(
    $url,
    [
        'headers' =&gt; [
            'Authorization' =&gt; 'Bearer {YOUR_BEARER_TOKEN}',
            'Content-Type' =&gt; 'application/json',
            'Accept' =&gt; 'application/json',
        ],
        'json' =&gt; [
            'kind' =&gt; 'vip',
            'price' =&gt; 150000,
            'currency' =&gt; 'BDT',
            'quantity_total' =&gt; 50,
            'sales_start' =&gt; '2026-08-01T00:00:00+06:00',
            'sales_end' =&gt; '2026-09-19T23:59:59+06:00',
        ],
    ]
);
$body = $response-&gt;getBody();
print_r(json_decode((string) $body));</code></pre></div>

</span>

<span id="example-responses-PUTapi-v1-events--event_id--ticket-types--ticketType_id-">
</span>
<span id="execution-results-PUTapi-v1-events--event_id--ticket-types--ticketType_id-" hidden>
    <blockquote>Received response<span
                id="execution-response-status-PUTapi-v1-events--event_id--ticket-types--ticketType_id-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-PUTapi-v1-events--event_id--ticket-types--ticketType_id-"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-PUTapi-v1-events--event_id--ticket-types--ticketType_id-" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-PUTapi-v1-events--event_id--ticket-types--ticketType_id-">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-PUTapi-v1-events--event_id--ticket-types--ticketType_id-" data-method="PUT"
      data-path="api/v1/events/{event_id}/ticket-types/{ticketType_id}"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('PUTapi-v1-events--event_id--ticket-types--ticketType_id-', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-PUTapi-v1-events--event_id--ticket-types--ticketType_id-"
                    onclick="tryItOut('PUTapi-v1-events--event_id--ticket-types--ticketType_id-');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-PUTapi-v1-events--event_id--ticket-types--ticketType_id-"
                    onclick="cancelTryOut('PUTapi-v1-events--event_id--ticket-types--ticketType_id-');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-PUTapi-v1-events--event_id--ticket-types--ticketType_id-"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-darkblue">PUT</small>
            <b><code>api/v1/events/{event_id}/ticket-types/{ticketType_id}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="PUTapi-v1-events--event_id--ticket-types--ticketType_id-"
               value="Bearer {YOUR_BEARER_TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_BEARER_TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="PUTapi-v1-events--event_id--ticket-types--ticketType_id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="PUTapi-v1-events--event_id--ticket-types--ticketType_id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>event_id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="event_id"                data-endpoint="PUTapi-v1-events--event_id--ticket-types--ticketType_id-"
               value="architecto"
               data-component="url">
    <br>
<p>The ID of the event. Example: <code>architecto</code></p>
            </div>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>ticketType_id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="ticketType_id"                data-endpoint="PUTapi-v1-events--event_id--ticket-types--ticketType_id-"
               value="architecto"
               data-component="url">
    <br>
<p>The ID of the ticketType. Example: <code>architecto</code></p>
            </div>
                            <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>kind</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="kind"                data-endpoint="PUTapi-v1-events--event_id--ticket-types--ticketType_id-"
               value="vip"
               data-component="body">
    <br>
<p>Example: <code>vip</code></p>
Must be one of:
<ul style="list-style-type: square;"><li><code>early_bird</code></li> <li><code>vip</code></li> <li><code>general</code></li></ul>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>price</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="price"                data-endpoint="PUTapi-v1-events--event_id--ticket-types--ticketType_id-"
               value="150000"
               data-component="body">
    <br>
<p>Must be at least 0. Example: <code>150000</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>currency</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="currency"                data-endpoint="PUTapi-v1-events--event_id--ticket-types--ticketType_id-"
               value="BDT"
               data-component="body">
    <br>
<p>Must be 3 characters. Example: <code>BDT</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>quantity_total</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="quantity_total"                data-endpoint="PUTapi-v1-events--event_id--ticket-types--ticketType_id-"
               value="50"
               data-component="body">
    <br>
<p>Must be at least 1. Example: <code>50</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>group_size</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="group_size"                data-endpoint="PUTapi-v1-events--event_id--ticket-types--ticketType_id-"
               value=""
               data-component="body">
    <br>
<p>Must be at least 2.</p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>group_discount</code></b>&nbsp;&nbsp;
<small>number</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="group_discount"                data-endpoint="PUTapi-v1-events--event_id--ticket-types--ticketType_id-"
               value=""
               data-component="body">
    <br>
<p>Must be at least 0.</p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>sales_start</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="sales_start"                data-endpoint="PUTapi-v1-events--event_id--ticket-types--ticketType_id-"
               value="2026-08-01T00:00:00+06:00"
               data-component="body">
    <br>
<p>Must be a valid date. Example: <code>2026-08-01T00:00:00+06:00</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>sales_end</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="sales_end"                data-endpoint="PUTapi-v1-events--event_id--ticket-types--ticketType_id-"
               value="2026-09-19T23:59:59+06:00"
               data-component="body">
    <br>
<p>Must be a valid date. Example: <code>2026-09-19T23:59:59+06:00</code></p>
        </div>
        </form>

                    <h2 id="vendor-DELETEapi-v1-events--event_id--ticket-types--ticketType_id-">Delete ticket type</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Soft-delete a ticket type. Only allowed if no paid orders reference it.</p>

<span id="example-requests-DELETEapi-v1-events--event_id--ticket-types--ticketType_id-">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request DELETE \
    "http://localhost:8000/api/v1/events/architecto/ticket-types/architecto" \
    --header "Authorization: Bearer {YOUR_BEARER_TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/events/architecto/ticket-types/architecto"
);

const headers = {
    "Authorization": "Bearer {YOUR_BEARER_TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "DELETE",
    headers,
}).then(response =&gt; response.json());</code></pre></div>


<div class="php-example">
    <pre><code class="language-php">$client = new \GuzzleHttp\Client();
$url = 'http://localhost:8000/api/v1/events/architecto/ticket-types/architecto';
$response = $client-&gt;delete(
    $url,
    [
        'headers' =&gt; [
            'Authorization' =&gt; 'Bearer {YOUR_BEARER_TOKEN}',
            'Content-Type' =&gt; 'application/json',
            'Accept' =&gt; 'application/json',
        ],
    ]
);
$body = $response-&gt;getBody();
print_r(json_decode((string) $body));</code></pre></div>

</span>

<span id="example-responses-DELETEapi-v1-events--event_id--ticket-types--ticketType_id-">
</span>
<span id="execution-results-DELETEapi-v1-events--event_id--ticket-types--ticketType_id-" hidden>
    <blockquote>Received response<span
                id="execution-response-status-DELETEapi-v1-events--event_id--ticket-types--ticketType_id-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-DELETEapi-v1-events--event_id--ticket-types--ticketType_id-"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-DELETEapi-v1-events--event_id--ticket-types--ticketType_id-" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-DELETEapi-v1-events--event_id--ticket-types--ticketType_id-">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-DELETEapi-v1-events--event_id--ticket-types--ticketType_id-" data-method="DELETE"
      data-path="api/v1/events/{event_id}/ticket-types/{ticketType_id}"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('DELETEapi-v1-events--event_id--ticket-types--ticketType_id-', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-DELETEapi-v1-events--event_id--ticket-types--ticketType_id-"
                    onclick="tryItOut('DELETEapi-v1-events--event_id--ticket-types--ticketType_id-');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-DELETEapi-v1-events--event_id--ticket-types--ticketType_id-"
                    onclick="cancelTryOut('DELETEapi-v1-events--event_id--ticket-types--ticketType_id-');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-DELETEapi-v1-events--event_id--ticket-types--ticketType_id-"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-red">DELETE</small>
            <b><code>api/v1/events/{event_id}/ticket-types/{ticketType_id}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="DELETEapi-v1-events--event_id--ticket-types--ticketType_id-"
               value="Bearer {YOUR_BEARER_TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_BEARER_TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="DELETEapi-v1-events--event_id--ticket-types--ticketType_id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="DELETEapi-v1-events--event_id--ticket-types--ticketType_id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>event_id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="event_id"                data-endpoint="DELETEapi-v1-events--event_id--ticket-types--ticketType_id-"
               value="architecto"
               data-component="url">
    <br>
<p>The ID of the event. Example: <code>architecto</code></p>
            </div>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>ticketType_id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="ticketType_id"                data-endpoint="DELETEapi-v1-events--event_id--ticket-types--ticketType_id-"
               value="architecto"
               data-component="url">
    <br>
<p>The ID of the ticketType. Example: <code>architecto</code></p>
            </div>
                    </form>

                                <h2 id="vendor-kyc">KYC</h2>
                                                    <h2 id="vendor-POSTapi-v1-vendor-kyc">Submit KYC (vendor)</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Submit or re-submit the authenticated vendor's KYC documents for admin review.
KYC status transitions: <code>pending → verified</code> (admin approves) or <code>pending → rejected</code>.
Only <code>pending</code> and <code>rejected</code> vendors may re-submit.</p>

<span id="example-requests-POSTapi-v1-vendor-kyc">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://localhost:8000/api/v1/vendor/kyc" \
    --header "Authorization: Bearer {YOUR_BEARER_TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"documents\": [
        {
            \"type\": \"trade_license\",
            \"storage_path\": \"kyc\\/vendors\\/01JWXYZ0000VENDOR\\/trade_license.pdf\"
        }
    ]
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/vendor/kyc"
);

const headers = {
    "Authorization": "Bearer {YOUR_BEARER_TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "documents": [
        {
            "type": "trade_license",
            "storage_path": "kyc\/vendors\/01JWXYZ0000VENDOR\/trade_license.pdf"
        }
    ]
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>


<div class="php-example">
    <pre><code class="language-php">$client = new \GuzzleHttp\Client();
$url = 'http://localhost:8000/api/v1/vendor/kyc';
$response = $client-&gt;post(
    $url,
    [
        'headers' =&gt; [
            'Authorization' =&gt; 'Bearer {YOUR_BEARER_TOKEN}',
            'Content-Type' =&gt; 'application/json',
            'Accept' =&gt; 'application/json',
        ],
        'json' =&gt; [
            'documents' =&gt; [
                ['type' =&gt; 'trade_license', 'storage_path' =&gt; 'kyc/vendors/01JWXYZ0000VENDOR/trade_license.pdf'],
            ],
        ],
    ]
);
$body = $response-&gt;getBody();
print_r(json_decode((string) $body));</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-vendor-kyc">
</span>
<span id="execution-results-POSTapi-v1-vendor-kyc" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-vendor-kyc"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-vendor-kyc"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-vendor-kyc" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-vendor-kyc">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-vendor-kyc" data-method="POST"
      data-path="api/v1/vendor/kyc"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-vendor-kyc', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-v1-vendor-kyc"
                    onclick="tryItOut('POSTapi-v1-vendor-kyc');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-v1-vendor-kyc"
                    onclick="cancelTryOut('POSTapi-v1-vendor-kyc');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-v1-vendor-kyc"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/vendor/kyc</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="POSTapi-v1-vendor-kyc"
               value="Bearer {YOUR_BEARER_TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_BEARER_TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-vendor-kyc"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-vendor-kyc"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
        <details>
            <summary style="padding-bottom: 10px;">
                <b style="line-height: 2;"><code>documents</code></b>&nbsp;&nbsp;
<small>object[]</small>&nbsp;
 &nbsp;
 &nbsp;
<br>
<p>Must have at least 1 items.</p>
            </summary>
                                                <div style="margin-left: 14px; clear: unset;">
                        <b style="line-height: 2;"><code>type</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="documents.0.type"                data-endpoint="POSTapi-v1-vendor-kyc"
               value="trade_license"
               data-component="body">
    <br>
<p>Example: <code>trade_license</code></p>
Must be one of:
<ul style="list-style-type: square;"><li><code>trade_license</code></li> <li><code>nid</code></li> <li><code>bank_statement</code></li></ul>
                    </div>
                                                                <div style="margin-left: 14px; clear: unset;">
                        <b style="line-height: 2;"><code>storage_path</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="documents.0.storage_path"                data-endpoint="POSTapi-v1-vendor-kyc"
               value="kyc/vendors/01JWXYZ0000VENDOR/trade_license.pdf"
               data-component="body">
    <br>
<p>Must not be greater than 1024 characters. Example: <code>kyc/vendors/01JWXYZ0000VENDOR/trade_license.pdf</code></p>
                    </div>
                                    </details>
        </div>
        </form>

                <h1 id="attendee">Attendee</h1>

    

                        <h2 id="attendee-orders">Orders</h2>
                                                    <h2 id="attendee-POSTapi-v1-orders">Checkout</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Reserve tickets with a 15-minute hold and initiate payment via the payment-service.
The order is created with <code>status=pending</code>; it becomes <code>paid</code> when the payment webhook
confirms success, or <code>expired</code> if the hold expires without payment.</p>
<p><strong>Idempotency:</strong> include a unique <code>Idempotency-Key</code> header. Replaying the same key returns
the existing order without creating a second charge.</p>

<span id="example-requests-POSTapi-v1-orders">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://localhost:8000/api/v1/orders" \
    --header "Authorization: Bearer {YOUR_BEARER_TOKEN}" \
    --header "Idempotency-Key: string required A unique key (UUID recommended) to make this request idempotent. Example: 550e8400-e29b-41d4-a716-446655440000" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"idempotency_key\": \"No-example\",
    \"items\": [
        {
            \"ticket_type_id\": \"01JWXYZ0000000000000TICKET1\",
            \"quantity\": 2
        }
    ]
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/orders"
);

const headers = {
    "Authorization": "Bearer {YOUR_BEARER_TOKEN}",
    "Idempotency-Key": "string required A unique key (UUID recommended) to make this request idempotent. Example: 550e8400-e29b-41d4-a716-446655440000",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "idempotency_key": "No-example",
    "items": [
        {
            "ticket_type_id": "01JWXYZ0000000000000TICKET1",
            "quantity": 2
        }
    ]
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>


<div class="php-example">
    <pre><code class="language-php">$client = new \GuzzleHttp\Client();
$url = 'http://localhost:8000/api/v1/orders';
$response = $client-&gt;post(
    $url,
    [
        'headers' =&gt; [
            'Authorization' =&gt; 'Bearer {YOUR_BEARER_TOKEN}',
            'Idempotency-Key' =&gt; 'string required A unique key (UUID recommended) to make this request idempotent. Example: 550e8400-e29b-41d4-a716-446655440000',
            'Content-Type' =&gt; 'application/json',
            'Accept' =&gt; 'application/json',
        ],
        'json' =&gt; [
            'idempotency_key' =&gt; 'No-example',
            'items' =&gt; [
                ['ticket_type_id' =&gt; '01JWXYZ0000000000000TICKET1', 'quantity' =&gt; 2],
            ],
        ],
    ]
);
$body = $response-&gt;getBody();
print_r(json_decode((string) $body));</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-orders">
            <blockquote>
            <p>Example response (201, Order created (pending payment)):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;success&quot;: true,
    &quot;message&quot;: &quot;Order created, payment initiated.&quot;,
    &quot;data&quot;: {
        &quot;order&quot;: {
            &quot;id&quot;: &quot;01J000000000000DEMOORDER1&quot;,
            &quot;status&quot;: {
                &quot;value&quot;: &quot;pending&quot;,
                &quot;label&quot;: &quot;Pending&quot;
            },
            &quot;total&quot;: 75000,
            &quot;currency&quot;: &quot;BDT&quot;,
            &quot;items&quot;: [
                {
                    &quot;ticket_type_id&quot;: &quot;01J000000000000DEMOTICKET&quot;,
                    &quot;quantity&quot;: 3,
                    &quot;unit_price&quot;: 25000
                }
            ],
            &quot;created_at&quot;: &quot;2026-06-30T10:05:00Z&quot;
        }
    },
    &quot;errors&quot;: null
}</code>
 </pre>
            <blockquote>
            <p>Example response (409, Tickets unavailable):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;success&quot;: false,
    &quot;message&quot;: &quot;Insufficient tickets available. Please try a smaller quantity or a different ticket type.&quot;,
    &quot;data&quot;: null,
    &quot;errors&quot;: null
}</code>
 </pre>
            <blockquote>
            <p>Example response (422, Missing idempotency key):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;success&quot;: false,
    &quot;message&quot;: &quot;Validation failed.&quot;,
    &quot;data&quot;: null,
    &quot;errors&quot;: {
        &quot;idempotency_key&quot;: [
            &quot;The idempotency key is required.&quot;
        ]
    }
}</code>
 </pre>
    </span>
<span id="execution-results-POSTapi-v1-orders" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-orders"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-orders"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-orders" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-orders">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-orders" data-method="POST"
      data-path="api/v1/orders"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-orders', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-v1-orders"
                    onclick="tryItOut('POSTapi-v1-orders');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-v1-orders"
                    onclick="cancelTryOut('POSTapi-v1-orders');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-v1-orders"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/orders</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="POSTapi-v1-orders"
               value="Bearer {YOUR_BEARER_TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_BEARER_TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Idempotency-Key</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Idempotency-Key"                data-endpoint="POSTapi-v1-orders"
               value="string required A unique key (UUID recommended) to make this request idempotent. Example: 550e8400-e29b-41d4-a716-446655440000"
               data-component="header">
    <br>
<p>Example: <code>string required A unique key (UUID recommended) to make this request idempotent. Example: 550e8400-e29b-41d4-a716-446655440000</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-orders"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-orders"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>idempotency_key</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="idempotency_key"                data-endpoint="POSTapi-v1-orders"
               value="No-example"
               data-component="body">
    <br>
<p>Must not be greater than 255 characters. Example: <code>No-example</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
        <details>
            <summary style="padding-bottom: 10px;">
                <b style="line-height: 2;"><code>items</code></b>&nbsp;&nbsp;
<small>object[]</small>&nbsp;
 &nbsp;
 &nbsp;
<br>
<p>Must have at least 1 items. Must not have more than 50 items.</p>
            </summary>
                                                <div style="margin-left: 14px; clear: unset;">
                        <b style="line-height: 2;"><code>ticket_type_id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="items.0.ticket_type_id"                data-endpoint="POSTapi-v1-orders"
               value="01JWXYZ0000000000000TICKET1"
               data-component="body">
    <br>
<p>Must match an existing stored value. Example: <code>01JWXYZ0000000000000TICKET1</code></p>
                    </div>
                                                                <div style="margin-left: 14px; clear: unset;">
                        <b style="line-height: 2;"><code>quantity</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="items.0.quantity"                data-endpoint="POSTapi-v1-orders"
               value="2"
               data-component="body">
    <br>
<p>Must be at least 1. Must not be greater than 100. Example: <code>2</code></p>
                    </div>
                                    </details>
        </div>
        </form>

                    <h2 id="attendee-POSTapi-v1-orders--order_id--pay">Pay order</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Explicitly initiates payment for a pending order. Call this after displaying the payment
form to the attendee — the charge job is dispatched only when the attendee submits.</p>

<span id="example-requests-POSTapi-v1-orders--order_id--pay">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://localhost:8000/api/v1/orders/architecto/pay" \
    --header "Authorization: Bearer {YOUR_BEARER_TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/orders/architecto/pay"
);

const headers = {
    "Authorization": "Bearer {YOUR_BEARER_TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "POST",
    headers,
}).then(response =&gt; response.json());</code></pre></div>


<div class="php-example">
    <pre><code class="language-php">$client = new \GuzzleHttp\Client();
$url = 'http://localhost:8000/api/v1/orders/architecto/pay';
$response = $client-&gt;post(
    $url,
    [
        'headers' =&gt; [
            'Authorization' =&gt; 'Bearer {YOUR_BEARER_TOKEN}',
            'Content-Type' =&gt; 'application/json',
            'Accept' =&gt; 'application/json',
        ],
    ]
);
$body = $response-&gt;getBody();
print_r(json_decode((string) $body));</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-orders--order_id--pay">
            <blockquote>
            <p>Example response (200, Payment initiated):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;success&quot;: true,
    &quot;message&quot;: &quot;Payment initiated. Your order will be confirmed shortly.&quot;,
    &quot;data&quot;: {
        &quot;order&quot;: {
            &quot;id&quot;: &quot;01J000000000000DEMOORDER1&quot;,
            &quot;status&quot;: {
                &quot;value&quot;: &quot;pending&quot;,
                &quot;label&quot;: &quot;Pending&quot;
            }
        }
    },
    &quot;errors&quot;: null
}</code>
 </pre>
            <blockquote>
            <p>Example response (422, Order not payable):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;success&quot;: false,
    &quot;message&quot;: &quot;This order is not in a payable state.&quot;,
    &quot;data&quot;: null,
    &quot;errors&quot;: null
}</code>
 </pre>
    </span>
<span id="execution-results-POSTapi-v1-orders--order_id--pay" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-orders--order_id--pay"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-orders--order_id--pay"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-orders--order_id--pay" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-orders--order_id--pay">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-orders--order_id--pay" data-method="POST"
      data-path="api/v1/orders/{order_id}/pay"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-orders--order_id--pay', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-v1-orders--order_id--pay"
                    onclick="tryItOut('POSTapi-v1-orders--order_id--pay');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-v1-orders--order_id--pay"
                    onclick="cancelTryOut('POSTapi-v1-orders--order_id--pay');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-v1-orders--order_id--pay"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/orders/{order_id}/pay</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="POSTapi-v1-orders--order_id--pay"
               value="Bearer {YOUR_BEARER_TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_BEARER_TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-orders--order_id--pay"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-orders--order_id--pay"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>order_id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="order_id"                data-endpoint="POSTapi-v1-orders--order_id--pay"
               value="architecto"
               data-component="url">
    <br>
<p>The ID of the order. Example: <code>architecto</code></p>
            </div>
                    </form>

                                <h2 id="attendee-refunds">Refunds</h2>
                                                    <h2 id="attendee-POSTapi-v1-orders--order_id--refund">Request refund (attendee)</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Request a refund for a paid order (or a subset of its tickets). The refund amount is
auto-derived from the time-based cancellation policy — the attendee does not specify an amount.
In-policy requests are auto-approved and executed immediately. Out-of-policy requests open a
dispute for admin mediation.</p>

<span id="example-requests-POSTapi-v1-orders--order_id--refund">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://localhost:8000/api/v1/orders/architecto/refund" \
    --header "Authorization: Bearer {YOUR_BEARER_TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"items\": [
        {
            \"order_item_id\": \"01JWXYZ000000000000OITEM1\",
            \"quantity\": 1
        }
    ]
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/orders/architecto/refund"
);

const headers = {
    "Authorization": "Bearer {YOUR_BEARER_TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "items": [
        {
            "order_item_id": "01JWXYZ000000000000OITEM1",
            "quantity": 1
        }
    ]
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>


<div class="php-example">
    <pre><code class="language-php">$client = new \GuzzleHttp\Client();
$url = 'http://localhost:8000/api/v1/orders/architecto/refund';
$response = $client-&gt;post(
    $url,
    [
        'headers' =&gt; [
            'Authorization' =&gt; 'Bearer {YOUR_BEARER_TOKEN}',
            'Content-Type' =&gt; 'application/json',
            'Accept' =&gt; 'application/json',
        ],
        'json' =&gt; [
            'items' =&gt; [
                ['order_item_id' =&gt; '01JWXYZ000000000000OITEM1', 'quantity' =&gt; 1],
            ],
        ],
    ]
);
$body = $response-&gt;getBody();
print_r(json_decode((string) $body));</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-orders--order_id--refund">
            <blockquote>
            <p>Example response (202, Refund accepted):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;success&quot;: true,
    &quot;message&quot;: &quot;Refund requested and queued for processing.&quot;,
    &quot;data&quot;: {
        &quot;refund&quot;: {
            &quot;id&quot;: &quot;01J000000000000DEMOREFUND&quot;,
            &quot;order_id&quot;: &quot;01J000000000000DEMOORDER1&quot;,
            &quot;amount&quot;: 75000,
            &quot;currency&quot;: &quot;BDT&quot;,
            &quot;policy_applied&quot;: &quot;100&quot;,
            &quot;status&quot;: {
                &quot;value&quot;: &quot;pending&quot;,
                &quot;label&quot;: &quot;Pending&quot;
            },
            &quot;reason&quot;: {
                &quot;value&quot;: &quot;attendee_requested&quot;,
                &quot;label&quot;: &quot;Attendee Requested&quot;
            },
            &quot;created_at&quot;: &quot;2026-06-30T10:10:00Z&quot;
        }
    },
    &quot;errors&quot;: null
}</code>
 </pre>
            <blockquote>
            <p>Example response (422, Order not refundable):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;success&quot;: false,
    &quot;message&quot;: &quot;Validation failed.&quot;,
    &quot;data&quot;: null,
    &quot;errors&quot;: {
        &quot;order&quot;: [
            &quot;This order is not eligible for a refund.&quot;
        ]
    }
}</code>
 </pre>
    </span>
<span id="execution-results-POSTapi-v1-orders--order_id--refund" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-orders--order_id--refund"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-orders--order_id--refund"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-orders--order_id--refund" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-orders--order_id--refund">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-orders--order_id--refund" data-method="POST"
      data-path="api/v1/orders/{order_id}/refund"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-orders--order_id--refund', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-v1-orders--order_id--refund"
                    onclick="tryItOut('POSTapi-v1-orders--order_id--refund');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-v1-orders--order_id--refund"
                    onclick="cancelTryOut('POSTapi-v1-orders--order_id--refund');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-v1-orders--order_id--refund"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/orders/{order_id}/refund</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="POSTapi-v1-orders--order_id--refund"
               value="Bearer {YOUR_BEARER_TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_BEARER_TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-orders--order_id--refund"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-orders--order_id--refund"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>order_id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="order_id"                data-endpoint="POSTapi-v1-orders--order_id--refund"
               value="architecto"
               data-component="url">
    <br>
<p>The ID of the order. Example: <code>architecto</code></p>
            </div>
                            <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
        <details>
            <summary style="padding-bottom: 10px;">
                <b style="line-height: 2;"><code>items</code></b>&nbsp;&nbsp;
<small>object[]</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
<br>
<p>Must have at least 1 items. Must not have more than 50 items.</p>
            </summary>
                                                <div style="margin-left: 14px; clear: unset;">
                        <b style="line-height: 2;"><code>order_item_id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="items.0.order_item_id"                data-endpoint="POSTapi-v1-orders--order_id--refund"
               value="01JWXYZ000000000000OITEM1"
               data-component="body">
    <br>
<p>This field is required when <code>items</code> is present. Must match an existing stored value. Example: <code>01JWXYZ000000000000OITEM1</code></p>
                    </div>
                                                                <div style="margin-left: 14px; clear: unset;">
                        <b style="line-height: 2;"><code>quantity</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="items.0.quantity"                data-endpoint="POSTapi-v1-orders--order_id--refund"
               value="1"
               data-component="body">
    <br>
<p>This field is required when <code>items</code> is present. Must be at least 1. Must not be greater than 100. Example: <code>1</code></p>
                    </div>
                                    </details>
        </div>
        </form>

                <h1 id="admin">Admin</h1>

    

                        <h2 id="admin-vendors">Vendors</h2>
                                                    <h2 id="admin-GETapi-v1-admin-vendors">List pending vendors (admin)</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Paginated list of vendors with <code>kyc_status=pending</code>, awaiting an admin decision.</p>

<span id="example-requests-GETapi-v1-admin-vendors">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:8000/api/v1/admin/vendors" \
    --header "Authorization: Bearer {YOUR_BEARER_TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/admin/vendors"
);

const headers = {
    "Authorization": "Bearer {YOUR_BEARER_TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>


<div class="php-example">
    <pre><code class="language-php">$client = new \GuzzleHttp\Client();
$url = 'http://localhost:8000/api/v1/admin/vendors';
$response = $client-&gt;get(
    $url,
    [
        'headers' =&gt; [
            'Authorization' =&gt; 'Bearer {YOUR_BEARER_TOKEN}',
            'Content-Type' =&gt; 'application/json',
            'Accept' =&gt; 'application/json',
        ],
    ]
);
$body = $response-&gt;getBody();
print_r(json_decode((string) $body));</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-admin-vendors">
            <blockquote>
            <p>Example response (200, Success):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;success&quot;: true,
    &quot;message&quot;: &quot;Vendors pending KYC review retrieved.&quot;,
    &quot;data&quot;: {
        &quot;vendors&quot;: [
            {
                &quot;id&quot;: &quot;01JWXYZ0000000000000VENDOR&quot;,
                &quot;business_name&quot;: &quot;Acme Events Ltd&quot;,
                &quot;legal_name&quot;: null,
                &quot;trade_license_no&quot;: null,
                &quot;contact_phone&quot;: &quot;+8801711000000&quot;,
                &quot;address&quot;: null,
                &quot;kyc_status&quot;: {
                    &quot;value&quot;: &quot;pending&quot;,
                    &quot;label&quot;: &quot;Pending&quot;
                },
                &quot;submitted_at&quot;: &quot;2026-06-30T09:00:00+00:00&quot;,
                &quot;reviewed_at&quot;: null,
                &quot;rejection_reason&quot;: null,
                &quot;kyc_documents&quot;: [],
                &quot;created_at&quot;: &quot;2026-06-30T09:00:00+00:00&quot;
            }
        ],
        &quot;pagination&quot;: {
            &quot;current_page&quot;: 1,
            &quot;per_page&quot;: 25,
            &quot;total&quot;: 1,
            &quot;last_page&quot;: 1
        }
    },
    &quot;errors&quot;: null
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-admin-vendors" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-admin-vendors"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-admin-vendors"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-admin-vendors" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-admin-vendors">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-admin-vendors" data-method="GET"
      data-path="api/v1/admin/vendors"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-admin-vendors', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-admin-vendors"
                    onclick="tryItOut('GETapi-v1-admin-vendors');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-admin-vendors"
                    onclick="cancelTryOut('GETapi-v1-admin-vendors');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-admin-vendors"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/admin/vendors</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-v1-admin-vendors"
               value="Bearer {YOUR_BEARER_TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_BEARER_TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-admin-vendors"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-admin-vendors"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                    <h2 id="admin-POSTapi-v1-admin-vendors--vendor_id--verify">Verify vendor (admin)</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Approve a vendor's KYC submission (<code>pending → verified</code>). Verified vendors can publish
events and receive payouts.</p>

<span id="example-requests-POSTapi-v1-admin-vendors--vendor_id--verify">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://localhost:8000/api/v1/admin/vendors/architecto/verify" \
    --header "Authorization: Bearer {YOUR_BEARER_TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/admin/vendors/architecto/verify"
);

const headers = {
    "Authorization": "Bearer {YOUR_BEARER_TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "POST",
    headers,
}).then(response =&gt; response.json());</code></pre></div>


<div class="php-example">
    <pre><code class="language-php">$client = new \GuzzleHttp\Client();
$url = 'http://localhost:8000/api/v1/admin/vendors/architecto/verify';
$response = $client-&gt;post(
    $url,
    [
        'headers' =&gt; [
            'Authorization' =&gt; 'Bearer {YOUR_BEARER_TOKEN}',
            'Content-Type' =&gt; 'application/json',
            'Accept' =&gt; 'application/json',
        ],
    ]
);
$body = $response-&gt;getBody();
print_r(json_decode((string) $body));</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-admin-vendors--vendor_id--verify">
</span>
<span id="execution-results-POSTapi-v1-admin-vendors--vendor_id--verify" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-admin-vendors--vendor_id--verify"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-admin-vendors--vendor_id--verify"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-admin-vendors--vendor_id--verify" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-admin-vendors--vendor_id--verify">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-admin-vendors--vendor_id--verify" data-method="POST"
      data-path="api/v1/admin/vendors/{vendor_id}/verify"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-admin-vendors--vendor_id--verify', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-v1-admin-vendors--vendor_id--verify"
                    onclick="tryItOut('POSTapi-v1-admin-vendors--vendor_id--verify');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-v1-admin-vendors--vendor_id--verify"
                    onclick="cancelTryOut('POSTapi-v1-admin-vendors--vendor_id--verify');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-v1-admin-vendors--vendor_id--verify"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/admin/vendors/{vendor_id}/verify</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="POSTapi-v1-admin-vendors--vendor_id--verify"
               value="Bearer {YOUR_BEARER_TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_BEARER_TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-admin-vendors--vendor_id--verify"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-admin-vendors--vendor_id--verify"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>vendor_id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="vendor_id"                data-endpoint="POSTapi-v1-admin-vendors--vendor_id--verify"
               value="architecto"
               data-component="url">
    <br>
<p>The ID of the vendor. Example: <code>architecto</code></p>
            </div>
                    </form>

                    <h2 id="admin-POSTapi-v1-admin-vendors--vendor_id--reject">Reject vendor (admin)</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Reject a vendor's KYC submission (<code>pending → rejected</code>) with a mandatory reason.
The vendor may re-submit after addressing the stated issues.</p>

<span id="example-requests-POSTapi-v1-admin-vendors--vendor_id--reject">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://localhost:8000/api/v1/admin/vendors/architecto/reject" \
    --header "Authorization: Bearer {YOUR_BEARER_TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"rejection_reason\": \"Submitted documents are blurry or incomplete. Please re-upload a clearer copy of your trade license.\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/admin/vendors/architecto/reject"
);

const headers = {
    "Authorization": "Bearer {YOUR_BEARER_TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "rejection_reason": "Submitted documents are blurry or incomplete. Please re-upload a clearer copy of your trade license."
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>


<div class="php-example">
    <pre><code class="language-php">$client = new \GuzzleHttp\Client();
$url = 'http://localhost:8000/api/v1/admin/vendors/architecto/reject';
$response = $client-&gt;post(
    $url,
    [
        'headers' =&gt; [
            'Authorization' =&gt; 'Bearer {YOUR_BEARER_TOKEN}',
            'Content-Type' =&gt; 'application/json',
            'Accept' =&gt; 'application/json',
        ],
        'json' =&gt; [
            'rejection_reason' =&gt; 'Submitted documents are blurry or incomplete. Please re-upload a clearer copy of your trade license.',
        ],
    ]
);
$body = $response-&gt;getBody();
print_r(json_decode((string) $body));</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-admin-vendors--vendor_id--reject">
</span>
<span id="execution-results-POSTapi-v1-admin-vendors--vendor_id--reject" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-admin-vendors--vendor_id--reject"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-admin-vendors--vendor_id--reject"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-admin-vendors--vendor_id--reject" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-admin-vendors--vendor_id--reject">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-admin-vendors--vendor_id--reject" data-method="POST"
      data-path="api/v1/admin/vendors/{vendor_id}/reject"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-admin-vendors--vendor_id--reject', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-v1-admin-vendors--vendor_id--reject"
                    onclick="tryItOut('POSTapi-v1-admin-vendors--vendor_id--reject');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-v1-admin-vendors--vendor_id--reject"
                    onclick="cancelTryOut('POSTapi-v1-admin-vendors--vendor_id--reject');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-v1-admin-vendors--vendor_id--reject"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/admin/vendors/{vendor_id}/reject</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="POSTapi-v1-admin-vendors--vendor_id--reject"
               value="Bearer {YOUR_BEARER_TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_BEARER_TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-admin-vendors--vendor_id--reject"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-admin-vendors--vendor_id--reject"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>vendor_id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="vendor_id"                data-endpoint="POSTapi-v1-admin-vendors--vendor_id--reject"
               value="architecto"
               data-component="url">
    <br>
<p>The ID of the vendor. Example: <code>architecto</code></p>
            </div>
                            <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>rejection_reason</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="rejection_reason"                data-endpoint="POSTapi-v1-admin-vendors--vendor_id--reject"
               value="Submitted documents are blurry or incomplete. Please re-upload a clearer copy of your trade license."
               data-component="body">
    <br>
<p>Must not be greater than 1000 characters. Example: <code>Submitted documents are blurry or incomplete. Please re-upload a clearer copy of your trade license.</code></p>
        </div>
        </form>

                                <h2 id="admin-refunds">Refunds</h2>
                                                    <h2 id="admin-POSTapi-v1-admin-orders--order_id--refund">Initiate refund (admin)</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Admin-initiated refund — e.g. when an event is cancelled. The reason can be
<code>attendee_requested</code> or <code>event_cancelled</code>. Amount is policy-derived (cancellations are 100%).
Idempotent: replaying the same order returns the existing open refund.</p>

<span id="example-requests-POSTapi-v1-admin-orders--order_id--refund">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://localhost:8000/api/v1/admin/orders/architecto/refund" \
    --header "Authorization: Bearer {YOUR_BEARER_TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"reason\": \"event_cancelled\",
    \"items\": [
        {
            \"order_item_id\": \"01JWXYZ000000000000OITEM1\",
            \"quantity\": 1
        }
    ]
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/admin/orders/architecto/refund"
);

const headers = {
    "Authorization": "Bearer {YOUR_BEARER_TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "reason": "event_cancelled",
    "items": [
        {
            "order_item_id": "01JWXYZ000000000000OITEM1",
            "quantity": 1
        }
    ]
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>


<div class="php-example">
    <pre><code class="language-php">$client = new \GuzzleHttp\Client();
$url = 'http://localhost:8000/api/v1/admin/orders/architecto/refund';
$response = $client-&gt;post(
    $url,
    [
        'headers' =&gt; [
            'Authorization' =&gt; 'Bearer {YOUR_BEARER_TOKEN}',
            'Content-Type' =&gt; 'application/json',
            'Accept' =&gt; 'application/json',
        ],
        'json' =&gt; [
            'reason' =&gt; 'event_cancelled',
            'items' =&gt; [
                ['order_item_id' =&gt; '01JWXYZ000000000000OITEM1', 'quantity' =&gt; 1],
            ],
        ],
    ]
);
$body = $response-&gt;getBody();
print_r(json_decode((string) $body));</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-admin-orders--order_id--refund">
            <blockquote>
            <p>Example response (202, Refund initiated):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;success&quot;: true,
    &quot;message&quot;: &quot;Refund requested and queued for processing.&quot;,
    &quot;data&quot;: {
        &quot;refund&quot;: {
            &quot;id&quot;: &quot;01J000000000000DEMOREFUND&quot;,
            &quot;order_id&quot;: &quot;01J000000000000DEMOORDER1&quot;,
            &quot;amount&quot;: 75000,
            &quot;currency&quot;: &quot;BDT&quot;,
            &quot;policy_applied&quot;: &quot;100&quot;,
            &quot;status&quot;: {
                &quot;value&quot;: &quot;pending&quot;,
                &quot;label&quot;: &quot;Pending&quot;
            },
            &quot;reason&quot;: {
                &quot;value&quot;: &quot;event_cancelled&quot;,
                &quot;label&quot;: &quot;Event Cancelled&quot;
            },
            &quot;created_at&quot;: &quot;2026-06-30T10:10:00Z&quot;
        }
    },
    &quot;errors&quot;: null
}</code>
 </pre>
    </span>
<span id="execution-results-POSTapi-v1-admin-orders--order_id--refund" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-admin-orders--order_id--refund"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-admin-orders--order_id--refund"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-admin-orders--order_id--refund" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-admin-orders--order_id--refund">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-admin-orders--order_id--refund" data-method="POST"
      data-path="api/v1/admin/orders/{order_id}/refund"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-admin-orders--order_id--refund', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-v1-admin-orders--order_id--refund"
                    onclick="tryItOut('POSTapi-v1-admin-orders--order_id--refund');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-v1-admin-orders--order_id--refund"
                    onclick="cancelTryOut('POSTapi-v1-admin-orders--order_id--refund');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-v1-admin-orders--order_id--refund"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/admin/orders/{order_id}/refund</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="POSTapi-v1-admin-orders--order_id--refund"
               value="Bearer {YOUR_BEARER_TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_BEARER_TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-admin-orders--order_id--refund"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-admin-orders--order_id--refund"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>order_id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="order_id"                data-endpoint="POSTapi-v1-admin-orders--order_id--refund"
               value="architecto"
               data-component="url">
    <br>
<p>The ID of the order. Example: <code>architecto</code></p>
            </div>
                            <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>reason</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="reason"                data-endpoint="POSTapi-v1-admin-orders--order_id--refund"
               value="event_cancelled"
               data-component="body">
    <br>
<p>Example: <code>event_cancelled</code></p>
Must be one of:
<ul style="list-style-type: square;"><li><code>attendee_requested</code></li> <li><code>event_cancelled</code></li> <li><code>late_payment</code></li></ul>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
        <details>
            <summary style="padding-bottom: 10px;">
                <b style="line-height: 2;"><code>items</code></b>&nbsp;&nbsp;
<small>object[]</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
<br>
<p>Must have at least 1 items. Must not have more than 50 items.</p>
            </summary>
                                                <div style="margin-left: 14px; clear: unset;">
                        <b style="line-height: 2;"><code>order_item_id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="items.0.order_item_id"                data-endpoint="POSTapi-v1-admin-orders--order_id--refund"
               value="01JWXYZ000000000000OITEM1"
               data-component="body">
    <br>
<p>This field is required when <code>items</code> is present. Must match an existing stored value. Example: <code>01JWXYZ000000000000OITEM1</code></p>
                    </div>
                                                                <div style="margin-left: 14px; clear: unset;">
                        <b style="line-height: 2;"><code>quantity</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="items.0.quantity"                data-endpoint="POSTapi-v1-admin-orders--order_id--refund"
               value="1"
               data-component="body">
    <br>
<p>This field is required when <code>items</code> is present. Must be at least 1. Must not be greater than 100. Example: <code>1</code></p>
                    </div>
                                    </details>
        </div>
        </form>

                                        <h2 id="admin-GETapi-v1-admin-disputes">List open disputes</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Returns a paginated list of open disputes awaiting admin review.</p>

<span id="example-requests-GETapi-v1-admin-disputes">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:8000/api/v1/admin/disputes" \
    --header "Authorization: Bearer {YOUR_BEARER_TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/admin/disputes"
);

const headers = {
    "Authorization": "Bearer {YOUR_BEARER_TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>


<div class="php-example">
    <pre><code class="language-php">$client = new \GuzzleHttp\Client();
$url = 'http://localhost:8000/api/v1/admin/disputes';
$response = $client-&gt;get(
    $url,
    [
        'headers' =&gt; [
            'Authorization' =&gt; 'Bearer {YOUR_BEARER_TOKEN}',
            'Content-Type' =&gt; 'application/json',
            'Accept' =&gt; 'application/json',
        ],
    ]
);
$body = $response-&gt;getBody();
print_r(json_decode((string) $body));</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-admin-disputes">
            <blockquote>
            <p>Example response (200, Success):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;success&quot;: true,
    &quot;message&quot;: &quot;Disputes retrieved.&quot;,
    &quot;data&quot;: {
        &quot;disputes&quot;: [
            {
                &quot;id&quot;: &quot;01J000000000000DEMODISPUTE&quot;,
                &quot;order_id&quot;: &quot;01J000000000000DEMOORDER1&quot;,
                &quot;status&quot;: {
                    &quot;value&quot;: &quot;open&quot;,
                    &quot;label&quot;: &quot;Open&quot;
                },
                &quot;reason&quot;: &quot;attendee_requested&quot;,
                &quot;created_at&quot;: &quot;2026-06-30T20:00:00Z&quot;
            }
        ],
        &quot;pagination&quot;: {
            &quot;current_page&quot;: 1,
            &quot;per_page&quot;: 15,
            &quot;total&quot;: 1,
            &quot;last_page&quot;: 1
        }
    },
    &quot;errors&quot;: null
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-admin-disputes" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-admin-disputes"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-admin-disputes"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-admin-disputes" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-admin-disputes">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-admin-disputes" data-method="GET"
      data-path="api/v1/admin/disputes"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-admin-disputes', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-admin-disputes"
                    onclick="tryItOut('GETapi-v1-admin-disputes');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-admin-disputes"
                    onclick="cancelTryOut('GETapi-v1-admin-disputes');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-admin-disputes"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/admin/disputes</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-v1-admin-disputes"
               value="Bearer {YOUR_BEARER_TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_BEARER_TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-admin-disputes"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-admin-disputes"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                    <h2 id="admin-POSTapi-v1-admin-disputes--dispute_id--resolve">Resolve dispute (approve refund)</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Approve the dispute: creates a full-remaining-balance refund override (ignoring the
time-based policy) and marks the dispute resolved. Idempotent on an already-resolved dispute.</p>

<span id="example-requests-POSTapi-v1-admin-disputes--dispute_id--resolve">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://localhost:8000/api/v1/admin/disputes/architecto/resolve" \
    --header "Authorization: Bearer {YOUR_BEARER_TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"resolution\": \"Reviewed CCTV footage — attendee did not enter. Approved full refund.\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/admin/disputes/architecto/resolve"
);

const headers = {
    "Authorization": "Bearer {YOUR_BEARER_TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "resolution": "Reviewed CCTV footage — attendee did not enter. Approved full refund."
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>


<div class="php-example">
    <pre><code class="language-php">$client = new \GuzzleHttp\Client();
$url = 'http://localhost:8000/api/v1/admin/disputes/architecto/resolve';
$response = $client-&gt;post(
    $url,
    [
        'headers' =&gt; [
            'Authorization' =&gt; 'Bearer {YOUR_BEARER_TOKEN}',
            'Content-Type' =&gt; 'application/json',
            'Accept' =&gt; 'application/json',
        ],
        'json' =&gt; [
            'resolution' =&gt; 'Reviewed CCTV footage — attendee did not enter. Approved full refund.',
        ],
    ]
);
$body = $response-&gt;getBody();
print_r(json_decode((string) $body));</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-admin-disputes--dispute_id--resolve">
            <blockquote>
            <p>Example response (200, Resolved):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;success&quot;: true,
    &quot;message&quot;: &quot;Dispute resolved. Refund has been queued.&quot;,
    &quot;data&quot;: {
        &quot;dispute&quot;: {
            &quot;id&quot;: &quot;01J000000000000DEMODISPUTE&quot;,
            &quot;order_id&quot;: &quot;01J000000000000DEMOORDER1&quot;,
            &quot;status&quot;: {
                &quot;value&quot;: &quot;resolved&quot;,
                &quot;label&quot;: &quot;Resolved&quot;
            },
            &quot;reason&quot;: &quot;attendee_requested&quot;,
            &quot;created_at&quot;: &quot;2026-06-30T20:00:00Z&quot;
        }
    },
    &quot;errors&quot;: null
}</code>
 </pre>
    </span>
<span id="execution-results-POSTapi-v1-admin-disputes--dispute_id--resolve" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-admin-disputes--dispute_id--resolve"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-admin-disputes--dispute_id--resolve"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-admin-disputes--dispute_id--resolve" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-admin-disputes--dispute_id--resolve">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-admin-disputes--dispute_id--resolve" data-method="POST"
      data-path="api/v1/admin/disputes/{dispute_id}/resolve"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-admin-disputes--dispute_id--resolve', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-v1-admin-disputes--dispute_id--resolve"
                    onclick="tryItOut('POSTapi-v1-admin-disputes--dispute_id--resolve');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-v1-admin-disputes--dispute_id--resolve"
                    onclick="cancelTryOut('POSTapi-v1-admin-disputes--dispute_id--resolve');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-v1-admin-disputes--dispute_id--resolve"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/admin/disputes/{dispute_id}/resolve</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="POSTapi-v1-admin-disputes--dispute_id--resolve"
               value="Bearer {YOUR_BEARER_TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_BEARER_TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-admin-disputes--dispute_id--resolve"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-admin-disputes--dispute_id--resolve"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>dispute_id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="dispute_id"                data-endpoint="POSTapi-v1-admin-disputes--dispute_id--resolve"
               value="architecto"
               data-component="url">
    <br>
<p>The ID of the dispute. Example: <code>architecto</code></p>
            </div>
                            <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>resolution</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="resolution"                data-endpoint="POSTapi-v1-admin-disputes--dispute_id--resolve"
               value="Reviewed CCTV footage — attendee did not enter. Approved full refund."
               data-component="body">
    <br>
<p>Admin note explaining the resolution outcome (optional). Must not be greater than 1000 characters. Example: <code>Reviewed CCTV footage — attendee did not enter. Approved full refund.</code></p>
        </div>
        </form>

                    <h2 id="admin-POSTapi-v1-admin-disputes--dispute_id--reject">Reject dispute</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Deny the dispute: no refund is issued. The dispute is closed with the admin's resolution note.
Idempotent on an already-rejected dispute.</p>

<span id="example-requests-POSTapi-v1-admin-disputes--dispute_id--reject">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://localhost:8000/api/v1/admin/disputes/architecto/reject" \
    --header "Authorization: Bearer {YOUR_BEARER_TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"resolution\": \"Event terms clearly state no refunds within 24 hours. Dispute rejected.\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/admin/disputes/architecto/reject"
);

const headers = {
    "Authorization": "Bearer {YOUR_BEARER_TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "resolution": "Event terms clearly state no refunds within 24 hours. Dispute rejected."
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>


<div class="php-example">
    <pre><code class="language-php">$client = new \GuzzleHttp\Client();
$url = 'http://localhost:8000/api/v1/admin/disputes/architecto/reject';
$response = $client-&gt;post(
    $url,
    [
        'headers' =&gt; [
            'Authorization' =&gt; 'Bearer {YOUR_BEARER_TOKEN}',
            'Content-Type' =&gt; 'application/json',
            'Accept' =&gt; 'application/json',
        ],
        'json' =&gt; [
            'resolution' =&gt; 'Event terms clearly state no refunds within 24 hours. Dispute rejected.',
        ],
    ]
);
$body = $response-&gt;getBody();
print_r(json_decode((string) $body));</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-admin-disputes--dispute_id--reject">
            <blockquote>
            <p>Example response (200, Rejected):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;success&quot;: true,
    &quot;message&quot;: &quot;Dispute rejected.&quot;,
    &quot;data&quot;: {
        &quot;dispute&quot;: {
            &quot;id&quot;: &quot;01J000000000000DEMODISPUTE&quot;,
            &quot;order_id&quot;: &quot;01J000000000000DEMOORDER1&quot;,
            &quot;status&quot;: {
                &quot;value&quot;: &quot;rejected&quot;,
                &quot;label&quot;: &quot;Rejected&quot;
            },
            &quot;reason&quot;: &quot;attendee_requested&quot;,
            &quot;created_at&quot;: &quot;2026-06-30T20:00:00Z&quot;
        }
    },
    &quot;errors&quot;: null
}</code>
 </pre>
    </span>
<span id="execution-results-POSTapi-v1-admin-disputes--dispute_id--reject" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-admin-disputes--dispute_id--reject"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-admin-disputes--dispute_id--reject"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-admin-disputes--dispute_id--reject" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-admin-disputes--dispute_id--reject">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-admin-disputes--dispute_id--reject" data-method="POST"
      data-path="api/v1/admin/disputes/{dispute_id}/reject"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-admin-disputes--dispute_id--reject', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-v1-admin-disputes--dispute_id--reject"
                    onclick="tryItOut('POSTapi-v1-admin-disputes--dispute_id--reject');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-v1-admin-disputes--dispute_id--reject"
                    onclick="cancelTryOut('POSTapi-v1-admin-disputes--dispute_id--reject');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-v1-admin-disputes--dispute_id--reject"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/admin/disputes/{dispute_id}/reject</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="POSTapi-v1-admin-disputes--dispute_id--reject"
               value="Bearer {YOUR_BEARER_TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_BEARER_TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-admin-disputes--dispute_id--reject"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-admin-disputes--dispute_id--reject"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>dispute_id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="dispute_id"                data-endpoint="POSTapi-v1-admin-disputes--dispute_id--reject"
               value="architecto"
               data-component="url">
    <br>
<p>The ID of the dispute. Example: <code>architecto</code></p>
            </div>
                            <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>resolution</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="resolution"                data-endpoint="POSTapi-v1-admin-disputes--dispute_id--reject"
               value="Event terms clearly state no refunds within 24 hours. Dispute rejected."
               data-component="body">
    <br>
<p>Admin note explaining why the dispute was rejected (required). Must not be greater than 1000 characters. Example: <code>Event terms clearly state no refunds within 24 hours. Dispute rejected.</code></p>
        </div>
        </form>

                                <h2 id="admin-payouts">Payouts</h2>
                                                    <h2 id="admin-GETapi-v1-admin-payouts">List payouts (admin)</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Paginated list of all vendor payouts. Filter by <code>status</code> and/or <code>vendor_id</code>.</p>

<span id="example-requests-GETapi-v1-admin-payouts">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:8000/api/v1/admin/payouts?status=pending&amp;vendor_id=no-example&amp;per_page=20" \
    --header "Authorization: Bearer {YOUR_BEARER_TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/admin/payouts"
);

const params = {
    "status": "pending",
    "vendor_id": "no-example",
    "per_page": "20",
};
Object.keys(params)
    .forEach(key =&gt; url.searchParams.append(key, params[key]));

const headers = {
    "Authorization": "Bearer {YOUR_BEARER_TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>


<div class="php-example">
    <pre><code class="language-php">$client = new \GuzzleHttp\Client();
$url = 'http://localhost:8000/api/v1/admin/payouts';
$response = $client-&gt;get(
    $url,
    [
        'headers' =&gt; [
            'Authorization' =&gt; 'Bearer {YOUR_BEARER_TOKEN}',
            'Content-Type' =&gt; 'application/json',
            'Accept' =&gt; 'application/json',
        ],
        'query' =&gt; [
            'status' =&gt; 'pending',
            'vendor_id' =&gt; 'no-example',
            'per_page' =&gt; '20',
        ],
    ]
);
$body = $response-&gt;getBody();
print_r(json_decode((string) $body));</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-admin-payouts">
            <blockquote>
            <p>Example response (200, Success):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;success&quot;: true,
    &quot;message&quot;: &quot;Payouts retrieved.&quot;,
    &quot;data&quot;: {
        &quot;payouts&quot;: [
            {
                &quot;id&quot;: &quot;01JWXYZ000000000000PAYOUT1&quot;,
                &quot;vendor_id&quot;: &quot;01JWXYZ0000000000000VENDOR&quot;,
                &quot;batch_id&quot;: &quot;2026-09-20&quot;,
                &quot;currency&quot;: &quot;BDT&quot;,
                &quot;gross&quot;: 500000,
                &quot;commission&quot;: 50000,
                &quot;net&quot;: 450000,
                &quot;payable&quot;: 450000,
                &quot;reserved_refund&quot;: 0,
                &quot;status&quot;: {
                    &quot;value&quot;: &quot;pending&quot;,
                    &quot;label&quot;: &quot;Pending&quot;
                },
                &quot;created_at&quot;: &quot;2026-06-30T09:00:00+00:00&quot;,
                &quot;updated_at&quot;: &quot;2026-06-30T09:00:00+00:00&quot;
            }
        ],
        &quot;pagination&quot;: {
            &quot;current_page&quot;: 1,
            &quot;last_page&quot;: 1,
            &quot;total&quot;: 1,
            &quot;per_page&quot;: 20
        }
    },
    &quot;errors&quot;: null
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-admin-payouts" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-admin-payouts"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-admin-payouts"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-admin-payouts" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-admin-payouts">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-admin-payouts" data-method="GET"
      data-path="api/v1/admin/payouts"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-admin-payouts', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-admin-payouts"
                    onclick="tryItOut('GETapi-v1-admin-payouts');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-admin-payouts"
                    onclick="cancelTryOut('GETapi-v1-admin-payouts');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-admin-payouts"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/admin/payouts</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-v1-admin-payouts"
               value="Bearer {YOUR_BEARER_TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_BEARER_TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-admin-payouts"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-admin-payouts"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                            <h4 class="fancy-heading-panel"><b>Query Parameters</b></h4>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>status</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="status"                data-endpoint="GETapi-v1-admin-payouts"
               value="pending"
               data-component="query">
    <br>
<p>Filter by payout status. Example: <code>pending</code></p>
Must be one of:
<ul style="list-style-type: square;"><li><code>pending</code></li> <li><code>approved</code></li> <li><code>processing</code></li> <li><code>paid</code></li> <li><code>failed</code></li></ul>
            </div>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>vendor_id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="vendor_id"                data-endpoint="GETapi-v1-admin-payouts"
               value="no-example"
               data-component="query">
    <br>
<p>Filter by vendor (admin only). Must not be greater than 26 characters. Example: <code>no-example</code></p>
            </div>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>per_page</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="per_page"                data-endpoint="GETapi-v1-admin-payouts"
               value="20"
               data-component="query">
    <br>
<p>Items per page (1–100). Must be at least 1. Must not be greater than 100. Example: <code>20</code></p>
            </div>
                </form>

                    <h2 id="admin-POSTapi-v1-admin-payouts-build">Build payout batch (admin)</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Calculate pending settlements for all eligible vendors (or a single vendor if <code>vendor_id</code>
is provided). This is a <strong>decide-only</strong> step — it creates <code>Payout</code> records but moves no money.
Call <code>/admin/payouts/{payout}/execute</code> to actually disburse. Idempotent per <code>batch_id</code>.</p>

<span id="example-requests-POSTapi-v1-admin-payouts-build">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://localhost:8000/api/v1/admin/payouts/build" \
    --header "Authorization: Bearer {YOUR_BEARER_TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"vendor_id\": \"no-example\",
    \"batch_id\": \"2026-09-20\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/admin/payouts/build"
);

const headers = {
    "Authorization": "Bearer {YOUR_BEARER_TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "vendor_id": "no-example",
    "batch_id": "2026-09-20"
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>


<div class="php-example">
    <pre><code class="language-php">$client = new \GuzzleHttp\Client();
$url = 'http://localhost:8000/api/v1/admin/payouts/build';
$response = $client-&gt;post(
    $url,
    [
        'headers' =&gt; [
            'Authorization' =&gt; 'Bearer {YOUR_BEARER_TOKEN}',
            'Content-Type' =&gt; 'application/json',
            'Accept' =&gt; 'application/json',
        ],
        'json' =&gt; [
            'vendor_id' =&gt; 'no-example',
            'batch_id' =&gt; '2026-09-20',
        ],
    ]
);
$body = $response-&gt;getBody();
print_r(json_decode((string) $body));</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-admin-payouts-build">
            <blockquote>
            <p>Example response (201, Batch built):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;success&quot;: true,
    &quot;message&quot;: &quot;1 payout(s) built.&quot;,
    &quot;data&quot;: {
        &quot;batch_id&quot;: &quot;2026-06-30&quot;,
        &quot;count&quot;: 1,
        &quot;payouts&quot;: [
            {
                &quot;id&quot;: &quot;01J000000000000DEMOPAYOUT&quot;,
                &quot;vendor_id&quot;: &quot;01J000000000000DEMOVENDOR&quot;,
                &quot;gross&quot;: 500000,
                &quot;commission&quot;: 50000,
                &quot;net&quot;: 450000,
                &quot;currency&quot;: &quot;BDT&quot;,
                &quot;status&quot;: {
                    &quot;value&quot;: &quot;pending&quot;,
                    &quot;label&quot;: &quot;Pending&quot;
                },
                &quot;batch_id&quot;: &quot;2026-06-30&quot;,
                &quot;created_at&quot;: &quot;2026-06-30T09:00:00Z&quot;
            }
        ]
    },
    &quot;errors&quot;: null
}</code>
 </pre>
    </span>
<span id="execution-results-POSTapi-v1-admin-payouts-build" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-admin-payouts-build"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-admin-payouts-build"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-admin-payouts-build" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-admin-payouts-build">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-admin-payouts-build" data-method="POST"
      data-path="api/v1/admin/payouts/build"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-admin-payouts-build', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-v1-admin-payouts-build"
                    onclick="tryItOut('POSTapi-v1-admin-payouts-build');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-v1-admin-payouts-build"
                    onclick="cancelTryOut('POSTapi-v1-admin-payouts-build');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-v1-admin-payouts-build"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/admin/payouts/build</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="POSTapi-v1-admin-payouts-build"
               value="Bearer {YOUR_BEARER_TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_BEARER_TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-admin-payouts-build"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-admin-payouts-build"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>vendor_id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="vendor_id"                data-endpoint="POSTapi-v1-admin-payouts-build"
               value="no-example"
               data-component="body">
    <br>
<p>Must match an existing stored value. Must not be greater than 26 characters. Example: <code>no-example</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>batch_id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="batch_id"                data-endpoint="POSTapi-v1-admin-payouts-build"
               value="2026-09-20"
               data-component="body">
    <br>
<p>Must not be greater than 64 characters. Example: <code>2026-09-20</code></p>
        </div>
        </form>

                    <h2 id="admin-POSTapi-v1-admin-payouts--payout_id--execute">Execute payout</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Dispatch payment for a <code>pending</code> or <code>approved</code> payout. The job transitions the payout to
<code>processing</code> and calls payment-service; the terminal result (<code>paid</code> or <code>failed</code>) arrives
via the signed payment-service webhook. Idempotent — re-dispatching the same payout
reuses the deterministic idempotency key.</p>

<span id="example-requests-POSTapi-v1-admin-payouts--payout_id--execute">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://localhost:8000/api/v1/admin/payouts/architecto/execute" \
    --header "Authorization: Bearer {YOUR_BEARER_TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/admin/payouts/architecto/execute"
);

const headers = {
    "Authorization": "Bearer {YOUR_BEARER_TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "POST",
    headers,
}).then(response =&gt; response.json());</code></pre></div>


<div class="php-example">
    <pre><code class="language-php">$client = new \GuzzleHttp\Client();
$url = 'http://localhost:8000/api/v1/admin/payouts/architecto/execute';
$response = $client-&gt;post(
    $url,
    [
        'headers' =&gt; [
            'Authorization' =&gt; 'Bearer {YOUR_BEARER_TOKEN}',
            'Content-Type' =&gt; 'application/json',
            'Accept' =&gt; 'application/json',
        ],
    ]
);
$body = $response-&gt;getBody();
print_r(json_decode((string) $body));</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-admin-payouts--payout_id--execute">
            <blockquote>
            <p>Example response (200, Queued):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;success&quot;: true,
    &quot;message&quot;: &quot;Payout execution queued.&quot;,
    &quot;data&quot;: {
        &quot;payout&quot;: {
            &quot;id&quot;: &quot;01J000000000000DEMOPAYOUT&quot;,
            &quot;vendor_id&quot;: &quot;01J000000000000DEMOVENDOR&quot;,
            &quot;gross&quot;: 500000,
            &quot;commission&quot;: 50000,
            &quot;net&quot;: 450000,
            &quot;currency&quot;: &quot;BDT&quot;,
            &quot;status&quot;: {
                &quot;value&quot;: &quot;pending&quot;,
                &quot;label&quot;: &quot;Pending&quot;
            },
            &quot;batch_id&quot;: &quot;01J0BATCHID&quot;,
            &quot;created_at&quot;: &quot;2026-06-30T09:00:00Z&quot;
        }
    },
    &quot;errors&quot;: null
}</code>
 </pre>
            <blockquote>
            <p>Example response (409, Not executable):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;success&quot;: false,
    &quot;message&quot;: &quot;This payout cannot be executed in its current status.&quot;,
    &quot;data&quot;: null,
    &quot;errors&quot;: null
}</code>
 </pre>
    </span>
<span id="execution-results-POSTapi-v1-admin-payouts--payout_id--execute" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-admin-payouts--payout_id--execute"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-admin-payouts--payout_id--execute"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-admin-payouts--payout_id--execute" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-admin-payouts--payout_id--execute">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-admin-payouts--payout_id--execute" data-method="POST"
      data-path="api/v1/admin/payouts/{payout_id}/execute"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-admin-payouts--payout_id--execute', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-v1-admin-payouts--payout_id--execute"
                    onclick="tryItOut('POSTapi-v1-admin-payouts--payout_id--execute');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-v1-admin-payouts--payout_id--execute"
                    onclick="cancelTryOut('POSTapi-v1-admin-payouts--payout_id--execute');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-v1-admin-payouts--payout_id--execute"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/admin/payouts/{payout_id}/execute</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="POSTapi-v1-admin-payouts--payout_id--execute"
               value="Bearer {YOUR_BEARER_TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_BEARER_TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-admin-payouts--payout_id--execute"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-admin-payouts--payout_id--execute"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>payout_id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="payout_id"                data-endpoint="POSTapi-v1-admin-payouts--payout_id--execute"
               value="architecto"
               data-component="url">
    <br>
<p>The ID of the payout. Example: <code>architecto</code></p>
            </div>
                    </form>

                                <h2 id="admin-system">System</h2>
                                                    <h2 id="admin-GETapi-v1-admin-ping">Admin ping</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Liveness check scoped to the admin area. Verifies the bearer token carries the admin role.</p>

<span id="example-requests-GETapi-v1-admin-ping">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:8000/api/v1/admin/ping" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/admin/ping"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>


<div class="php-example">
    <pre><code class="language-php">$client = new \GuzzleHttp\Client();
$url = 'http://localhost:8000/api/v1/admin/ping';
$response = $client-&gt;get(
    $url,
    [
        'headers' =&gt; [
            'Content-Type' =&gt; 'application/json',
            'Accept' =&gt; 'application/json',
        ],
    ]
);
$body = $response-&gt;getBody();
print_r(json_decode((string) $body));</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-admin-ping">
            <blockquote>
            <p>Example response (200, Admin area reachable.):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;success&quot;: true,
    &quot;message&quot;: &quot;ok&quot;,
    &quot;data&quot;: {
        &quot;area&quot;: &quot;admin&quot;
    },
    &quot;errors&quot;: null
}</code>
 </pre>
            <blockquote>
            <p>Example response (401, Unauthenticated.):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;success&quot;: false,
    &quot;message&quot;: &quot;Unauthenticated.&quot;,
    &quot;data&quot;: null,
    &quot;errors&quot;: null
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-admin-ping" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-admin-ping"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-admin-ping"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-admin-ping" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-admin-ping">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-admin-ping" data-method="GET"
      data-path="api/v1/admin/ping"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-admin-ping', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-admin-ping"
                    onclick="tryItOut('GETapi-v1-admin-ping');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-admin-ping"
                    onclick="cancelTryOut('GETapi-v1-admin-ping');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-admin-ping"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/admin/ping</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-admin-ping"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-admin-ping"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                <h1 id="orders">Orders</h1>

    

                                <h2 id="orders-GETapi-v1-orders">List orders</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Returns a paginated list of orders. Attendees see only their own orders;
admins see all orders and can filter by status (e.g. for the dispute queue).</p>

<span id="example-requests-GETapi-v1-orders">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:8000/api/v1/orders?status=paid&amp;per_page=15" \
    --header "Authorization: Bearer {YOUR_BEARER_TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/orders"
);

const params = {
    "status": "paid",
    "per_page": "15",
};
Object.keys(params)
    .forEach(key =&gt; url.searchParams.append(key, params[key]));

const headers = {
    "Authorization": "Bearer {YOUR_BEARER_TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>


<div class="php-example">
    <pre><code class="language-php">$client = new \GuzzleHttp\Client();
$url = 'http://localhost:8000/api/v1/orders';
$response = $client-&gt;get(
    $url,
    [
        'headers' =&gt; [
            'Authorization' =&gt; 'Bearer {YOUR_BEARER_TOKEN}',
            'Content-Type' =&gt; 'application/json',
            'Accept' =&gt; 'application/json',
        ],
        'query' =&gt; [
            'status' =&gt; 'paid',
            'per_page' =&gt; '15',
        ],
    ]
);
$body = $response-&gt;getBody();
print_r(json_decode((string) $body));</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-orders">
            <blockquote>
            <p>Example response (200, Success):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;success&quot;: true,
    &quot;message&quot;: &quot;Orders retrieved.&quot;,
    &quot;data&quot;: {
        &quot;orders&quot;: [
            {
                &quot;id&quot;: &quot;01J000000000000DEMOORDER1&quot;,
                &quot;status&quot;: {
                    &quot;value&quot;: &quot;paid&quot;,
                    &quot;label&quot;: &quot;Paid&quot;
                },
                &quot;total&quot;: 75000,
                &quot;currency&quot;: &quot;BDT&quot;,
                &quot;commission_rate&quot;: &quot;0.1000&quot;,
                &quot;created_at&quot;: &quot;2026-06-30T10:05:00Z&quot;
            }
        ],
        &quot;pagination&quot;: {
            &quot;current_page&quot;: 1,
            &quot;per_page&quot;: 15,
            &quot;total&quot;: 1,
            &quot;last_page&quot;: 1
        }
    },
    &quot;errors&quot;: null
}</code>
 </pre>
            <blockquote>
            <p>Example response (401, Unauthenticated):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;success&quot;: false,
    &quot;message&quot;: &quot;Unauthenticated.&quot;,
    &quot;data&quot;: null,
    &quot;errors&quot;: null
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-orders" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-orders"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-orders"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-orders" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-orders">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-orders" data-method="GET"
      data-path="api/v1/orders"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-orders', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-orders"
                    onclick="tryItOut('GETapi-v1-orders');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-orders"
                    onclick="cancelTryOut('GETapi-v1-orders');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-orders"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/orders</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-v1-orders"
               value="Bearer {YOUR_BEARER_TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_BEARER_TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-orders"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-orders"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                            <h4 class="fancy-heading-panel"><b>Query Parameters</b></h4>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>status</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="status"                data-endpoint="GETapi-v1-orders"
               value="paid"
               data-component="query">
    <br>
<p>Filter orders by status. Attendees always see only their own orders; admins may use this to narrow the result set. Example: <code>paid</code></p>
Must be one of:
<ul style="list-style-type: square;"><li><code>pending</code></li> <li><code>paid</code></li> <li><code>partially_refunded</code></li> <li><code>refunded</code></li> <li><code>expired</code></li> <li><code>failed</code></li> <li><code>cancelled</code></li></ul>
            </div>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>per_page</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="per_page"                data-endpoint="GETapi-v1-orders"
               value="15"
               data-component="query">
    <br>
<p>Number of items per page (1–100). Must be at least 1. Must not be greater than 100. Example: <code>15</code></p>
            </div>
                </form>

                    <h2 id="orders-GETapi-v1-orders--id-">Get order</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Retrieve a single order with its items and holds. Attendees can only view
their own orders; admins can view any order.</p>

<span id="example-requests-GETapi-v1-orders--id-">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:8000/api/v1/orders/architecto" \
    --header "Authorization: Bearer {YOUR_BEARER_TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/orders/architecto"
);

const headers = {
    "Authorization": "Bearer {YOUR_BEARER_TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>


<div class="php-example">
    <pre><code class="language-php">$client = new \GuzzleHttp\Client();
$url = 'http://localhost:8000/api/v1/orders/architecto';
$response = $client-&gt;get(
    $url,
    [
        'headers' =&gt; [
            'Authorization' =&gt; 'Bearer {YOUR_BEARER_TOKEN}',
            'Content-Type' =&gt; 'application/json',
            'Accept' =&gt; 'application/json',
        ],
    ]
);
$body = $response-&gt;getBody();
print_r(json_decode((string) $body));</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-orders--id-">
            <blockquote>
            <p>Example response (200, Success):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;success&quot;: true,
    &quot;message&quot;: &quot;Order retrieved.&quot;,
    &quot;data&quot;: {
        &quot;order&quot;: {
            &quot;id&quot;: &quot;01J000000000000DEMOORDER1&quot;,
            &quot;status&quot;: {
                &quot;value&quot;: &quot;paid&quot;,
                &quot;label&quot;: &quot;Paid&quot;
            },
            &quot;total&quot;: 75000,
            &quot;currency&quot;: &quot;BDT&quot;,
            &quot;commission_rate&quot;: &quot;0.1000&quot;,
            &quot;items&quot;: [
                {
                    &quot;id&quot;: &quot;01J000000000000DEMOITEM1&quot;,
                    &quot;ticket_type_id&quot;: &quot;01J000000000000DEMOTICKET&quot;,
                    &quot;quantity&quot;: 3,
                    &quot;unit_price&quot;: 25000
                }
            ],
            &quot;holds&quot;: [],
            &quot;hold_expires_at&quot;: null,
            &quot;created_at&quot;: &quot;2026-06-30T10:05:00Z&quot;
        }
    },
    &quot;errors&quot;: null
}</code>
 </pre>
            <blockquote>
            <p>Example response (403, Unauthorized):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;success&quot;: false,
    &quot;message&quot;: &quot;This action is unauthorized.&quot;,
    &quot;data&quot;: null,
    &quot;errors&quot;: null
}</code>
 </pre>
            <blockquote>
            <p>Example response (404, Not Found):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;success&quot;: false,
    &quot;message&quot;: &quot;Resource not found.&quot;,
    &quot;data&quot;: null,
    &quot;errors&quot;: null
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-orders--id-" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-orders--id-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-orders--id-"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-orders--id-" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-orders--id-">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-orders--id-" data-method="GET"
      data-path="api/v1/orders/{id}"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-orders--id-', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-orders--id-"
                    onclick="tryItOut('GETapi-v1-orders--id-');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-orders--id-"
                    onclick="cancelTryOut('GETapi-v1-orders--id-');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-orders--id-"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/orders/{id}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-v1-orders--id-"
               value="Bearer {YOUR_BEARER_TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_BEARER_TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-orders--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-orders--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="id"                data-endpoint="GETapi-v1-orders--id-"
               value="architecto"
               data-component="url">
    <br>
<p>The ID of the order. Example: <code>architecto</code></p>
            </div>
                    </form>

                <h1 id="payouts">Payouts</h1>

    

                                <h2 id="payouts-GETapi-v1-payouts">My payouts (vendor)</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Paginated list of the authenticated vendor's own payouts, newest first.
Optionally filter by <code>status</code>.</p>

<span id="example-requests-GETapi-v1-payouts">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:8000/api/v1/payouts?status=pending&amp;vendor_id=no-example&amp;per_page=20" \
    --header "Authorization: Bearer {YOUR_BEARER_TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/payouts"
);

const params = {
    "status": "pending",
    "vendor_id": "no-example",
    "per_page": "20",
};
Object.keys(params)
    .forEach(key =&gt; url.searchParams.append(key, params[key]));

const headers = {
    "Authorization": "Bearer {YOUR_BEARER_TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>


<div class="php-example">
    <pre><code class="language-php">$client = new \GuzzleHttp\Client();
$url = 'http://localhost:8000/api/v1/payouts';
$response = $client-&gt;get(
    $url,
    [
        'headers' =&gt; [
            'Authorization' =&gt; 'Bearer {YOUR_BEARER_TOKEN}',
            'Content-Type' =&gt; 'application/json',
            'Accept' =&gt; 'application/json',
        ],
        'query' =&gt; [
            'status' =&gt; 'pending',
            'vendor_id' =&gt; 'no-example',
            'per_page' =&gt; '20',
        ],
    ]
);
$body = $response-&gt;getBody();
print_r(json_decode((string) $body));</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-payouts">
            <blockquote>
            <p>Example response (200, Success):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;success&quot;: true,
    &quot;message&quot;: &quot;Payouts retrieved.&quot;,
    &quot;data&quot;: {
        &quot;payouts&quot;: [
            {
                &quot;id&quot;: &quot;01JWXYZ000000000000PAYOUT1&quot;,
                &quot;vendor_id&quot;: &quot;01JWXYZ0000000000000VENDOR&quot;,
                &quot;batch_id&quot;: &quot;2026-09-20&quot;,
                &quot;currency&quot;: &quot;BDT&quot;,
                &quot;gross&quot;: 500000,
                &quot;commission&quot;: 50000,
                &quot;net&quot;: 450000,
                &quot;payable&quot;: 450000,
                &quot;reserved_refund&quot;: 0,
                &quot;status&quot;: {
                    &quot;value&quot;: &quot;pending&quot;,
                    &quot;label&quot;: &quot;Pending&quot;
                },
                &quot;created_at&quot;: &quot;2026-06-30T09:00:00+00:00&quot;,
                &quot;updated_at&quot;: &quot;2026-06-30T09:00:00+00:00&quot;
            }
        ],
        &quot;pagination&quot;: {
            &quot;current_page&quot;: 1,
            &quot;last_page&quot;: 1,
            &quot;total&quot;: 1,
            &quot;per_page&quot;: 20
        }
    },
    &quot;errors&quot;: null
}</code>
 </pre>
            <blockquote>
            <p>Example response (401, Unauthenticated):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;success&quot;: false,
    &quot;message&quot;: &quot;Unauthenticated.&quot;,
    &quot;data&quot;: null,
    &quot;errors&quot;: null
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-payouts" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-payouts"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-payouts"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-payouts" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-payouts">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-payouts" data-method="GET"
      data-path="api/v1/payouts"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-payouts', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-payouts"
                    onclick="tryItOut('GETapi-v1-payouts');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-payouts"
                    onclick="cancelTryOut('GETapi-v1-payouts');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-payouts"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/payouts</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-v1-payouts"
               value="Bearer {YOUR_BEARER_TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_BEARER_TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-payouts"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-payouts"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                            <h4 class="fancy-heading-panel"><b>Query Parameters</b></h4>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>status</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="status"                data-endpoint="GETapi-v1-payouts"
               value="pending"
               data-component="query">
    <br>
<p>Filter by payout status. Example: <code>pending</code></p>
Must be one of:
<ul style="list-style-type: square;"><li><code>pending</code></li> <li><code>approved</code></li> <li><code>processing</code></li> <li><code>paid</code></li> <li><code>failed</code></li></ul>
            </div>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>vendor_id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="vendor_id"                data-endpoint="GETapi-v1-payouts"
               value="no-example"
               data-component="query">
    <br>
<p>Filter by vendor (admin only). Must not be greater than 26 characters. Example: <code>no-example</code></p>
            </div>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>per_page</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="per_page"                data-endpoint="GETapi-v1-payouts"
               value="20"
               data-component="query">
    <br>
<p>Items per page (1–100). Must be at least 1. Must not be greater than 100. Example: <code>20</code></p>
            </div>
                </form>

                    <h2 id="payouts-GETapi-v1-payouts-preview">Preview next payout (vendor)</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Returns the estimated payout breakdown without creating anything. Returns null data when
there are no eligible settled orders or the balance is below the minimum threshold.</p>

<span id="example-requests-GETapi-v1-payouts-preview">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:8000/api/v1/payouts/preview" \
    --header "Authorization: Bearer {YOUR_BEARER_TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/payouts/preview"
);

const headers = {
    "Authorization": "Bearer {YOUR_BEARER_TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>


<div class="php-example">
    <pre><code class="language-php">$client = new \GuzzleHttp\Client();
$url = 'http://localhost:8000/api/v1/payouts/preview';
$response = $client-&gt;get(
    $url,
    [
        'headers' =&gt; [
            'Authorization' =&gt; 'Bearer {YOUR_BEARER_TOKEN}',
            'Content-Type' =&gt; 'application/json',
            'Accept' =&gt; 'application/json',
        ],
    ]
);
$body = $response-&gt;getBody();
print_r(json_decode((string) $body));</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-payouts-preview">
            <blockquote>
            <p>Example response (500):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
log-trace-id: dd9f3e33-c86d-42b0-93ab-f00e220be759
access-control-allow-origin: *
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;success&quot;: false,
    &quot;data&quot;: null,
    &quot;message&quot;: &quot;Server error.&quot;,
    &quot;errors&quot;: null
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-payouts-preview" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-payouts-preview"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-payouts-preview"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-payouts-preview" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-payouts-preview">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-payouts-preview" data-method="GET"
      data-path="api/v1/payouts/preview"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-payouts-preview', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-payouts-preview"
                    onclick="tryItOut('GETapi-v1-payouts-preview');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-payouts-preview"
                    onclick="cancelTryOut('GETapi-v1-payouts-preview');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-payouts-preview"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/payouts/preview</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-v1-payouts-preview"
               value="Bearer {YOUR_BEARER_TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_BEARER_TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-payouts-preview"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-payouts-preview"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                    <h2 id="payouts-POSTapi-v1-payouts-request">Request payout (vendor)</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Creates a pending payout record for the authenticated vendor using today as the batch date.
Idempotent — calling again on the same day returns the existing pending payout unchanged.
Returns 422 when there are no eligible orders or the balance is below threshold.</p>

<span id="example-requests-POSTapi-v1-payouts-request">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://localhost:8000/api/v1/payouts/request" \
    --header "Authorization: Bearer {YOUR_BEARER_TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/payouts/request"
);

const headers = {
    "Authorization": "Bearer {YOUR_BEARER_TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "POST",
    headers,
}).then(response =&gt; response.json());</code></pre></div>


<div class="php-example">
    <pre><code class="language-php">$client = new \GuzzleHttp\Client();
$url = 'http://localhost:8000/api/v1/payouts/request';
$response = $client-&gt;post(
    $url,
    [
        'headers' =&gt; [
            'Authorization' =&gt; 'Bearer {YOUR_BEARER_TOKEN}',
            'Content-Type' =&gt; 'application/json',
            'Accept' =&gt; 'application/json',
        ],
    ]
);
$body = $response-&gt;getBody();
print_r(json_decode((string) $body));</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-payouts-request">
</span>
<span id="execution-results-POSTapi-v1-payouts-request" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-payouts-request"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-payouts-request"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-payouts-request" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-payouts-request">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-payouts-request" data-method="POST"
      data-path="api/v1/payouts/request"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-payouts-request', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-v1-payouts-request"
                    onclick="tryItOut('POSTapi-v1-payouts-request');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-v1-payouts-request"
                    onclick="cancelTryOut('POSTapi-v1-payouts-request');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-v1-payouts-request"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/payouts/request</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="POSTapi-v1-payouts-request"
               value="Bearer {YOUR_BEARER_TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_BEARER_TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-payouts-request"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-payouts-request"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

            

        
    </div>
    <div class="dark-box">
                    <div class="lang-selector">
                                                        <button type="button" class="lang-button" data-language-name="bash">bash</button>
                                                        <button type="button" class="lang-button" data-language-name="javascript">javascript</button>
                                                        <button type="button" class="lang-button" data-language-name="php">php</button>
                            </div>
            </div>
</div>
</body>
</html>
