/**
 * LezWatch.TV AI Discovery Engine
 *
 * Handles the Discovery chat UI: Happy Ending toggle, Mood Chips, show cards with
 * Ending Status badges, SearchWP bridge (failed query as context), auto-open on 404/no-results.
 */
document.addEventListener('DOMContentLoaded', function() {
	const trigger = document.getElementById('lwtv-chat-trigger');
	const box = document.getElementById('lwtv-chat-box');
	const close = document.getElementById('lwtv-close');
	const input = document.getElementById('lwtv-chat-input');
	const send = document.getElementById('lwtv-chat-send');
	const msgs = document.getElementById('lwtv-chat-msgs');
	const happyToggle = document.getElementById('lwtv-happy-ending-toggle');

	if (!box || !input || !send || !msgs) return;

	// CONFIGURATION
	let authHeader;
	if (typeof lwtv_settings !== 'undefined' && false !== lwtv_settings.staging_creds) {
		authHeader = 'Basic ' + btoa(lwtv_settings.staging_creds);
	} else {
		authHeader = null;
	}
	const aiKey = (typeof lwtv_settings !== 'undefined' && lwtv_settings.ai_key) ? lwtv_settings.ai_key : '';
	const endpoint = (typeof lwtv_settings !== 'undefined' && lwtv_settings.endpoint) ? lwtv_settings.endpoint : '';
	const moodChips = (typeof lwtv_settings !== 'undefined' && lwtv_settings.mood_chips) ? lwtv_settings.mood_chips : [];

	// Floating trigger (only on global pages)
	if (trigger) {
		trigger.addEventListener('click', () => {
			box.classList.toggle('active');
			if (box.classList.contains('active')) input.focus();
		});
	}

	if (close) {
		close.addEventListener('click', (e) => {
			e.stopPropagation();
			box.classList.remove('active');
		});
	}

	function getEffectivePrompt(val) {
		const happyOn = happyToggle && happyToggle.checked;
		if (happyOn && val && !val.toLowerCase().includes('happy-ending') && !val.toLowerCase().includes('happy ending')) {
			return val.trim() + ' trope:happy-ending';
		}
		return val ? val.trim() : '';
	}

	function buildShowCardHtml(s) {
		const dead = s.dead ?? 0;
		const chars = s.characters ?? 0;
		const endingBadge = dead === 0
			? '<span class="lwtv-ending-badge lwtv-ending-happy">Happy Ending</span>'
			: '<span class="lwtv-ending-badge lwtv-ending-tragic">Tragic</span>';
		let charLine = `${chars} queer characters`;
		if (dead > 0) {
			charLine += ` (${dead} are dead)`;
		} else if (chars > 0) {
			charLine += ' (none are dead)';
		}
		const tropesLine = (s.tropes && s.tropes.length)
			? `Tropes: ${s.tropes.join(', ')}`
			: '';
		return `<div class="lwtv-show-card">
			<a href="${s.permalink}">${s.title}</a> (Score: ${s.score}) ${endingBadge}<br>
			${s.excerpt || ''}<br>
			${charLine}<br>
			${tropesLine ? tropesLine + '<br>' : ''}
		</div>`;
	}

	async function handleSend() {
		let val = input.value.trim();
		val = getEffectivePrompt(val);
		if (!val) return;

		appendMsg('user', input.value.trim());
		input.value = '';

		const loader = appendMsg('ai', '<span class="lwtv-loading">Consulting the 12-core brain...</span>');

		const failedQuery = input.dataset?.failedQuery || '';
		let url = `${endpoint}?prompt=${encodeURIComponent(val)}`;
		if (failedQuery) {
			url += '&context=' + encodeURIComponent('The user tried to find ' + failedQuery + ' and failed. Help them find the closest match.');
		}

		const headers = { 'X-LezWatch-AI-Key': aiKey };
		if (authHeader) headers['Authorization'] = authHeader;

		try {
			const response = await fetch(url, { headers });
			const data = await response.json();
			loader.remove();

			if (data.shows && data.shows.length > 0) {
				let html = "I found these for you:<br>";
				data.shows.forEach(s => {
					html += buildShowCardHtml(s);
				});
				appendMsg('ai', html);
			} else {
				appendMsg('ai', data.message || "I don't have a record of a show like that yet in our database.");
			}
		} catch (e) {
			if (loader) loader.innerHTML = "Sorry, I lost my connection to the server.";
			console.error('AI Agent Error:', e);
		}
	}

	function appendMsg(who, html) {
		const div = document.createElement('div');
		div.className = `lwtv-msg lwtv-msg-${who}`;
		div.innerHTML = html;
		msgs.appendChild(div);
		msgs.scrollTop = msgs.scrollHeight;
		return div;
	}

	// Mood chips
	const chipsContainer = box.querySelector('.lwtv-mood-chips');
	if (chipsContainer) {
		chipsContainer.querySelectorAll('.lwtv-mood-chip').forEach(btn => {
			btn.addEventListener('click', () => {
				const prompt = btn.dataset.prompt || btn.textContent;
				input.value = prompt;
				handleSend();
			});
		});
	}

	send.addEventListener('click', handleSend);
	input.addEventListener('keypress', (e) => {
		if (e.key === 'Enter') handleSend();
	});

	// Auto-open and auto-send on 404 / no-results
	const initialPrompt = input.dataset?.initialPrompt || '';
	const hasFailedQuery = input.dataset?.failedQuery || '';
	if (box.classList.contains('lwtv-discovery-404') || box.classList.contains('lwtv-discovery-no-results')) {
		box.classList.add('active');
		if (initialPrompt || hasFailedQuery) {
			input.value = initialPrompt || hasFailedQuery;
			handleSend();
		}
	}
});
