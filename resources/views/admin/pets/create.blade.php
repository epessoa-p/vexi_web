@extends('layouts.app')

@section('title', 'Nueva mascota')

@section('page')
@include('admin.pets.form', ['action' => route('pets.store'), 'method' => 'POST', 'pet' => null])
@endsection
