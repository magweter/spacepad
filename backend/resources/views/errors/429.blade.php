@extends('layouts.error')

@section('code', '429')
@section('title', 'Too many requests')
@section('message', 'You sent a lot of requests in a short time. Wait a minute and try again.')

@section('actions')
    <a href="{{ url('/') }}" class="sp-btn sp-btn-md sp-btn-primary">Back to overview</a>
@endsection
