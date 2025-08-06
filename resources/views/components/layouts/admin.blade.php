@extends('adminlte::page')

@section('title', $title ?? 'Admin Panel - Meditative Brains')

@section('content_header')
    <h1>{{ $header ?? 'Admin Panel' }}</h1>
@stop

@section('content')
    {{ $slot }}
@stop
