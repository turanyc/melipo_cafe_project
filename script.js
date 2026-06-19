document.addEventListener('DOMContentLoaded', () => {
    // --- Loading Screen Logic ---
    const loadingScreen = document.getElementById('loading-screen');
    if (loadingScreen) {
        document.body.style.overflow = 'hidden';
        // Wait for the progress bar animation to complete (~2.5s)
        setTimeout(() => {
            loadingScreen.classList.add('hidden');
            document.body.style.overflow = '';
            // Remove from DOM after fade out transition (0.8s)
            setTimeout(() => {
                loadingScreen.remove();
            }, 800);
        }, 2600);
    }

    // --- Lightbox ---
    let lightboxEl = null;

    function openLightbox(src, alt) {
        if (!lightboxEl) {
            lightboxEl = document.createElement('div');
            lightboxEl.className = 'lightbox-overlay';
            lightboxEl.innerHTML = `
                <button class="lightbox-close" aria-label="Kapat"><i class="fa-solid fa-xmark"></i></button>
                <div class="lightbox-inner">
                    <img class="lightbox-img" src="" alt="">
                </div>`;
            document.body.appendChild(lightboxEl);
            // Close on backdrop or close button click
            lightboxEl.addEventListener('click', (e) => {
                if (!e.target.closest('.lightbox-inner') || e.target.closest('.lightbox-close')) {
                    closeLightbox();
                }
            });
            lightboxEl.querySelector('.lightbox-close').addEventListener('click', closeLightbox);
        }
        lightboxEl.querySelector('.lightbox-img').src = src;
        lightboxEl.querySelector('.lightbox-img').alt = alt;
        lightboxEl.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeLightbox() {
        if (lightboxEl) {
            lightboxEl.classList.remove('active');
            document.body.style.overflow = '';
        }
    }

    // Close with Escape key
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeLightbox(); });

    // --- State & DOM Elements ---
    const homeView = document.getElementById('home-view');
    const productsView = document.getElementById('products-view');
    const productList = document.getElementById('productList');

    const headerLeft = document.getElementById('headerLeft');
    const headerTitle = document.getElementById('headerTitle');

    const categoryCards = document.querySelectorAll('.category-card');

    // Original Header State
    const originalHeaderLeftHTML = ``;
    const originalHeaderTitle = ``;

    // --- Menu Data from PHP Database ---
    const categoryData = window.menuData || {};
    let activeCategoryId = null;

    // --- Navigation Logic ---
    function showCategory(categoryId) {
        activeCategoryId = categoryId;
        const data = categoryData[categoryId];
        if (!data) return;

        // Update Header — show back arrow + category title, hide logo
        headerLeft.innerHTML = `<i class="fa-solid fa-arrow-left" style="font-size: 1.1rem; padding: 4px; cursor: pointer;"></i>`;
        headerLeft.onclick = showHome;
        headerTitle.innerHTML = `<span style="font-family:'Cormorant Garamond', serif; font-size:1.4rem; font-weight:600; letter-spacing:1px; color:var(--text-main); text-transform:uppercase;">${data.title}</span>`;

        // Render notice banner if present
        productList.innerHTML = '';
        if (data.notice) {
            const noticeBanner = document.createElement('div');
            noticeBanner.className = 'category-notice';
            noticeBanner.innerHTML = `<i class="fa-solid fa-circle-info"></i> ${data.notice}`;
            productList.appendChild(noticeBanner);
        }
        data.products.forEach((prod, idx) => {
            const delay = idx * 0.05; // staggered animation

            // Generate flavors HTML if they exist
            let flavorsHtml = '';
            let clickableClass = '';
            if (prod.flavors && prod.flavors.length > 0) {
                clickableClass = 'has-flavors';
                const list = prod.flavors.map(f => `<div class="flavor-item">${f}</div>`).join('');
                flavorsHtml = `
                    <div class="product-flavors">
                        <div class="flavors-title">Aroma Seçenekleri (Siparişte belirtebilirsiniz):</div>
                        ${list}
                    </div>
                `;
            }

            const wrapper = document.createElement('div');
            wrapper.className = `product-item ${clickableClass}`;
            wrapper.style.animationDelay = `${delay}s`;

            wrapper.innerHTML = `
                <div class="product-main">
                    <div class="product-img-wrapper" style="cursor:zoom-in;">
                        <img src="${prod.image || data.image}" class="product-img" alt="${prod.name}">
                        <span class="img-zoom-icon"><i class="fa-solid fa-magnifying-glass-plus"></i></span>
                        ${prod.flavors ? '<span class="product-img-info-icon"><i class="fa-solid fa-list-ul"></i></span>' : ''}
                    </div>
                    <div class="product-info">
                        <div class="product-name">${prod.name}</div>
                        <div class="product-desc">${prod.desc}</div>
                        <div class="product-price">${prod.price}</div>
                    </div>
                    ${prod.flavors ? '<i class="fa-solid fa-chevron-down expand-icon" style="top: 24px;"></i>' : ''}
                </div>
                ${flavorsHtml}
            `;

            // Add click listener if it has flavors
            if (prod.flavors) {
                wrapper.addEventListener('click', function (e) {
                    if (e.target.closest('.expand-icon') || e.target.closest('.product-img-wrapper')) {
                        const flavorsContainer = this.querySelector('.product-flavors');
                        const icon = this.querySelector('.expand-icon');
                        const isOpen = flavorsContainer.classList.contains('open');

                        if (isOpen) {
                            flavorsContainer.classList.remove('open');
                            icon.style.transform = 'rotate(0deg)';
                        } else {
                            // Close all others first
                            document.querySelectorAll('.product-flavors.open').forEach(el => {
                                el.classList.remove('open');
                                el.parentElement.querySelector('.expand-icon').style.transform = 'rotate(0deg)';
                            });
                            flavorsContainer.classList.add('open');
                            icon.style.transform = 'rotate(180deg)';
                        }
                    }
                });
            }

            productList.appendChild(wrapper);
        });

        // Attach lightbox to every product image
        productList.querySelectorAll('.product-img-wrapper').forEach(wrapper => {
            wrapper.addEventListener('click', (e) => {
                // Don't open if user clicked a flavors accordion
                if (e.target.closest('.product-flavors') || e.target.closest('.expand-icon')) return;
                const img = wrapper.querySelector('.product-img');
                openLightbox(img.src, img.alt);
            });
        });

        // Switch Views
        homeView.style.display = 'none';
        productsView.style.display = 'block';
        window.scrollTo(0, 0); // Reset scroll position
    }

    function animateCards() {
        const cards = document.querySelectorAll('.category-card');
        cards.forEach((card, index) => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(24px)';
            card.style.transition = 'none';
            // Force reflow
            card.offsetHeight;
            card.style.transition = 'opacity 0.6s cubic-bezier(0.16, 1, 0.3, 1), transform 0.6s cubic-bezier(0.16, 1, 0.3, 1)';
            setTimeout(() => {
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, 80 + (index * 50));
        });
    }

    function showHome() {
        // Restore Header — clear back arrow, restore logo
        headerLeft.innerHTML = originalHeaderLeftHTML;
        headerLeft.onclick = null;
        headerTitle.innerHTML = originalHeaderTitle;
        activeCategoryId = null;

        // Switch Views
        productsView.style.display = 'none';
        homeView.style.display = 'block';
        animateCards();
    }

    // Bind clicks
    categoryCards.forEach(card => {
        card.addEventListener('click', (e) => {
            e.preventDefault();
            const categoryId = card.getAttribute('data-category');
            showCategory(categoryId);
        });
    });

    // Initial load animation for grid cards (after loading screen)
    if (loadingScreen) {
        setTimeout(animateCards, 3400); // Trigger right after loading screen fades out
    } else {
        animateCards();
    }
});
