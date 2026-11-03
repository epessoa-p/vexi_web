@extends('layouts.app')

@section('title', 'Nueva vacuna')

@section('page')
@include('admin.vaccines.form', ['action' => route('vaccines.store'), 'method' => 'POST', 'vaccine' => null])
@endsection
