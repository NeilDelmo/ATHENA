@extends('errors.layout')

@section('page_title', 'Session expired')
@section('code', '419')
@section('heading', 'Your session has expired.')
@section('message')
    Your session expired before this action could be completed. Refresh the page, then sign in again if prompted and retry your action.
@endsection
