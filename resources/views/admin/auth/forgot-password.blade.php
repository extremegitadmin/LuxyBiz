<!doctype html>
<html lang="en" data-bs-theme="blue-theme">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>LuxyBix | Forgot Password</title>
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
    <link rel="stylesheet" href="{{asset('assets/css/extra-icons.css')}}">
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
                <h3 class="text-white">Run every journey from one calm place.</h3>
                <p>Bookings, departures, travelers and payments for your Kerala wellness and travel programmes.</p>
            </div>
            <div class="small" style="color:#6d9496">Staff access only</div>
        </aside>
        <section class="auth-form">
            <div class="auth-box">
                <h1 class="h3 page-title mb-1 text-black">Reset your password</h1>
                <p class="text-secondary mb-4">Enter your admin/staff email and we will send a reset link.</p>
                @if (session('status'))
                    <div class="alert alert-success" role="alert">
                        <i class="bi bi-check-circle me-1"></i>{{ session('status') }}
                    </div>
                @endif

                @if ($errors->any())
                    <div class="alert alert-danger" role="alert">
                        <ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                    </div>
                @endif
                <form method="POST" action="{{ route('admin.password.email') }}" novalidate="">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label text-black" for="email">Email</label>
                        <input id="email" type="email" name="email" value="{{ old('email') }}" class="form-control @error('email') is-invalid @enderror" autocomplete="username" autofocus required>
                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>                    
                    <button class="btn btn-primary w-100 py-2">Send reset link</button>                    
                </form>
                <div class="row mt-3">
                    <div class="col-md-12 text-center">
                        <a style="color:rgb(5, 5, 148)" href="{{ route('admin.login') }}"><i class="bx bx-left-arrow-circle"></i> Back to sign in</a>
                    </div>
                </div>
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