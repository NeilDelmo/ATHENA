@extends('errors.layout')

@section('page_title', 'Access denied')
@section('code', '403')
@section('heading', "This page isn't for your account.")
@section('message')
    You're signed in, but your current role does not have access to this page. Nothing is wrong with your account.
@endsection
