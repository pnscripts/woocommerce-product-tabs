// Captures the WordPress.org listing screenshots (assets/screenshots/screenshot-N.png) from the local
// dev site built by bin/setup-wp.sh and served by bin/serve.sh. Local test credentials only.
//
//   SITE=http://127.0.0.1:8795 PRODUCT=10 FAQ_PRODUCT=12 GLOBAL_TAB=256 node bin/screenshots.mjs
import puppeteer from 'puppeteer-core';
import { execFileSync } from 'node:child_process';
import { mkdirSync } from 'node:fs';

const site = process.env.SITE || 'http://127.0.0.1:8795';
const product = process.env.PRODUCT || '10';
const faqProduct = process.env.FAQ_PRODUCT || '12';
const globalTab = process.env.GLOBAL_TAB || '';
const only = ( process.env.ONLY || '' ).split( ',' ).filter( Boolean );
const out = new URL( '../assets/screenshots/', import.meta.url ).pathname;
const wp = ( ...args ) => execFileSync( new URL( './wp.sh', import.meta.url ).pathname, args, { stdio: [ 'ignore', 'pipe', 'ignore' ] } ).toString().trim();
mkdirSync( out, { recursive: true } );

const browser = await puppeteer.launch( {
	executablePath: process.env.CHROME || '/usr/bin/google-chrome',
	headless: true,
	args: [ '--no-sandbox', '--hide-scrollbars', '--lang=en-US' ],
	defaultViewport: { width: 1280, height: 900, deviceScaleFactor: 1 },
} );
const page = await browser.newPage();
page.on( 'pageerror', ( e ) => console.error( 'pageerror:', e.message ) );
page.on( 'console', ( m ) => { if ( 'error' === m.type() ) console.error( 'console:', m.text() ); } );

async function login() {
	await page.goto( `${ site }/wp-login.php`, { waitUntil: 'networkidle2' } );
	await page.type( '#user_login', 'admin' );
	await page.type( '#user_pass', 'admin' );
	await Promise.all( [ page.waitForNavigation( { waitUntil: 'networkidle2' } ), page.click( '#wp-submit' ) ] );
}

async function shot( n, selector, pad = 16 ) {
	if ( only.length && ! only.includes( String( n ) ) ) {
		return;
	}
	const file = `${ out }screenshot-${ n }.png`;
	await page.addStyleTag( { content: '#wpadminbar{display:none!important} html.wp-toolbar{padding-top:0!important} html{margin-top:0!important}' } );
	if ( selector ) {
		const el = await page.$( selector );
		await el.evaluate( ( node, p ) => { node.style.outline = `${ p }px solid transparent`; node.scrollIntoView(); }, pad );
		await el.screenshot( { path: file } );
	} else {
		await page.screenshot( { path: file } );
	}
	console.log( 'saved', file );
}

const want = ( n ) => ! only.length || only.includes( String( n ) );

wp( 'theme', 'activate', 'storefront' );
await login();

// 1. Storefront (classic theme): product page with imported and global tabs.
if ( want( 1 ) ) {
	await page.goto( `${ site }/?p=${ product }`, { waitUntil: 'networkidle2' } );
	await page.evaluate( () => {
		document.querySelector( '#wpadminbar' )?.remove();
		document.querySelector( '.woocommerce-tabs' ).scrollIntoView();
		document.querySelector( '.woocommerce-tabs .materials_tab a, .woocommerce-tabs li:nth-child(3) a' )?.click();
	} );
	await shot( 1, '.woocommerce-tabs' );
}

// 2. Product data → Custom tabs panel.
if ( want( 2 ) ) {
	await page.goto( `${ site }/wp-admin/post.php?post=${ product }&action=edit`, { waitUntil: 'networkidle2' } );
	await page.evaluate( () => {
		document.querySelector( '.pnscripts_product_tabs_tab a' ).click();
		const row = document.querySelectorAll( '[data-pnscripts-pt-row]' )[ 1 ];
		row.querySelector( '[data-pnscripts-pt-toggle]' ).click();
		document.querySelector( '#woocommerce-product-data' ).scrollIntoView();
	} );
	await new Promise( ( r ) => setTimeout( r, 1500 ) );
	await shot( 2, '#woocommerce-product-data', 0 );
}

