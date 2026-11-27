@extends('layouts.error')

@section('code', '419')
@section('title', 'Page expired')
@section('message', 'You were on this page for a while, so it expired for your security. Reload it and try again.')

@section('actions')
    <a href="{{ url()->previous() }}" class="sp-btn sp-btn-md sp-btn-primary">Reload the page</a>
    <a href="{{ url('/') }}" class="sp-btn sp-btn-md sp-btn-secondary">Back to overview</a>
@endsection
