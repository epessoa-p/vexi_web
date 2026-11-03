@extends('layouts.app')

@section('title', 'Registrar gasto')

@section('page')
@include('admin.expenses.form', ['action' => route('expenses.store'), 'method' => 'POST'])
@endsection
