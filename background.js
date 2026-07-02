const CONFIG = {
    apiUrl: 'http://localhost:8000/api.php',
    maxMessages: 10
};

let cachedApiToken = null;

async function getApiToken() {
    if (cachedApiToken) {
        return cachedApiToken;
    }

    const response = await fetch(browser.runtime.getURL('.env'));
    if (!response.ok) {
        throw new Error('.env file not found. Copy .env.example to .env and set THUNDERBIRD_API_TOKEN.');
    }

    const envContent = await response.text();
    const match = envContent.match(/^THUNDERBIRD_API_TOKEN=(.+)$/m);
    const token = match?.[1]?.trim();

    if (!token) {
        throw new Error('THUNDERBIRD_API_TOKEN is not set in .env.');
    }

    cachedApiToken = token;
    return cachedApiToken;
}

async function postToApi(payload) {
    const apiToken = await getApiToken();
    const response = await fetch(CONFIG.apiUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Thunderbird-Token': apiToken
        },
        body: JSON.stringify(payload)
    });

    if (!response.ok) {
        throw new Error(`Server responded with error code: ${response.status}`);
    }

    return response.json();
}

function formatDate(value) {
    const d = value instanceof Date ? value : new Date(value);
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`;
}

function flattenFolders(folders) {
    const all = [];
    for (const folder of folders ?? []) {
        all.push(folder);
        if (folder.subFolders?.length) {
            all.push(...flattenFolders(folder.subFolders));
        }
    }
    return all;
}

async function collectRecentMessages(limit) {
    const accountSummaries = await browser.accounts.list();
    const collected = [];

    for (const summary of accountSummaries) {
        const account = await browser.accounts.get(summary.id, true);
        const folders = flattenFolders(account.rootFolder?.subFolders);

        for (const folder of folders) {
            try {
                const page = await browser.messages.list(folder.id);
                for (const header of page.messages) {
                    collected.push({
                        id: `${account.id}_${header.id}`,
                        subject: header.subject || 'No subject',
                        author: header.author || 'Unknown',
                        date: formatDate(header.date),
                        body_preview: ''
                    });
                }
            } catch (folderError) {
                console.warn(`[Integration] Failed to read folder ${folder.path}:`, folderError.message);
            }
        }
    }

    collected.sort((a, b) => b.date.localeCompare(a.date));
    return collected.slice(0, limit);
}

async function synchronizeAccountsWithExternalApp() {
    console.log("[Integration] Starting account synchronization...");

    try {
        const accounts = await browser.accounts.list();

        const payload = {
            system_source: "Thunderbird Client",
            exported_at: new Date().toISOString(),
            accounts: accounts.map(acc => ({
                tb_account_id: acc.id,
                account_name: acc.name,
                type: acc.type,
                identities: acc.identities.map(id => ({
                    name: id.name,
                    email: id.email,
                    organization: id.organization
                }))
            }))
        };

        const result = await postToApi(payload);
        console.log("[Integration] Success! Accounts synchronized:", result);
    } catch (error) {
        console.error("[Integration] Critical error during account synchronization:", error.message);
    }
}

async function synchronizeMessagesWithExternalApp() {
    console.log("[Integration] Starting message synchronization...");

    try {
        const messages = await collectRecentMessages(CONFIG.maxMessages);

        const payload = {
            system_source: "Thunderbird Client",
            exported_at: new Date().toISOString(),
            messages
        };

        const result = await postToApi(payload);
        console.log("[Integration] Success! Messages synchronized:", result);
    } catch (error) {
        console.error("[Integration] Critical error during message synchronization:", error.message);
    }
}

async function synchronizeAll() {
    await synchronizeAccountsWithExternalApp();
    await synchronizeMessagesWithExternalApp();
}

browser.runtime.onStartup.addListener(() => {
    console.log("Thunderbird started – triggering auto-sync.");
    synchronizeAll();
});

browser.accounts.onCreated.addListener((account) => {
    console.log(`New account detected: ${account.name}. Starting synchronization...`);
    synchronizeAccountsWithExternalApp();
});

browser.messages.onNewMailReceived.addListener((folder, messages) => {
    console.log(`New mail detected in folder ${folder.path}. Starting message synchronization...`);
    synchronizeMessagesWithExternalApp();
});

browser.action.onClicked.addListener(() => {
    console.log("Manual synchronization request from user.");
    synchronizeAll();
});
