@extends('layouts.error')

@section('code', '500')
@section('title', 'Something went wrong')
@section('message', 'This one is on our side, not yours. Try again in a moment.')

@section('actions')
    <a href="{{ url()->current() }}" class="sp-btn sp-btn-md sp-btn-primary">Try again</a>
    <a href="{{ url('/') }}" class="sp-btn sp-btn-md sp-btn-secondary">Back to overview</a>
@endsection
