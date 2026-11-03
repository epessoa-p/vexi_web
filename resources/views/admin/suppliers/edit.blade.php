@extends('layouts.app')

@section('title', 'Editar proveedor')

@section('page')
@include('admin.suppliers.form', ['action' => route('suppliers.update', $supplier), 'method' => 'PUT'])
@endsection
