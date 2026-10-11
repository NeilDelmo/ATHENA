@extends('errors.layout')

@section('page_title', 'Sign in required')
@section('code', '401')
@section('heading', 'Sign in to continue.')
@section('message')
    You need to sign in to open this page or complete this action.
@endsection
@section('recovery_url', route('login'))
@section('recovery_label', 'Sign in')
