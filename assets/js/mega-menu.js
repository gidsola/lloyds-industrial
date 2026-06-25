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
                    $link.off('click.megaMenu keydown.megaMenu');

                    // Prevent default click on link if it has dropdown
                    $link.on('click.megaMenu', function(e) {
                        // On mobile/touch: prevent navigation if clicking the dropdown trigger
                        if (isMobile || isTouchDevice) {
                            if ($item.hasClass('li-mega-menu__item--has-dropdown')) {
                                e.preventDefault();
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
            $(document).off('click.megaMenuClose');
            $(document).on('click.megaMenuClose', function(e) {
                var $target = $(e.target);
                
                // If clicking outside the mega menu, close all dropdowns
                if (!$target.closest('.li-mega-menu').length) {
                    closeAllDropdowns();
                }
            });

            // Close dropdowns when clicking on a link inside dropdown (for navigation)
            $('.li-mega-menu__sublist .li-mega-menu__link').off('click.megaMenuNav');
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

        function openDropdown($item) {
            var $dropdown = $item.children('.li-mega-menu__dropdown');

            if (!$dropdown.length) {
                return;
            }

            closeAllDropdowns();
            $item.addClass('open');

            if (isMobile || isTouchDevice) {
                $dropdown.stop(true, true).slideDown(200);
            } else {
                $dropdown.css('display', '');
            }

            updateAriaAttributes();
        }

        // Close all dropdowns
        function closeAllDropdowns() {
            $('.li-mega-menu__item--top-level.open').removeClass('open');
            if (isMobile || isTouchDevice) {
                $('.li-mega-menu__dropdown').stop(true, true).slideUp(200);
            } else {
                $('.li-mega-menu__dropdown').css('display', '');
            }

            updateAriaAttributes();
        }

        function focusTopLevel($current, direction) {
            var $links = $('.li-mega-menu__item--top-level > .li-mega-menu__link');
            var currentIndex = $links.index($current);

            if (currentIndex === -1) {
                return;
            }

            var nextIndex = (currentIndex + direction + $links.length) % $links.length;
            $links.eq(nextIndex).trigger('focus');
        }

        function focusFirstDropdownLink($item) {
            var $firstLink = $item.find('.li-mega-menu__dropdown a').first();

            if ($firstLink.length) {
                $firstLink.trigger('focus');
            }
        }

        function initKeyboardBehavior() {
            $('.li-mega-menu__item--top-level > .li-mega-menu__link').off('keydown.megaMenuKeys');
            $('.li-mega-menu__dropdown a').off('keydown.megaMenuKeys');

            $('.li-mega-menu__item--top-level > .li-mega-menu__link').on('keydown.megaMenuKeys', function(e) {
                var $link = $(this);
                var $item = $link.parent();

                if (e.key === 'ArrowRight') {
                    e.preventDefault();
                    focusTopLevel($link, 1);
                    return;
                }

                if (e.key === 'ArrowLeft') {
                    e.preventDefault();
                    focusTopLevel($link, -1);
                    return;
                }

                if (e.key === 'ArrowDown' && $item.children('.li-mega-menu__dropdown').length) {
                    e.preventDefault();
                    openDropdown($item);
                    focusFirstDropdownLink($item);
                    return;
                }

                if (e.key === 'Escape') {
                    e.preventDefault();
                    closeAllDropdowns();
                    $link.trigger('focus');
                }
            });

            $('.li-mega-menu__dropdown a').on('keydown.megaMenuKeys', function(e) {
                var $link = $(this);
                var $dropdownLinks = $link.closest('.li-mega-menu__dropdown').find('a');
                var currentIndex = $dropdownLinks.index($link);

                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    $dropdownLinks.eq(Math.min(currentIndex + 1, $dropdownLinks.length - 1)).trigger('focus');
                    return;
                }

                if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    $dropdownLinks.eq(Math.max(currentIndex - 1, 0)).trigger('focus');
                    return;
                }

                if (e.key === 'Escape') {
                    e.preventDefault();
                    var $topItem = $link.closest('.li-mega-menu__item--top-level');
                    closeAllDropdowns();
                    $topItem.children('.li-mega-menu__link').trigger('focus');
                }
            });
        }

        // Initialize based on screen size
        function updateMenuBehavior() {
            isMobile = window.innerWidth <= 960;
            
            if (isMobile || isTouchDevice) {
                // On mobile, dropdowns start hidden
                $('.li-mega-menu__item--top-level.open').removeClass('open');
                $('.li-mega-menu__dropdown').hide();
                initMobileBehavior();
                initKeyboardBehavior();
            } else {
                // On desktop, dropdowns are controlled by CSS hover
                $('.li-mega-menu__link').off('click.megaMenu keydown.megaMenu');
                $('.li-mega-menu__sublist .li-mega-menu__link').off('click.megaMenuNav');
                $(document).off('click.megaMenuClose');
                $('.li-mega-menu__item--top-level.open').removeClass('open');
                $('.li-mega-menu__dropdown').css('display', '');
                initKeyboardBehavior();
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
                var $focusedTopItem = $(document.activeElement).closest('.li-mega-menu__item--top-level');
                closeAllDropdowns();

                if ($focusedTopItem.length) {
                    $focusedTopItem.children('.li-mega-menu__link').trigger('focus');
                }
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
