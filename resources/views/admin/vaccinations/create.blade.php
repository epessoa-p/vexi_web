@extends('layouts.app')

@section('title', 'Aplicar vacuna')

@section('page')
@include('admin.vaccinations.form', ['action' => route('vaccinations.store'), 'method' => 'POST'])
@endsection
