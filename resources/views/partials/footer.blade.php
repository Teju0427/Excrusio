<footer class="ex-footer">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-4">
                <div class="ex-brand mb-2">Excursio</div>
                <p class="mb-3" style="max-width: 22rem">Stay somewhere special. Experience something extraordinary.</p>
                <p class="small mb-0"><i class="bi bi-envelope me-1"></i> hello@excursio.example <span class="opacity-75">(placeholder)</span></p>
            </div>
            <div class="col-6 col-lg-2">
                <h4>Explore</h4>
                <ul>
                    <li><a href="{{ route('listings.index', 'stays') }}">Stays</a></li>
                    <li><a href="{{ route('listings.index', 'dining') }}">Dining</a></li>
                    <li><a href="{{ route('listings.index', 'experiences') }}">Experiences</a></li>
                    <li><a href="{{ route('destinations.index') }}">Destinations</a></li>
                </ul>
            </div>
            <div class="col-6 col-lg-2">
                <h4>Excursio</h4>
                <ul>
                    <li><a href="{{ route('pages.about') }}">About</a></li>
                    <li><a href="{{ route('contact') }}">Contact</a></li>
                    <li><a href="{{ route('pages.terms') }}">Terms</a></li>
                    <li><a href="{{ route('pages.privacy') }}">Privacy</a></li>
                </ul>
            </div>
            <div class="col-lg-4">
                <h4>About this demo</h4>
                <p class="small mb-0">Excursio is a portfolio project. Listings, prices and availability are illustrative, and payments are simulated — no real money is taken and no card details are collected.</p>
            </div>
        </div>
        <div class="ex-footer-bottom d-flex flex-wrap justify-content-between gap-2">
            <span>&copy; {{ date('Y') }} Excursio · Built with Laravel</span>
            <span>Photos: see <a href="{{ route('pages.credits') }}">image credits</a></span>
        </div>
    </div>
</footer>
