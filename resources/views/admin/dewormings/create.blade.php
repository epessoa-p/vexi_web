@extends('layouts.app')

@section('title', 'Registrar desparasitación')

@section('page')
@include('admin.dewormings.form', ['action' => route('dewormings.store'), 'method' => 'POST'])
@endsection
