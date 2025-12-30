<!DOCTYPE html>
<html>
<head>
    <title>Authorizing...</title>
</head>
<body>
    <p>Authorizing, please wait...</p>
    <form method="POST" action="{{ route('passport.authorizations.approve') }}" id="approveForm">
        <input type="hidden" name="_token" value="{{ csrf_token() }}">
        <input type="hidden" name="state" value="{{ $request->state }}">
        <input type="hidden" name="client_id" value="{{ $client->id }}">
        <input type="hidden" name="auth_token" value="{{ $authToken }}">
    </form>
    <script>
        console.log('CSRF Token:', document.querySelector('input[name="_token"]').value);
        console.log('Auth Token:', '{{ $authToken }}');
        console.log('Client ID:', '{{ $client->id }}');
        setTimeout(function() {
            document.getElementById('approveForm').submit();
        }, 100);
    </script>
</body>
</html>
