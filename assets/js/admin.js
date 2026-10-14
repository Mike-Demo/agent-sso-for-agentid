(function () {
	'use strict';

	var strings = window.agentidSsoAdmin || {};

	function showCopyResult(button, ok) {
		button.textContent = ok ? strings.copied || 'Copied' : strings.copyFailed || 'Copy failed';
		setTimeout(function () {
			button.textContent = strings.copy || 'Copy';
		}, 2500);
	}

	function fallbackCopy(text) {
		var area = document.createElement('textarea');
		area.value = text;
		area.setAttribute('readonly', '');
		area.style.position = 'absolute';
		area.style.left = '-9999px';
		document.body.appendChild(area);
		area.select();
		var ok = false;
		try {
			ok = document.execCommand('copy');
		} catch (e) {
			ok = false;
		}
		document.body.removeChild(area);
		return ok;
	}

	function copyFromTarget(button) {
		var target = document.getElementById(button.getAttribute('data-copy-target'));
		if (!target) {
			return;
		}
		var text = target.textContent.trim();
		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(text).then(
				function () {
					showCopyResult(button, true);
				},
				function () {
					showCopyResult(button, fallbackCopy(text));
				}
			);
		} else {
			showCopyResult(button, fallbackCopy(text));
		}
	}

	document.querySelectorAll('[data-copy-target]').forEach(function (button) {
		button.addEventListener('click', function () {
			copyFromTarget(button);
		});
	});

	var testButton = document.getElementById('agentid-test-connection');
	if (testButton && window.agentidSsoAdmin) {
		testButton.addEventListener('click', function () {
			var result = document.getElementById('agentid-test-result');
			result.textContent = window.agentidSsoAdmin.testing;
			var body = new URLSearchParams();
			body.append('action', 'agentid_test_connection');
			body.append('_ajax_nonce', window.agentidSsoAdmin.nonce);
			fetch(window.agentidSsoAdmin.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: body.toString()
			})
				.then(function (response) {
					return response.json();
				})
				.then(function (data) {
					if (data && data.data && data.data.message) {
						result.textContent = data.data.message;
					} else {
						result.textContent = strings.testFailed || 'Test failed.';
					}
				})
				.catch(function () {
					result.textContent = strings.testFailed || 'Test failed.';
				});
		});
	}
})();
