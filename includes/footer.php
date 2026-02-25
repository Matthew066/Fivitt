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
</script>

</body>
</html>