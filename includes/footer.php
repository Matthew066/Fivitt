<footer class="footer">
    &copy; FIVIT <?= date('Y') ?>
</footer>

<div class="modal fade" id="logoutModal" tabindex="-1" aria-labelledby="logoutModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 16px;">
            <div class="modal-header" style="border-bottom: none;">
                <h5 class="modal-title" id="logoutModalLabel">Konfirmasi Logout</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Anda yakin ingin logout?
            </div>
            <div class="modal-footer" style="border-top: none;">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius: 999px;">Batal</button>
                <a href="/Fivitt/logout.php" class="btn btn-danger" style="border-radius: 999px;">Logout</a>
            </div>
        </div>
    </div>
</div>

<button type="button" class="fvai-trigger" id="fvai-trigger" aria-label="Buka AI FiVit">AI</button>
<section class="fvai-box hidden" id="fvai-box" aria-live="polite">
    <div class="fvai-head">
        <strong>FiVit AI</strong>
        <button type="button" class="fvai-close" id="fvai-close">x</button>
    </div>
    <div class="fvai-body" id="fvai-body">
        <div class="fvai-msg bot">Halo, saya FiVit AI. Saya bisa bantu navigasi fitur FiVit, coach directory, membership gym, partnership, workout, sleep, dan health monitoring.</div>
    </div>
    <form class="fvai-row" id="fvai-form">
        <input class="fvai-input" id="fvai-input" type="text" placeholder="Ketik pertanyaan...">
        <button class="fvai-send" type="submit">Kirim</button>
    </form>
</section>

<script src="/Fivitt/assets/js/jquery.min.js"></script>
<script src="/Fivitt/assets/js/bootstrap.bundle.min.js"></script>
<script src="/Fivitt/assets/js/custom.js"></script>

<script src="/Fivitt/assets/js/fivit-cursor.js" defer></script>
<script>
(() => {
    const menuBtn = document.querySelector('.menu');
    const drawer = document.querySelector('.drawer');
    const backdrop = document.querySelector('.drawer-backdrop');
    const closeTargets = document.querySelectorAll('[data-drawer-close]');
    const logoutTrigger = document.querySelector('[data-logout-trigger="true"]');
    const logoutModalEl = document.getElementById('logoutModal');

    if (!menuBtn || !drawer || !backdrop) return;

    function openDrawer() {
        drawer.classList.add('open');
        backdrop.classList.add('show');
        menuBtn.setAttribute('aria-expanded', 'true');
        drawer.setAttribute('aria-hidden', 'false');
        document.body.classList.add('no-scroll');
    }

    function closeDrawer() {
        drawer.classList.remove('open');
        backdrop.classList.remove('show');
        menuBtn.setAttribute('aria-expanded', 'false');
        drawer.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('no-scroll');
    }

    menuBtn.addEventListener('click', openDrawer);
    closeTargets.forEach(target => target.addEventListener('click', closeDrawer));
    drawer.querySelectorAll('a:not([data-logout-trigger="true"])').forEach(link => {
        link.addEventListener('click', closeDrawer);
    });

    if (logoutTrigger && logoutModalEl && typeof bootstrap !== 'undefined') {
        logoutTrigger.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            closeDrawer();

            window.requestAnimationFrame(() => {
                window.requestAnimationFrame(() => {
                    const logoutModal = bootstrap.Modal.getOrCreateInstance(logoutModalEl);
                    logoutModal.show();
                });
            });
        });
    }
})();

(() => {
    function parseScore(text) {
        if (!text) return null;
        const normalized = text.replace(',', '.');
        const match = normalized.match(/(\d+(?:\.\d+)?)/);
        if (!match) return null;
        const value = parseFloat(match[1]);
        return Number.isFinite(value) ? value : null;
    }

    function tierClass(score) {
        if (score === null) return '';
        if (score < 5) return 'score-tier-low';
        if (score < 7) return 'score-tier-mid';
        if (score < 9.5) return 'score-tier-good';
        return 'score-tier-excellent';
    }

    document.querySelectorAll('.score-card .score-number').forEach((el) => {
        const score = parseScore(el.textContent || '');
        const cls = tierClass(score);
        if (!cls) return;
        const card = el.closest('.score-card');
        if (card) card.classList.add(cls);
    });

    document.querySelectorAll('.summary-pill').forEach((el) => {
        const txt = (el.textContent || '').toLowerCase();
        if (!txt.includes('skor')) return;
        const score = parseScore(el.textContent || '');
        const cls = tierClass(score);
        if (!cls) return;
        el.classList.add(cls);
    });
})();

(() => {
    const box = document.getElementById('fvai-box');
    const trigger = document.getElementById('fvai-trigger');
    const closeBtn = document.getElementById('fvai-close');
    const form = document.getElementById('fvai-form');
    const input = document.getElementById('fvai-input');
    const body = document.getElementById('fvai-body');
    if (!box || !trigger || !closeBtn || !form || !input || !body) return;

    function addMsg(text, who) {
        const node = document.createElement('div');
        node.className = 'fvai-msg ' + who;
        node.textContent = text;
        body.appendChild(node);
        body.scrollTop = body.scrollHeight;
    }

    function reply(text) {
        const t = (text || '').toLowerCase();
        if (t.includes('membership') || t.includes('paket gym')) return 'Info membership gym ada di halaman Gym Booking. Paket dan harga diatur dari database/admin.';
        if (t.includes('partnership') || t.includes('partner gym')) return 'Untuk partnership, buka Gym Booking lalu isi form Ajukan Partnership Perusahaan.';
        if (t.includes('sesi') && (t.includes('coach') || t.includes('pelatih'))) return 'Untuk request/approve jadwal sesi coach, buka menu Coach Sessions.';
        if (t.includes('komunitas') || t.includes('community') || t.includes('chat')) return 'Untuk chat global/private dan undangan sesi/membership, buka menu Community Hub.';
        if (t.includes('coach') || t.includes('pelatih')) return 'Untuk daftar coach (internal/eksternal) dan undang coach, buka menu Coach Directory.';
        if (t.includes('sleep') || t.includes('tidur')) return 'Untuk data tidur, buka menu Sleep Tracking.';
        if (t.includes('workout') || t.includes('latihan')) return 'Untuk rekomendasi latihan personal, buka menu Work Out Personalization.';
        if (t.includes('health') || t.includes('bmi') || t.includes('air')) return 'Untuk health check, buka Basic Health Monitoring.';
        return 'Saya bisa bantu soal navigasi fitur FiVit dan flow gym network. Coba tanya: membership, partnership, workout, sleep, atau health.';
    }

    trigger.addEventListener('click', () => box.classList.toggle('hidden'));
    closeBtn.addEventListener('click', () => box.classList.add('hidden'));

    form.addEventListener('submit', (e) => {
        e.preventDefault();
        const text = (input.value || '').trim();
        if (!text) return;
        addMsg(text, 'user');
        addMsg(reply(text), 'bot');
        input.value = '';
        input.focus();
    });
})();
</script>

</body>
</html>
