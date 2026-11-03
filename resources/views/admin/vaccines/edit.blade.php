@extends('layouts.app')

@section('title', 'Editar vacuna')

@section('page')
@include('admin.vaccines.form', ['action' => route('vaccines.update', $vaccine), 'method' => 'PUT'])
@endsection
