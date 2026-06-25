@extends('errors.layout')

@section('title', 'Bad Request')

@section('content')
    <div class="error-icon">⚠️</div>
    <div class="error-code">400</div>
    <h1 class="error-title">Bad Request</h1>
    <p class="error-message">
        The request you sent was invalid or malformed. Please check your input and try again.
    </p>
    <div class="error-actions">
        <a href="{{ url()->previous() }}" class="btn btn-secondary">Go Back</a>
        <a href="{{ url('/') }}" class="btn btn-primary">Go Home</a>
    </div>
@endsection
