@extends('layouts.app')

@section('title', 'Editar producto')

@section('page')
@include('admin.products.form', ['action' => route('products.update', $product), 'method' => 'PUT'])
@endsection
