@extends('errors.layout')

@section('page_title', 'Server error')
@section('code', $exception->getStatusCode())
@section('heading', 'Something went wrong on our side.')
@section('message')
    ATHENA is temporarily unable to complete your request. Try again shortly. If you were saving changes, check whether they were saved before trying again.
@endsection
