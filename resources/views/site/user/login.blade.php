<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    <title>Log in or create an account | LuxyBiz Tours &amp; Wellness</title>
    <meta name="description" content="Log in to see your LuxyBiz bookings, itineraries and travel documents, or create an account.">
    <meta name="robots" content="noindex">
    <meta name="theme-color" content="#F6F9F5">
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,300..600&family=Figtree:wght@400..700&display=swap" rel="stylesheet">
    <!-- Shared tokens, buttons and form fields from the home page, then the auth additions -->
    <link rel="stylesheet" href="{{asset('frontend/css/home.css')}}">
    <link rel="stylesheet" href="{{asset('frontend/css/auth.css')}}">
    <script src="{{asset('frontend/js/luxy-catalog.js')}}"></script>
    <script src="{{asset('frontend/js/luxy-store.js')}}"></script>
    <script src="{{asset('frontend/js/auth.js')}}" defer></script>
</head>
<body>
    <a class="skip" href="#main">Skip to content</a>
    <div class="auth">
        <!-- Visual panel: photo and message change with the view -->
        <aside class="vis" aria-hidden="true">
            <img class="on" data-for="login" src="https://images.unsplash.com/photo-1764012393710?fm=jpg&q=80&w=1200&h=1600&fit=crop&auto=format" alt="">
            <img data-for="register" src="https://images.unsplash.com/photo-1654530050931?fm=jpg&q=80&w=1200&h=1600&fit=crop&auto=format" alt="">
            <div class="vcopy on" data-for="login">
            <h2>Welcome back.</h2>
            <p>Your bookings, itineraries and travel documents, all in one place.</p>
            </div>
            <div class="vcopy" data-for="register">
            <h2>Start your Kerala story.</h2>
            <ul class="vlist">
                <li><svg viewBox="0 0 24 24"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>See every booking and its status</li>
                <li><svg viewBox="0 0 24 24"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>Read your day-by-day itinerary anywhere</li>
                <li><svg viewBox="0 0 24 24"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>Keep your travel documents together</li>
            </ul>
            </div>
            <div class="vcopy" data-for="forgot">
            <h2>It happens to everyone.</h2>
            <p>We will email you a link to choose a new password.</p>
            </div>
        </aside>

        <div class="pane">
            <div class="atop">
                <a class="abrand" href="index.html" aria-label="LuxyBiz Tours &amp; Wellness, home">
                    <span class="mark"><img src="{{asset('frontend/images/luxy-biz-png.png')}}" width="200" height="200" alt=""></span>
                    <span><strong>LuxyBiz</strong><span>TOURS &amp; WELLNESS</span></span>
                </a>
                <a class="aback" href="index.html">Back to home</a>
            </div>
            <main class="amid" id="main">
                <div class="acard">
                    <div class="tabs" id="tabs" role="tablist" aria-label="Account">
                        <a href="#login" role="tab" id="t-login" aria-controls="v-login" aria-selected="true">Log in</a>
                        <a href="#register" role="tab" id="t-register" aria-controls="v-register" aria-selected="false">Create account</a>
                    </div>
                    <!-- LOG IN (wireframe P11). Authentication method to be confirmed: set data-endpoint when it is. -->
                    <section class="view on" id="v-login" role="tabpanel" aria-labelledby="t-login">
                        <h1>Log in</h1>
                        <p class="sub">Welcome back. Sign in to see your bookings, itinerary and documents.</p>
                        <!-- Design preview only: remove before launch -->
                        <p class="demo-note">Demo account: <b>demo@luxybiz.example</b> / <b>Demo@1234</b> 
                            <button type="button" id="fill-demo">Fill in</button>
                        </p>
                        <form class="af" id="f-login" novalidate data-endpoint="" data-success="dashboard.html">
                            <div class="alert" id="login-alert" role="alert" hidden>
                                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7.5v5M12 16h.01"/></svg>
                                <span>Email or password is incorrect.</span>
                            </div>
                        <div class="fld"><label for="l-mail">Email <span class="req" aria-hidden="true">*</span></label><input id="l-mail" name="email" type="email" autocomplete="username" inputmode="email" required aria-describedby="l-mail-e"><span class="err" id="l-mail-e"></span></div>
                        <div class="fld"><label for="l-pass">Password <span class="req" aria-hidden="true">*</span></label>
                            <div class="pw"><input id="l-pass" name="password" type="password" autocomplete="current-password" required aria-describedby="l-pass-e"><button type="button" data-toggle="l-pass" aria-pressed="false" aria-label="Show password">Show</button></div>
                            <span class="err" id="l-pass-e"></span></div>
                        <!-- "Forgot password?" is marked "(if supported)" in the wireframe. Remove this row if it is not. -->
                        <div class="lrow"><span></span><a class="flink" href="#forgot">Forgot password?</a></div>
                        <p class="req-note">* Required</p>
                        <button class="btn solid" type="submit">Log in</button>
                        <p class="status" id="login-status" role="status" hidden></p>
                        </form>
                        <p class="alt">New to LuxyBiz? <a href="#register">Create an account</a></p>
                    </section>

                    <!-- CREATE ACCOUNT (not in the wireframe set; fields follow Checkout 1: full name, email, phone) -->
                    <section class="view" id="v-register" role="tabpanel" aria-labelledby="t-register">
                        <h1>Create your account</h1>
                        <p class="sub">One account for every journey. It takes a minute.</p>
                        <form class="af" id="f-register" novalidate data-endpoint="" data-success="dashboard.html">
                        <div class="alert" id="reg-alert" role="alert" hidden><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7.5v5M12 16h.01"/></svg><span>We could not create that account. Try logging in, or use a different email.</span></div>
                        <div class="fld"><label for="r-name">Full name <span class="req" aria-hidden="true">*</span></label><input id="r-name" name="name" autocomplete="name" required aria-describedby="r-name-e"><span class="err" id="r-name-e"></span></div>
                        <div class="row2">
                            <div class="fld"><label for="r-mail">Email <span class="req" aria-hidden="true">*</span></label><input id="r-mail" name="email" type="email" autocomplete="email" inputmode="email" required aria-describedby="r-mail-e"><span class="err" id="r-mail-e"></span></div>
                            <div class="fld"><label for="r-phone">Phone <span class="opt">(optional)</span></label><input id="r-phone" name="phone" type="tel" autocomplete="tel" inputmode="tel" aria-describedby="r-phone-e"><span class="err" id="r-phone-e"></span></div>
                        </div>
                        <div class="fld"><label for="r-pass">Password <span class="req" aria-hidden="true">*</span></label>
                            <div class="pw"><input id="r-pass" name="password" type="password" autocomplete="new-password" required aria-describedby="r-pass-h r-pass-e"><button type="button" data-toggle="r-pass" aria-pressed="false" aria-label="Show password">Show</button></div>
                            <div class="meter" id="meter" data-s="0" aria-hidden="true"><i></i><i></i><i></i><i></i></div>
                            <div class="mlabel" id="r-pass-h">Use at least 8 characters. A mix of letters, numbers and symbols is stronger.</div>
                            <span class="err" id="r-pass-e"></span></div>
                        <div class="fld"><label for="r-pass2">Confirm password <span class="req" aria-hidden="true">*</span></label>
                            <div class="pw"><input id="r-pass2" name="password2" type="password" autocomplete="new-password" required aria-describedby="r-pass2-e"><button type="button" data-toggle="r-pass2" aria-pressed="false" aria-label="Show password">Show</button></div>
                            <span class="err" id="r-pass2-e"></span></div>
                        <div><label class="chk"><input type="checkbox" id="r-agree" name="agree" aria-describedby="r-agree-e"><span>I agree to the <a href="#" >Terms</a> and the <a href="#">Privacy Policy</a>.</span></label><span class="err" id="r-agree-e"></span></div>
                        <p class="req-note">* Required</p>
                        <button class="btn solid" type="submit">Create account</button>
                        <p class="status" id="reg-status" role="status" hidden></p>
                        </form>
                        <p class="alt">Already have an account? <a href="#login">Log in</a></p>
                    </section>

                    <!-- FORGOT PASSWORD (only if supported) -->
                    <section class="view" id="v-forgot" aria-labelledby="forgot-h">
                        <h1 id="forgot-h">Reset your password</h1>
                        <p class="sub">Enter the email you signed up with and we will send you a link to choose a new password.</p>
                        <form class="af" id="f-forgot" novalidate data-endpoint="">
                        <div class="fld"><label for="f-mail">Email <span class="req" aria-hidden="true">*</span></label><input id="f-mail" name="email" type="email" autocomplete="email" inputmode="email" required aria-describedby="f-mail-e"><span class="err" id="f-mail-e"></span></div>
                        <button class="btn solid" type="submit">Send reset link</button>
                        <p class="status" id="forgot-status" role="status" hidden></p>
                        <p class="demo-note" id="demo-link" hidden>Demo only: no email is sent here. <a href="#reset">Open the reset link we would have emailed</a></p>
                        </form>
                        <p class="alt"><a href="#login">Back to log in</a></p>
                    </section>

                    <!-- CHOOSE A NEW PASSWORD (reached from the reset link) -->
                    <section class="view" id="v-reset" aria-labelledby="reset-h">
                        <h1 id="reset-h">Choose a new password</h1>
                        <p class="sub">Use at least 8 characters.</p>
                        <form class="af" id="f-reset" novalidate>
                        <div class="alert" id="reset-alert" role="alert" hidden><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7.5v5M12 16h.01"/></svg><span>This reset link has expired. Please request a new one.</span></div>
                        <div class="fld"><label for="n-pass">New password <span class="req" aria-hidden="true">*</span></label>
                            <div class="pw"><input id="n-pass" name="password" type="password" autocomplete="new-password" required aria-describedby="n-pass-e"><button type="button" data-toggle="n-pass" aria-pressed="false" aria-label="Show password">Show</button></div>
                            <span class="err" id="n-pass-e"></span></div>
                        <div class="fld"><label for="n-pass2">Confirm new password <span class="req" aria-hidden="true">*</span></label>
                            <div class="pw"><input id="n-pass2" name="password2" type="password" autocomplete="new-password" required aria-describedby="n-pass2-e"><button type="button" data-toggle="n-pass2" aria-pressed="false" aria-label="Show password">Show</button></div>
                            <span class="err" id="n-pass2-e"></span></div>
                        <button class="btn solid" type="submit">Save new password</button>
                        </form>
                        <p class="alt"><a href="#forgot">Request a new link</a></p>
                    </section>
                </div>
            </main>
            <div class="afoot">
                <span>&copy; 2026 LuxyBiz Tours &amp; Wellness</span>
                <nav aria-label="Legal">
                    <a href="#">Privacy</a>
                    <a href="#">Terms</a>
                    <a href="index.html#contact">Contact</a>
                </nav>
            </div>
        </div>
    </div>
</body>
</html>
