// ============================================================
//  FILTER KOS (Pria / Campur / Wanita)
//  Logika sama persis dengan dashboard-pemilik & dashboard-pencari
//  - Klik satu → yang lain dimmed (fc-redup), yang dipilih scale up (fc-dipilih)
//  - Klik yang sama lagi → reset semua, tampilkan semua kartu
// ============================================================
var fcAktif = null;

function filterKos(gender) {
    var cards    = document.querySelectorAll('.filter-card');
    var kosCards = document.querySelectorAll('.kos-card');

    if (fcAktif === gender) {
        // Klik yang sama → reset
        fcAktif = null;
        cards.forEach(function(c) {
            c.classList.remove('fc-dipilih', 'fc-redup');
        });
        kosCards.forEach(function(k) {
            k.style.display = '';
        });
    } else {
        fcAktif = gender;
        // Terapkan state aktif / redup ke filter card
        cards.forEach(function(c) {
            var tipe = c.getAttribute('data-tipe');
            if (tipe === gender) {
                c.classList.add('fc-dipilih');
                c.classList.remove('fc-redup');
            } else {
                c.classList.add('fc-redup');
                c.classList.remove('fc-dipilih');
            }
        });
        // Tampilkan / sembunyikan kos-card
        kosCards.forEach(function(k) {
            k.style.display = (k.getAttribute('data-gender').toLowerCase() === gender) ? '' : 'none';
        });
        // Scroll ke listing
        scrollToCari();
    }
}

// ============================================================
//  PENCARIAN
// ============================================================
function performSearch() {
    var searchTerm = document.getElementById('searchInput').value.toLowerCase().trim();
    var cards = document.querySelectorAll('.kos-card');
    var found = false;

    // Reset filter card saat search dijalankan
    fcAktif = null;
    document.querySelectorAll('.filter-card').forEach(function(c) {
        c.classList.remove('fc-dipilih', 'fc-redup');
    });

    if (searchTerm === '') {
        cards.forEach(function(card) { card.style.display = ''; });
        return;
    }

    cards.forEach(function(card) {
        var name       = (card.querySelector('.kos-name')?.textContent       || '').toLowerCase();
        var price      = (card.querySelector('.kos-price')?.textContent      || '').toLowerCase();
        var facilities = (card.querySelector('.kos-facilities')?.textContent || '').toLowerCase();
        var gender     = (card.getAttribute('data-gender')                   || '').toLowerCase();
        var cleanPrice = price.replace(/[^\d]/g, '');

        var cocok = name.includes(searchTerm)
                 || price.includes(searchTerm)
                 || facilities.includes(searchTerm)
                 || gender.includes(searchTerm)
                 || cleanPrice.includes(searchTerm);

        card.style.display = cocok ? '' : 'none';
        if (cocok) found = true;
    });

    if (!found) {
        // Gunakan kosAlert jika tersedia, fallback ke console
        if (typeof kosAlert === 'function') {
            kosAlert({
                ikon: '🔍',
                judul: 'Tidak Ditemukan',
                pesan: 'Tidak ada kos yang cocok dengan pencarian "' + searchTerm + '"',
                tipe: 'info'
            });
        } else {
            console.log('Tidak ada hasil untuk: ' + searchTerm);
        }
    }

    scrollToCari();
}

// ============================================================
//  SCROLL KE LISTING
// ============================================================
function scrollToCari() {
    var listing = document.getElementById('kos-listing');
    if (listing) {
        listing.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

// ============================================================
//  ADVANCED SEARCH (placeholder)
// ============================================================
function toggleAdvancedSearch() {
    if (typeof kosAlert === 'function') {
        kosAlert({
            ikon: '🛠️',
            judul: 'Segera Hadir',
            pesan: 'Fitur pencarian lanjutan akan segera hadir!',
            tipe: 'info'
        });
    }
}

// ============================================================
//  EVENT LISTENERS
// ============================================================
document.addEventListener('DOMContentLoaded', function() {
    var searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') performSearch();
        });
    }
});