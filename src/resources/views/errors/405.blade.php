@extends('errors.layout')

@section('page_title', 'Action not allowed')
@section('code', '405')
@section('heading', 'This action is not available here.')
@section('message')
    Open the page again from your dashboard and use the controls provided there to complete your action.
@endsection
