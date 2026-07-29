import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import vm from 'node:vm';

const root = new URL('../', import.meta.url);
const read = (path) => readFile(new URL(path, root), 'utf8');

test('Settings tabs expose complete accessible relationships and search feedback', async () => {
	const [view, js] = await Promise.all([
		read('admin/views/settings-page.php'),
		read('assets/admin/js/siteintelix-settings.js'),
	]);
	assert.match(view, /aria-controls=/);
	assert.match(view, /role="tabpanel"/);
	assert.match(view, /aria-labelledby=/);
	assert.match(view, /data-siteintelix-settings-results[^>]*aria-live="polite"/);
	assert.match(js, /ArrowRight/);
	assert.match(js, /ArrowLeft/);
	assert.match(js, /Home/);
	assert.match(js, /End/);
	assert.match(js, /sessionStorage/);
	assert.match(js, /panel\.hidden/);
});

test('Plugin preserves WordPress notices and avoids native prompt/confirm dialogs', async () => {
	const [main, shared, email, fileManager] = await Promise.all([
		read('siteintelix.php'),
		read('assets/admin/js/siteintelix-admin.js'),
		read('assets/admin/js/siteintelix-email-log.js'),
		read('includes/modules/file-manager/assets/file-manager.js').catch(() => ''),
	]);
	assert.doesNotMatch(main, /remove_all_actions\(\s*['"](?:admin_notices|all_admin_notices|network_admin_notices|user_admin_notices)/);
	assert.doesNotMatch(shared, /window\.(?:prompt|confirm)\s*\(/);
	assert.doesNotMatch(email, /window\.(?:prompt|confirm)\s*\(/);
	assert.doesNotMatch(fileManager, /window\.(?:prompt|confirm)\s*\(/);
	assert.match(shared, /role/);
	assert.match(shared, /aria-live/);
});

function createCoreConfirmationFixture() {
	const domReadyListeners = [];
	const documentListeners = {};
	const navigations = [];
	const submissions = [];

	class FakeElement {
		constructor(tagName) {
			this.tagName = tagName.toUpperCase();
			this.attributes = {};
			this.children = [];
			this.listeners = {};
			this.parentNode = null;
			this.form = null;
			this.href = '';
			this.textContent = '';
		}

		addEventListener(type, listener) {
			this.listeners[type] = listener;
		}

		appendChild(child) {
			child.parentNode = this;
			this.children.push(child);
			return child;
		}

		closest(selector) {
			if ('[data-siteintelix-confirm]' === selector && this.attributes['data-siteintelix-confirm']) {
				return this;
			}
			return null;
		}

		focus() {
			document.activeElement = this;
		}

		getAttribute(name) {
			return this.attributes[name] ?? null;
		}

		matches(selector) {
			return 'a[href]' === selector && 'A' === this.tagName && Boolean(this.href);
		}

		remove() {
			if (this.parentNode) {
				this.parentNode.children = this.parentNode.children.filter((child) => child !== this);
			}
		}

		setAttribute(name, value) {
			this.attributes[name] = String(value);
		}
	}

	const body = new FakeElement('body');
	const document = {
		activeElement: null,
		body,
		addEventListener(type, listener) {
			if ('DOMContentLoaded' === type) {
				domReadyListeners.push(listener);
			} else {
				documentListeners[type] = listener;
			}
		},
		createElement(tagName) {
			return new FakeElement(tagName);
		},
		getElementById() {
			return null;
		},
		querySelector() {
			return null;
		},
		querySelectorAll() {
			return [];
		},
	};
	const window = {
		location: {
			assign(url) {
				navigations.push(url);
			},
		},
		setTimeout() {},
	};

	return {
		async load() {
			const script = await read('assets/admin/js/siteintelix-core.js');
			vm.runInNewContext(script, { document, requestAnimationFrame(callback) { callback(); }, window });
			domReadyListeners.forEach((listener) => listener());
		},
		action(tagName, message) {
			const element = new FakeElement(tagName);
			element.setAttribute('data-siteintelix-confirm', message);
			return element;
		},
		cancel() {
			const dialog = body.children.at(-1);
			dialog.children[0].children[2].listeners.click();
		},
		confirm() {
			const dialog = body.children.at(-1);
			dialog.children[0].children[3].listeners.click();
		},
		open(element) {
			documentListeners.click({
				target: element,
				preventDefault() {},
			});
		},
		form() {
			return {
				requestSubmit(submitter) {
					submissions.push(submitter);
				},
				submit() {
					submissions.push('fallback');
				},
			};
		},
		navigations,
		submissions,
	};
}

test('Shared confirmation submits the associated form for confirmed submit buttons', async () => {
	const fixture = createCoreConfirmationFixture();
	await fixture.load();
	const button = fixture.action('button', 'Apply selected items?');
	button.form = fixture.form();

	fixture.open(button);
	fixture.confirm();

	assert.deepEqual(fixture.submissions, [button]);
	assert.deepEqual(fixture.navigations, []);
});

test('Shared confirmation preserves link navigation and cancellation', async () => {
	const navigationFixture = createCoreConfirmationFixture();
	await navigationFixture.load();
	const link = navigationFixture.action('a', 'Delete this item?');
	link.href = '/wp-admin/admin-post.php?action=delete';

	navigationFixture.open(link);
	navigationFixture.confirm();
	assert.deepEqual(navigationFixture.navigations, [link.href]);

	const cancelFixture = createCoreConfirmationFixture();
	await cancelFixture.load();
	const button = cancelFixture.action('button', 'Apply selected items?');
	button.form = cancelFixture.form();

	cancelFixture.open(button);
	cancelFixture.cancel();
	assert.deepEqual(cancelFixture.submissions, []);
	assert.deepEqual(cancelFixture.navigations, []);
});

function createSettingsMediaPickerFixture() {
	const frames = [];

	function createButton() {
		let clickListener = null;
		return {
			addEventListener(type, listener) {
				if ('click' === type) { clickListener = listener; }
			},
			click() {
				clickListener();
			},
		};
	}

	function createPreview() {
		let text = '';
		return {
			children: [],
			appendChild(child) {
				this.children.push(child);
			},
			get textContent() {
				return text;
			},
			set textContent(value) {
				text = value;
				if ('' === value) { this.children = []; }
			},
		};
	}

	function createPicker(size, withUrl) {
		const select = createButton();
		const remove = createButton();
		const preview = createPreview();
		const id = { value: '' };
		const url = withUrl ? { value: '', addEventListener() {} } : undefined;
		const attributes = {
			'data-media-title': 'Choose Image',
			'data-media-button': 'Use this image',
			'data-media-size': size,
		};
		const children = {
			'[data-siteintelix-media-select]': select,
			'[data-siteintelix-media-remove]': remove,
			'[data-siteintelix-media-preview]': preview,
			'[data-siteintelix-media-id]': id,
			'[data-siteintelix-media-url]': url ?? null,
		};

		return {
			select,
			remove,
			preview,
			id,
			url,
			classList: { toggle() {} },
			getAttribute(name) {
				return attributes[name] ?? null;
			},
			querySelector(selector) {
				return children[selector] ?? null;
			},
		};
	}

	const logo = createPicker('medium', true);
	const artwork = createPicker('large', false);
	const documentObject = {
		createElement() {
			return { alt: '', src: '' };
		},
		querySelectorAll(selector) {
			return '[data-siteintelix-maintenance-media-picker]' === selector ? [logo, artwork] : [];
		},
	};
	const windowObject = {
		wp: {
			media(config) {
				let selected = null;
				let selectListener = null;
				const frame = {
					config,
					on(type, listener) {
						if ('select' === type) { selectListener = listener; }
					},
					open() {},
					state() {
						return {
							get() {
								return {
									first() {
										return { toJSON: () => selected };
									},
								};
							},
						};
					},
					choose(item) {
						selected = item;
						selectListener();
					},
				};
				frames.push(frame);
				return frame;
			},
		},
	};

	return {
		logo,
		artwork,
		frames,
		async load() {
			const script = await read('assets/admin/js/siteintelix-settings.js');
			const moduleObject = { exports: {} };
			vm.runInNewContext(script, { module: moduleObject });
			moduleObject.exports.initMaintenanceMediaPickers(documentObject, windowObject);
		},
	};
}

test('Maintenance logo and artwork use independent image-only media pickers', async () => {
	const fixture = createSettingsMediaPickerFixture();
	await fixture.load();

	fixture.logo.select.click();
	assert.equal(fixture.frames[0].config.multiple, false);
	assert.equal(fixture.frames[0].config.library.type, 'image');
	fixture.frames[0].choose({ id: 11, url: 'logo-full.png', sizes: { medium: { url: 'logo-medium.png' } } });
	assert.equal(fixture.logo.id.value, 11);
	assert.equal(fixture.logo.url.value, 'logo-medium.png');

	fixture.artwork.select.click();
	assert.equal(fixture.frames[1].config.multiple, false);
	assert.equal(fixture.frames[1].config.library.type, 'image');
	fixture.frames[1].choose({ id: 17, url: 'art-full.jpg', sizes: { large: { url: 'art-large.jpg' } } });
	assert.equal(fixture.artwork.id.value, 17);
	assert.equal(fixture.artwork.url, undefined);
	assert.equal(fixture.artwork.preview.children[0].src, 'art-large.jpg');

	fixture.artwork.remove.click();
	assert.equal(fixture.artwork.id.value, '');
	assert.equal(fixture.artwork.preview.children.length, 0);
	assert.equal(fixture.logo.id.value, 11, 'clearing artwork must not change the logo');
});

function createOverviewToggleFixture(responseFactory) {
	const domReadyListeners = [];
	const changeListeners = [];
	const requests = [];
	const toasts = [];
	let reloads = 0;

	const cardClasses = new Set();
	const card = {
		classList: {
			add(name) { cardClasses.add(name); },
			remove(name) { cardClasses.delete(name); },
			contains(name) { return cardClasses.has(name); },
		},
	};
	const toggle = {
		checked: false,
		disabled: false,
		value: 'cron_events',
		closest(selector) {
			if ('[data-siteintelix-module-toggle]' === selector) { return this; }
			if ('[data-siteintelix-module-card]' === selector) { return card; }
			return null;
		},
	};
	const dashboard = {
		addEventListener(type, listener) {
			if ('change' === type) { changeListeners.push(listener); }
		},
	};
	const document = {
		body: {
			appendChild() {},
			removeChild() {},
		},
		addEventListener(type, listener) {
			if ('DOMContentLoaded' === type) { domReadyListeners.push(listener); }
		},
		createElement() {
			return {
				style: {},
				appendChild() {},
				click() {},
				remove() {},
				select() {},
				setAttribute() {},
			};
		},
		execCommand() { return true; },
		getElementById(id) {
			if ('siteintelix-dashboard' === id) { return dashboard; }
			return null;
		},
	};
	const context = {
		Blob: class {},
		URL: {
			createObjectURL() { return 'blob:test'; },
			revokeObjectURL() {},
		},
		URLSearchParams,
		console,
		document,
		navigator: {},
		siteintelixOverviewData: {
			ajaxUrl: '/wp-admin/admin-ajax.php',
			moduleToggleNonce: 'overview-nonce',
			moduleUpdating: 'Updating module…',
			moduleUpdated: 'Module updated.',
			moduleUpdateFailed: 'Module update failed.',
		},
		window: {
			SiteIntelixAdmin: {
				toast(message, type) { toasts.push({ message, type }); },
			},
			fetch(url, options) {
				requests.push({ url, options });
				return Promise.resolve(responseFactory());
			},
			location: {
				reload() { reloads += 1; },
			},
			setTimeout(callback) { callback(); },
		},
	};
	context.fetch = context.window.fetch;

	return {
		async load(script) {
			vm.runInNewContext(script, context);
			domReadyListeners.forEach((listener) => listener());
			assert.equal(changeListeners.length, 1, 'Overview must register one delegated toggle handler');
			toggle.checked = true;
			changeListeners[0]({ target: toggle });
			await new Promise((resolve) => setImmediate(resolve));
		},
		card,
		requests,
		toasts,
		toggle,
		get reloads() { return reloads; },
	};
}

test('Overview quick module toggles persist and roll back safely', async () => {
	const script = await read('assets/admin/js/siteintelix-overview.js');
	const success = createOverviewToggleFixture(() => ({
		ok: true,
		json: async () => ({ success: true, data: { message: 'Saved.' } }),
	}));

	await success.load(script);

	assert.equal(success.requests.length, 1);
	assert.equal(success.requests[0].url, '/wp-admin/admin-ajax.php');
	const payload = new URLSearchParams(success.requests[0].options.body);
	assert.equal(payload.get('action'), 'siteintelix_toggle_module');
	assert.equal(payload.get('nonce'), 'overview-nonce');
	assert.equal(payload.get('module'), 'cron_events');
	assert.equal(payload.get('enabled'), '1');
	assert.equal(success.reloads, 1);
	assert.deepEqual(success.toasts.at(-1), { message: 'Saved.', type: undefined });

	const failure = createOverviewToggleFixture(() => ({
		ok: false,
		json: async () => ({ success: false, data: { message: 'Rejected.' } }),
	}));

	await failure.load(script);

	assert.equal(failure.toggle.checked, false);
	assert.equal(failure.toggle.disabled, false);
	assert.equal(failure.card.classList.contains('is-updating'), false);
	assert.equal(failure.reloads, 0);
	assert.deepEqual(failure.toasts.at(-1), { message: 'Rejected.', type: 'error' });
});
