@extends('layouts.auth')
@section('title', 'Create your account')
@section('content')
<div class="eyebrow">YOUR NEXT INTERVIEW STARTS HERE</div><h1>Make room to practice.</h1><p class="muted">Build confidence one thoughtful answer at a time.</p>
@if($errors->any())<div class="notice">{{ $errors->first() }}</div>@endif
<form method="post" action="{{ route('register.store') }}" class="auth-form">@csrf<label>Your name<input name="name" autocomplete="name" required minlength="2" value="{{ old('name') }}" placeholder="Alex Morgan"></label><label>Email address<input name="email" type="email" autocomplete="email" required value="{{ old('email') }}" placeholder="you@example.com"></label><label>Password<input name="password" type="password" autocomplete="new-password" minlength="8" required placeholder="At least 8 characters"></label><label>Confirm password<input name="password_confirmation" type="password" autocomplete="new-password" minlength="8" required placeholder="Enter your password again"></label><label class="terms-row"><input name="terms" type="checkbox" required><span>I understand my practice answers are saved to my account for my own review.</span></label><button class="button button-dark button-wide" type="submit">Create account <span>→</span></button></form><p class="auth-switch">Already have an account? <a href="{{ route('login') }}">Sign in</a></p>
@endsection
