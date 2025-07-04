<form method="POST" action="{{ route('password.email') }}">
    @csrf
    <input type="email" name="email" required placeholder="Enter your email">
    <button type="submit">Send Reset Link</button>
</form>