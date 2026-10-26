const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const source = fs.readFileSync(path.resolve(__dirname, '../../../themes/portfolio/assets/scripts/portfolio.js'), 'utf8');
const context = { FormData: class { set() {} }, Math, crypto: require('node:crypto').webcrypto };
vm.createContext(context);
vm.runInContext(source, context);

(async () => {
	const component = context.portfolioContact();
	component.init();
	const firstId = component.requestId;
	let resets = 0;
	const urls = [];
	// An input named action shadows HTMLFormElement.action in a real browser.
	const form = {
		action: { toString: () => '[object HTMLInputElement]' },
		getAttribute: (name) => name === 'action' ? 'https://portfolio.test/wp-admin/admin-ajax.php' : null,
		reportValidity: () => true,
		reset: () => { resets++; },
	};
	context.fetch = async (url) => { urls.push(url); throw new Error('Offline'); };
	await component.submit({ target: form });
	assert.equal(component.failed, true);
	assert.equal(resets, 0);
	assert.equal(component.requestId, firstId);
	assert.equal(component.busy, false);
	context.fetch = async (url) => { urls.push(url); return { json: async () => ({ success: false, data: { message: 'Storage failure' } }) }; };
	await component.submit({ target: form });
	assert.equal(resets, 0);
	assert.equal(component.requestId, firstId);
	assert.equal(component.message, 'Storage failure');
	context.fetch = async (url) => { urls.push(url); return { json: async () => ({ success: true, data: { message: 'Saved' } }) }; };
	await component.submit({ target: form });
	assert.equal(component.failed, false);
	assert.equal(component.message, 'Saved');
	assert.equal(resets, 1);
	assert.deepEqual(urls, Array(3).fill('https://portfolio.test/wp-admin/admin-ajax.php'));
	assert.notEqual(component.requestId, firstId);
	component.busy = true;
	context.fetch = async () => { throw new Error('Should not submit while busy'); };
	await component.submit({ target: form });
	assert.equal(resets, 1);
	assert.equal(fs.readFileSync(path.resolve(__dirname, '../../../themes/portfolio/src/scripts/portfolio.js'), 'utf8'), source);
	console.log('PASS: success resets form and request ID; errors preserve input; busy blocks repeated submit; source matches asset.');
})().catch((error) => { console.error(error); process.exit(1); });
