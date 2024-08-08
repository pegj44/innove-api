<form method="post" action="{{ route('unit.ui.login') }}">
    @csrf
    <input type="text" name="email">
    <input type="password" name="password">
    <input type="submit" value="Login">
</form>
