@extends('errors.layout')

@section('title', 'Method Not Allowed')

@section('content')
    <div class="error-icon">🚫</div>
    <div class="error-code">405</div>
    <h1 class="error-title">Method Not Allowed</h1>
    <p class="error-message">
        The HTTP method you used is not allowed for this route. Please check the request method and try again.
    </p>
    <div class="error-actions">
        <a href="{{ url()->previous() }}" class="btn btn-secondary">Go Back</a>
        <a href="{{ url('/') }}" class="btn btn-primary">Go Home</a>
    </div>
@endsection
