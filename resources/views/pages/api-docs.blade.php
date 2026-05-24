@extends('layouts.app')

@section('content')
  <h1 class="mt-3">API Documentation — Anemometers</h1>
  <p class="lead">REST reference for the anemometer domain of the Wind4Life API.</p>

  <div class="alert alert-warning" role="alert">
    <p class="mb-1"><strong>Authentication required on every endpoint.</strong></p>
    <p class="mb-1">Get a token by POSTing your credentials to
      <code>/api/auth-token</code>:</p>
    <pre class="mb-1"><code>POST /api/auth-token
Content-Type: application/json

{ "username": "admin", "password": "admin" }</code></pre>
    <p class="mb-1">Then send it as a Bearer token on every subsequent request:</p>
    <pre class="mb-0"><code>Authorization: Bearer &lt;your-token&gt;</code></pre>
  </div>

  <div class="alert alert-info" role="alert">
    <strong>Pagination envelope.</strong> All list endpoints return the
    DRF-shaped envelope:
    <code>{ count, next, previous, results }</code>. Page size is the
    Laravel default (15). Use <code>?page=N</code> to navigate.
  </div>

  <h2 class="mt-5 mb-3">Anemometer CRUD</h2>

  {{-- LIST --}}
  <div class="card mb-3">
    <div class="card-body">
      <h5 class="card-title">
        <span class="badge bg-success">GET</span>
        <code>/api/anemometers</code>
      </h5>
      <p class="card-text">Paginated list of all anemometers.</p>

      <h6>Response <code>200 OK</code></h6>
      <pre><code>{
  "count": 50,
  "next": "http://localhost:8000/api/anemometers?page=2",
  "previous": null,
  "results": [
    {
      "id": "a1d9a65d-1595-4472-97ac-8084e70d4199",
      "name": "Mistral-1",
      "longitude": "5.123456",
      "latitude": "43.654321"
    }
  ]
}</code></pre>
    </div>
  </div>

  {{-- DETAIL --}}
  <div class="card mb-3">
    <div class="card-body">
      <h5 class="card-title">
        <span class="badge bg-success">GET</span>
        <code>/api/anemometers/{id}</code>
      </h5>
      <p class="card-text">Single anemometer with its embedded readings.</p>

      <h6>Path parameters</h6>
      <ul>
        <li><code>id</code> — UUID of the anemometer</li>
      </ul>

      <h6>Response <code>200 OK</code></h6>
      <pre><code>{
  "id": "a1d9a65d-1595-4472-97ac-8084e70d4199",
  "name": "Mistral-1",
  "longitude": "5.123456",
  "latitude": "43.654321",
  "readings": [
    {
      "id": "a1d9a689-...",
      "speed": 23.817,
      "recorded_at": "2026-05-23T18:42:20.000000Z",
      "tags": ["squally", "whipping"]
    }
  ]
}</code></pre>
      <h6>Errors</h6>
      <ul>
        <li><code>404</code> — anemometer not found</li>
      </ul>
    </div>
  </div>

  {{-- CREATE --}}
  <div class="card mb-3">
    <div class="card-body">
      <h5 class="card-title">
        <span class="badge bg-primary">POST</span>
        <code>/api/anemometers</code>
      </h5>
      <p class="card-text">Create a new anemometer.</p>

      <h6>Request body</h6>
      <table class="table table-sm">
        <thead><tr><th>Field</th><th>Type</th><th>Rules</th></tr></thead>
        <tbody>
          <tr><td><code>name</code></td><td>string</td><td>required, max 100 chars</td></tr>
          <tr><td><code>longitude</code></td><td>number</td><td>required, between -180 and 180</td></tr>
          <tr><td><code>latitude</code></td><td>number</td><td>required, between -90 and 90</td></tr>
        </tbody>
      </table>

      <h6>Example</h6>
      <pre><code>{
  "name": "Mistral-7",
  "longitude": 5.123456,
  "latitude": 43.654321
}</code></pre>

      <h6>Response <code>201 Created</code></h6>
      <pre><code>{
  "id": "a1d9a65d-...",
  "name": "Mistral-7",
  "longitude": "5.123456",
  "latitude": "43.654321"
}</code></pre>
      <h6>Errors</h6>
      <ul>
        <li><code>422</code> — validation failure (missing fields, out-of-range coordinates)</li>
      </ul>
    </div>
  </div>

  {{-- UPDATE --}}
  <div class="card mb-3">
    <div class="card-body">
      <h5 class="card-title">
        <span class="badge bg-warning text-dark">PATCH</span>
        <code>/api/anemometers/{id}</code>
      </h5>
      <p class="card-text">Update one or more fields of an anemometer. All
        fields are optional; only those provided are changed.</p>

      <h6>Path parameters</h6>
      <ul>
        <li><code>id</code> — UUID of the anemometer</li>
      </ul>

      <h6>Request body (any subset)</h6>
      <table class="table table-sm">
        <thead><tr><th>Field</th><th>Type</th><th>Rules</th></tr></thead>
        <tbody>
          <tr><td><code>name</code></td><td>string</td><td>max 100 chars</td></tr>
          <tr><td><code>longitude</code></td><td>number</td><td>between -180 and 180</td></tr>
          <tr><td><code>latitude</code></td><td>number</td><td>between -90 and 90</td></tr>
        </tbody>
      </table>

      <h6>Example</h6>
      <pre><code>{ "name": "Mistral-7 (renamed)" }</code></pre>

      <h6>Response <code>200 OK</code></h6>
      <pre><code>{
  "id": "a1d9a65d-...",
  "name": "Mistral-7 (renamed)",
  "longitude": "5.123456",
  "latitude": "43.654321"
}</code></pre>
      <h6>Errors</h6>
      <ul>
        <li><code>404</code> — anemometer not found</li>
        <li><code>422</code> — validation failure</li>
      </ul>
    </div>
  </div>

  {{-- DELETE --}}
  <div class="card mb-3">
    <div class="card-body">
      <h5 class="card-title">
        <span class="badge bg-danger">DELETE</span>
        <code>/api/anemometers/{id}</code>
      </h5>
      <p class="card-text">Delete an anemometer. Its readings are cascaded
        away by the database.</p>

      <h6>Path parameters</h6>
      <ul>
        <li><code>id</code> — UUID of the anemometer</li>
      </ul>

      <h6>Response <code>204 No Content</code></h6>
      <p class="text-muted">Empty body.</p>
      <h6>Errors</h6>
      <ul>
        <li><code>404</code> — anemometer not found</li>
      </ul>
    </div>
  </div>

  <h2 class="mt-5 mb-3">Custom action</h2>

  {{-- RECENT READINGS --}}
  <div class="card mb-3">
    <div class="card-body">
      <h5 class="card-title">
        <span class="badge bg-success">GET</span>
        <code>/api/anemometers/recent-readings</code>
      </h5>
      <p class="card-text">Paginated list of anemometers, each annotated
        with average wind speed over the last 24 hours and 7 days, plus
        the 5 most-recent readings.</p>

      <h6>Response <code>200 OK</code></h6>
      <pre><code>{
  "count": 50,
  "next": "http://localhost:8000/api/anemometers/recent-readings?page=2",
  "previous": null,
  "results": [
    {
      "id": "a1d9a65d-...",
      "name": "Mistral-1",
      "longitude": "5.123456",
      "latitude": "43.654321",
      "average_daily_speed": 487.32,
      "average_weekly_speed": 502.18,
      "recent_readings": [
        {
          "id": "a1d9a689-...",
          "speed": 23.817,
          "recorded_at": "2026-05-23T18:42:20.000000Z",
          "tags": ["squally", "whipping"]
        }
      ]
    }
  ]
}</code></pre>
      <p class="text-muted small mb-0">
        <strong>Note:</strong> <code>average_daily_speed</code> and
        <code>average_weekly_speed</code> are <code>null</code> when the
        anemometer has no readings in the corresponding window.
      </p>
    </div>
  </div>

  <h2 class="mt-5 mb-3">Nested readings</h2>

  {{-- NESTED LIST --}}
  <div class="card mb-3">
    <div class="card-body">
      <h5 class="card-title">
        <span class="badge bg-success">GET</span>
        <code>/api/anemometers/{anemometer}/readings</code>
      </h5>
      <p class="card-text">Paginated readings for a single anemometer,
        newest first (per the model's <code>recorded_at DESC</code> default
        ordering).</p>

      <h6>Path parameters</h6>
      <ul>
        <li><code>anemometer</code> — UUID of the parent anemometer</li>
      </ul>

      <h6>Response <code>200 OK</code></h6>
      <pre><code>{
  "count": 100,
  "next": "http://localhost:8000/api/anemometers/{id}/readings?page=2",
  "previous": null,
  "results": [
    {
      "id": "a1d9a689-...",
      "speed": 23.817,
      "recorded_at": "2026-05-23T18:42:20.000000Z",
      "tags": ["squally", "whipping"]
    }
  ]
}</code></pre>
      <h6>Errors</h6>
      <ul>
        <li><code>404</code> — anemometer not found</li>
      </ul>
    </div>
  </div>

  {{-- NESTED DETAIL --}}
  <div class="card mb-3">
    <div class="card-body">
      <h5 class="card-title">
        <span class="badge bg-success">GET</span>
        <code>/api/anemometers/{anemometer}/readings/{reading}</code>
      </h5>
      <p class="card-text">A single reading scoped to its anemometer.</p>

      <h6>Path parameters</h6>
      <ul>
        <li><code>anemometer</code> — UUID of the parent anemometer</li>
        <li><code>reading</code> — UUID of the reading</li>
      </ul>

      <h6>Response <code>200 OK</code></h6>
      <pre><code>{
  "id": "a1d9a689-...",
  "anemometer": {
    "id": "a1d9a65d-...",
    "name": "Mistral-1",
    "longitude": "5.123456",
    "latitude": "43.654321"
  },
  "speed": 23.817,
  "recorded_at": "2026-05-23T18:42:20.000000Z",
  "tags": ["squally", "whipping"]
}</code></pre>
      <h6>Errors</h6>
      <ul>
        <li><code>404</code> — anemometer or reading not found</li>
      </ul>
    </div>
  </div>

  <p class="text-muted small mt-5">
    Generated by hand. For changes, edit
    <code>resources/views/pages/api-docs.blade.php</code>.
  </p>
@endsection
