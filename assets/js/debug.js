/**
 * WC Generic Product Designer — browser console debug helper.
 */
( function ( window ) {
	'use strict';

	const levels = [ 'debug', 'info', 'warn', 'error' ];

	const debugApi = {
		enabled: false,

		/**
		 * @param {boolean} on Enable console output.
		 */
		setEnabled( on ) {
			this.enabled = !! on;
		},

		/**
		 * @param {string} level   Log level.
		 * @param {string} message Message.
		 * @param {...*}   rest    Extra data.
		 */
		log( level, message, ...rest ) {
			if ( ! this.enabled || ! levels.includes( level ) ) {
				return;
			}
			const prefix = '[WC GPD]';
			const fn = level === 'debug' ? 'log' : level;
			if ( rest.length ) {
				console[ fn ]( prefix, message, ...rest );
			} else {
				console[ fn ]( prefix, message );
			}
		},

		debug( message, ...rest ) {
			this.log( 'debug', message, ...rest );
		},

		info( message, ...rest ) {
			this.log( 'info', message, ...rest );
		},

		warn( message, ...rest ) {
			this.log( 'warn', message, ...rest );
		},

		error( message, ...rest ) {
			this.log( 'error', message, ...rest );
		},
	};

	window.wcGpdDebug = debugApi;
} )( window );
