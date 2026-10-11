@extends('errors.layout')

@section('page_title', 'Request could not be completed')
@section('code', $exception->getStatusCode())
@section('heading', 'Your request could not be completed.')
@section('message')
    ATHENA could not process this request. Return to your dashboard and try again using the controls on the page.
@endsection
