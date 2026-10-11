@extends('errors.layout')

@section('page_title', 'Access denied')
@section('code', '403')
@section('heading', "This page isn't for your account.")
@section('message')
    Your account or current workspace does not have access to this page. Return to your dashboard to choose an available workspace. If you believe you should have access, contact the system administrator.
@endsection
