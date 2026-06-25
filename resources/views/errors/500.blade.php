@extends('errors.layout')

@section('title', 'Server Error')

@section('content')
    <div class="error-icon">💥</div>
    <div class="error-code">500</div>
    <h1 class="error-title">Server Error</h1>
    <p class="error-message">
        Something went wrong on our end. We're working to fix this issue. Please try again later.
    </p>
    <div class="error-actions">
        <a href="{{ url()->previous() }}" class="btn btn-secondary">Go Back</a>
        <a href="{{ url('/') }}" class="btn btn-primary">Go Home</a>
    </div>
@endsection
