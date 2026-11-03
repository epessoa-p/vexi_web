@extends('layouts.app')

@section('title', 'Editar desparasitación')

@section('page')
@include('admin.dewormings.form', ['action' => route('dewormings.update', $deworming), 'method' => 'PUT'])
@endsection
