/**
 * LezWatch.TV AI Agent Orchestrator
 */
document.addEventListener('DOMContentLoaded', function() {
    const trigger = document.getElementById('lwtv-chat-trigger');
    const box = document.getElementById('lwtv-chat-box');
    const close = document.getElementById('lwtv-close');
    const input = document.getElementById('lwtv-chat-input');
    const send = document.getElementById('lwtv-chat-send');
    const msgs = document.getElementById('lwtv-chat-msgs');

    if (!trigger || !box) return;

    // CONFIGURATION
    // ---------------------------------------------------------
    // Note: On your staging site, ensure these match your .htpasswd
    // In production, you can likely remove the authHeader line.
	let authHeader;
	if (false !== lwtv_settings.staging_creds) {
		authHeader = 'Basic ' + btoa(lwtv_settings.staging_creds);
	} else {
		authHeader = null;
	}
    const aiKey    = lwtv_settings.ai_key;
    const endpoint = lwtv_settings.endpoint;

    trigger.addEventListener('click', () => {
        box.classList.toggle('active');
        if (box.classList.contains('active')) input.focus();
    });

    close.addEventListener('click', (e) => {
        e.stopPropagation();
        box.classList.remove('active');
    });

    async function handleSend() {
        const val = input.value.trim();
        if (!val) return;

        appendMsg('user', val);
        input.value = '';

        const loader = appendMsg('ai', '<span class="lwtv-loading">Consulting the 12-core brain...</span>');

        try {
            const url = `${endpoint}?prompt=${encodeURIComponent(val)}`;
            const headers = {
                'X-LezWatch-AI-Key': aiKey
            };
            if (authHeader) headers['Authorization'] = authHeader;

            const response = await fetch(url, { headers });

            const data = await response.json();
            loader.remove();

            if (data.shows && data.shows.length > 0) {
                let html = "I found these for you:<br>";
                data.shows.forEach(s => {
                    let charLine = `${s.characters ?? 0} queer characters`;
                    if ( (s.dead ?? 0) > 0 ) {
                        charLine += ` (${s.dead} are dead)`;
                    } else if ( (s.characters ?? 0) > 0 ) {
                        charLine += ' (none are dead)';
                    }
                    const tropesLine = (s.tropes && s.tropes.length)
                        ? `Tropes: ${s.tropes.join(', ')}`
                        : '';
                    html += `<div class="lwtv-show-card">
                        <a href="${s.permalink}">${s.title}</a> (Score: ${s.score})<br>
                        ${s.excerpt}<br>
                        ${charLine}<br>
                        ${tropesLine ? tropesLine + '<br>' : ''}
                    </div>`;
                });
                appendMsg('ai', html);
            } else {
                appendMsg('ai', data.message || "I couldn't find any shows matching that. Try a different trope or score!");
            }
        } catch(e) {
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

    send.addEventListener('click', handleSend);
    input.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') handleSend();
    });
});
