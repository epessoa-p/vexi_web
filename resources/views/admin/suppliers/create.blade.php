@extends('layouts.app')

@section('title', 'Nuevo proveedor')

@section('page')
@include('admin.suppliers.form', ['action' => route('suppliers.store'), 'method' => 'POST'])
@endsection
