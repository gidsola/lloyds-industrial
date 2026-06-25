/**
 * Lloyds Industrial - Mega Menu JavaScript
 * Handles mobile accordion behavior and touch interactions
 */

(function($) {
    'use strict';

    // Wait for DOM to be ready
    $(document).ready(function() {
        // Cache selectors
        var $megaMenu = $('.li-mega-menu');
        
        if (!$megaMenu.length) {
            return;
        }

        // Detect touch devices
        var isTouchDevice = ('ontouchstart' in window) || 
                           (navigator.maxTouchPoints > 0) || 
                           (navigator.msMaxTouchPoints > 0);

        // Check if we're on mobile (adjust breakpoint as needed)
        var isMobile = window.innerWidth <= 960;

        // Toggle dropdowns on mobile/touch devices
        function initMobileBehavior() {
            // For top-level items with dropdowns
            $('.li-mega-menu__item--top-level').each(function() {
                var $item = $(this);
                var $link = $item.children('.li-mega-menu__link');
                var $dropdown = $item.children('.li-mega-menu__dropdown');

                // Only add click handler if dropdown exists
                if ($dropdown.length) {
                    // Store original href
                    var originalHref = $link.attr('href');

                    // Prevent default click on link if it has dropdown
                    $link.on('click.megaMenu', function(e) {
                        // On mobile/touch: prevent navigation if clicking the dropdown trigger
                        if (isMobile || isTouchDevice) {
                            // Check if the click target is the arrow or the main link
                            var $target = $(e.target);
                            var isArrowClick = $target.hasClass('li-mega-menu__link--has-dropdown') ||
                                              $target.parent().hasClass('li-mega-menu__link--has-dropdown');

                            // If the item has children and we're on mobile, toggle instead of navigating
                            if ($dropdown.length && $item.hasClass('li-mega-menu__item--has-children')) {
                                // Only prevent default if clicking directly on the link text/area
                                if (!$target.closest('.li-mega-menu__link').hasClass('li-mega-menu__link--has-dropdown') ||
                                    $target.is('.li-mega-menu__label') || $target.is('.li-mega-menu__icon')) {
                                    e.preventDefault();
                                }
                                toggleDropdown($item, $dropdown);
                            }
                        }
                    });

                    // Also handle enter key for accessibility
                    $link.on('keydown.megaMenu', function(e) {
                        if (e.key === 'Enter' || e.key === ' ') {
                            if ($dropdown.length && (isMobile || isTouchDevice)) {
                                e.preventDefault();
                                toggleDropdown($item, $dropdown);
                            }
                        }
                    });
                }
            });

            // Close all dropdowns when clicking outside
            $(document).on('click.megaMenuClose', function(e) {
                var $target = $(e.target);
                
                // If clicking outside the mega menu, close all dropdowns
                if (!$target.closest('.li-mega-menu').length) {
                    closeAllDropdowns();
                }
            });

            // Close dropdowns when clicking on a link inside dropdown (for navigation)
            $('.li-mega-menu__sublist .li-mega-menu__link').on('click.megaMenuNav', function(e) {
                // Don't close if this is a parent link with children
                if ($(this).parent().hasClass('li-mega-menu__item--has-children')) {
                    return;
                }
                // Close all dropdowns after a brief delay to allow navigation
                setTimeout(function() {
                    closeAllDropdowns();
                }, 200);
            });
        }

        // Toggle a single dropdown
        function toggleDropdown($item, $dropdown) {
            var isOpen = $item.hasClass('open');
            
            // Close all other dropdowns first
            closeAllDropdowns();
            
            if (!isOpen) {
                $item.addClass('open');
                $dropdown.slideDown(200);
            } else {
                $item.removeClass('open');
                $dropdown.slideUp(200);
            }
        }

        // Close all dropdowns
        function closeAllDropdowns() {
            $('.li-mega-menu__item--top-level.open').removeClass('open');
            $('.li-mega-menu__dropdown').slideUp(200);
        }

        // Initialize based on screen size
        function updateMenuBehavior() {
            isMobile = window.innerWidth <= 960;
            
            if (isMobile || isTouchDevice) {
                // On mobile, dropdowns start hidden
                $('.li-mega-menu__dropdown').hide();
                initMobileBehavior();
            } else {
                // On desktop, dropdowns are controlled by CSS hover
                $('.li-mega-menu__dropdown').css('display', '');
                closeAllDropdowns();
                $('.li-mega-menu__item--top-level').off('click.megaMenu keydown.megaMenu');
            }
        }

        // Initial setup
        updateMenuBehavior();

        // Update on resize with debounce
        var resizeTimer;
        $(window).on('resize.megaMenu', function() {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function() {
                updateMenuBehavior();
            }, 250);
        });

        // Handle escape key to close dropdowns
        $(document).on('keydown.megaMenuEscape', function(e) {
            if (e.key === 'Escape') {
                closeAllDropdowns();
            }
        });

        // Add aria-expanded attributes for accessibility
        function updateAriaAttributes() {
            $('.li-mega-menu__item--top-level').each(function() {
                var $item = $(this);
                var $link = $item.children('.li-mega-menu__link');
                var $dropdown = $item.children('.li-mega-menu__dropdown');
                
                if ($dropdown.length) {
                    var isOpen = $item.hasClass('open');
                    $link.attr('aria-expanded', isOpen ? 'true' : 'false');
                }
            });
        }

        // Update aria attributes when dropdowns change
        var observer = new MutationObserver(function(mutations) {
            updateAriaAttributes();
        });
        
        observer.observe($megaMenu[0], {
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: ['class']
        });

        // Initial aria update
        updateAriaAttributes();
    });

})(jQuery);
