// Browser smoke test of the admin flows on the local dev site (bin/setup-wp.sh + bin/serve.sh):
// creates a global tab in the block editor, adds a rich-text and a FAQ tab on a product through the
// product data panel, saves settings, and checks the storefront. Local test credentials only.
//
//   SITE=http://127.0.0.1:8795 node bin/e2e-smoke.mjs
import puppeteer from 'puppeteer-core';
import { execFileSync } from 'node:child_process';
import assert from 'node:assert/strict';

const site = process.env.SITE || 'http://127.0.0.1:8795';
const wp = ( ...args ) => execFileSync( new URL( './wp.sh', import.meta.url ).pathname, args, { stdio: [ 'ignore', 'pipe', 'ignore' ] } ).toString().trim();
const stamp = Date.now().toString( 36 );
const errors = [];

const browser = await puppeteer.launch( {
	executablePath: process.env.CHROME || '/usr/bin/google-chrome',
	headless: true,
	args: [ '--no-sandbox' ],
	defaultViewport: { width: 1400, height: 1000 },
} );
const page = await browser.newPage();
page.on( 'pageerror', ( e ) => errors.push( e.message ) );
page.on( 'dialog', ( d ) => d.accept() );

async function step( name, fn ) {
	process.stdout.write( `- ${ name } … ` );
	await fn();
	console.log( 'ok' );
}

await step( 'log in', async () => {
	await page.goto( `${ site }/wp-login.php`, { waitUntil: 'networkidle2' } );
	await page.type( '#user_login', 'admin' );
	await page.type( '#user_pass', 'admin' );
	await Promise.all( [ page.waitForNavigation( { waitUntil: 'networkidle2' } ), page.click( '#wp-submit' ) ] );
} );

const title = `E2E global ${ stamp }`;
await step( 'create a global tab in the block editor (all products, priority 21)', async () => {
	await page.goto( `${ site }/wp-admin/post-new.php?post_type=pnscripts_ptab`, { waitUntil: 'networkidle2' } );
	await page.waitForFunction( () => window.wp && wp.data && wp.data.select( 'core/editor' ) );
	await page.evaluate( ( t ) => {
		try {
			wp.data.dispatch( 'core/preferences' ).set( 'core/edit-post', 'welcomeGuide', false );
		} catch ( e ) {}
		wp.data.dispatch( 'core/editor' ).editPost( { title: t } );
		wp.data.dispatch( 'core/block-editor' ).insertBlocks( wp.blocks.createBlock( 'core/paragraph', { content: 'Made in the block editor.' } ) );
		document.querySelector( 'input[name="pnscripts_product_tabs_global[scope]"][value="all"]' ).checked = true;
		document.querySelector( '#pnscripts-pt-priority' ).value = '21';
	}, title );
	await page.evaluate( () => wp.data.dispatch( 'core/editor' ).editPost( { status: 'publish' } ) );
	await page.evaluate( () => wp.data.dispatch( 'core/editor' ).savePost() );
	await page.waitForFunction( () => ! wp.data.select( 'core/editor' ).isSavingPost() && ! wp.data.select( 'core/edit-post' ).isSavingMetaBoxes() && wp.data.select( 'core/editor' ).getCurrentPostId() && 'publish' === wp.data.select( 'core/editor' ).getCurrentPostAttribute( 'status' ), { timeout: 30000 } );
	await new Promise( ( r ) => setTimeout( r, 2000 ) );
	const id = await page.evaluate( () => wp.data.select( 'core/editor' ).getCurrentPostId() );
	assert.equal( wp( 'post', 'meta', 'get', String( id ), '_pnscripts_product_tabs_scope' ), 'all' );
	assert.equal( wp( 'post', 'meta', 'get', String( id ), '_pnscripts_product_tabs_priority' ), '21' );
} );

const productId = wp( 'wc', 'product', 'create', `--name=E2E product ${ stamp }`, '--regular_price=5', '--description=E2E description.', '--user=admin', '--porcelain' );
await step( `add a rich-text tab and a FAQ tab to product #${ productId }`, async () => {
	await page.goto( `${ site }/wp-admin/post.php?post=${ productId }&action=edit`, { waitUntil: 'networkidle2' } );
	await page.click( '.pnscripts_product_tabs_tab a' );
	await page.click( '[data-pnscripts-pt-add="content"]' );
	await page.waitForFunction( () => window.tinymce && document.querySelectorAll( '[data-pnscripts-pt-row]' ).length === 1 && tinymce.editors.some( ( e ) => e.id.startsWith( 'pnscripts-pt-content-' ) ), { timeout: 90000 } );
	await page.type( '[data-pnscripts-pt-row] [data-pnscripts-pt-title-input]', 'E2E materials' );
	await page.evaluate( () => tinymce.editors.find( ( e ) => e.id.startsWith( 'pnscripts-pt-content-' ) ).setContent( '<p>Typed <strong>in TinyMCE</strong>.</p>' ) );
	await page.click( '[data-pnscripts-pt-add="faq"]' );
	const faqRow = '[data-pnscripts-pt-row][data-type="faq"]';
	await page.type( `${ faqRow } [data-pnscripts-pt-title-input]`, 'E2E FAQ' );
	await page.type( `${ faqRow } input[name$="[q]"]`, 'Is it tested?' );
	await page.type( `${ faqRow } textarea[name$="[a]"]`, 'Yes, end to end.' );
	await page.click( `${ faqRow } [data-pnscripts-pt-faq-add]` );
	const inputs = await page.$$( `${ faqRow } input[name$="[q]"]` );
	await inputs[ 1 ].type( 'Second question?' );
	const answers = await page.$$( `${ faqRow } textarea[name$="[a]"]` );
	await answers[ 1 ].type( 'Second answer.' );
	// Move the FAQ tab above the rich-text tab (what a drag does).
	await page.evaluate( ( sel ) => {
		const list = document.querySelector( '[data-pnscripts-pt-list]' );
		list.prepend( document.querySelector( sel ) );
	}, faqRow );
	await Promise.all( [ page.waitForNavigation( { waitUntil: 'networkidle2' } ), page.click( '#publish' ) ] );
	const meta = JSON.parse( wp( 'post', 'meta', 'get', productId, '_pnscripts_product_tabs', '--format=json' ) );
	assert.deepEqual( meta.map( ( t ) => t.title ), [ 'E2E FAQ', 'E2E materials' ] );
	assert.equal( meta[ 0 ].faq.length, 2 );
	assert.match( meta[ 1 ].content, /<strong>in TinyMCE<\/strong>/ );
} );

