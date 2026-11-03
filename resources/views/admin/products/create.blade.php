@extends('layouts.app')

@section('title', 'Nuevo producto')

@section('page')
@include('admin.products.form', ['action' => route('products.store'), 'method' => 'POST'])
@endsection
