const CONFIG = {
    apiUrl: 'http://localhost:8000/api.php',
    apiToken: null, // wczytywany z .env analogicznie jak w thunderbird_api
};

async function loadToken() {
    const res = await fetch(chrome.runtime.getURL('.env'));
    const text = await res.text();
    const match = text.match(/GMAIL_API_TOKEN=(.+)/);
    if (!match) throw new Error('Brak GMAIL_API_TOKEN w .env');
    CONFIG.apiToken = match[1].trim();
}

function getAuthToken() {
    return new Promise((resolve, reject) => {
        chrome.identity.getAuthToken({ interactive: true }, (token) => {
            if (chrome.runtime.lastError) reject(chrome.runtime.lastError);
            else resolve(token);
        });
    });
}

async function gmailFetch(path, token) {
    const res = await fetch(`https://www.googleapis.com/gmail/v1/users/me${path}`, {
        headers: { Authorization: `Bearer ${token}` },
    });
    return res.json();
}

async function postToBackend(payload) {
    await fetch(CONFIG.apiUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Gmail-Token': CONFIG.apiToken,
        },
        body: JSON.stringify(payload),
    });
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

chrome.action.onClicked.addListener(syncAll);
chrome.runtime.onStartup.addListener(syncAll);