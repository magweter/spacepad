@extends('layouts.error')

@section('code', '403')
@section('title', 'You do not have access')
@section('message', 'You do not have permission to open this page. If you think you should, ask an owner of your workspace.')

@section('actions')
    <a href="{{ url('/') }}" class="sp-btn sp-btn-md sp-btn-primary">Back to overview</a>
@endsection
