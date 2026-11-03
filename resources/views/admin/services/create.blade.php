@extends('layouts.app')

@section('title', 'Nuevo servicio')

@section('page')
@include('admin.services.form', ['action' => route('services.store'), 'method' => 'POST', 'service' => null])
@endsection
