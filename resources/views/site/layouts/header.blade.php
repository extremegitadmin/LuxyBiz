<header class="hdr" id="hdr">
  <div class="bar">
    <a class="brand" href="#top" aria-label="LuxyBiz Tours &amp; Wellness, home">
      <span class="logo"><img src="{{asset('frontend/images/luxy-biz-png.png')}}" width="200" height="200" alt=""></span>
      <span class="brand-copy"><strong>LUXYBIZ</strong><span>TOURS &amp; WELLNESS</span></span>
    </a>
    <button class="burger" id="burger" aria-expanded="false" aria-controls="menu" aria-label="Open menu"><span></span><span></span><span></span></button>
    <div class="menu" id="menu">
      <nav class="nav" aria-label="Primary">
        <a href="#journeys">Explore Kerala / Packages</a>
        <a href="#about">About</a>
        <a href="#contact">Contact</a>
      </nav>
      <div class="tools">
        <div class="cur header-cur" role="group" aria-label="Display prices in">
          <button type="button" aria-pressed="true" data-cur="usd">USD</button>
          <button type="button" aria-pressed="false" data-cur="inr">INR</button>
        </div>
        <a class="login" href="{{route('signin')}}">Login / Account</a>
        <a class="btn solid" href="#plan">Plan Your Journey</a>
      </div>
    </div>
  </div>
</header>