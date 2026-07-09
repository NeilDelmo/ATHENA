@extends('errors.layout')

@section('page_title', 'Server error')
@section('code', '500')
@section('heading', 'Something went wrong on our side.')
@section('message')
    Your request could not be completed right now. Please try again shortly; your account is still safe.
@endsection
