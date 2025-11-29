/**
 * Mail System by Katsarov Design - GitHub Pages Scripts
 * 
 * Language switching functionality with localStorage persistence
 */

(function() {
    'use strict';

    // Language configuration
    const STORAGE_KEY = 'mskd-lang';
    const DEFAULT_LANG = 'en';
    const SUPPORTED_LANGS = ['en', 'bg'];

    // Language display names
    const LANG_NAMES = {
        en: 'EN',
        bg: 'BG'
    };

    /**
     * Get the current language from localStorage or default
     * @returns {string} Current language code
     */
    function getCurrentLang() {
        const storedLang = localStorage.getItem(STORAGE_KEY);
        if (storedLang && SUPPORTED_LANGS.includes(storedLang)) {
            return storedLang;
        }
        
        // Try to detect from browser language
        const browserLang = navigator.language.split('-')[0];
        if (SUPPORTED_LANGS.includes(browserLang)) {
            return browserLang;
        }
        
        return DEFAULT_LANG;
    }

    /**
     * Save language preference to localStorage
     * @param {string} lang - Language code to save
     */
    function saveLang(lang) {
        localStorage.setItem(STORAGE_KEY, lang);
    }

    /**
     * Update all translatable elements on the page
     * @param {string} lang - Target language code
     */
    function updatePageContent(lang) {
        // Update all elements with data-en and data-bg attributes
        const elements = document.querySelectorAll('[data-en][data-bg]');
        
        elements.forEach(function(el) {
            const text = el.getAttribute('data-' + lang);
            if (text) {
                el.textContent = text;
            }
        });

        // Update the language switcher button
        const langSwitcher = document.getElementById('langSwitcher');
        if (langSwitcher) {
            const langCurrent = langSwitcher.querySelector('.lang-current');
            if (langCurrent) {
                langCurrent.textContent = LANG_NAMES[lang];
            }
        }

        // Update the html lang attribute
        document.documentElement.lang = lang;
    }

    /**
     * Get the next language in rotation
     * @param {string} currentLang - Current language code
     * @returns {string} Next language code
     */
    function getNextLang(currentLang) {
        const currentIndex = SUPPORTED_LANGS.indexOf(currentLang);
        const nextIndex = (currentIndex + 1) % SUPPORTED_LANGS.length;
        return SUPPORTED_LANGS[nextIndex];
    }

    /**
     * Toggle to the next language
     */
    function toggleLanguage() {
        const currentLang = getCurrentLang();
        const nextLang = getNextLang(currentLang);
        
        saveLang(nextLang);
        updatePageContent(nextLang);
    }

    /**
     * Initialize mobile menu toggle
     */
    function initMobileMenu() {
        const menuToggle = document.getElementById('mobileMenuToggle');
        const nav = document.querySelector('.nav');
        
        if (menuToggle && nav) {
            menuToggle.addEventListener('click', function() {
                nav.classList.toggle('active');
                menuToggle.classList.toggle('active');
            });

            // Close menu when clicking on a link
            nav.querySelectorAll('a').forEach(function(link) {
                link.addEventListener('click', function() {
                    nav.classList.remove('active');
                    menuToggle.classList.remove('active');
                });
            });
        }
    }

    /**
     * Initialize smooth scroll for anchor links
     */
    function initSmoothScroll() {
        document.querySelectorAll('a[href^="#"]').forEach(function(anchor) {
            anchor.addEventListener('click', function(e) {
                const targetId = this.getAttribute('href');
                if (targetId === '#') return;
                
                const target = document.querySelector(targetId);
                if (target) {
                    e.preventDefault();
                    const headerHeight = document.querySelector('.header').offsetHeight;
                    const targetPosition = target.offsetTop - headerHeight - 20;
                    
                    window.scrollTo({
                        top: targetPosition,
                        behavior: 'smooth'
                    });
                }
            });
        });
    }

    /**
     * Initialize everything when DOM is ready
     */
    function init() {
        // Set initial language
        const currentLang = getCurrentLang();
        updatePageContent(currentLang);

        // Set up language switcher
        const langSwitcher = document.getElementById('langSwitcher');
        if (langSwitcher) {
            langSwitcher.addEventListener('click', toggleLanguage);
        }

        // Initialize mobile menu
        initMobileMenu();

        // Initialize smooth scrolling
        initSmoothScroll();
    }

    // Run initialization when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
