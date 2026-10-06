/**
 * Mphysiocare — Kinesitherapie Merksem
 * Main JavaScript
 */

(function() {
    'use strict';

    // ==========================================================================
    // DOM Elements
    // ==========================================================================
    const header = document.getElementById('header');
    const nav = document.getElementById('nav');
    const navToggle = document.getElementById('navToggle');
    const floatingCta = document.getElementById('floatingCta');
    const contactForm = document.getElementById('contactForm');
    const formMessage = document.getElementById('formMessage');
    const yearSpan = document.getElementById('year');

    // ==========================================================================
    // Header Scroll Effect
    // ==========================================================================
    let lastScrollY = window.scrollY;
    let ticking = false;

    function updateHeader() {
        const scrollY = window.scrollY;
        
        // Add scrolled class for shadow
        if (scrollY > 50) {
            header.classList.add('scrolled');
        } else {
            header.classList.remove('scrolled');
        }

        // Show/hide floating CTA
        if (scrollY > 400) {
            floatingCta.classList.add('visible');
        } else {
            floatingCta.classList.remove('visible');
        }

        lastScrollY = scrollY;
        ticking = false;
    }

    window.addEventListener('scroll', function() {
        if (!ticking) {
            window.requestAnimationFrame(updateHeader);
            ticking = true;
        }
    }, { passive: true });

    // ==========================================================================
    // Mobile Navigation
    // ==========================================================================
    if (navToggle && nav) {
        navToggle.addEventListener('click', function() {
            navToggle.classList.toggle('active');
            nav.classList.toggle('active');
            
            // Toggle aria-expanded
            const isExpanded = nav.classList.contains('active');
            navToggle.setAttribute('aria-expanded', isExpanded);
            navToggle.setAttribute('aria-label', isExpanded ? 'Menu sluiten' : 'Menu openen');
        });

        // Close nav when clicking a link
        nav.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', function() {
                navToggle.classList.remove('active');
                nav.classList.remove('active');
            });
        });

        // Close nav when clicking outside
        document.addEventListener('click', function(e) {
            if (!nav.contains(e.target) && !navToggle.contains(e.target)) {
                navToggle.classList.remove('active');
                nav.classList.remove('active');
            }
        });
    }

    // ==========================================================================
    // Floating CTA Click
    // ==========================================================================
    if (floatingCta) {
        floatingCta.addEventListener('click', function() {
            const contactSection = document.getElementById('contact');
            if (contactSection) {
                contactSection.scrollIntoView({ behavior: 'smooth' });
            }
        });
    }

    // ==========================================================================
    // Smooth Scroll for anchor links
    // ==========================================================================
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            const href = this.getAttribute('href');
            
            // Skip if it's just "#"
            if (href === '#') return;
            
            const target = document.querySelector(href);
            if (target) {
                e.preventDefault();
                
                const headerHeight = header ? header.offsetHeight : 0;
                const targetPosition = target.getBoundingClientRect().top + window.pageYOffset - headerHeight - 20;
                
                window.scrollTo({
                    top: targetPosition,
                    behavior: 'smooth'
                });
            }
        });
    });

    // ==========================================================================
    // Contact Form (Formspree)
    // ==========================================================================
    if (contactForm) {
        contactForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Basic validation
            const name = document.getElementById('name');
            const email = document.getElementById('email');
            
            // Reset previous errors
            formMessage.textContent = '';
            formMessage.className = 'form-message';
            
            // Validate name
            if (!name.value.trim()) {
                showFormMessage('Vul alstublieft uw naam in.', 'error');
                name.focus();
                return;
            }
            
            // Validate email
            if (!email.value.trim()) {
                showFormMessage('Vul alstublieft uw e-mailadres in.', 'error');
                email.focus();
                return;
            }
            
            if (!isValidEmail(email.value)) {
                showFormMessage('Vul alstublieft een geldig e-mailadres in.', 'error');
                email.focus();
                return;
            }
            
            // Submit form via AJAX to Formspree
            const submitBtn = contactForm.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            submitBtn.innerHTML = '<span>Versturen...</span>';
            submitBtn.disabled = true;
            
            const formData = new FormData(contactForm);
            
            fetch(contactForm.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'Accept': 'application/json'
                }
            })
            .then(response => {
                if (response.ok) {
                    showFormMessage('Bedankt voor uw aanvraag! We nemen zo snel mogelijk contact met u op.', 'success');
                    contactForm.reset();
                } else {
                    return response.json().then(data => {
                        if (data.errors) {
                            showFormMessage('Er is iets misgegaan: ' + data.errors.map(e => e.message).join(', '), 'error');
                        } else {
                            showFormMessage('Er is iets misgegaan. Probeer het opnieuw of neem telefonisch contact op.', 'error');
                        }
                    });
                }
            })
            .catch(error => {
                showFormMessage('Er is iets misgegaan. Probeer het opnieuw of neem telefonisch contact op.', 'error');
            })
            .finally(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        });
    }

    function showFormMessage(message, type) {
        formMessage.textContent = message;
        formMessage.className = 'form-message ' + type;
        
        // Scroll message into view on mobile
        if (window.innerWidth < 768) {
            formMessage.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    }

    function isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    // ==========================================================================
    // Current Year
    // ==========================================================================
    if (yearSpan) {
        yearSpan.textContent = new Date().getFullYear();
    }

    // ==========================================================================
    // Scroll Animations (Intersection Observer)
    // ==========================================================================
    const observerOptions = {
        root: null,
        rootMargin: '0px 0px -100px 0px',
        threshold: 0.1
    };

    const observer = new IntersectionObserver(function(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
                observer.unobserve(entry.target);
            }
        });
    }, observerOptions);

    // Add animation to elements
    function initScrollAnimations() {
        const animatedElements = document.querySelectorAll(
            '.service-card, .practice-card, .testimonial, .credential'
        );
        
        animatedElements.forEach((el, index) => {
            el.classList.add('animate-on-scroll');
            el.style.transitionDelay = `${index * 0.1}s`;
            observer.observe(el);
        });
    }

    // ==========================================================================
    // Service Cards Hover Effect
    // ==========================================================================
    function initServiceCards() {
        const serviceCards = document.querySelectorAll('.service-card');
        
        serviceCards.forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.zIndex = '10';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.zIndex = '';
            });
        });
    }

    // ==========================================================================
    // Preload Images
    // ==========================================================================
    function preloadCriticalImages() {
        const criticalImages = document.querySelectorAll('.hero-image, .service-image img');
        
        criticalImages.forEach(img => {
            if (img.loading === 'lazy') {
                img.loading = 'eager';
            }
        });
    }

    // ==========================================================================
    // About Section Photo Slider
    // ==========================================================================
    const aboutSlider = document.getElementById('aboutSlider');
    
    if (aboutSlider) {
        const slides = aboutSlider.querySelectorAll('.about-slide');
        const dots = aboutSlider.querySelectorAll('.slider-dot');
        const prevBtn = aboutSlider.querySelector('.slider-btn-prev');
        const nextBtn = aboutSlider.querySelector('.slider-btn-next');
        let currentSlide = 0;
        let autoSlideInterval;

        function goToSlide(index) {
            // Wrap around
            if (index < 0) index = slides.length - 1;
            if (index >= slides.length) index = 0;
            
            // Update slides
            slides.forEach((slide, i) => {
                slide.classList.toggle('active', i === index);
            });
            
            // Update dots
            dots.forEach((dot, i) => {
                dot.classList.toggle('active', i === index);
            });
            
            currentSlide = index;
        }

        function nextSlide() {
            goToSlide(currentSlide + 1);
        }

        function prevSlide() {
            goToSlide(currentSlide - 1);
        }

        // Event listeners
        if (prevBtn) prevBtn.addEventListener('click', prevSlide);
        if (nextBtn) nextBtn.addEventListener('click', nextSlide);
        
        dots.forEach((dot, index) => {
            dot.addEventListener('click', () => goToSlide(index));
        });

        // Auto-slide every 5 seconds
        function startAutoSlide() {
            autoSlideInterval = setInterval(nextSlide, 5000);
        }

        function stopAutoSlide() {
            clearInterval(autoSlideInterval);
        }

        // Pause on hover
        aboutSlider.addEventListener('mouseenter', stopAutoSlide);
        aboutSlider.addEventListener('mouseleave', startAutoSlide);

        // Start auto-slide
        startAutoSlide();

        // Touch/swipe support
        let touchStartX = 0;
        let touchEndX = 0;

        aboutSlider.addEventListener('touchstart', (e) => {
            touchStartX = e.changedTouches[0].screenX;
            stopAutoSlide();
        }, { passive: true });

        aboutSlider.addEventListener('touchend', (e) => {
            touchEndX = e.changedTouches[0].screenX;
            handleSwipe();
            startAutoSlide();
        }, { passive: true });

        function handleSwipe() {
            const swipeThreshold = 50;
            const diff = touchStartX - touchEndX;
            
            if (Math.abs(diff) > swipeThreshold) {
                if (diff > 0) {
                    nextSlide(); // Swipe left
                } else {
                    prevSlide(); // Swipe right
                }
            }
        }
    }

    // ==========================================================================
    // Active Navigation Link
    // ==========================================================================
    function updateActiveNavLink() {
        const sections = document.querySelectorAll('section[id]');
        const navLinks = document.querySelectorAll('.nav-link');
        
        let currentSection = '';
        
        sections.forEach(section => {
            const sectionTop = section.offsetTop - 150;
            const sectionHeight = section.offsetHeight;
            
            if (window.scrollY >= sectionTop && window.scrollY < sectionTop + sectionHeight) {
                currentSection = section.getAttribute('id');
            }
        });
        
        navLinks.forEach(link => {
            link.classList.remove('active');
            if (link.getAttribute('href') === `#${currentSection}`) {
                link.classList.add('active');
            }
        });
    }

    window.addEventListener('scroll', updateActiveNavLink, { passive: true });

    // ==========================================================================
    // Initialize
    // ==========================================================================
    function init() {
        // Initial header state
        updateHeader();
        
        // Initialize components
        initScrollAnimations();
        initServiceCards();
        preloadCriticalImages();
        updateActiveNavLink();
        
        // Add loaded class to body for animations
        document.body.classList.add('loaded');
    }

    // Run on DOMContentLoaded
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // ==========================================================================
    // Performance: Reduce motion for users who prefer it
    // ==========================================================================
    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    
    if (prefersReducedMotion.matches) {
        document.documentElement.style.setProperty('--transition-base', '0.01s');
        document.documentElement.style.setProperty('--transition-slow', '0.01s');
        document.documentElement.style.setProperty('--transition-slower', '0.01s');
    }

    // ==========================================================================
    // Map Modal Functions (Global)
    // ==========================================================================
    window.openMapModal = function() {
        const modal = document.getElementById('mapModal');
        if (modal) {
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
    };

    window.closeMapModal = function() {
        const modal = document.getElementById('mapModal');
        if (modal) {
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }
    };

    // Close modal on escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            window.closeMapModal();
        }
    });

    // ==========================================================================
    // Language Detection Banner (Optie 2)
    // ==========================================================================
    function initLangBanner() {
        const banner = document.getElementById('langBanner');
        if (!banner) return;

        // Check if user already dismissed the banner
        if (localStorage.getItem('mphysio-lang-banner-dismissed')) {
            banner.classList.add('hidden');
            return;
        }

        // Detect browser language
        const browserLang = (navigator.language || navigator.userLanguage || '').toLowerCase();
        
        // Detect current page language (check HTML lang attribute AND URL)
        const htmlLang = document.documentElement.lang.toLowerCase();
        const currentPage = window.location.pathname.toLowerCase();
        const isPolishPage = htmlLang === 'pl' || currentPage.includes('-pl') || currentPage.includes('_pl');
        
        // Check if browser is Polish
        const isPolishBrowser = browserLang.startsWith('pl');

        console.log('Browser lang:', browserLang, '| Page lang:', htmlLang, '| Is Polish page:', isPolishPage, '| Is Polish browser:', isPolishBrowser);

        // Only show if there's a mismatch
        if ((isPolishBrowser && !isPolishPage) || (!isPolishBrowser && isPolishPage)) {
            // Show banner after a short delay
            setTimeout(function() {
                banner.classList.add('visible');
            }, 1500);

            // Auto-hide after 12 seconds
            setTimeout(function() {
                if (banner.classList.contains('visible')) {
                    banner.classList.remove('visible');
                }
            }, 12000);
        } else {
            // No mismatch - hide banner
            banner.classList.add('hidden');
        }
    }

    // Close banner function (global)
    window.closeLangBanner = function() {
        const banner = document.getElementById('langBanner');
        if (banner) {
            banner.classList.remove('visible');
            localStorage.setItem('mphysio-lang-banner-dismissed', 'true');
        }
    };

    // Initialize banner
    initLangBanner();

})();
