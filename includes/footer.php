<footer class="footer">
    &copy; FIVIT <?= date('Y') ?>
</footer>

<script>
(() => {
    const menuBtn = document.querySelector('.menu');
    const drawer = document.querySelector('.drawer');
    const backdrop = document.querySelector('.drawer-backdrop');
    const closeTargets = document.querySelectorAll('[data-drawer-close]');

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
    drawer.querySelectorAll('a').forEach(link => link.addEventListener('click', closeDrawer));
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
</script>

</body>
</html>
