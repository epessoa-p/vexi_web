@extends('layouts.app')

@section('title', 'Editar cita')

@section('page')
@include('admin.appointments.form', ['action' => route('appointments.update', $appointment), 'method' => 'PUT'])
@endsection
