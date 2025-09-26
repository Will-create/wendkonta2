<!DOCTYPE html>
<html lang="en">
    <head>
        <title>SalePro Installer | Step-4</title>
        <link rel="shortcut icon" type="image/x-icon" href="{{ asset('install-assets/images/favicon.ico') }}">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link href="{{ asset('install-assets/css/bootstrap.min.css') }}" rel="stylesheet">
        <link href="{{ asset('install-assets/css/font-awesome.min.css') }}" rel="stylesheet">
        <link href="{{ asset('install-assets/css/style.css') }}" rel="stylesheet">
    </head>
<body>
    <div class="col-md-6 offset-md-3">
        <div class='wrapper'>
            <header>
                <img src="{{ asset('install-assets/images/logo.png')}}" alt="Logo" style="max-width: 120px;"/>
                <h1 class="text-center">SalePro Auto Installer</h1>
            </header>
            <hr>
            <div class="content pad-top-bot-50">
                <h3 class="text-center"><strong class="theme-color">Congratulations!</strong></h3><br>
                <h5 class="text-center">You have successfully installed SalepPro.</h5><br>
                <hr>
                <br>
                <p>Access admin login page - <strong><a href="{{ url('/login') }}" target="__blank">Click here</a></strong></p>
                <p>
                    Username: <strong>admin</strong>
                    <br>
                    Password: <strong>admin</strong>
                </p>
            </div>
            <hr>
            <footer>Copyright &copy; LionCoders. All Rights Reserved.</footer>
        </div>
    </div>
    <script type="text/javascript" src="{{ asset('install-assets/js/jquery.min.js')}}"></script>
</body>
</html>
