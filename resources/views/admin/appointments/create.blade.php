@extends('layouts.app')

@section('title', 'Agendar cita')

@section('page')
@include('admin.appointments.form', ['action' => route('appointments.store'), 'method' => 'POST'])
@endsection
