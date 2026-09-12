/**
 * VM Social AI — admin behaviour.
 */

// 1. Core Utilities
( function () {
	'use strict';

	if ( typeof window.VMSAI === 'undefined' ) {
		return;
	}

	var cfg = window.VMSAI;

	cfg.number = function ( value ) {
		return new Intl.NumberFormat().format( value || 0 );
	};

	cfg.esc = function ( value ) {
		return String( null === value || undefined === value ? '' : value )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' )
			.replace( /'/g, '&#39;' );
	};

	cfg.api = function ( path, method, payload ) {
		var endpoint = cfg.root.replace( /\/+$/, '' ) + '/' + path.replace( /^\/+/, '' );

		return fetch( endpoint, {
			method: method || 'GET',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': cfg.nonce
			},
			body: payload ? JSON.stringify( payload ) : undefined
		} ).then( function ( response ) {
			if ( ! response.ok ) {
				return response.text().then( function( text ) {
					var msg = 'HTTP ' + response.status;
					try {
						var json = JSON.parse( text );
						if ( json.message ) msg += ': ' + json.message;
					} catch( e ) {
						if ( text.length < 100 ) msg += ': ' + text;
					}
					throw new Error( msg );
				} );
			}
			return response.json();
		} );
	};

	var toastTimer = null;
	cfg.toast = function ( message, bad ) {
		var el = document.getElementById( 'vmsai-toast' );
		if ( ! el ) return;

		el.textContent = message;
		el.classList.toggle( 'is-bad', !! bad );
		el.classList.add( 'is-open' );

		window.clearTimeout( toastTimer );
		toastTimer = window.setTimeout( function () {
			el.classList.remove( 'is-open' );
		}, 6000 );
	};
} )();

