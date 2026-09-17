@extends('layouts.app')

@section('pageTitle', 'Client Documents')

@section('breadcrumb')
<a href="{{ route('clients') }}">Clients</a> <span>/</span> <span>Documents</span>
@endsection

@section('content')

<div style="margin-bottom:14px">
  <h1 style="font-size:1.4rem;margin:0 0 4px">{{ $client->company_name ?: $client->contact_person }}</h1>
  <p style="font-size:.85rem;color:var(--text-muted);margin:0">
    {{ $client->contact_person }}
    <a href="{{ route('clients') }}" style="margin-left:10px;color:var(--accent)">&larr; Back to Clients</a>
  </p>
</div>

@include('partials.documents-card')

@endsection
