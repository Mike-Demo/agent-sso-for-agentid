(function () {
	'use strict';

	function copyFromTarget(button) {
		var target = document.getElementById(button.getAttribute('data-copy-target'));
		if (!target) {
			return;
		}
		var text = target.textContent.trim();
		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(text);
		}
		button.textContent = 'Copied';
		setTimeout(function () {
			button.textContent = 'Copy';
		}, 2000);
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
					if (data && data.success && data.data && data.data.message) {
						result.textContent = data.data.message;
					} else if (data && data.data && data.data.message) {
						result.textContent = data.data.message;
					} else {
						result.textContent = 'Test failed.';
					}
				})
				.catch(function () {
					result.textContent = 'Test failed.';
				});
		});
	}
})();
