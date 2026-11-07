@extends('layouts.app')

@section('title', 'Nuevo estudio')

@section('page')
@include('admin.lab-studies.form', ['action' => route('lab-studies.store'), 'method' => 'POST'])
@endsection
