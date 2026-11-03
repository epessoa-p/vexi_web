@extends('layouts.app')

@section('title', "Editar {$pet->name}")

@section('page')
@include('admin.pets.form', ['action' => route('pets.update', $pet), 'method' => 'PUT'])
@endsection
