<?php
session_start();
$pageTitle = 'Food Selection';
$bodyClass = 'foodselection-page';
require 'includes/header.php';
?>

<link rel="stylesheet" href="assets/css/all.min.css">
<main class="food-screen">
    <section class="food-head">
        <h1 id="screen-title">Food Selection</h1>
        <button type="button" class="cart-btn" id="cart-toggle" aria-label="Toggle Order">
            <i class="fa-solid fa-cart-shopping"></i>
            <span class="cart-count" id="cart-count">0</span>
        </button>
    </section>

    <section class="panel">
        <h2 class="panel-title">Menu</h2>
        <div class="food-grid" id="food-grid"></div>
        <button type="button" class="confirm-btn is-hidden" id="confirm-btn">Confirm</button>
    </section>
</main>

<script>
(() => {
    const menu = [
        { id: 1, name: 'Veggie Burger', calories: 100, qty: 0 },
        { id: 2, name: 'Hamburger', calories: 100, qty: 0 },
        { id: 3, name: 'Cheeseburger', calories: 100, qty: 0 },
        { id: 4, name: 'Chicken Burger', calories: 100, qty: 0 },
        { id: 5, name: 'Chicken Burger', calories: 100, qty: 0 },
        { id: 6, name: 'Chicken Burger', calories: 100, qty: 0 },
        { id: 7, name: 'Chicken Burger', calories: 100, qty: 0 },
        { id: 8, name: 'Chicken Burger', calories: 100, qty: 0 }
    ];

    let isOrderMode = false;
    const grid = document.getElementById('food-grid');
    const title = document.getElementById('screen-title');
    const cartCount = document.getElementById('cart-count');
    const confirmBtn = document.getElementById('confirm-btn');
    const cartToggle = document.getElementById('cart-toggle');

    function totalItems() {
        return menu.reduce((sum, item) => sum + item.qty, 0);
    }

    function visibleItems() {
        if (!isOrderMode) return menu;
        return menu.filter(item => item.qty > 0);
    }

    function render() {
        const items = visibleItems();
        const total = totalItems();

        title.textContent = isOrderMode ? 'Food Order' : 'Food Selection';
        cartCount.textContent = String(total);
        cartCount.style.display = total > 0 ? 'inline-flex' : 'none';
        confirmBtn.classList.toggle('is-hidden', !isOrderMode || total === 0);

        grid.innerHTML = items.map(item => `
            <article class="food-card">
                <div class="food-image"></div>
                <h3 class="food-name">${item.name}</h3>
                <p class="food-meta">
                    <span>${item.calories} calories</span>
                    <button type="button"
                        class="action-btn ${isOrderMode ? 'minus' : 'plus'}"
                        data-id="${item.id}"
                        data-action="${isOrderMode ? 'remove' : 'add'}"
                        aria-label="${isOrderMode ? 'Remove' : 'Add'}">
                        <i class="fa-solid fa-${isOrderMode ? 'minus' : 'plus'}"></i>
                    </button>
                </p>
            </article>
        `).join('');
    }

    grid.addEventListener('click', (event) => {
        const btn = event.target.closest('button[data-id]');
        if (!btn) return;

        const id = Number(btn.dataset.id);
        const item = menu.find(food => food.id === id);
        if (!item) return;

        if (btn.dataset.action === 'add') {
            item.qty += 1;
        } else {
            item.qty = Math.max(0, item.qty - 1);
        }

        render();
    });

    cartToggle.addEventListener('click', () => {
        if (totalItems() === 0) return;
        isOrderMode = !isOrderMode;
        render();
    });

    confirmBtn.addEventListener('click', () => {
        alert('Order confirmed');
    });

    render();
})();
</script>

<?php include 'includes/footer.php'; ?>