// 2. jQuery-dependent UI Logic
jQuery( function ( $ ) {
	'use strict';

	if ( typeof window.VMSAI === 'undefined' ) {
		return;
	}

	var cfg = window.VMSAI;
	var api = cfg.api;
	var toast = cfg.toast;

	function busy( button, on ) {
		if ( ! button ) return;
		if ( on ) {
			button.dataset.label = button.textContent;
			button.textContent = cfg.i18n.working;
			button.disabled = true;
		} else {
			button.textContent = button.dataset.label || button.textContent;
			button.disabled = false;
		}
	}

	/**
	 * Scrape the form for any non-masked credentials to send with a test call.
	 */
	/**
	 * Unsaved credential values, so a provider can be tested before saving.
	 *
	 * Only fields explicitly marked as credentials are collected. This used to
	 * sweep up every named input and textarea on the page, which meant a Test
	 * click posted the nonce, the referer, the form's action/section markers,
	 * the chain checkboxes and every model select to the server — all of which
	 * were then written into the encrypted credential store as junk keys.
	 */
	function currentCredentials() {
		var creds = {};
		$( '[data-vmsai-cred]' ).each( function () {
			var val = $( this ).val();
			if ( this.name && val && ! val.match( /^•+$/ ) ) {
				creds[ this.name ] = val;
			}
		} );
		return creds;
	}

	/* ---------- Action dispatch ---------- */

	var actions = {};

	$( document ).on( 'click', '[data-vmsai-action]', function ( event ) {
		var trigger = this;
		var name = trigger.getAttribute( 'data-vmsai-action' );

		if ( actions[ name ] ) {
			event.preventDefault();
			actions[ name ]( trigger );
		}
	} );

	/* ---------- Brain ---------- */

	actions.discover = function ( button ) {
		busy( button, true );
		api( '/brain/discover', 'POST', {} ).then( function ( result ) {
			busy( button, false );
			if ( ! result.ok ) {
				toast( result.message || cfg.i18n.failed, true );
				return;
			}
			var filled = 0;
			Object.keys( result.data ).forEach( function ( key ) {
				var field = document.querySelector( '[data-brain-field="' + key + '"]' );
				if ( field && result.data[ key ] && ! field.value.trim() ) {
					field.value = result.data[ key ];
					filled++;
				}
			} );
			toast( filled ? filled + ' fields drafted.' : 'Nothing new to add.' );
		} ).catch( function ( err ) {
			busy( button, false );
			toast( err.message || cfg.i18n.failed, true );
		} );
	};

	actions.reflect = function ( button ) {
		busy( button, true );
		api( '/brain/reflect', 'POST', {} ).then( function ( result ) {
			if ( ! result.ok ) {
				toast( result.message || cfg.i18n.failed, true );
				return;
			}
			toast( result.message );
			window.setTimeout( function () { window.location.reload(); }, 1200 );
		} ).finally( function() {
			busy( button, false );
		} );
	};

	/* ---------- Inbox ---------- */

	actions[ 'refresh-inbox' ] = function ( button ) {
		busy( button, true );
		api( '/inbox/list', 'GET' ).then( function ( result ) {
			busy( button, false );
			if ( ! result.ok || ! result.comments.length ) {
				toast( 'No new comments found.' );
				return;
			}
			toast( 'Fetched ' + result.comments.length + ' comments.' );
			window.location.reload();
		} ).catch( function ( err ) {
			busy( button, false );
			toast( err.message || cfg.i18n.failed, true );
		} );
	};

	actions[ 'suggest-reply' ] = function ( button ) {
		var card = $( button ).closest( '.vmsai-inbox-item' )[0];
		var $textarea = $( card ).find( 'textarea' );
		var $tagBox = $( card ).find( '.vmsai-inbox-meta-tags' );

		busy( button, true );

		api( '/inbox/suggest', 'POST', {
			text: card.dataset.text,
			author: card.dataset.author,
			channel: card.dataset.channel,
			rating: card.dataset.rating
		} ).then( function ( result ) {
			busy( button, false );
			if ( ! result.ok ) {
				toast( result.message || cfg.i18n.failed, true );
				return;
			}
			if ( $textarea.length ) $textarea.val( result.reply );
			if ( $tagBox.length ) {
				var sentimentClass = result.sentiment === 'positive' ? 'good' : ( result.sentiment === 'negative' ? 'error' : '' );
				var intentClass = result.intent === 'lead' ? 'score' : '';
				$tagBox.html(
					'<span class="vmsai-chip vmsai-chip--' + sentimentClass + '">' + result.sentiment + '</span>' +
					'<span class="vmsai-chip vmsai-chip--' + intentClass + '">' + result.intent + '</span>' +
					( result.lead_score ? '<span class="vmsai-chip vmsai-chip--score">🔥 ' + result.lead_score + '</span>' : '' )
				);
			}
		} ).catch( function ( err ) {
			busy( button, false );
			toast( err.message || cfg.i18n.failed, true );
		} );
	};

	/* ---------- UI Helpers & Tab Switching ---------- */

	$( document ).on( 'click', '.vmsai-nav-sub a', function ( e ) {
		var $link = $( this );
		var target = $link.attr( 'href' ) || '';

		if ( '#' !== target.charAt( 0 ) ) return;

		var $target = $( target );
		if ( ! $target.length ) {
			var pureId = target.substring(1);
			var el = document.getElementById( pureId );
			if ( el ) $target = $( el );
		}

		if ( ! $target.length ) return;

		e.preventDefault();

		// Update UI
		$link.closest( '.vmsai-nav-sub' ).find( 'a' ).removeClass( 'is-active' );
		$link.addClass( 'is-active' );

		$( '.vmsai-settings-section, .vmsai-subview' ).hide();
		$target.show();

		if ( history.replaceState ) {
			history.replaceState( null, null, target );
		}
	} );

	// Auto-open section if hash exists on load
	var hash = window.location.hash;
	if ( hash && hash.charAt( 0 ) === '#' ) {
		var $trigger = $( '.vmsai-nav-sub a[href="' + hash + '"]' );
		if ( $trigger.length ) $trigger.trigger( 'click' );
	}

	/* ---------- Commander ---------- */

	$( document ).on( 'click', '#vmsai-commander-toggle', function() {
		$( '#vmsai-commander-popup' ).toggleClass( 'is-open' );
	} );

	setTimeout( function() { $( '#vmsai-commander-popup' ).addClass( 'is-open' ); }, 1500 );

	$( document ).on( 'mousedown', function( e ) {
		var container = $( '#vmsai-commander-popup, #vmsai-commander-toggle' );
		if ( ! container.is( e.target ) && container.has( e.target ).length === 0 ) {
			$( '#vmsai-commander-popup' ).removeClass( 'is-open' );
		}
	} );

	$( document ).on( 'click', '[data-vmsai-prompt]', function() {
		var input = document.getElementById( 'vmsai-commander-input' );
		var btn = document.querySelector( '[data-vmsai-action="commander-chat"]' );
		if ( input && btn ) {
			input.value = this.dataset.vmsaiPrompt;
			btn.click();
		}
	} );

	actions[ 'commander-chat' ] = function ( button ) {
		var input = document.getElementById( 'vmsai-commander-input' );
		var log = document.getElementById( 'vmsai-commander-log' );
		var message = input.value.trim();
		if ( ! message ) return;

		busy( button, true );
		input.value = '';

		var userWrap = document.createElement( 'div' );
		userWrap.style.textAlign = 'right';
		userWrap.style.margin = '10px 0';
		var userBubble = document.createElement( 'span' );
		userBubble.style.cssText = 'background:var(--raised); padding:8px 12px; border-radius:15px 15px 0 15px; display:inline-block; font-size:13px; max-width:80%;';
		userBubble.textContent = message;
		userWrap.appendChild( userBubble );
		log.appendChild( userWrap );
		log.scrollTop = log.scrollHeight;

		api( '/commander/chat', 'POST', { message: message } ).then( function ( result ) {
			busy( button, false );
			var aiWrap = document.createElement( 'div' );
			aiWrap.style.margin = '10px 0';
			var avatar = document.createElement( 'span' );
			avatar.className = 'vmsai-director-avatar';
			avatar.textContent = 'SD';
			var aiBubble = document.createElement( 'span' );
			aiBubble.style.cssText = 'background:var(--gold); color:var(--ink); padding:8px 12px; border-radius:15px 15px 15px 0; display:inline-block; font-size:13px; max-width:80%; font-weight:500;';
			aiBubble.textContent = result.reply || 'Executing...';
			aiWrap.appendChild( avatar );
			aiWrap.appendChild( aiBubble );
			log.appendChild( aiWrap );
			log.scrollTop = log.scrollHeight;
			if ( result.refresh ) window.setTimeout( function () { window.location.reload(); }, 2500 );
		} ).catch( function ( err ) {
			busy( button, false );
			toast( err.message || cfg.i18n.failed, true );
		} );
	};

	/* ---------- Queue Actions ---------- */

	function cardOf( trigger ) {
		return $( trigger ).closest( '.vmsai-card-v2' );
	}

	function cardPayload( $card ) {
		var body = $card.find( '[data-field="body"]' ).val();
		var firstComment = $card.find( '[data-field="first_comment"]' ).val();
		var evergreen = $card.find( '.vmsai-evergreen-toggle' ).prop( 'checked' );

		return {
			id: parseInt( $card.attr( 'data-id' ), 10 ),
			body: body,
			first_comment: firstComment,
			is_evergreen: evergreen ? 1 : 0
		};
	}

	actions[ 'save-post' ] = function ( button ) {
		var $card = cardOf( button );
		busy( button, true );
		api( '/queue/update', 'POST', cardPayload( $card ) ).then( function ( result ) {
			busy( button, false );
			toast( result.ok ? cfg.i18n.saved : ( result.message || cfg.i18n.failed ), ! result.ok );
		} ).catch( function( err ) {
			busy( button, false );
			toast( err.message || cfg.i18n.failed, true );
		} );
	};

	actions[ 'approve-post' ] = function ( button ) {
		var $card = cardOf( button );
		var payload = cardPayload( $card );
		payload.status = 'approved';

		busy( button, true );
		api( '/queue/update', 'POST', payload ).then( function ( result ) {
			busy( button, false );
			if ( ! result.ok ) {
				toast( result.message || cfg.i18n.failed, true );
				return;
			}
			$card.removeClass( 'is-draft' ).addClass( 'is-approved' );
			toast( 'Approved. It will go out at its scheduled time.' );
		} ).catch( function( err ) {
			busy( button, false );
			toast( err.message || cfg.i18n.failed, true );
		} );
	};

	actions[ 'reject-post' ] = function ( button ) {
		var reason = window.prompt( 'Why is this post being rejected?' );
		if ( null === reason || ! reason.trim() ) return;

		var $card = cardOf( button );
		var payload = cardPayload( $card );
		payload.status = 'draft';
		payload.reviewer_notes = reason.trim();

		busy( button, true );
		api( '/queue/update', 'POST', payload ).then( function ( result ) {
			busy( button, false );
			if ( ! result.ok ) {
				toast( result.message || cfg.i18n.failed, true );
				return;
			}
			toast( 'Rejected.' );
			window.setTimeout( function () { window.location.reload(); }, 900 );
		} );
	};

	actions[ 'regenerate-post' ] = function ( button ) {
		var $card = cardOf( button );
		$card.addClass( 'is-busy' );
		busy( button, true );

		api( '/queue/regenerate', 'POST', { id: cardPayload( $card ).id } ).then( function ( result ) {
			busy( button, false );
			$card.removeClass( 'is-busy' );
			if ( ! result.ok ) {
				toast( result.message || cfg.i18n.failed, true );
				return;
			}
			toast( 'Rewritten.' );
			window.location.reload();
		} );
	};

	actions[ 'publish-post' ] = function ( button ) {
		var $card = cardOf( button );
		$card.addClass( 'is-busy' );
		busy( button, true );

		api( '/queue/publish-now', 'POST', { id: cardPayload( $card ).id } ).then( function ( result ) {
			busy( button, false );
			$card.removeClass( 'is-busy' );
			if ( ! result.ok ) {
				toast( result.message || cfg.i18n.failed, true );
				return;
			}
			$card.addClass( 'is-published' );
			toast( cfg.i18n.published );
		} );
	};

	actions[ 'delete-post' ] = function ( button ) {
		if ( ! window.confirm( cfg.i18n.confirm ) ) return;
		var $card = cardOf( button );
		api( '/queue/delete', 'POST', { id: cardPayload( $card ).id } ).then( function () {
			$card.remove();
			toast( 'Deleted.' );
		} );
	};

	actions[ 'bulk-queue' ] = function ( button ) {
		var action = button.dataset.bulk;
		busy( button, true );
		api( '/queue/bulk', 'POST', { bulk_action: action } ).then( function ( result ) {
			busy( button, false );
			toast( result.count + ' posts updated.' );
			window.location.reload();
		} );
	};

	actions[ 'copy-portal-link' ] = function ( button ) {
		var id = cardOf( button ).attr( 'data-id' );
		var token = button.getAttribute( 'data-token' );
		var url = window.location.origin + window.location.pathname + '?vmsai_portal=1&id=' + id + '&token=' + token;
		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			navigator.clipboard.writeText( url ).then( function() { toast( 'Review Link copied.' ); } );
		} else {
			window.prompt( 'Copy link:', url );
		}
	};

	actions[ 'regen-image' ] = function ( button ) {
		var $card = cardOf( button );
		var $container = $card.find( '.vmsai-card-v2__media' );
		var $wrap = $card.find( '.vmsai-card-v2__media-wrap' );
		var $select = $card.find( '.vmsai-media-provider-select' );
		var provider = $select.val();

		busy( button, true );
		$container.addClass( 'is-busy' );

		api( '/queue/regen-image', 'POST', { id: parseInt( $card.attr( 'data-id' ), 10 ), provider: provider } ).then( function ( result ) {
			busy( button, false );
			$container.removeClass( 'is-busy' );
			if ( ! result.ok ) {
				toast( result.message || cfg.i18n.failed, true );
				return;
			}
			$wrap.removeClass( 'is-missing' ).html( '<a href="' + result.url + '" target="_blank"><img src="' + result.url + '"></a>' );
			$container.find( '.vmsai-media-tag' ).text( result.provider.toUpperCase() );
			toast( 'Image regenerated.' );
		} ).catch( function ( err ) {
			busy( button, false );
			$container.removeClass( 'is-busy' );
			toast( err.message || cfg.i18n.failed, true );
		} );
	};

	actions[ 'compose-slot' ] = function ( button ) {
		var $ghost = $( button ).closest( '.vmsai-ghost' );
		var slotId = parseInt( $ghost.attr( 'data-slot-id' ), 10 );
		if ( ! slotId ) return;

		busy( button, true );
		toast( 'Writing the post...' );

		api( '/campaign-gen/compose', 'POST', { slot_id: slotId } ).then( function ( result ) {
			busy( button, false );
			if ( ! result.ok ) {
				toast( result.error || result.message || cfg.i18n.failed, true );
				return;
			}
			toast( 'Written.' );
			window.location.reload();
		} );
	};

	/* ---------- Engines & Channels ---------- */

	actions[ 'test-text' ] = function ( button ) {
		busy( button, true );
		var box = document.getElementById( 'vmsai-text-test' );
		api( '/engine/test', 'POST', { engine: 'text', credentials: currentCredentials() } ).then( function ( result ) {
			busy( button, false );
			if ( ! box ) return;
			if ( ! result.ok ) {
				// Provider errors relay text straight from third-party APIs, so
				// none of this can go into innerHTML unescaped (same rule the
				// image test below follows).
				var triedHtml = '';
				if ( result.tried ) {
					triedHtml = '<ul style="margin:10px 0; font-size:11px; opacity:0.8;">';
					Object.keys( result.tried ).forEach( function( p ) {
						triedHtml += '<li><strong>' + cfg.esc( p ) + ':</strong> ' + cfg.esc( result.tried[ p ] ) + '</li>';
					} );
					triedHtml += '</ul>';
				}
				box.innerHTML = '<p>Engine chain test failed.</p>' + triedHtml + '<p>' + cfg.esc( result.message || '' ) + '</p>';
				toast( result.message || cfg.i18n.failed, true );
				return;
			}
			box.innerHTML = '<p><strong>' + cfg.esc( result.provider ) + '</strong> answered.</p><p>' + cfg.esc( result.text || '' ) + '</p>';
			toast( 'Text engine reachable via ' + result.provider + '.' );
		} );
	};

	actions[ 'test-image' ] = function ( button ) {
		busy( button, true );
		var box = document.getElementById( 'vmsai-image-test' );

		// Generation can take the better part of a minute; the video test says
		// so and this one left the operator staring at a dead button.
		if ( box ) {
			box.innerHTML = '<p>Generating a test image — this can take up to a minute.</p>';
		}

		api( '/engine/test', 'POST', { engine: 'image', credentials: currentCredentials() } ).then( function ( result ) {
			busy( button, false );
			if ( ! box ) return;
			if ( ! result.ok ) {
				// Provider errors relay text straight from third-party APIs, so
				// none of this can go into innerHTML unescaped.
				var triedHtml = '';
				if ( result.tried ) {
					triedHtml = '<ul style="margin:10px 0; font-size:11px; opacity:0.8;">';
					Object.keys( result.tried ).forEach( function( p ) {
						triedHtml += '<li><strong>' + cfg.esc( p ) + ':</strong> ' + cfg.esc( result.tried[ p ] ) + '</li>';
					} );
					triedHtml += '</ul>';
				}
				box.innerHTML = '<p>No provider produced an image. ' + cfg.esc( result.message || '' ) + '</p>' + triedHtml;
				toast( result.message || cfg.i18n.failed, true );
				return;
			}
			box.innerHTML = '<p>Rendered by <strong>' + cfg.esc( result.provider ) + '</strong> and saved to the media library.</p>' +
				'<img src="' + cfg.esc( result.url ) + '" alt="" style="max-width:320px; border-radius:8px;">';
			toast( 'Image engine working.' );
		} ).catch( function ( err ) {
			busy( button, false );
			toast( err.message || cfg.i18n.failed, true );
		} );
	};

	actions[ 'test-video' ] = function ( button ) {
		busy( button, true );
		var box = document.getElementById( 'vmsai-video-test' );
		if ( box ) box.innerHTML = '<p>Starting video generation... This may take up to 2 minutes.</p>';

		api( '/engine/test-video', 'POST', { credentials: currentCredentials() } ).then( function ( result ) {
			busy( button, false );
			if ( ! box ) return;
			if ( ! result.ok ) {
				box.innerHTML = '<p>Video engine test failed. ' + cfg.esc( result.message || '' ) + '</p>';
				toast( result.message || cfg.i18n.failed, true );
				return;
			}
			box.innerHTML = '<p>Video generated successfully.</p>' +
				'<video controls style="max-width:320px; border-radius:8px;"><source src="' + cfg.esc( result.url ) + '" type="video/mp4"></video>';
			toast( 'Video engine working.' );
		} ).catch( function ( err ) {
			busy( button, false );
			toast( err.message || cfg.i18n.failed, true );
		} );
	};

	actions[ 'test-provider' ] = function ( button ) {
		var engine = button.dataset.engine;
		var provider = button.dataset.provider;
		var $item = $( button ).closest( '.vmsai-chain__item' );
		var $result = $item.find( '.vmsai-chain__result' );

		busy( button, true );
		$result.text( '' ).removeClass( 'is-ok is-bad' );

		api( '/engine/test-provider', 'POST', { engine: engine, provider: provider, credentials: currentCredentials() } ).then( function ( res ) {
			busy( button, false );
			$result.addClass( res.ok ? 'is-ok' : 'is-bad' );
			if ( res.ok ) {
				if ( 'image' === engine ) $result.text( '✓ Rendered and saved.' );
				else if ( 'video' === engine ) $result.html( '✓ Video generated: <a href="' + cfg.esc( res.url || '#' ) + '" target="_blank">View</a>' );
				else $result.text( '✓ ' + ( res.text || 'Reachable.' ) );
			} else {
				$result.text( '✗ ' + ( res.message || cfg.i18n.failed ) );
			}
		} ).catch( function ( err ) {
			busy( button, false );
			$result.addClass( 'is-bad' ).text( '✗ ' + ( err.message || cfg.i18n.failed ) );
		} );
	};

	actions[ 'test-channel' ] = function ( button ) {
		var slug = button.dataset.slug;
		var $card = $( button ).closest( '.vmsai-panel' );
		var creds = {};

		$card.find( 'input, textarea' ).each( function () {
			var val = $( this ).val();
			if ( this.name && val && ! val.match( /^•+$/ ) ) creds[ this.name ] = val;
		} );

		busy( button, true );
		api( '/channel/test', 'POST', { slug: slug, credentials: creds } ).then( function ( result ) {
			busy( button, false );
			toast( result.message || ( result.ok ? 'Connection successful.' : cfg.i18n.failed ), ! result.ok );
		} ).catch( function ( err ) {
			busy( button, false );
			toast( err.message || cfg.i18n.failed, true );
		} );
	};

	actions[ 'sync-models' ] = function ( button ) {
		busy( button, true );
		var creds = {};
		$( '.vmsai-chain__body input, .vmsai-chain__body textarea' ).each( function () {
			var val = $( this ).val();
			if ( this.name && val && ! val.match( /^•+$/ ) ) creds[ this.name ] = val;
		} );
		api( '/engine/sync', 'POST', { credentials: creds } ).then( function ( result ) {
			busy( button, false );
			toast( result.synced + ' models catalogued. Reloading.' );
			window.setTimeout( function () { window.location.reload(); }, 900 );
		} ).catch( function ( err ) {
			busy( button, false );
			toast( err.message || cfg.i18n.failed, true );
		} );
	};

	actions[ 'test-all' ] = function ( button ) {
		busy( button, true );
		var summary = document.getElementById( 'vmsai-testall-summary' );
		if ( summary ) summary.textContent = 'Testing everything...';

		api( '/system/test-all', 'POST', {} ).then( function ( res ) {
			busy( button, false );
			if ( summary ) summary.textContent = res.passed + ' of ' + res.total + ' passed.';
			toast( res.passed + ' checks passed.' );
		} );
	};

	actions[ 'refresh-rag' ] = function ( button ) {
		busy( button, true );
		api( '/rag/sync', 'POST', {} ).then( function ( result ) {
			if ( result.ok ) toast( 'Context refreshed.' );
			window.location.reload();
		} ).finally( function() { busy( button, false ); } );
	};

	actions[ 'clear-cache' ] = function ( button ) {
		busy( button, true );
		api( '/cache/clear', 'POST', {} ).then( function ( result ) {
			if ( result.ok ) toast( 'Cache wiped.' );
			window.location.reload();
		} ).finally( function() { busy( button, false ); } );
	};

	actions[ 'offload-existing' ] = function ( button ) {
		if ( ! confirm( 'Copy all existing queue images to Cloudflare R2?' ) ) return;
		busy( button, true );
		api( '/storage/offload', 'POST', {} ).then( function ( result ) {
			if ( result.ok ) toast( result.count + ' images offloaded.' );
		} ).finally( function() { busy( button, false ); } );
	};

	/* ---------- Campaigns ---------- */

	actions[ 'create-campaign' ] = function ( button ) {
		var channels = [];
		$( '.vmsai-campaign-channel:checked' ).each( function() { channels.push( this.value ); } );
		var input = {
			name: $( '#vmsai-campaign-name' ).val(),
			target_views: parseInt( $( '#vmsai-target' ).val(), 10 ),
			horizon_days: parseInt( $( '#vmsai-horizon' ).val(), 10 ),
			channels: channels,
			language: $( '#vmsai-language' ).val(),
			locale_flavour: $( '#vmsai-flavour' ).val()
		};
		if ( ! channels.length ) { toast( 'Pick a channel.', true ); return; }
		busy( button, true );
		api( '/campaign/create', 'POST', input ).then( function ( result ) {
			busy( button, false );
			if ( result.ok ) {
				toast( 'Planned. Opening calendar.' );
				window.location.href = cfg.page + '&tab=plan';
			}
		} );
	};

	actions[ 'open-campaign-planner' ] = function ( button ) {
		$( '#vmsai-planner-modal' ).slideToggle();
	};

	actions[ 'draft-campaign' ] = function ( button ) {
		var name = $( '#vmsai-cg-name' ).val();
		var idea = $( '#vmsai-cg-details' ).val();
		if ( ! name ) { toast( 'Enter a campaign name first.', true ); return; }

		busy( button, true );
		api( '/campaign-gen/draft', 'POST', { name: name, idea: idea } ).then( function ( result ) {
			busy( button, false );
			if ( result.ok ) {
				$( '#vmsai-cg-details' ).val( result.details );
				$( '#vmsai-cg-cta' ).val( result.cta );
				$( '#vmsai-cg-count' ).val( result.post_count );
				// Update end date based on duration
				var start = new Date( $( '#vmsai-cg-start' ).val() );
				start.setDate( start.getDate() + result.duration_days );
				$( '#vmsai-cg-end' ).val( start.toISOString().split('T')[0] );
				toast( 'Brief drafted by AI.' );
			}
		} );
	};

	actions[ 'generate-campaign' ] = function ( button ) {
		var channels = [];
		$( '.vmsai-cg-channel:checked' ).each( function() { channels.push( this.value ); } );

		var input = {
			name: $( '#vmsai-cg-name' ).val(),
			details: $( '#vmsai-cg-details' ).val(),
			start_date: $( '#vmsai-cg-start' ).val(),
			end_date: $( '#vmsai-cg-end' ).val(),
			count: parseInt( $( '#vmsai-cg-count' ).val(), 10 ),
			channels: channels,
			cta: $( '#vmsai-cg-cta' ).val(),
			language: $( '#vmsai-cg-language' ).val(),
			flavour: $( '#vmsai-cg-flavour' ).val()
		};

		if ( ! channels.length ) { toast( 'Pick at least one channel.', true ); return; }

		busy( button, true );
		$( '#vmsai-cg-form' ).slideUp();
		$( '#vmsai-cg-progress' ).show();
		$( '#vmsai-cg-progress-text' ).text( 'Planning mission arc...' );

		api( '/campaign-gen/create', 'POST', input ).then( function ( result ) {
			if ( ! result.ok ) {
				toast( result.error || 'Planning failed.', true );
				busy( button, false );
				$( '#vmsai-cg-form' ).slideDown();
				return;
			}

			var slots = result.slots || [];
			var $grid = $( '#vmsai-cg-grid' ).empty();

			slots.forEach( function( s ) {
				$grid.append( '<div class="vmsai-cg-card is-waiting" id="cg-slot-' + s.id + '">' +
					'<div class="vmsai-cg-card__media"><span>WAITTING</span></div>' +
					'<div class="vmsai-cg-card__body">' +
						'<div class="vmsai-cg-card__angle">' + s.angle + '</div>' +
						'<div class="vmsai-cg-card__meta">' + s.channel.toUpperCase() + ' • ' + s.date + '</div>' +
						'<div class="vmsai-cg-card__title">Synchronizing...</div>' +
					'</div></div>' );
			} );

			// Chain composition
			var chain = Promise.resolve();
			slots.forEach( function( s, i ) {
				chain = chain.then( function() {
					$( '#vmsai-cg-progress-text' ).text( 'Composing post ' + (i+1) + ' of ' + slots.length + '...' );
					var $card = $( '#cg-slot-' + s.id ).removeClass( 'is-waiting' ).addClass( 'is-busy' );

					return api( '/campaign-gen/compose', 'POST', { slot_id: s.id } ).then( function( post ) {
						$card.removeClass( 'is-busy' );
						if ( post.ok ) {
							$card.find( '.vmsai-cg-card__media' ).html( '<img src="' + post.media_url + '">' );
							$card.find( '.vmsai-cg-card__title' ).text( post.title );
							$card.append( '<div class="vmsai-cg-card__snippet">' + post.body + '</div>' );
						} else {
							$card.addClass( 'is-failed' ).append( '<div class="vmsai-cg-card__error">' + (post.error || 'Failed') + '</div>' );
						}
					} );
				} );
			} );

			chain.then( function() {
				$( '#vmsai-cg-progress-text' ).text( 'Mission arc complete.' );
				$( '#vmsai-cg-done' ).fadeIn();
				busy( button, false );
			} );
		} );
	};

	actions[ 'extend-plan' ] = function ( button ) {
		busy( button, true );
		api( '/plan/generate', 'POST', { days: 14 } ).then( function ( result ) {
			if ( result.ok ) window.location.reload();
		} );
	};

	actions[ 'reset-plan' ] = function ( button ) {
		busy( button, true );
		api( '/plan/reset', 'POST', {} ).then( function ( result ) {
			toast( result.count + ' slots reset.' );
			window.location.reload();
		} );
	};

	/* ---------- Calendar (Simplified) ---------- */

	var calDate = new Date();
	calDate.setDate(1);

	function renderCalendar() {
		var $mount = $('#vmsai-calendar-mount');
		if (!$mount.length) return;
		var month = calDate.getMonth();
		var year = calDate.getFullYear();
		$('#vmsai-cal-month-label').text(calDate.toLocaleString('default', { month: 'long', year: 'numeric' }));
		$mount.find('.vmsai-cal-day').remove();

		var startDay = new Date(year, month, 1).getDay();
		startDay = startDay === 0 ? 6 : startDay - 1;
		var daysInMonth = new Date(year, month + 1, 0).getDate();

		for (var i = 0; i < startDay; i++) $mount.append('<div class="vmsai-cal-day is-outside"></div>');

		api( '/plan/calendar', 'GET', { from: year + '-' + (month+1) + '-01', to: year + '-' + (month+1) + '-' + daysInMonth } ).then( function(res) {
			for (var d = 1; d <= daysInMonth; d++) {
				var m_str = (month + 1) < 10 ? '0' + (month + 1) : (month + 1);
				var d_str = d < 10 ? '0' + d : d;
				var dateStr = year + '-' + m_str + '-' + d_str;
				var isToday = new Date().toISOString().slice(0,10) === dateStr;
				var $day = $('<div class="vmsai-cal-day '+(isToday?'is-today':'')+'" data-date="'+dateStr+'"><div class="vmsai-cal-num">'+d+'</div></div>');
				(res.rows || []).filter(function(r){ return r.slot_date === dateStr; }).forEach(function(s){
					$day.append('<div class="vmsai-cal-slot is-'+s.status+'" data-id="'+s.id+'">'+s.channel.toUpperCase()+': '+s.topic+'</div>');
				});
				$mount.append($day);
			}
		});
	}

	if ($('#vmsai-plan-grid').length) renderCalendar();

	$(document).on('click', '[data-vmsai-action="prev-month"]', function() { calDate.setMonth(calDate.getMonth()-1); renderCalendar(); });
	$(document).on('click', '[data-vmsai-action="next-month"]', function() { calDate.setMonth(calDate.getMonth()+1); renderCalendar(); });

	actions.tick = function ( button ) {
		busy( button, true );
		api( '/tick', 'POST', {} ).then( function ( result ) {
			busy( button, false );
			toast( 'Engine run complete.' );
		} );
	};

	/* ---------- GitHub Updates ---------- */

	actions[ 'check-github-update' ] = function ( button ) {
		busy( button, true );
		var $spinner = $( '#vmsai-update-spinner' );
		var $progress = $( '#vmsai-update-progress-text' );
		if ( $spinner.length ) {
			$progress.text( 'Checking GitHub repository…' );
			$spinner.css( 'display', 'inline-flex' );
		}

		api( '/system/check-update', 'POST', { force: true } ).then( function ( result ) {
			busy( button, false );
			if ( $spinner.length ) $spinner.hide();

			if ( ! result.ok ) {
				toast( result.message || 'Could not check updates from GitHub.', true );
				return;
			}

			if ( $( '#vmsai-installed-ver' ).length ) $( '#vmsai-installed-ver' ).text( result.current_version );
			if ( $( '#vmsai-latest-ver' ).length ) $( '#vmsai-latest-ver' ).text( result.latest_version );
			if ( $( '#vmsai-last-checked-time' ).length ) $( '#vmsai-last-checked-time' ).text( result.last_checked || 'Just now' );

			var $badge = $( '#vmsai-update-status-badge' );
			if ( result.has_update ) {
				if ( $badge.length ) {
					$badge.html( '<span class="vmsai-chip" style="background: rgba(201,162,39,0.2); color: var(--gold); border: 1px solid var(--gold); padding: 4px 10px; font-weight: 600;">⚡ New Version Available (v' + cfg.esc( result.latest_version ) + ')</span>' );
				}
				if ( result.release_notes ) {
					$( '#vmsai-release-notes-body' ).text( result.release_notes );
					$( '#vmsai-release-notes-panel' ).fadeIn();
				}
				toast( 'Update available: v' + result.latest_version + ' on GitHub!' );
			} else {
				if ( $badge.length ) {
					$badge.html( '<span class="vmsai-chip" style="background: rgba(111,168,138,0.2); color: var(--green); border: 1px solid var(--green); padding: 4px 10px; font-weight: 600;">✓ Up to date</span>' );
				}
				toast( 'VM Social AI Pro is up to date (v' + result.current_version + ').' );
			}
		} ).catch( function ( err ) {
			busy( button, false );
			if ( $spinner.length ) $spinner.hide();
			toast( err.message || 'GitHub check failed.', true );
		} );
	};

	actions[ 'run-github-update' ] = function ( button ) {
		if ( ! confirm( 'Install update directly from GitHub repository now?' ) ) {
			return;
		}

		busy( button, true );
		var $checkBtn = $( '#vmsai-btn-check-update' );
		if ( $checkBtn.length ) $checkBtn.prop( 'disabled', true );

		var $spinner = $( '#vmsai-update-spinner' );
		var $progress = $( '#vmsai-update-progress-text' );
		if ( $spinner.length ) {
			$progress.text( 'Downloading & installing update from GitHub…' );
			$spinner.css( 'display', 'inline-flex' );
		}

		api( '/system/github-update', 'POST', {} ).then( function ( result ) {
			if ( result.ok ) {
				if ( $progress.length ) $progress.text( 'Update installed! Reloading…' );
				toast( result.message || 'Updated successfully!' );
				setTimeout( function () {
					window.location.reload();
				}, 1500 );
			} else {
				busy( button, false );
				if ( $checkBtn.length ) $checkBtn.prop( 'disabled', false );
				if ( $spinner.length ) $spinner.hide();
				toast( result.message || 'Update failed.', true );
			}
		} ).catch( function ( err ) {
			busy( button, false );
			if ( $checkBtn.length ) $checkBtn.prop( 'disabled', false );
			if ( $spinner.length ) $spinner.hide();
			toast( err.message || 'Update failed.', true );
		} );
	};
} );
