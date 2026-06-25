@extends('errors.layout')

@section('title', 'Page Not Found')

@section('content')
    <div class="error-icon">🔍</div>
    <div class="error-code">404</div>
    <h1 class="error-title">Page Not Found</h1>
    <p class="error-message">
        Sorry, the page you are looking for does not exist or has been moved.
    </p>
    <div class="error-actions">
        <a href="{{ url()->previous() }}" class="btn btn-secondary">Go Back</a>
        <a href="{{ url('/') }}" class="btn btn-primary">Go Home</a>
    </div>
@endsection
