const menuData = {
    'desserts': {
        title: 'Tatlılar',
        image: 'assets/img/desserts.png',
        items: [
            { name: 'Tiramisu', desc: 'Espresso ve mascarpone peynirli klasik İtalyan tatlısı.', price: '8.50₺', img: 'assets/tiramisu.png' },
            { name: 'Cheesecake', desc: 'Orman meyveleri soslu New York usulü cheesecake.', price: '7.00₺', img: 'assets/img/desserts.png' },
            { name: 'Makaron', desc: '4 adet el yapımı Fransız makaronu seçkisi.', price: '6.00₺', img: 'assets/img/desserts.png' },
            { name: 'Sufle', desc: 'Akışkan dolgulu, sıcacık çikolatalı kek.', price: '9.00₺', img: 'assets/img/desserts.png' }
        ]
    },
    'hot-drinks': {
        title: 'Sıcak İçecekler',
        image: 'assets/img/hot_drinks.png',
        items: [
            { name: 'Espresso', desc: 'Zengin ve yoğun tek kökenli shot.', price: '3.50₺', img: 'assets/espresso.png' },
            { name: 'Cappuccino', desc: 'Espresso, buharla ısıtılmış süt ve yoğun köpük.', price: '4.50₺', img: 'assets/img/hot_drinks.png' },
            { name: 'Latte', desc: 'Espresso, bol sıcak süt ve hafif köpük katmanı.', price: '5.00₺', img: 'assets/img/hot_drinks.png' },
            { name: 'Mocha', desc: 'Espresso, el yapımı çikolata ve sıcak süt.', price: '5.50₺', img: 'assets/img/hot_drinks.png' }
        ]
    },
    'cold-drinks': {
        title: 'Soğuk İçecekler',
        image: 'assets/img/cold_drinks.png',
        items: [
            { name: 'Iced Latte', desc: 'Buzlu espresso ve soğuk süt.', price: '5.50₺', img: 'assets/iced_latte.png' },
            { name: 'Cold Brew', desc: 'Yavaş demlenmiş, son derece yumuşak ve yoğun kahve.', price: '4.50₺', img: 'assets/img/cold_drinks.png' },
            { name: 'Buzlu Matcha', desc: 'Buz üzerinde almond sütü ile premium matcha yeşil çayı.', price: '6.00₺', img: 'assets/img/cold_drinks.png' }
        ]
    },
    'cakes': {
        title: 'Pastalar',
        image: 'assets/img/cakes.png',
        items: [
            { name: 'Çikolatalı Fudge', desc: 'Nefis üç katlı çikolatalı pasta.', price: '7.50₺', img: 'assets/chocolate_fudge.png' },
            { name: 'Red Velvet', desc: 'Zengin krem peynir dolgulu klasik kırmızı kadife pasta.', price: '7.00₺', img: 'assets/img/cakes.png' },
            { name: 'Havuçlu Kek', desc: 'Cevizli ve kremalı baharatlı havuçlu kek.', price: '6.50₺', img: 'assets/img/cakes.png' }
        ]
    },
    'ice-cream': {
        title: 'Dondurma',
        image: 'assets/img/ice_cream.png',
        items: [
            { name: 'Vanilyalı', desc: 'Gerçek Madagaskar vanilya çubuğundan yapılmıştır.', price: '4.50₺', img: 'assets/img/ice_cream.png' },
            { name: 'Bitter Çikolata', desc: 'Zengin ve kremsi bitter çikolatalı İtalyan dondurması.', price: '5.00₺', img: 'assets/img/ice_cream.png' },
            { name: 'Antep Fıstıklı', desc: 'Orijinal İtalyan fıstık aroması.', price: '5.50₺', img: 'assets/img/ice_cream.png' },
            { name: 'Çilekli', desc: 'Mükemmel kıvamda karıştırılmış taze yerel çilekler.', price: '4.50₺', img: 'assets/img/ice_cream.png' }
        ]
    }
};

document.addEventListener("DOMContentLoaded", () => {
    // Theme logic
    const themeBtn = document.getElementById('theme-toggle');
    if (themeBtn) {
        const savedTheme = localStorage.getItem('melipo-theme');
        if (savedTheme === 'light' || (!savedTheme && window.matchMedia('(prefers-color-scheme: light)').matches)) {
            document.documentElement.setAttribute('data-theme', 'light');
        }

        themeBtn.addEventListener('click', () => {
            const currentTheme = document.documentElement.getAttribute('data-theme');
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';
            document.documentElement.setAttribute('data-theme', newTheme);
            localStorage.setItem('melipo-theme', newTheme);
        });
    }

    // For index.html cards
    const cards = document.querySelectorAll('.category-card');
    cards.forEach(card => {
        card.addEventListener('click', function() {
            const originalTransform = this.style.transform;
            this.style.transform = 'scale(0.96)';
            setTimeout(() => {
                this.style.transform = originalTransform;
            }, 150);
        });
    });

    // For category.html page rendering
    const categoryContainer = document.getElementById('category-container');
    if (categoryContainer) {
        const urlParams = new URLSearchParams(window.location.search);
        const type = urlParams.get('type');

        if (type && menuData[type]) {
            const data = menuData[type];
            // Set title and bg
            document.getElementById('cat-title').textContent = data.title;
            document.getElementById('cat-header').style.backgroundImage = `url('${data.image}')`;
            
            // Render items
            const listContainer = document.getElementById('product-list');
            listContainer.innerHTML = '';

            data.items.forEach((item, index) => {
                const delay = 0.1 * (index + 1);
                listContainer.innerHTML += `
                    <div class="product-item" style="animation-delay: ${delay}s">
                        <img class="product-img" src="${item.img}" alt="${item.name}">
                        <div class="product-details">
                            <div class="product-header">
                                <h3>${item.name}</h3>
                                <span class="product-price">${item.price}</span>
                            </div>
                            <p>${item.desc}</p>
                        </div>
                    </div>
                `;
            });

            // Lightbox logic
            const lightbox = document.getElementById('image-lightbox');
            const lightboxImg = document.getElementById('lightbox-img');
            const closeBtn = document.querySelector('.lightbox-close');

            if (lightbox && lightboxImg) {
                document.querySelectorAll('.product-img').forEach(img => {
                    img.addEventListener('click', (e) => {
                        lightboxImg.src = e.target.src;
                        lightbox.classList.add('active');
                    });
                });

                const closeLightbox = () => {
                    lightbox.classList.remove('active');
                };

                closeBtn.addEventListener('click', closeLightbox);
                lightbox.addEventListener('click', (e) => {
                    if (e.target === lightbox) {
                        closeLightbox();
                    }
                });
            }
        } else {
            categoryContainer.innerHTML = `
                <nav class="top-nav">
                    <a href="index.html" class="back-btn">
                        <svg viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
                        Menüye Dön
                    </a>
                </nav>
                <div style="text-align:center; padding: 4rem 1rem;">
                    <h2 style="color:var(--accent-color); font-family:'Playfair Display', serif;">Kategori Bulunamadı</h2>
                </div>
            `;
        }
    }
});
