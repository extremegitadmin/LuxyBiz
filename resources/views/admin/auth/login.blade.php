<!doctype html>
<html lang="en" data-bs-theme="blue-theme">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>LuxyBix | Admin Login</title>
    <!--favicon-->
	<link rel="icon" href="{{asset('assets/images/favicon-32x32.png')}}" type="image/png">
    <!-- loader-->
	<link href="{{asset('assets/css/pace.min.css')}}" rel="stylesheet">
	<script src="{{asset('assets/js/pace.min.js')}}"></script>

    <!--plugins-->
    <link href="{{asset('assets/plugins/perfect-scrollbar/css/perfect-scrollbar.css')}}" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="{{asset('assets/plugins/metismenu/metisMenu.min.css')}}">
    <link rel="stylesheet" type="text/css" href="{{asset('assets/plugins/metismenu/mm-vertical.css')}}">
    <!--bootstrap css-->
    <link href="{{asset('assets/css/bootstrap.min.css')}}" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons+Outlined" rel="stylesheet">
    <!--main css-->
    <link href="{{asset('assets/css/bootstrap-extended.css')}}" rel="stylesheet">
    <link href="{{asset('sass/main.css')}}" rel="stylesheet">
    <link href="{{asset('sass/dark-theme.css')}}" rel="stylesheet">
    <link href="{{asset('sass/blue-theme.css')}}" rel="stylesheet">
    <link href="{{asset('sass/responsive.css')}}" rel="stylesheet">
  </head>

<body>
    <!--authentication-->
    <div class="auth-wrap">
        <aside class="auth-art" aria-hidden="true">
            <div class="d-flex align-items-center gap-2">
                <span class="brand-mark">L</span>
                <span class="brand-text text-white">LuxyBiz<small>Tours &amp; Wellness</small></span>
            </div>
            <div>
                <h2>Run every journey from one calm place.</h2>
                <p>Bookings, departures, travelers and payments for your Kerala wellness and travel programmes.</p>
            </div>
            <div class="small" style="color:#6d9496">Staff access only</div>
        </aside>
        <section class="auth-form">
            <div class="auth-box">
                <h1 class="h3 page-title mb-1 text-black">Welcome back</h1>
                <p class="text-secondary mb-4">Sign in to the LuxyBiz staff admin.</p>
                <form method="POST" action="#" novalidate="">
                    <input type="hidden" name="_token" value="4QUms35HHNPCYHxETZQHHF2XuVGci463JU9uAIDr" autocomplete="off">    
                    <div class="mb-3">
                        <label class="form-label text-black" for="email">Email</label>
                        <input id="email" type="email" name="email" value="" class="form-control " autocomplete="username" autofocus="" required="">
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-black d-flex justify-content-between" for="password">Password
                        <a class="small fw-normal" href="#">Forgot password?</a></label>
                        <input id="password" type="password" name="password" class="form-control " autocomplete="current-password" required="">
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="remember" id="remember">
                        <label class="form-label text-secondary" for="remember">Keep me signed in on this device</label>
                    </div>
                    <button class="btn btn-primary w-100 py-2">Sign in</button>
                </form>
            </div>
        </section>
    </div>

    <!--authentication-->
    <!--plugins-->
    <script src="{{asset('assets/js/jquery.min.js')}}"></script>

    <script>
        $(document).ready(function () {
            $("#show_hide_password a").on('click', function (event) {
                event.preventDefault();
                if ($('#show_hide_password input').attr("type") == "text") {
                $('#show_hide_password input').attr('type', 'password');
                $('#show_hide_password i').addClass("bi-eye-slash-fill");
                $('#show_hide_password i').removeClass("bi-eye-fill");
                } else if ($('#show_hide_password input').attr("type") == "password") {
                $('#show_hide_password input').attr('type', 'text');
                $('#show_hide_password i').removeClass("bi-eye-slash-fill");
                $('#show_hide_password i').addClass("bi-eye-fill");
                }
            });
        });
    </script>
</body>

</html>