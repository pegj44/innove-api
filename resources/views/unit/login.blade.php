<form method="post" action="{{ route('unit.ui.login') }}">
    @csrf
    <input type="text" name="username">
    <input type="password" name="password">
    <input type="text" name="ip">
    <input type="submit" value="Login">
</form>
