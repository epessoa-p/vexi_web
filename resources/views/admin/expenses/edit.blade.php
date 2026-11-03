@extends('layouts.app')

@section('title', 'Editar gasto')

@section('page')
@include('admin.expenses.form', ['action' => route('expenses.update', $expense), 'method' => 'PUT'])
@endsection
