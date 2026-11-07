@extends('layouts.app')

@section('title', 'Editar estudio')

@section('page')
@include('admin.lab-studies.form', ['action' => route('lab-studies.update', $studyType), 'method' => 'PUT'])
@endsection
