/**
 * WH4U Domains - Frontend Domain Lookup & Public Registration
 *
 * Renders card-based results with inline registration form for
 * available domains. No WordPress login required.
 *
 * @package WH4U_Domains
 */
( function() {
	'use strict';

	var config = window.wh4uPublic || {};
	var currentDomain = '';
	var currentOrderType = 'register';
	var pricingCache = null;
	var pricingPromise = null;
	var placeholderTimer = null;
	var turnstileWidgets = {};

	var SVG_CHECK = '<svg class="wh4u-domains__status-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>';
	var SVG_X = '<svg class="wh4u-domains__status-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>';
	

	function init() {
		var form = document.getElementById( 'wh4u-public-lookup-form' );
		if ( ! form ) {
			return;
		}

		form.addEventListener( 'submit', function( e ) {
			e.preventDefault();
			performLookup();
		} );

		var cancelBtn = document.getElementById( 'wh4u-form-cancel' );
		if ( cancelBtn ) {
			cancelBtn.addEventListener( 'click', cancelRegistration );
		}

		var registerForm = document.getElementById( 'wh4u-public-register-form' );
		if ( registerForm ) {
			registerForm.addEventListener( 'submit', function( e ) {
				e.preventDefault();
				submitRegistration();
			} );
		}

		var transferCancelBtn = document.getElementById( 'wh4u-transfer-cancel' );
		if ( transferCancelBtn ) {
			transferCancelBtn.addEventListener( 'click', cancelTransfer );
		}

		var transferForm = document.getElementById( 'wh4u-public-transfer-form' );
		if ( transferForm ) {
			transferForm.addEventListener( 'submit', function( e ) {
				e.preventDefault();
				submitTransfer();
			} );
		}

		initTldChips();
		initPlaceholderCycling();
		prefetchPricing();
		initTurnstile();
	}

	/* ─── Turnstile ─────────────────────────────────────────── */

	function initTurnstile() {
		if ( ! config.turnstileSiteKey ) {
			return;
		}

		var containers = [
			{ id: 'wh4u-turnstile-register', key: 'register' },
			{ id: 'wh4u-turnstile-transfer', key: 'transfer' }
		];

		function renderWidgets() {
			for ( var i = 0; i < containers.length; i++ ) {
				var el = document.getElementById( containers[i].id );
				if ( el && typeof turnstileWidgets[ containers[i].key ] === 'undefined' ) {
					turnstileWidgets[ containers[i].key ] = window.turnstile.render( '#' + containers[i].id, {
						sitekey: config.turnstileSiteKey,
						theme: 'auto',
						size: 'normal'
					} );
				}
			}
		}

		if ( window.turnstile ) {
			renderWidgets();
		} else {
			var poll = setInterval( function() {
				if ( window.turnstile ) {
					clearInterval( poll );
					renderWidgets();
				}
			}, 200 );
		}
	}

	function getTurnstileToken( formKey ) {
		if ( ! config.turnstileSiteKey ) {
			return '';
		}
		var widgetId = turnstileWidgets[ formKey ];
		if ( typeof widgetId === 'undefined' || ! window.turnstile ) {
			return '';
		}
		return window.turnstile.getResponse( widgetId ) || '';
	}

	function resetTurnstile( formKey ) {
		if ( ! config.turnstileSiteKey || ! window.turnstile ) {
			return;
		}
		var widgetId = turnstileWidgets[ formKey ];
		if ( typeof widgetId !== 'undefined' ) {
			window.turnstile.reset( widgetId );
		}
	}

	/* ─── TLD Chips ──────────────────────────────────────────── */

	function initTldChips() {
		var chips = document.querySelectorAll( '.wh4u-domains__tld-chip' );
		if ( ! chips.length ) {
			return;
		}
		for ( var i = 0; i < chips.length; i++ ) {
			chips[i].addEventListener( 'click', function() {
				var tld = this.getAttribute( 'data-tld' );
				if ( ! tld ) {
					return;
				}
				var input = document.getElementById( 'wh4u-public-search' );
				if ( ! input ) {
					return;
				}
				var current = input.value.trim();
				var dot = current.indexOf( '.' );
				var sld = dot > 0 ? current.substring( 0, dot ) : current;
				if ( ! sld ) {
					sld = 'example';
				}
				input.value = sld + tld;
				stopPlaceholderCycling();
				performLookup();
			} );
		}
	}

	/* ─── Animated Placeholder ───────────────────────────────── */

	function initPlaceholderCycling() {
		var input = document.getElementById( 'wh4u-public-search' );
		if ( ! input ) {
			return;
		}

		if ( config.customPlaceholder ) {
			return;
		}

		var examples = [ 'mybusiness.com', 'myshop.io', 'myname.gr', 'startup.net', 'brand.org', 'ideas.co' ];
		var exIndex = 0;
		var charIndex = 0;
		var isDeleting = false;
		var pauseCount = 0;

		function tick() {
			var target = examples[ exIndex ];
			if ( pauseCount > 0 ) {
				pauseCount--;
				return;
			}

			if ( ! isDeleting ) {
				charIndex++;
				input.setAttribute( 'placeholder', target.substring( 0, charIndex ) + '|' );
				if ( charIndex >= target.length ) {
					input.setAttribute( 'placeholder', target );
					pauseCount = 20;
					isDeleting = true;
				}
			} else {
				charIndex--;
				input.setAttribute( 'placeholder', charIndex > 0 ? target.substring( 0, charIndex ) + '|' : '' );
				if ( charIndex <= 0 ) {
					isDeleting = false;
					exIndex = ( exIndex + 1 ) % examples.length;
					pauseCount = 4;
				}
			}
		}

		placeholderTimer = setInterval( tick, 80 );

		input.addEventListener( 'focus', stopPlaceholderCycling );
		input.addEventListener( 'input', stopPlaceholderCycling );
	}

	function stopPlaceholderCycling() {
		if ( placeholderTimer ) {
			clearInterval( placeholderTimer );
			placeholderTimer = null;
			var input = document.getElementById( 'wh4u-public-search' );
			if ( input ) {
				input.setAttribute( 'placeholder', config.i18n.searchPlaceholder || '' );
			}
		}
	}

	/* ─── Pricing Prefetch ───────────────────────────────────── */

	function prefetchPricing() {
		var container = document.querySelector( '.wh4u-domains' );
		if ( ! container || container.getAttribute( 'data-show-pricing' ) !== 'true' ) {
			return;
		}
		pricingPromise = fetchJSON( config.restUrl + 'tlds/pricing', { method: 'GET' } )
			.then( function( data ) {
				pricingCache = {};
				var items = Array.isArray( data ) ? data : [];
				for ( var i = 0; i < items.length; i++ ) {
					var tld = items[i].tld || '';
					if ( tld && ! tld.startsWith( '.' ) ) {
						tld = '.' + tld;
					}
					pricingCache[ tld.toLowerCase() ] = items[i].register || '';
				}
				return pricingCache;
			} )
			.catch( function() {
				pricingCache = {};
				return pricingCache;
			} );
	}

	function getPriceForTld( tld ) {
		if ( ! pricingCache ) {
			return '';
		}
		tld = ( tld || '' ).toLowerCase();
		if ( ! tld.startsWith( '.' ) ) {
			tld = '.' + tld;
		}
		return pricingCache[ tld ] || '';
	}

	/* ─── Skeleton Loading ───────────────────────────────────── */

	function showSkeletons() {
		var container = document.getElementById( 'wh4u-public-results' );
		if ( ! container ) {
			return;
		}
		container.innerHTML = '';
		for ( var i = 0; i < 4; i++ ) {
			var skel = document.createElement( 'div' );
			skel.className = 'wh4u-domains__skeleton-card';
			skel.style.animationDelay = ( i * 80 ) + 'ms';
			skel.innerHTML =
				'<div class="wh4u-domains__skeleton-line wh4u-domains__skeleton-line--wide"></div>' +
				'<div class="wh4u-domains__skeleton-line wh4u-domains__skeleton-line--pill"></div>' +
				'<div class="wh4u-domains__skeleton-line wh4u-domains__skeleton-line--btn"></div>';
			container.appendChild( skel );
		}
		show( container );
	}

	/* ─── Search ──────────────────────────────────────────────── */

	function performLookup() {
		var input = document.getElementById( 'wh4u-public-search' );
		var searchTerm = ( input && input.value ) ? input.value.trim() : '';

		if ( ! searchTerm || searchTerm.length < 2 ) {
			return;
		}

		stopPlaceholderCycling();

		var btn          = document.getElementById( 'wh4u-public-search-btn' );
		var loading      = document.getElementById( 'wh4u-public-loading' );
		var errorEl      = document.getElementById( 'wh4u-public-error' );
		var results      = document.getElementById( 'wh4u-public-results' );
		var formSect     = document.getElementById( 'wh4u-public-form-section' );
		var transferSect = document.getElementById( 'wh4u-public-transfer-section' );
		var successEl    = document.getElementById( 'wh4u-public-success' );

		hide( errorEl );
		hide( formSect );
		hide( transferSect );
		hide( successEl );
		show( loading );
		showSkeletons();

		if ( btn ) {
			btn.disabled = true;
		}

		var lookupPromise = fetchJSON( config.restUrl + 'domains/lookup', {
			method: 'POST',
			body: JSON.stringify( { searchTerm: searchTerm } )
		} );

		lookupPromise
			.then( function( lookupData ) {
				renderResults( lookupData );
			} )
			.catch( function( err ) {
				hide( results );
				showError( err.message || config.i18n.error );
			} )
			.finally( function() {
				hide( loading );
				if ( btn ) {
					btn.disabled = false;
				}
			} );
	}

	/* ─── Results Rendering ──────────────────────────────────── */

	function renderResults( data ) {
		var container = document.getElementById( 'wh4u-public-results' );
		if ( ! container ) {
			return;
		}

		container.innerHTML = '';

		var items = normalizeResults( data );

		if ( ! items.length ) {
			container.innerHTML = '<div class="wh4u-domains__no-results">' + escHtml( config.i18n.noResults ) + '</div>';
			show( container );
			return;
		}

		var showPricing = container.closest( '.wh4u-domains' ) &&
			container.closest( '.wh4u-domains' ).getAttribute( 'data-show-pricing' ) === 'true';

		items.forEach( function( item, index ) {
			var domain      = item.domainName || item.domain || item.sld || '';
			var isAvailable = item.status === 'available' || item.isAvailable === true || item.available === true;

			var card = document.createElement( 'div' );
			card.className = 'wh4u-domains__result-card wh4u-domains__result-card--' + ( isAvailable ? 'available' : 'unavailable' );
			card.style.animationDelay = ( index * 50 ) + 'ms';

			var nameParts = splitDomain( domain );

			var domainEl = document.createElement( 'div' );
			domainEl.className = 'wh4u-domains__result-domain';
			domainEl.innerHTML = '<span class="wh4u-domains__result-domain-name">' +
				escHtml( nameParts.sld ) +
				'<span class="wh4u-domains__result-tld">' + escHtml( nameParts.tld ) + '</span>' +
				'</span>';
			card.appendChild( domainEl );

			var statusEl = document.createElement( 'span' );
			statusEl.className = 'wh4u-domains__result-status wh4u-domains__result-status--' + ( isAvailable ? 'available' : 'unavailable' );
			statusEl.innerHTML = ( isAvailable ? SVG_CHECK : SVG_X ) + ' ' + escHtml( isAvailable ? config.i18n.available : config.i18n.unavailable );
			card.appendChild( statusEl );

			if ( showPricing && isAvailable ) {
				var price = getPriceForTld( nameParts.tld );
				if ( price ) {
					var priceEl = document.createElement( 'span' );
					priceEl.className = 'wh4u-domains__result-price';
					priceEl.textContent = price + '/yr';
					card.appendChild( priceEl );
				}
			}

			var actionEl = document.createElement( 'div' );
			actionEl.className = 'wh4u-domains__result-action';

			if ( isAvailable ) {
				var regBtn = document.createElement( 'button' );
				regBtn.type = 'button';
				regBtn.className = 'wh4u-domains__register-btn';
				regBtn.textContent = config.i18n.register;
				regBtn.setAttribute( 'data-domain', domain );
				regBtn.addEventListener( 'click', function() {
					tryCartRedirect( domain, 'register', function() {
						showRegistrationForm( domain );
					} );
				} );
				actionEl.appendChild( regBtn );
			} else if ( showTransferEnabled() ) {
				var xferBtn = document.createElement( 'button' );
				xferBtn.type = 'button';
				xferBtn.className = 'wh4u-domains__transfer-btn';
				xferBtn.textContent = config.i18n.transfer || '';
				xferBtn.setAttribute( 'data-domain', domain );
				xferBtn.addEventListener( 'click', function() {
					tryCartRedirect( domain, 'transfer', function() {
						showTransferForm( domain );
					} );
				} );
				actionEl.appendChild( xferBtn );
			}

			if ( actionEl.childNodes.length ) {
				card.appendChild( actionEl );
			}

			container.appendChild( card );
		} );

		show( container );
	}

	/* ─── Shopping cart redirect ──────────────────────────────── */

	function tryCartRedirect( domain, action, fallback ) {
		if ( ! config.cartRedirectEnabled || ! domain ) {
			fallback();
			return;
		}
		var url = config.restUrl + 'domains/cart-redirect?domain=' + encodeURIComponent( domain ) + '&action=' + encodeURIComponent( action );
		fetch( url, { method: 'GET', credentials: 'same-origin' } )
			.then( function( res ) {
				return res.json().then( function( data ) {
					return { ok: res.ok, data: data };
				} );
			} )
			.then( function( result ) {
				if ( result.ok && result.data && result.data.url ) {
					window.location.href = result.data.url;
				} else {
					fallback();
				}
			} )
			.catch( function() {
				fallback();
			} );
	}

	/* ─── Registration Form ──────────────────────────────────── */

	function showRegistrationForm( domain ) {
		currentDomain = domain;

		var formSection = document.getElementById( 'wh4u-public-form-section' );
		var badge       = document.getElementById( 'wh4u-form-domain-badge' );
		var results     = document.getElementById( 'wh4u-public-results' );

		var container = getContainer( formSection );
		if ( container ) {
			var titleEl = document.getElementById( 'wh4u-form-title' );
			var descEl  = document.getElementById( 'wh4u-form-desc' );
			if ( titleEl && container.getAttribute( 'data-form-title' ) ) {
				titleEl.textContent = container.getAttribute( 'data-form-title' );
			}
			if ( descEl && container.getAttribute( 'data-form-description' ) ) {
				descEl.textContent = container.getAttribute( 'data-form-description' );
			}
		}

		if ( badge ) {
			badge.textContent = domain;
		}

		hide( results );
		show( formSection );

		if ( formSection ) {
			formSection.scrollIntoView( { behavior: 'smooth', block: 'start' } );
		}
	}

	function cancelRegistration() {
		var formSection = document.getElementById( 'wh4u-public-form-section' );
		var results     = document.getElementById( 'wh4u-public-results' );

		hide( formSection );
		show( results );
	}

	/* ─── Transfer Form ─────────────────────────────────────── */

	function showTransferEnabled() {
		var container = document.querySelector( '.wh4u-domains' );
		return container && container.getAttribute( 'data-show-transfer' ) === 'true';
	}

	function showTransferForm( domain ) {
		currentDomain = domain;
		currentOrderType = 'transfer';

		var transferSection = document.getElementById( 'wh4u-public-transfer-section' );
		var badge           = document.getElementById( 'wh4u-transfer-domain-badge' );
		var results         = document.getElementById( 'wh4u-public-results' );

		if ( badge ) {
			badge.textContent = domain;
		}

		hide( results );
		show( transferSection );

		if ( transferSection ) {
			transferSection.scrollIntoView( { behavior: 'smooth', block: 'start' } );
		}
	}

	function cancelTransfer() {
		var transferSection = document.getElementById( 'wh4u-public-transfer-section' );
		var results         = document.getElementById( 'wh4u-public-results' );

		hide( transferSection );
		show( results );
	}

	function submitTransfer() {
		var form = document.getElementById( 'wh4u-public-transfer-form' );
		if ( ! form ) {
			return;
		}

		clearFieldErrors( form );

		if ( config.turnstileSiteKey && ! getTurnstileToken( 'transfer' ) ) {
			showError( config.i18n.turnstileRequired || 'Please complete the security check.' );
			return;
		}

		var fields = {
			domain:         currentDomain,
			regperiod:      parseInt( form.querySelector( '[name="regperiod"]' ).value, 10 ) || 1,
			eppcode:        val( form, 'eppcode' ),
			firstName:      val( form, 'firstName' ),
			lastName:       val( form, 'lastName' ),
			email:          val( form, 'email' ),
			phone:          val( form, 'phone' ),
			company:        val( form, 'company' ),
			address:        val( form, 'address' ),
			city:           val( form, 'city' ),
			state:          val( form, 'state' ),
			country:        val( form, 'country' ).toUpperCase(),
			zip:            val( form, 'zip' ),
			wh4u_hp_check:  val( form, 'wh4u_hp_check' ),
			'cf-turnstile-response': getTurnstileToken( 'transfer' )
		};

		var validationError = validateFields( fields, form );
		if ( validationError ) {
			showError( validationError );
			return;
		}

		var submitBtn = document.getElementById( 'wh4u-transfer-submit' );
		var errorEl   = document.getElementById( 'wh4u-public-error' );
		hide( errorEl );

		if ( submitBtn ) {
			submitBtn.disabled = true;
		}

		fetchJSON( config.restUrl + 'orders/public-transfer', {
			method: 'POST',
			body: JSON.stringify( fields )
		} )
			.then( function( data ) {
				showSuccess( data.message || config.i18n.transferSuccess || config.i18n.successMessage );
			} )
			.catch( function( err ) {
				showError( err.message || config.i18n.error );
				resetTurnstile( 'transfer' );
			} )
			.finally( function() {
				if ( submitBtn ) {
					submitBtn.disabled = false;
				}
			} );
	}

	function submitRegistration() {
		var form = document.getElementById( 'wh4u-public-register-form' );
		if ( ! form ) {
			return;
		}

		clearFieldErrors( form );

		if ( config.turnstileSiteKey && ! getTurnstileToken( 'register' ) ) {
			showError( config.i18n.turnstileRequired || 'Please complete the security check.' );
			return;
		}

		var fields = {
			domain:         currentDomain,
			regperiod:      parseInt( form.querySelector( '[name="regperiod"]' ).value, 10 ) || 1,
			firstName:      val( form, 'firstName' ),
			lastName:       val( form, 'lastName' ),
			email:          val( form, 'email' ),
			phone:          val( form, 'phone' ),
			company:        val( form, 'company' ),
			address:        val( form, 'address' ),
			city:           val( form, 'city' ),
			state:          val( form, 'state' ),
			country:        val( form, 'country' ).toUpperCase(),
			zip:            val( form, 'zip' ),
			wh4u_hp_check:  val( form, 'wh4u_hp_check' ),
			'cf-turnstile-response': getTurnstileToken( 'register' )
		};

		var validationError = validateFields( fields, form );
		if ( validationError ) {
			showError( validationError );
			return;
		}

		var submitBtn = document.getElementById( 'wh4u-form-submit' );
		var errorEl   = document.getElementById( 'wh4u-public-error' );
		hide( errorEl );

		if ( submitBtn ) {
			submitBtn.disabled = true;
		}

		fetchJSON( config.restUrl + 'orders/public-register', {
			method: 'POST',
			body: JSON.stringify( fields )
		} )
			.then( function( data ) {
				showSuccess( data.message || config.i18n.successMessage );
			} )
			.catch( function( err ) {
				showError( err.message || config.i18n.error );
				resetTurnstile( 'register' );
			} )
			.finally( function() {
				if ( submitBtn ) {
					submitBtn.disabled = false;
				}
			} );
	}

	/* ─── Validation ─────────────────────────────────────────── */

	function validateFields( fields, form ) {
		var required = [ 'firstName', 'lastName', 'email', 'phone', 'address', 'city', 'state', 'country', 'zip' ];

		for ( var i = 0; i < required.length; i++ ) {
			if ( ! fields[ required[i] ] || ! fields[ required[i] ].trim() ) {
				markFieldError( form, required[i] );
				return config.i18n.requiredFields;
			}
		}

		if ( ! /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test( fields.email ) ) {
			markFieldError( form, 'email' );
			return config.i18n.invalidEmail;
		}

		if ( ! /^[+0-9\s()-]{5,50}$/.test( fields.phone ) ) {
			markFieldError( form, 'phone' );
			return config.i18n.invalidPhone;
		}

		if ( ! /^[A-Z]{2}$/.test( fields.country ) ) {
			markFieldError( form, 'country' );
			return config.i18n.invalidCountry;
		}

		return null;
	}

	function markFieldError( form, name ) {
		var input = form.querySelector( '[name="' + name + '"]' );
		if ( input ) {
			input.classList.add( 'wh4u-domains__field-error' );
			input.focus();
		}
	}

	function clearFieldErrors( form ) {
		var errorInputs = form.querySelectorAll( '.wh4u-domains__field-error' );
		for ( var i = 0; i < errorInputs.length; i++ ) {
			errorInputs[i].classList.remove( 'wh4u-domains__field-error' );
		}
	}

	/* ─── Success State ──────────────────────────────────────── */

	function showSuccess( message ) {
		var formSection = document.getElementById( 'wh4u-public-form-section' );
		var successEl   = document.getElementById( 'wh4u-public-success' );

		hide( formSection );

		if ( successEl ) {
			var h3 = successEl.querySelector( 'h3' );
			var p  = successEl.querySelector( 'p' );
			if ( h3 ) { h3.textContent = config.i18n.successTitle; }
			if ( p )  { p.textContent = message; }

			var existing = successEl.querySelector( '.wh4u-domains__success-cta' );
			if ( ! existing ) {
				var btn = document.createElement( 'button' );
				btn.type = 'button';
				btn.className = 'wh4u-domains__success-cta';
				btn.textContent = config.i18n.searchAnother;
				btn.addEventListener( 'click', resetToSearch );
				successEl.appendChild( btn );
			}

			show( successEl );
			successEl.scrollIntoView( { behavior: 'smooth', block: 'center' } );
		}
	}

	function resetToSearch() {
		var successEl       = document.getElementById( 'wh4u-public-success' );
		var results         = document.getElementById( 'wh4u-public-results' );
		var formSection     = document.getElementById( 'wh4u-public-form-section' );
		var transferSection = document.getElementById( 'wh4u-public-transfer-section' );
		var input           = document.getElementById( 'wh4u-public-search' );
		var registerForm    = document.getElementById( 'wh4u-public-register-form' );
		var transferForm    = document.getElementById( 'wh4u-public-transfer-form' );

		hide( successEl );
		hide( results );
		hide( formSection );
		hide( transferSection );

		if ( registerForm ) {
			registerForm.reset();
		}
		if ( transferForm ) {
			transferForm.reset();
		}

		if ( input ) {
			input.value = '';
			input.focus();
		}

		currentDomain = '';
		currentOrderType = 'register';
	}

	/* ─── Error Handling ─────────────────────────────────────── */

	function showError( message ) {
		var el = document.getElementById( 'wh4u-public-error' );
		if ( el ) {
			var p = el.querySelector( 'p' );
			if ( p ) { p.textContent = message; }
			show( el );
			el.scrollIntoView( { behavior: 'smooth', block: 'nearest' } );
		}
	}

	/* ─── Helpers ─────────────────────────────────────────────── */

	function fetchJSON( url, options ) {
		var headers = {
			'Content-Type': 'application/json'
		};
		if ( config.nonce ) {
			headers['X-WP-Nonce'] = config.nonce;
		}

		var fetchOptions = {
			method: options.method || 'GET',
			headers: headers,
			credentials: 'same-origin'
		};
		if ( options.body ) {
			fetchOptions.body = options.body;
		}

		return fetch( url, fetchOptions ).then( function( response ) {
			return response.json().then( function( json ) {
				if ( ! response.ok ) {
					throw new Error( json.message || config.i18n.error );
				}
				return json;
			} );
		} );
	}

	function normalizeResults( data ) {
		if ( Array.isArray( data ) ) {
			return data;
		}
		if ( data && data.result ) {
			return Array.isArray( data.result ) ? data.result : [ data.result ];
		}
		if ( data && data.results ) {
			return Array.isArray( data.results ) ? data.results : [ data.results ];
		}
		if ( data && ( data.domainName || data.domain ) ) {
			return [ data ];
		}
		return [];
	}

	function splitDomain( domain ) {
		var dot = domain.indexOf( '.' );
		if ( dot === -1 ) {
			return { sld: domain, tld: '' };
		}
		return {
			sld: domain.substring( 0, dot ),
			tld: domain.substring( dot )
		};
	}

	function val( form, name ) {
		var input = form.querySelector( '[name="' + name + '"]' );
		return input ? ( input.value || '' ).trim() : '';
	}

	function getContainer( el ) {
		if ( ! el ) {
			return null;
		}
		return el.closest( '.wh4u-domains' );
	}

	function show( el ) {
		if ( el ) { el.style.display = ''; }
	}

	function hide( el ) {
		if ( el ) { el.style.display = 'none'; }
	}

	function escHtml( str ) {
		var div = document.createElement( 'div' );
		div.appendChild( document.createTextNode( str || '' ) );
		return div.innerHTML;
	}

	/* ─── Init ────────────────────────────────────────────────── */

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
