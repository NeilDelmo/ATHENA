@extends('errors.layout')

@section('page_title', 'Session expired')
@section('code', '419')
@section('heading', 'Your session has expired.')
@section('message')
    This happens when a page has been open for a while. Return to ATHENA, refresh the page, and try your action again.
@endsection
