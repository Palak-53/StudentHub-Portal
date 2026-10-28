/* =========================================================
   STUDENT HUB PORTAL — INTERACTIVE COMPONENTS
   FAQ | Modal | Slider | Notification Banner | Hamburger | Dark Mode (localStorage)
   ========================================================= */

// Mark this file as loaded so script.js does not attach a duplicate theme listener
window.__interactiveLoaded = true;

document.addEventListener('DOMContentLoaded', () => {

    // =====================================================
    // 1. LIGHT / DARK THEME (with localStorage)
    // =====================================================
    const themeToggle = document.getElementById('themeToggle');
    const DARK_KEY = 'studenthub-theme';

    // Restore saved preference on page load
    const savedTheme = localStorage.getItem(DARK_KEY);
    if (savedTheme === 'dark') {
        document.body.classList.add('dark-mode');
        if (themeToggle) themeToggle.textContent = '☀️';
    }

    if (themeToggle) {
        themeToggle.addEventListener('click', () => {
            document.body.classList.toggle('dark-mode');
            const isDark = document.body.classList.contains('dark-mode');
            themeToggle.textContent = isDark ? '☀️' : '🌙';
            // Save preference to localStorage
            localStorage.setItem(DARK_KEY, isDark ? 'dark' : 'light');
        });
    }


    // =====================================================
    // 2. NOTIFICATION BANNER
    // =====================================================
    const notifBanner = document.getElementById('notifBanner');
    const closeBanner  = document.getElementById('closeBanner');

    function dismissBanner() {
        if (!notifBanner) return;
        notifBanner.classList.add('hidden');
        notifBanner.addEventListener('transitionend', () => {
            notifBanner.style.display = 'none';
        }, { once: true });
    }

    if (closeBanner) {
        closeBanner.addEventListener('click', dismissBanner);
    }

    // Auto-dismiss after 8 seconds
    setTimeout(dismissBanner, 8000);


    // =====================================================
    // 3. MODAL POPUP (defined early so Escape key can use closeModal)
    // =====================================================
    const modalOverlay  = document.getElementById('modalOverlay');
    const openModalBtn  = document.getElementById('openModalBtn');
    const modalClose    = document.getElementById('modalClose');
    const modalBtnClose = document.getElementById('modalBtnClose');

    function openModal() {
        if (!modalOverlay) return;
        modalOverlay.classList.add('visible');
        modalOverlay.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        // Focus the close button for keyboard accessibility
        if (modalClose) setTimeout(() => modalClose.focus(), 50);
    }

    function closeModal() {
        if (!modalOverlay) return;
        modalOverlay.classList.remove('visible');
        modalOverlay.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    if (openModalBtn) {
        openModalBtn.addEventListener('click', openModal);
        openModalBtn.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                openModal();
            }
        });
    }

    if (modalClose)    modalClose.addEventListener('click', closeModal);
    if (modalBtnClose) modalBtnClose.addEventListener('click', closeModal);

    // Close when clicking outside the modal box
    if (modalOverlay) {
        modalOverlay.addEventListener('click', (e) => {
            if (e.target === modalOverlay) closeModal();
        });
    }


    // =====================================================
    // 4. HAMBURGER MENU
    // =====================================================
    const hamburgerBtn = document.getElementById('hamburgerBtn');
    const mobileNav    = document.getElementById('mobileNav');
    const navOverlay   = document.getElementById('navOverlay');

    function openNav() {
        if (!hamburgerBtn || !mobileNav || !navOverlay) return;
        hamburgerBtn.classList.add('open');
        hamburgerBtn.setAttribute('aria-expanded', 'true');
        mobileNav.classList.add('open');
        mobileNav.setAttribute('aria-hidden', 'false');
        navOverlay.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeNav() {
        if (!hamburgerBtn || !mobileNav || !navOverlay) return;
        hamburgerBtn.classList.remove('open');
        hamburgerBtn.setAttribute('aria-expanded', 'false');
        mobileNav.classList.remove('open');
        mobileNav.setAttribute('aria-hidden', 'true');
        navOverlay.classList.remove('active');
        document.body.style.overflow = '';
    }

    if (hamburgerBtn) {
        hamburgerBtn.addEventListener('click', () => {
            const isOpen = hamburgerBtn.classList.contains('open');
            isOpen ? closeNav() : openNav();
        });
    }

    if (navOverlay) {
        navOverlay.addEventListener('click', closeNav);
    }

    window.addEventListener('resize', () => {
        const breakpoint = document.querySelector('.portal-student-header') ? 1650 : 1300;
        if (window.innerWidth > breakpoint) closeNav();
    });

    // Escape key: close nav and modal
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeNav();
            closeModal();
        }
    });


    // =====================================================
    // 5. CONTENT SLIDER
    // =====================================================
    const sliderTrack         = document.getElementById('sliderTrack');
    const sliderDotsContainer = document.getElementById('sliderDots');
    const sliderPrev          = document.getElementById('sliderPrev');
    const sliderNext          = document.getElementById('sliderNext');

    if (sliderTrack && sliderDotsContainer) {
        const slides = sliderTrack.querySelectorAll('.slide');
        const total  = slides.length;
        let current  = 0;
        let autoSlideTimer;

        // Build dot indicators dynamically
        slides.forEach((_, i) => {
            const dot = document.createElement('button');
            dot.classList.add('slider-dot');
            dot.setAttribute('type', 'button');
            dot.setAttribute('aria-label', `Go to slide ${i + 1}`);
            if (i === 0) dot.classList.add('active');
            dot.addEventListener('click', () => goToSlide(i));
            sliderDotsContainer.appendChild(dot);
        });

        const dots = sliderDotsContainer.querySelectorAll('.slider-dot');

        function goToSlide(index) {
            current = (index + total) % total;
            sliderTrack.style.transform = `translateX(-${current * 100}%)`;
            dots.forEach(d => d.classList.remove('active'));
            if (dots[current]) dots[current].classList.add('active');
        }

        function nextSlide() { goToSlide(current + 1); }
        function prevSlide() { goToSlide(current - 1); }

        function startAutoSlide() {
            autoSlideTimer = setInterval(nextSlide, 4000);
        }

        function stopAutoSlide() {
            clearInterval(autoSlideTimer);
        }

        if (sliderPrev) {
            sliderPrev.addEventListener('click', () => {
                stopAutoSlide();
                prevSlide();
                startAutoSlide();
            });
        }

        if (sliderNext) {
            sliderNext.addEventListener('click', () => {
                stopAutoSlide();
                nextSlide();
                startAutoSlide();
            });
        }

        // Pause on hover
        sliderTrack.addEventListener('mouseenter', stopAutoSlide);
        sliderTrack.addEventListener('mouseleave', startAutoSlide);

        // Touch/swipe support
        let touchStartX = 0;
        sliderTrack.addEventListener('touchstart', (e) => {
            touchStartX = e.changedTouches[0].screenX;
        }, { passive: true });
        sliderTrack.addEventListener('touchend', (e) => {
            const diff = touchStartX - e.changedTouches[0].screenX;
            if (Math.abs(diff) > 40) {
                stopAutoSlide();
                diff > 0 ? nextSlide() : prevSlide();
                startAutoSlide();
            }
        }, { passive: true });

        startAutoSlide();
    }


    // =====================================================
    // 6. FAQ ACCORDION
    // =====================================================
    const faqItems = document.querySelectorAll('.faq-item');

    faqItems.forEach(item => {
        const btn    = item.querySelector('.faq-question');
        const answer = item.querySelector('.faq-answer');

        if (!btn || !answer) return;

        btn.addEventListener('click', () => {
            const isExpanded = btn.getAttribute('aria-expanded') === 'true';

            // Accordion: close all other items first
            faqItems.forEach(other => {
                if (other === item) return;
                const otherBtn    = other.querySelector('.faq-question');
                const otherAnswer = other.querySelector('.faq-answer');
                if (otherBtn) otherBtn.setAttribute('aria-expanded', 'false');
                if (otherAnswer) otherAnswer.classList.remove('open');
            });

            // Toggle clicked item
            if (isExpanded) {
                btn.setAttribute('aria-expanded', 'false');
                answer.classList.remove('open');
            } else {
                btn.setAttribute('aria-expanded', 'true');
                answer.classList.add('open');
            }
        });
    });

});
