<footer class="footer">
    &copy; FIVIT <?= date('Y') ?>
</footer>

<div class="modal fade logout-modal" id="logoutModal" tabindex="-1" aria-labelledby="logoutModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="logoutModalLabel">Konfirmasi Logout</h5>
            </div>
            <div class="modal-body">
                Anda yakin ingin logout?
            </div>
            <div class="modal-footer">
                <button type="button" class="logout-modal__button logout-modal__button--cancel" data-bs-dismiss="modal">Batal</button>
                <a href="/fivitt/logout.php" class="logout-modal__button logout-modal__button--confirm">Logout</a>
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

<script src="/fivitt/assets/js/jquery.min.js"></script>
<script src="/fivitt/assets/js/bootstrap.bundle.min.js"></script>
<script src="/fivitt/assets/js/custom.js"></script>

<script src="/fivitt/assets/js/fivit-cursor.js" defer></script>
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

    const currentPath = (window.location.pathname || '').toLowerCase();

    function hasAny(text, keywords) {
        return keywords.some((keyword) => text.includes(keyword));
    }

    function currentPageHint() {
        if (currentPath.includes('health')) return 'Kamu sedang di halaman Health.';
        if (currentPath.includes('sleep')) return 'Kamu sedang di halaman Sleep.';
        if (currentPath.includes('gym')) return 'Kamu sedang di halaman Gym.';
        if (currentPath.includes('community')) return 'Kamu sedang di halaman Community.';
        if (currentPath.includes('coach')) return 'Kamu sedang di halaman Coach.';
        if (currentPath.includes('education')) return 'Kamu sedang di halaman Education.';
        if (currentPath.includes('leaderboard')) return 'Kamu sedang di halaman Leaderboard.';
        return 'Kamu sedang di dashboard FiVit.';
    }

    function reply(text) {
        const t = (text || '').toLowerCase();
        if (!t) {
            return 'Coba tanya singkat saja, misalnya: "cara lihat BMI", "membership gym", atau "coach sessions".';
        }

        if (hasAny(t, ['halo', 'hai', 'hi', 'hello'])) {
            return currentPageHint() + ' Saya bisa bantu navigasi fitur FiVit seperti health, sleep, gym, coach, community, leaderboard, dan education.';
        }

        if (hasAny(t, ['siapa kamu', 'kamu bisa apa', 'bisa bantu apa'])) {
            return 'Saya FiVit AI. Saya bisa bantu jelaskan fungsi menu, arahkan kamu ke halaman yang tepat, dan jawab pertanyaan dasar seputar health, sleep, workout, coach, community, dan gym.';
        }

        const answers = [];

        if (hasAny(t, ['membership', 'paket gym', 'langganan gym', 'harga gym'])) {
            answers.push('Info membership gym ada di halaman Gym Booking. Di sana kamu bisa lihat paket, harga, dan proses pendaftaran.');
        }

        if (hasAny(t, ['partnership', 'partner gym', 'kerja sama gym'])) {
            answers.push('Untuk partnership perusahaan, buka Gym Booking lalu cari form pengajuan partnership.');
        }

        if (hasAny(t, ['coach session', 'coach sessions', 'sesi coach', 'jadwal coach'])) {
            answers.push('Untuk buat atau kelola jadwal sesi coach, buka menu Coach Sessions.');
        }

        if (hasAny(t, ['coach', 'pelatih', 'mentor'])) {
            answers.push('Untuk lihat directory coach, undang coach, atau daftar jadi coach, buka menu Coach Directory.');
        }

        if (hasAny(t, ['community', 'komunitas', 'chat', 'grup'])) {
            answers.push('Untuk chat global, private chat, dan interaksi komunitas, buka menu Community.');
        }

        if (hasAny(t, ['sleep', 'tidur', 'jam tidur'])) {
            answers.push('Untuk catat dan lihat data tidur, buka menu Sleep Tracking.');
        }

        if (hasAny(t, ['workout', 'latihan', 'olahraga', 'exercise'])) {
            answers.push('Untuk latihan personal atau rekomendasi workout, buka fitur workout atau gym yang tersedia di aplikasi.');
        }

        if (hasAny(t, ['health', 'bmi', 'imt', 'air', 'hidrasi', 'check-in kesehatan'])) {
            answers.push('Untuk BMI, hidrasi, aktivitas, dan check-in harian, buka menu Health.');
        }

        if (hasAny(t, ['leaderboard', 'ranking', 'peringkat'])) {
            answers.push('Untuk lihat peringkat member atau progress terbaik, buka halaman Leaderboard.');
        }

        if (hasAny(t, ['education', 'edukasi', 'artikel', 'tips'])) {
            answers.push('Untuk artikel dan tips kesehatan, buka menu Education.');
        }

        if (hasAny(t, ['halaman ini', 'page ini', 'di sini apa'])) {
            answers.push(currentPageHint());
        }

        if (answers.length) {
            return answers.join(' ');
        }

        return currentPageHint() + ' Saya belum yakin maksud pertanyaannya, tapi saya bisa bantu untuk: health, sleep, gym, membership, coach, community, leaderboard, atau education.';
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
