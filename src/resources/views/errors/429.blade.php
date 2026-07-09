@extends('errors.layout')

@section('page_title', 'Too many requests')
@section('code', '429')
@section('heading', 'Please slow down for a moment.')
@section('message')
    ATHENA received too many requests in a short time. Wait a little, then try again.
@endsection
