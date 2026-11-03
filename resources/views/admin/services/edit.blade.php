@extends('layouts.app')

@section('title', 'Editar servicio')

@section('page')
@include('admin.services.form', ['action' => route('services.update', $service), 'method' => 'PUT'])
@endsection