await step( 'hide Reviews on that product and switch the FAQ tab off, then on again', async () => {
	await page.goto( `${ site }/wp-admin/post.php?post=${ productId }&action=edit`, { waitUntil: 'networkidle2' } );
	await page.click( '.pnscripts_product_tabs_tab a' );
	await page.click( 'input[name="pnscripts_product_tabs[hidden_defaults][]"][value="reviews"]' );
	await page.click( '[data-pnscripts-pt-row][data-type="faq"] [data-pnscripts-pt-enabled]' );
	await Promise.all( [ page.waitForNavigation( { waitUntil: 'networkidle2' } ), page.click( '#publish' ) ] );
	const meta = JSON.parse( wp( 'post', 'meta', 'get', productId, '_pnscripts_product_tabs', '--format=json' ) );
	assert.equal( meta[ 0 ].enabled, false );
	assert.equal( wp( 'post', 'meta', 'get', productId, '_pnscripts_product_tabs_hidden_defaults', '--format=json' ), '["reviews"]' );
	await page.click( '.pnscripts_product_tabs_tab a' );
	await page.click( '[data-pnscripts-pt-row][data-type="faq"] [data-pnscripts-pt-enabled]' );
	await Promise.all( [ page.waitForNavigation( { waitUntil: 'networkidle2' } ), page.click( '#publish' ) ] );
} );

await step( 'link a manual global tab from the product panel', async () => {
	const manual = wp( 'post', 'create', '--post_type=pnscripts_ptab', '--post_status=publish', `--post_title=E2E manual ${ stamp }`, '--post_content=Linked by hand.', '--porcelain' );
	wp( 'post', 'meta', 'update', manual, '_pnscripts_product_tabs_scope', 'manual' );
	wp( 'option', 'delete', 'pnscripts_product_tabs_index' );
	await page.goto( `${ site }/wp-admin/post.php?post=${ productId }&action=edit`, { waitUntil: 'networkidle2' } );
	await page.click( '.pnscripts_product_tabs_tab a' );
	await page.select( '[data-pnscripts-pt-global-select]', manual );
	await page.click( '[data-pnscripts-pt-add="global"]' );
	await Promise.all( [ page.waitForNavigation( { waitUntil: 'networkidle2' } ), page.click( '#publish' ) ] );
	const meta = JSON.parse( wp( 'post', 'meta', 'get', productId, '_pnscripts_product_tabs', '--format=json' ) );
	assert.equal( meta[ 2 ].type, 'global' );
	assert.equal( String( meta[ 2 ].global_id ), manual );
	const html = await ( await fetch( `${ site }/?p=${ productId }` ) ).text();
	assert.match( html, /Linked by hand\./ );
	wp( 'post', 'delete', manual, '--force' );
} );

await step( 'storefront shows the tabs in order with FAQ schema, no Reviews', async () => {
	const html = await ( await fetch( `${ site }/?p=${ productId }` ) ).text();
	const titles = [ ...html.matchAll( /<li[^>]*role="presentation"[^>]*>\s*<a[^>]*>([^<]+)</g ) ].map( ( m ) => m[ 1 ].trim() );
	assert.deepEqual( titles, [ 'Description', title, 'E2E FAQ', 'E2E materials' ] );
	assert.match( html, /"@type":"FAQPage"/ );
	assert.match( html, /Second question\?/ );
} );

await step( 'settings page saves (rename Description)', async () => {
	await page.goto( `${ site }/wp-admin/edit.php?post_type=product&page=pnscripts-tabcrest`, { waitUntil: 'networkidle2' } );
	await page.$eval( 'input[name="pnscripts_product_tabs_settings[defaults][description][title]"]', ( el ) => { el.value = 'Overview'; } );
	await Promise.all( [ page.waitForNavigation( { waitUntil: 'networkidle2' } ), page.click( '#submit' ) ] );
	const html = await ( await fetch( `${ site }/?p=${ productId }` ) ).text();
	assert.match( html, />\s*Overview\s*</ );
	await page.goto( `${ site }/wp-admin/edit.php?post_type=product&page=pnscripts-tabcrest`, { waitUntil: 'networkidle2' } );
	await page.$eval( 'input[name="pnscripts_product_tabs_settings[defaults][description][title]"]', ( el ) => { el.value = ''; } );
	await Promise.all( [ page.waitForNavigation( { waitUntil: 'networkidle2' } ), page.click( '#submit' ) ] );
} );

await browser.close();
if ( errors.length ) {
	console.error( 'JavaScript errors:', errors );
	process.exit( 1 );
}
console.log( 'All admin flows passed.' );
