<!-- resources/views/auth/reset-password.blade.php -->
<!DOCTYPE html>
<html>
<head>
    <title>Reset Password</title>
</head>
<body>
    <h2>Reset Password</h2>

    <form method="POST" action="{{ route('password.update') }}">
        @csrf

        <input type="hidden" name="token" value="{{ request()->route('token') }}">
        <input type="hidden" name="email" value="{{ old('email', request()->email) }}">

        <label>New Password:</label>
        <input type="password" name="password" required>
        @error('password')
            <p style="color: red">{{ $message }}</p>
        @enderror

        <label>Confirm Password:</label>
        <input type="password" name="password_confirmation" required>

        <button type="submit">Reset Password</button>
    </form>
</body>
</html>