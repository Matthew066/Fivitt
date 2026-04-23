(() => {
    const body = document.body;
    if (!body) return;

    const cursor = document.createElement('div');
    const ring = document.createElement('div');
    cursor.className = 'fivit-cursor';
    ring.className = 'fivit-cursor-ring';
    body.appendChild(cursor);
    body.appendChild(ring);
    body.classList.add('has-fivit-cursor');

    const hoverSelector = 'a,button,.btn,.btn-primary,.menu,.drawer-link,.tab,.pill,.card,.mini-card,.activity-btn,.glass,label,summary,input,select,textarea';

    let mouseX = Math.round(window.innerWidth / 2);
    let mouseY = Math.round(window.innerHeight / 2);
    let ringX = mouseX;
    let ringY = mouseY;

    function animate() {
        ringX += (mouseX - ringX) * 0.18;
        ringY += (mouseY - ringY) * 0.18;
        cursor.style.left = `${mouseX}px`;
        cursor.style.top = `${mouseY}px`;
        ring.style.left = `${ringX}px`;
        ring.style.top = `${ringY}px`;
        requestAnimationFrame(animate);
    }

    cursor.classList.add('is-active');
    ring.classList.add('is-active');
    requestAnimationFrame(animate);

    document.addEventListener('mousemove', (event) => {
        mouseX = event.clientX;
        mouseY = event.clientY;
        cursor.classList.add('is-active');
        ring.classList.add('is-active');
    }, { passive: true });

    document.addEventListener('pointermove', (event) => {
        mouseX = event.clientX;
        mouseY = event.clientY;
        cursor.classList.add('is-active');
        ring.classList.add('is-active');

        if (event.target && event.target.closest(hoverSelector)) {
            cursor.classList.add('is-hover');
            ring.classList.add('is-hover');
        } else {
            cursor.classList.remove('is-hover');
            ring.classList.remove('is-hover');
        }
    }, { passive: true });

    document.addEventListener('mouseleave', () => {
        cursor.classList.remove('is-active');
        ring.classList.remove('is-active');
    });

    document.addEventListener('pointerleave', () => {
        cursor.classList.remove('is-active');
        ring.classList.remove('is-active');
        cursor.classList.remove('is-hover');
        ring.classList.remove('is-hover');
    }, { passive: true });

    document.addEventListener('pointerdown', (event) => {
        if (event.button && event.button !== 0) return;
        mouseX = event.clientX;
        mouseY = event.clientY;
        cursor.classList.add('is-active');
        ring.classList.add('is-active');
        cursor.classList.add('is-click');
        ring.classList.add('is-click');

        if (event.target && event.target.closest(hoverSelector)) {
            cursor.classList.add('is-hover');
            ring.classList.add('is-hover');
        }
    }, { passive: true });

    document.addEventListener('pointerup', () => {
        cursor.classList.remove('is-click');
        ring.classList.remove('is-click');
    }, { passive: true });

    document.addEventListener('mouseover', (event) => {
        if (!event.target) return;
        if (event.target.closest(hoverSelector)) {
            cursor.classList.add('is-hover');
            ring.classList.add('is-hover');
        }
    }, { passive: true });

    document.addEventListener('mouseout', (event) => {
        if (!event.target) return;
        if (event.target.closest(hoverSelector)) {
            cursor.classList.remove('is-hover');
            ring.classList.remove('is-hover');
        }
    }, { passive: true });
})();
