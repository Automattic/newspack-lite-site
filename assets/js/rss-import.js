/* global ajaxurl, nlsRssImport */
( function () {
	const form = document.getElementById( 'nls-rss-import-form' );
	if ( ! form ) {
		return;
	}

	function showNotice( message, type ) {
		const existing = document.getElementById( 'nls-import-notice' );
		if ( existing ) {
			existing.remove();
		}
		const notice = document.createElement( 'div' );
		notice.id = 'nls-import-notice';
		notice.className = 'notice notice-' + type + ' inline is-dismissible';
		const p = document.createElement( 'p' );
		p.textContent = message;
		const dismissBtn = document.createElement( 'button' );
		dismissBtn.type = 'button';
		dismissBtn.className = 'notice-dismiss';
		dismissBtn.addEventListener( 'click', function () {
			notice.remove();
		} );
		notice.appendChild( p );
		notice.appendChild( dismissBtn );
		form.insertAdjacentElement( 'beforebegin', notice );
	}

	function beforeUnloadHandler( e ) {
		e.preventDefault();
		e.returnValue = '';
	}

	form.addEventListener( 'submit', function ( e ) {
		e.preventDefault();

		const btn = document.getElementById( 'nls-rss-import-submit' );
		if ( ! btn ) {
			return;
		}

		const spinner = document.createElement( 'span' );
		spinner.className = 'spinner is-active';
		spinner.style.cssText =
			'float:none;margin:0 0 0 4px;vertical-align:middle;';
		btn.disabled = true;
		btn.insertAdjacentElement( 'afterend', spinner );
		btn.value = nlsRssImport.i18n.importing;

		window.addEventListener( 'beforeunload', beforeUnloadHandler );

		fetch( ajaxurl, {
			method: 'POST',
			body: new FormData( form ),
			credentials: 'same-origin',
		} )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( data ) {
				window.removeEventListener(
					'beforeunload',
					beforeUnloadHandler
				);
				spinner.remove();
				btn.disabled = false;
				btn.value = nlsRssImport.i18n.runImport;

				if ( data.success ) {
					showNotice( data.data.message, 'success' );
				} else {
					showNotice( data.data, 'error' );
				}
			} )
			.catch( function () {
				window.removeEventListener(
					'beforeunload',
					beforeUnloadHandler
				);
				spinner.remove();
				btn.disabled = false;
				btn.value = nlsRssImport.i18n.runImport;
				showNotice( nlsRssImport.i18n.unexpectedError, 'error' );
			} );
	} );
} )();
