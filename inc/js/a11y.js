/**
 * a11y.js
 *
 * Handles setting aria attributes dynamically and element focus
 * on special cases.
 */
( function($) {

    // Initialize all event listeners.
    function init() {
        widgetMenuAriaLabels();
        socialMenuAriaLabel();
        gravityFormWidgetAriaLabel();
    }
    
    // Add aria-labelledby attributes to menu widgets (<ul>s) with headings.
    function widgetMenuAriaLabels() {

        // Get all menu widgets being used on page.
        let widgetMenus = document.querySelectorAll(`.widget.widget_nav_menu`);
        if ( 0 === widgetMenus.length ) {
            return;
        }
        widgetMenus = Array.from(widgetMenus);

        // Loop throuogh each and check for heading 'id' attribute. If found, apply aria-labelledby attribute to ul.
        widgetMenus.map( menu => {
            let heading = menu.querySelector(`h2`);
            if ( null === heading ) {
                heading = menu.querySelector(`h3`);
            }

            if ( null === heading ) {
                return;
            }
            const headingId = heading.id;
            if ( `` === headingId ) {
                return;
            }
            const list = menu.querySelector(`ul`);
            if ( null === list ) {
                return;
            }
            list.setAttribute(`aria-labelledby`, heading.id);
        });
        
    }

    // Add aria-labelledby attribute to social navigation menu (<ul>).
    function socialMenuAriaLabel() {
        const socialId = `social-navigation`;
        let socialMenu = document.getElementById(socialId);
        if ( 0 === socialMenu.length ) {
            return;
        }

        let heading = socialMenu.querySelector(`h2`);
        if ( null === heading ) {
            heading = socialMenu.querySelector(`h3`);
        }

        if ( null === heading ) {
            return;
        }
        const headingId = heading.id;
        if ( `` === headingId ) {
            return;
        }

        const list = socialMenu.querySelector(`ul`);
        if ( null === list ) {
            return;
        }
        list.setAttribute(`aria-labelledby`, headingId);
       
    }

    function gravityFormWidgetAriaLabel() {
        let gWidgetForms = document.querySelectorAll(`.gform_widget`);
        if ( 0 === gWidgetForms.length ) {
            return;
        }

        gWidgetForms = Array.from(gWidgetForms);

        gWidgetForms.map( form => {
            let heading = form.querySelector(`h2`);
            if ( null === heading ) {
                heading = form.querySelector(`h3`);
            }

            if ( null === heading ) {
                return;
            }
            const headingId = heading.id;
            if ( `` === headingId ) {
                return;
            }
            const list = form.querySelector(`ul`);
            if ( null === list ) {
                return;
            }
            list.setAttribute(`aria-labelledby`, heading.id);
        });
    }

    init();
} )(jQuery);
