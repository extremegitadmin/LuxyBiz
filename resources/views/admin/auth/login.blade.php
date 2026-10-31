<!doctype html>
<html lang="en" >
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>LuxyBiz | Admin Login</title>
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
    <div class="auth-basic-wrapper d-flex align-items-center justify-content-center">
        <div class="container-fluid my-5 my-lg-0">
            <div class="row">
                <div class="col-12 col-md-6 col-lg-4 col-xl-5 col-xxl-4 mx-auto">
                    <div class="card rounded-4 mb-0 border-4 border-primary border-gradient-1">
                        <div class="card-body p-5">
                            <h1 class="text-center text-white mb-3">LuxyBiz</h1>
                            <h4 class="fw-bold text-white text-center">Get Started Now</h4>
                            <p class="mb-0 text-white text-center">Enter your credentials to login your account</p>
                            <div class="form-body my-5">
								<form class="row g-3">
									<div class="col-12">
                                        <label for="inputEmailAddress" class="form-label text-white">Email</label>
                                        <input type="email" class="form-control" id="inputEmailAddress" placeholder="jhon@example.com">
									</div>
									<div class="col-12">
                                        <label for="inputChoosePassword" class="form-label text-white">Password</label>
                                        <div class="input-group" id="show_hide_password">
										    <input type="password" class="form-control border-end-0" id="inputChoosePassword" value="12345678" placeholder="Enter Password"> 
                                            <a href="javascript:;" class="input-group-text bg-transparent"><i class="bi bi-eye-slash-fill"></i></a>
										</div>
									</div>
                                    
                                    <div class="col-md-12 text-end">	
                                        <a href="#" class="text-white">Forgot Password ?</a>
                                    </div>
                                    <div class="col-12">
                                        <div class="d-grid">
                                            <button type="submit" class="btn btn-primary">Login</button>
                                        </div>
                                    </div>
                                    
								</form>
							</div>
                            
                        </div>
                    </div>
                </div>
            </div><!--end row-->
        </div>
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