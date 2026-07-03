const api = typeof browser !== 'undefined' ? browser : chrome;
const IS_FIREFOX = typeof browser !== 'undefined' && typeof browser.runtime.getBrowserInfo === 'function';

const CONFIG = {
    apiUrl: 'http://localhost:8000/api.php',
    apiToken: null,
};

async function loadToken() {
    const res = await fetch(api.runtime.getURL('.env'));
    const text = await res.text();
    const match = text.match(/GMAIL_API_TOKEN=(.+)/);
    if (!match) throw new Error('Brak GMAIL_API_TOKEN w .env');
    CONFIG.apiToken = match[1].trim();
}

function getAuthTokenChrome() {
    return new Promise((resolve, reject) => {
        chrome.identity.getAuthToken({ interactive: true }, (token) => {
            if (chrome.runtime.lastError) reject(chrome.runtime.lastError);
            else resolve(token);
        });
    });
}

async function getAuthTokenFirefox() {
    const stored = await browser.storage.local.get(['accessToken', 'expiresAt']);
    if (stored.accessToken && stored.expiresAt > Date.now()) {
        return stored.accessToken;
    }

    const clientId = api.runtime.getManifest().oauth2.client_id;
    const redirectUri = browser.identity.getRedirectURL();
    const authUrl = new URL('https://accounts.google.com/o/oauth2/v2/auth');
    authUrl.searchParams.set('client_id', clientId);
    authUrl.searchParams.set('response_type', 'token');
    authUrl.searchParams.set('redirect_uri', redirectUri);
    authUrl.searchParams.set('scope', 'https://www.googleapis.com/auth/gmail.readonly');

    const responseUrl = await browser.identity.launchWebAuthFlow({
        url: authUrl.href,
        interactive: true,
    });

    const params = new URLSearchParams(new URL(responseUrl).hash.substring(1));
    const accessToken = params.get('access_token');
    const expiresIn = Number(params.get('expires_in'));

    await browser.storage.local.set({
        accessToken,
        expiresAt: Date.now() + expiresIn * 1000,
    });

    return accessToken;
}

async function getAuthToken() {
    return IS_FIREFOX ? getAuthTokenFirefox() : getAuthTokenChrome();
}

async function gmailFetch(path, token) {
    const res = await fetch(`https://www.googleapis.com/gmail/v1/users/me${path}`, {
        headers: { Authorization: `Bearer ${token}` },
    });
    if (!res.ok) throw new Error(`Gmail API ${path} -> ${res.status}`);
    return res.json();
}

async function postToBackend(payload) {
    const res = await fetch(CONFIG.apiUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Gmail-Token': CONFIG.apiToken,
        },
        body: JSON.stringify(payload),
    });
    if (!res.ok) throw new Error(`Backend ${CONFIG.apiUrl} -> ${res.status}`);
}

async function syncAll() {
    if (!CONFIG.apiToken) await loadToken();
    const authToken = await getAuthToken();

    const profile = await gmailFetch('/profile', authToken);
    await postToBackend({
        user: {
            email: profile.emailAddress,
            messages_total: profile.messagesTotal,
            threads_total: profile.threadsTotal,
        },
    });

    const list = await gmailFetch('/messages?maxResults=10', authToken);
    const messages = [];

    for (const ref of list.messages ?? []) {
        const msg = await gmailFetch(
            `/messages/${ref.id}?format=metadata&metadataHeaders=Subject&metadataHeaders=From&metadataHeaders=Date`,
            authToken
        );
        const header = (name) => msg.payload.headers.find((h) => h.name === name)?.value ?? '';

        messages.push({
            id: msg.id,
            subject: header('Subject'),
            sender: header('From'),
            snippet: msg.snippet,
            date: header('Date'),
        });
    }

    await postToBackend({ messages, userEmail: profile.emailAddress });
}

api.action.onClicked.addListener(() => {
    syncAll().catch((err) => console.error('Gmail sync failed:', err));
});
api.runtime.onStartup.addListener(() => {
    syncAll().catch((err) => console.error('Gmail sync failed:', err));
});
