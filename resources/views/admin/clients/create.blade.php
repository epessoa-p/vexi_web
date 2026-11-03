@extends('layouts.app')

@section('title', 'Nuevo cliente')

@section('page')
@include('admin.clients.form', ['action' => route('clients.store'), 'method' => 'POST', 'client' => null])
@endsection
