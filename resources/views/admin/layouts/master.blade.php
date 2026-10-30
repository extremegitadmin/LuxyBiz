<!doctype html>
<html lang="en" data-bs-theme="light">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>LuxyBiz Admin</title>
    @include('admin.layouts.head-css')
</head>

<body>
    <!--start header-->
    @include('admin.layouts.topbar')
    <!--sidebar -->
    @include('admin.layouts.sidebar')

    <!--start main wrapper-->
    <main class="main-wrapper">
        <div class="main-content">
            @yield('content')
        </div>
        <div class="overlay btn-toggle"></div>
    <!--end overlay-->

    <!--start footer-->
    @include('admin.layouts.footer')
    <!--end footer-->
    @include('admin.layouts.vendor-scripts') 
    </main>
    <!--end main wrapper-->   
    <!--start overlay-->
    
</body>
</html>