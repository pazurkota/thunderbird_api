const CONFIG = {
    apiUrl: 'http://localhost:8000/api.php',
    apiToken: 'pazurkota_super_secret_api_key_2026',
    maxMessages: 10
};

async function postToApi(payload) {
    const response = await fetch(CONFIG.apiUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Thunderbird-Token': CONFIG.apiToken
        },
        body: JSON.stringify(payload)
    });

    if (!response.ok) {
        throw new Error(`Serwer odpowiedział kodem błędu: ${response.status}`);
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
                console.warn(`[Integration] Nie udało się odczytać folderu ${folder.path}:`, folderError.message);
            }
        }
    }

    collected.sort((a, b) => b.date.localeCompare(a.date));
    return collected.slice(0, limit);
}

async function synchronizeAccountsWithExternalApp() {
    console.log("[Integration] Rozpoczynanie synchronizacji kont...");

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
        console.log("[Integration] Sukces! Konta zsynchronizowane:", result);
    } catch (error) {
        console.error("[Integration] Błąd krytyczny podczas synchronizacji kont:", error.message);
    }
}

async function synchronizeMessagesWithExternalApp() {
    console.log("[Integration] Rozpoczynanie synchronizacji wiadomości...");

    try {
        const messages = await collectRecentMessages(CONFIG.maxMessages);

        const payload = {
            system_source: "Thunderbird Client",
            exported_at: new Date().toISOString(),
            messages
        };

        const result = await postToApi(payload);
        console.log("[Integration] Sukces! Wiadomości zsynchronizowane:", result);
    } catch (error) {
        console.error("[Integration] Błąd krytyczny podczas synchronizacji wiadomości:", error.message);
    }
}

async function synchronizeAll() {
    await synchronizeAccountsWithExternalApp();
    await synchronizeMessagesWithExternalApp();
}

browser.runtime.onStartup.addListener(() => {
    console.log("Uruchomiono Thunderbirda – wywoływanie auto-sync.");
    synchronizeAll();
});

browser.accounts.onCreated.addListener((account) => {
    console.log(`Wykryto nowe konto: ${account.name}. Uruchamianie synchronizacji...`);
    synchronizeAccountsWithExternalApp();
});

browser.messages.onNewMailReceived.addListener((folder, messages) => {
    console.log(`Wykryto nową pocztę w folderze ${folder.path}. Uruchamianie synchronizacji wiadomości...`);
    synchronizeMessagesWithExternalApp();
});

browser.action.onClicked.addListener(() => {
    console.log("Ręczne żądanie synchronizacji użytkownika.");
    synchronizeAll();
});