// 3. Global tab in the block editor with its settings box.
if ( want( 3 ) && globalTab ) {
	await page.goto( `${ site }/wp-admin/post.php?post=${ globalTab }&action=edit`, { waitUntil: 'networkidle2' } );
	await page.evaluate( () => {
		try {
			wp.data.dispatch( 'core/preferences' ).set( 'core/edit-post', 'welcomeGuide', false );
			wp.data.dispatch( 'core/preferences' ).set( 'core', 'welcomeGuide', false );
		} catch ( e ) {}
	} );
	await page.reload( { waitUntil: 'networkidle2' } );
	await new Promise( ( r ) => setTimeout( r, 2500 ) );
	await page.evaluate( () => {
		document.querySelector( '.components-modal__screen-overlay button[aria-label="Close"]' )?.click();
		const toggle = [ ...document.querySelectorAll( 'button' ) ].find( ( b ) => /Meta Boxes/.test( b.textContent ) && 'false' === b.getAttribute( 'aria-expanded' ) );
		toggle?.click();
	} );
	await new Promise( ( r ) => setTimeout( r, 1200 ) );
	await page.evaluate( () => {
		const pane = document.querySelector( '.edit-post-meta-boxes-main' );
		if ( pane ) {
			pane.style.height = '520px';
			pane.style.maxHeight = 'none';
		}
	} );
	await new Promise( ( r ) => setTimeout( r, 500 ) );
	await shot( 3 );
}

// 4. Tab settings (default tabs).
if ( want( 4 ) ) {
	await page.goto( `${ site }/wp-admin/edit.php?post_type=product&page=pnscripts-tabwise`, { waitUntil: 'networkidle2' } );
	await shot( 4, '#wpbody-content .wrap', 8 );
}

// 5. Import from YIKES after a dry run.
if ( want( 5 ) ) {
	const run = async ( mode ) => {
		await page.goto( `${ site }/wp-admin/edit.php?post_type=product&page=pnscripts-tabwise&tab=import`, { waitUntil: 'networkidle2' } );
		await page.evaluate( () => { window.confirm = () => true; } );
		await page.click( `[data-pnscripts-pt-run="${ mode }"]` );
		await page.waitForFunction( () => /finished|removed/i.test( document.querySelector( '[data-pnscripts-pt-status]' ).textContent ), { timeout: 180000 } );
	};
	// Undo the earlier import, show the dry run, then import again (exercises the whole UI flow).
	await run( 'undo' );
	await run( 'dry-run' );
	await shot( 5, '#wpbody-content .wrap', 8 );
	await run( 'import' );
	console.log( 'import status:', await page.$eval( '[data-pnscripts-pt-status]', ( el ) => el.textContent ) );
}

// 6. Block theme (Twenty Twenty-Five): accordion Product Details with a FAQ tab.
if ( want( 6 ) ) {
	wp( 'theme', 'activate', 'twentytwentyfive' );
	await page.goto( `${ site }/?p=${ faqProduct }`, { waitUntil: 'networkidle2' } );
	await page.evaluate( () => {
		document.querySelector( '#wpadminbar' )?.remove();
		document.documentElement.style.marginTop = '0';
		const items = [ ...document.querySelectorAll( '.wp-block-accordion-item' ) ];
		const faq = items.find( ( i ) => i.querySelector( '.pnscripts-product-tabs-faq' ) );
		faq?.querySelector( 'button' )?.click();
		faq?.querySelector( 'details' )?.setAttribute( 'open', '' );
		document.querySelector( '.wp-block-woocommerce-product-details' ).scrollIntoView();
	} );
	await new Promise( ( r ) => setTimeout( r, 800 ) );
	await shot( 6, '.wp-block-woocommerce-product-details' );
	wp( 'theme', 'activate', 'storefront' );
}

await browser.close();
