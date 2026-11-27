@extends('layouts.error')

@section('code', '404')
@section('title', 'Page not found')
@section('message', 'The page you are looking for does not exist or has moved.')

@section('actions')
    <a href="{{ url('/') }}" class="sp-btn sp-btn-md sp-btn-primary">Back to overview</a>
@endsection
