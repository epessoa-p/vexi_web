@extends('layouts.app')

@section('title', 'Editar vacuna')

@section('page')
@include('admin.vaccinations.form', ['action' => route('vaccinations.update', $vaccination), 'method' => 'PUT'])
@endsection
