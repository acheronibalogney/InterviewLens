@extends('layouts.auth')
@section('title', 'Welcome back')
@section('content')
<div class="eyebrow">YOUR NEXT INTERVIEW STARTS HERE</div><h1>Good to have you back.</h1><p class="muted">Build confidence one thoughtful answer at a time.</p>
@if($errors->any())<div class="notice">{{ $errors->first() }}</div>@endif
<form method="post" action="{{ route('login.store') }}" class="auth-form">@csrf<label>Email address<input name="email" type="email" autocomplete="email" required value="{{ old('email') }}" placeholder="you@example.com"></label><label>Password<input name="password" type="password" autocomplete="current-password" required placeholder="Your password"></label><label class="remember-row"><span><input name="remember" type="checkbox" value="1"> Remember me</span></label><button class="button button-dark button-wide" type="submit">Sign in <span>→</span></button></form><p class="auth-switch">New to InterviewLens? <a href="{{ route('register') }}">Create an account</a></p>
@endsection
