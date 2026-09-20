const chatContainer = document.getElementById("chatContainer");
const chatForm = document.getElementById("chatForm");
const messageInput = document.getElementById("messageInput");
const sendBtn = document.getElementById("sendBtn");
const welcomeTime = document.getElementById("welcomeTime");
const newChatBtn = document.getElementById("newChatBtn");
const historyContainer = document.getElementById("historyContainer");
const chatTitleInput = document.getElementById("chatTitleInput");
const saveChatTitleBtn = document.getElementById("saveChatTitleBtn");
const authBtn = document.getElementById("authBtn");
const logoutBtn = document.getElementById("logoutBtn");
const userBadge = document.getElementById("userBadge");
const userMetaCard = document.getElementById("userMetaCard");
const loginForm = document.getElementById("loginForm");
const registerForm = document.getElementById("registerForm");
const authModalElement = document.getElementById("authModal");
const authModal = new bootstrap.Modal(authModalElement);
const authSuccessAlert = document.getElementById("authSuccessAlert");
const authSuccessTitle = document.getElementById("authSuccessTitle");
const authSuccessLineEn = document.getElementById("authSuccessLineEn");
const authSuccessLineI18n = document.getElementById("authSuccessLineI18n");

const AUTH_SUCCESS_COPY = {
    login: {
        title: "Login successful!",
        lineEn: "Welcome back — you're signed in.",
        lineI18n: "පැමිණීම සාර්ථකයි · உள்நுழைவு வெற்றிகரமாக முடிந்தது"
    },
    register: {
        title: "Registration successful!",
        lineEn: "You're signed in — you can start chatting.",
        lineI18n: "ලියාපදිංචිය සාර්ථකයි · பதிவு வெற்றிகரமாக முடிந்தது"
    }
};

/** @param {"login"|"register"} kind */
function showAuthSuccessMessage(kind) {
    const copy = AUTH_SUCCESS_COPY[kind] || AUTH_SUCCESS_COPY.register;
    if (authSuccessTitle) {
        authSuccessTitle.textContent = copy.title;
    }
    if (authSuccessLineEn) {
        authSuccessLineEn.textContent = copy.lineEn;
    }
    if (authSuccessLineI18n) {
        authSuccessLineI18n.textContent = copy.lineI18n;
    }
    if (!authSuccessAlert) {
        return;
    }
    authSuccessAlert.classList.remove("d-none");
    authSuccessAlert.scrollIntoView({ block: "nearest", behavior: "smooth" });
}

function hideAuthSuccessMessage() {
    if (!authSuccessAlert) {
        return;
    }
    authSuccessAlert.classList.add("d-none");
}

authModalElement.addEventListener("show.bs.modal", () => {
    hideAuthSuccessMessage();
});
const profileBtn = document.getElementById("profileBtn");
const profileForm = document.getElementById("profileForm");
const profileModalElement = document.getElementById("profileModal");
const profileModal = new bootstrap.Modal(profileModalElement);
const loginPasswordInput = document.getElementById("loginPassword");
const toggleLoginPasswordBtn = document.getElementById("toggleLoginPassword");
const welcomeScreen = document.getElementById("welcomeScreen");
const chatApp = document.getElementById("chatApp");
const welcomeLoginBtn = document.getElementById("welcomeLoginBtn");
const welcomeLoginBtnSecondary = document.getElementById("welcomeLoginBtnSecondary");
const navDestinationsBtn = document.getElementById("navDestinationsBtn");
const navPackagesBtn = document.getElementById("navPackagesBtn");
const browsePlansBtn = document.getElementById("browsePlansBtn");
const themeToggleBtn = document.getElementById("themeToggleBtn");
const themeToggleMiniBtn = document.getElementById("themeToggleMiniBtn");
const emojiToggleBtn = document.getElementById("emojiToggleBtn");
const emojiPanel = document.getElementById("emojiPanel");

let typingRow = null;
let currentSessionKey = "";
let isAuthenticated = false;
let currentTheme = "dark";

function applyTheme(theme) {
    currentTheme = theme === "light" ? "light" : "dark";
    document.body.classList.toggle("light-mode", currentTheme === "light");
    localStorage.setItem("travel_chat_theme", currentTheme);
    const label = currentTheme === "light" ? "🌙" : "☀";
    const ariaLabel = currentTheme === "light" ? "Switch to dark mode" : "Switch to light mode";
    if (themeToggleBtn) {
        themeToggleBtn.textContent = label;
        themeToggleBtn.setAttribute("aria-label", ariaLabel);
    }
    if (themeToggleMiniBtn) {
        themeToggleMiniBtn.textContent = label;
        themeToggleMiniBtn.setAttribute("aria-label", ariaLabel);
    }
}

function formatTime(dateObj = new Date()) {
    return dateObj.toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" });
}

function escapeHtml(text) {
    const div = document.createElement("div");
    div.textContent = text;
    return div.innerHTML;
}

function scrollToBottom() {
    chatContainer.scrollTop = chatContainer.scrollHeight;
}

function appendMessage(sender, message, timeText = formatTime()) {
    const row = document.createElement("div");
    row.className = `message-row ${sender}`;

    const safeMessage = escapeHtml(message).replace(/\n/g, "<br>");
    row.innerHTML = `
        <div class="message-bubble">
            ${safeMessage}
            <span class="message-time">${timeText}</span>
        </div>
    `;

    chatContainer.appendChild(row);
    scrollToBottom();
}

function insertEmojiAtCursor(emoji) {
    const start = messageInput.selectionStart ?? messageInput.value.length;
    const end = messageInput.selectionEnd ?? messageInput.value.length;
    const before = messageInput.value.slice(0, start);
    const after = messageInput.value.slice(end);
    messageInput.value = `${before}${emoji}${after}`;
    const newPos = start + emoji.length;
    messageInput.setSelectionRange(newPos, newPos);
    messageInput.focus();
}

function showTypingIndicator() {
    removeTypingIndicator();
    typingRow = document.createElement("div");
    typingRow.className = "message-row bot";
    typingRow.id = "typingRow";
    typingRow.innerHTML = `
        <div class="message-bubble typing-indicator">
            Bot is typing...
        </div>
    `;
    chatContainer.appendChild(typingRow);
    scrollToBottom();
}

function removeTypingIndicator() {
    if (typingRow && typingRow.parentNode) {
        typingRow.parentNode.removeChild(typingRow);
    }
    typingRow = null;
}

function clearChat() {
    chatContainer.innerHTML = `
        <div class="message-row bot">
            <div class="message-bubble">
                Hi there! I am your travel assistant 😊 Ask me about destinations, hotels, itineraries, or budgets.
                <span class="message-time">${formatTime()}</span>
            </div>
        </div>
    `;
    scrollToBottom();
}

async function postJson(url, payload) {
    const response = await fetch(url, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload)
    });
    const text = await response.text();
    let data = {};
    try {
        data = JSON.parse(text);
    } catch (err) {
        throw new Error("Invalid server response.");
    }
    if (!response.ok || !data.success) {
        throw new Error(data.error || "Request failed.");
    }
    return data;
}

function setAuthUI(user) {
    isAuthenticated = !!user;
    if (user) {
        userBadge.textContent = user.name;
        userMetaCard.classList.remove("d-none");
        profileBtn.classList.remove("d-none");
        logoutBtn.classList.remove("d-none");
        authBtn.classList.add("d-none");
        messageInput.disabled = false;
        sendBtn.disabled = false;
        welcomeScreen.classList.add("d-none");
        chatApp.classList.remove("d-none");
    } else {
        userMetaCard.classList.add("d-none");
        profileBtn.classList.add("d-none");
        logoutBtn.classList.add("d-none");
        authBtn.classList.remove("d-none");
        messageInput.disabled = true;
        sendBtn.disabled = true;
        chatApp.classList.add("d-none");
        welcomeScreen.classList.remove("d-none");
    }
}

function syncChatTitleInputFromSessions(sessions) {
    if (!chatTitleInput) {
        return;
    }
    if (!currentSessionKey) {
        chatTitleInput.value = "";
        return;
    }
    const row = sessions.find((s) => s.session_key === currentSessionKey);
    chatTitleInput.value = row ? row.session_title || "" : "";
}

function renderHistory(sessions) {
    const list = sessions || [];
    historyContainer.innerHTML = "";
    if (!list.length) {
        historyContainer.innerHTML = `<div class="text-muted small">No chats yet 😴</div>`;
        syncChatTitleInputFromSessions(list);
        return;
    }

    list.forEach((session) => {
        const item = document.createElement("div");
        item.className = "history-item";
        if (session.session_key === currentSessionKey) {
            item.classList.add("active");
        }
        item.innerHTML = `
            <div class="history-item-top">
                <strong>${escapeHtml(session.session_title || "New Chat")}</strong>
                <button class="history-delete-btn" type="button" title="Delete chat">🗑️</button>
            </div>
            <span class="history-time">${escapeHtml(session.updated_at || "")}</span>
        `;
        const deleteBtn = item.querySelector(".history-delete-btn");
        deleteBtn.addEventListener("click", async (event) => {
            event.stopPropagation();
            const ok = confirm("Delete this chat? 🗑️");
            if (!ok) {
                return;
            }
            try {
                await postJson("api/chat/sessions.php", { action: "delete", session_key: session.session_key });
                if (currentSessionKey === session.session_key) {
                    currentSessionKey = "";
                    clearChat();
                }
                await loadHistory();
            } catch (error) {
                appendMessage("bot", error.message);
            }
        });
        item.addEventListener("click", async () => {
            try {
                const data = await postJson("api/chat/sessions.php", { action: "load", session_key: session.session_key });
                currentSessionKey = session.session_key;
                chatContainer.innerHTML = "";
                if (!data.messages.length) {
                    clearChat();
                } else {
                    data.messages.forEach((msg) => {
                        appendMessage(msg.sender === "user" ? "user" : "bot", msg.message, formatTime(new Date(msg.created_at)));
                    });
                }
                await loadHistory();
            } catch (error) {
                appendMessage("bot", error.message);
            }
        });
        historyContainer.appendChild(item);
    });
    syncChatTitleInputFromSessions(list);
}

async function loadHistory() {
    if (!isAuthenticated) {
        renderHistory([]);
        return;
    }
    try {
        const data = await postJson("api/chat/sessions.php", { action: "list" });
        renderHistory(data.sessions || []);
    } catch (error) {
        historyContainer.innerHTML = `<div class="text-danger small">${escapeHtml(error.message)}</div>`;
    }
}

async function ensureSession() {
    if (currentSessionKey) {
        return;
    }
    const data = await postJson("api/chat/sessions.php", { action: "new", title: "New Chat" });
    currentSessionKey = data.session.session_key;
    await loadHistory();
}

async function sendMessage(message) {
    if (!isAuthenticated) {
        appendMessage("bot", "Please login/register first 🔐");
        return;
    }

    showTypingIndicator();
    sendBtn.disabled = true;
    messageInput.disabled = true;

    try {
        await ensureSession();
        const data = await postJson("api/chat/message.php", { message });
        removeTypingIndicator();
        appendMessage("bot", data.reply, data.timestamp || formatTime());
        await loadHistory();
    } catch (error) {
        removeTypingIndicator();
        appendMessage("bot", `Server issue: ${error.message || "unable to reach chatbot backend."}`);
    } finally {
        sendBtn.disabled = false;
        messageInput.disabled = false;
        messageInput.focus();
    }
}

function triggerQuickPrompt(promptText) {
    if (!isAuthenticated) {
        authModal.show();
        return;
    }
    const prompt = (promptText || "").trim();
    if (!prompt) return;
    appendMessage("user", prompt);
    sendMessage(prompt);
}

if (toggleLoginPasswordBtn && loginPasswordInput) {
    toggleLoginPasswordBtn.addEventListener("click", () => {
        const isPasswordHidden = loginPasswordInput.type === "password";
        loginPasswordInput.type = isPasswordHidden ? "text" : "password";
        toggleLoginPasswordBtn.textContent = isPasswordHidden ? "🙈" : "👁️";
        toggleLoginPasswordBtn.setAttribute("aria-label", isPasswordHidden ? "Hide password" : "Show password");
    });
}

newChatBtn.addEventListener("click", async () => {
    if (!isAuthenticated) {
        appendMessage("bot", "Please login/register first 🔐");
        return;
    }
    try {
        const data = await postJson("api/chat/sessions.php", { action: "new", title: "New Chat" });
        currentSessionKey = data.session.session_key;
        clearChat();
        await loadHistory();
    } catch (error) {
        appendMessage("bot", error.message);
    }
});

authBtn.addEventListener("click", () => authModal.show());
welcomeLoginBtn.addEventListener("click", () => authModal.show());
if (welcomeLoginBtnSecondary) {
    welcomeLoginBtnSecondary.addEventListener("click", () => authModal.show());
}
if (navDestinationsBtn) {
    navDestinationsBtn.addEventListener("click", () => {
        triggerQuickPrompt("Suggest top travel destinations in Sri Lanka for 3 days with budget options.");
    });
}
if (navPackagesBtn) {
    navPackagesBtn.addEventListener("click", () => {
        triggerQuickPrompt("Show me 3 travel packages (budget, standard, premium) with itinerary ideas.");
    });
}
if (browsePlansBtn) {
    browsePlansBtn.addEventListener("click", () => {
        triggerQuickPrompt("Create a smart travel plan based on destination, days, and budget.");
    });
}
if (themeToggleBtn) {
    themeToggleBtn.addEventListener("click", () => applyTheme(currentTheme === "dark" ? "light" : "dark"));
}
if (themeToggleMiniBtn) {
    themeToggleMiniBtn.addEventListener("click", () => applyTheme(currentTheme === "dark" ? "light" : "dark"));
}
if (emojiToggleBtn && emojiPanel) {
    if (window.EmojiButton) {
        const picker = new window.EmojiButton({
            position: "top-start",
            theme: currentTheme === "light" ? "light" : "dark",
            autoHide: true,
            showSearch: true,
            showRecents: true
        });

        picker.on("emoji", (selection) => {
            insertEmojiAtCursor(selection.emoji || "");
        });

        emojiToggleBtn.addEventListener("click", () => {
            picker.togglePicker(emojiToggleBtn);
        });
    } else {
        emojiToggleBtn.addEventListener("click", () => {
            emojiPanel.classList.toggle("d-none");
        });

        emojiPanel.querySelectorAll(".emoji-btn").forEach((btn) => {
            btn.addEventListener("click", () => {
                insertEmojiAtCursor(btn.textContent || "");
            });
        });

        document.addEventListener("click", (event) => {
            const target = event.target;
            if (!(target instanceof HTMLElement)) {
                return;
            }
            if (!emojiPanel.classList.contains("d-none") && !emojiPanel.contains(target) && target !== emojiToggleBtn) {
                emojiPanel.classList.add("d-none");
            }
        });
    }
}
async function saveChatTitleFromSidebar() {
    if (!isAuthenticated) {
        appendMessage("bot", "Please login first to save chat names.");
        return;
    }
    try {
        await ensureSession();
        const title = (chatTitleInput && chatTitleInput.value.trim()) || "";
        if (!title) {
            appendMessage("bot", "Type a name in “Save chat name”, then press Save.");
            return;
        }
        await postJson("api/chat/sessions.php", {
            action: "rename",
            session_key: currentSessionKey,
            title
        });
        await loadHistory();
        appendMessage("bot", `Chat saved as “${title}”.`);
    } catch (error) {
        appendMessage("bot", error.message);
    }
}

if (saveChatTitleBtn) {
    saveChatTitleBtn.addEventListener("click", () => {
        saveChatTitleFromSidebar();
    });
}

if (chatTitleInput) {
    chatTitleInput.addEventListener("keydown", (event) => {
        if (event.key === "Enter") {
            event.preventDefault();
            saveChatTitleFromSidebar();
        }
    });
}

profileBtn.addEventListener("click", async () => {
    try {
        const data = await postJson("api/auth/index.php", { action: "profile_get" });
        document.getElementById("profileName").value = data.user.full_name || "";
        document.getElementById("profileEmail").value = data.user.email || "";
        document.getElementById("profilePassword").value = "";
        profileModal.show();
    } catch (error) {
        alert(error.message);
    }
});

const logoutFeedback = document.getElementById("logoutFeedback");
const logoutFeedbackTitle = document.getElementById("logoutFeedbackTitle");
const logoutFeedbackI18n = document.getElementById("logoutFeedbackI18n");
let logoutFeedbackTimerId = 0;

function showLogoutFeedbackMessage() {
    if (logoutFeedbackTimerId) {
        window.clearTimeout(logoutFeedbackTimerId);
        logoutFeedbackTimerId = 0;
    }
    if (logoutFeedbackTitle) {
        logoutFeedbackTitle.textContent = "Logged out successfully.";
    }
    if (logoutFeedbackI18n) {
        logoutFeedbackI18n.textContent =
            "පිටවීම සාර්ථකයි · வெற்றிகரமாக வெளியேறியுள்ளீர்கள்";
    }
    if (logoutFeedback) {
        logoutFeedback.classList.remove("d-none");
        logoutFeedback.scrollIntoView({ block: "nearest", behavior: "smooth" });
    }
    logoutFeedbackTimerId = window.setTimeout(() => {
        if (logoutFeedback) {
            logoutFeedback.classList.add("d-none");
        }
        logoutFeedbackTimerId = 0;
    }, 4500);
}

logoutBtn.addEventListener("click", async () => {
    const originalHtml = logoutBtn.innerHTML;
    logoutBtn.disabled = true;
    logoutBtn.innerHTML = '<span class="logout-btn-loading">Logging out…</span>';
    try {
        await postJson("api/auth/index.php", { action: "logout" });
        currentSessionKey = "";
        setAuthUI(null);
        clearChat();
        await loadHistory();
        showLogoutFeedbackMessage();
    } catch (error) {
        appendMessage("bot", error.message);
    } finally {
        logoutBtn.disabled = false;
        logoutBtn.innerHTML = originalHtml;
    }
});

loginForm.addEventListener("submit", async (event) => {
    event.preventDefault();
    const loginEmailValue = document.getElementById("loginEmail").value.trim();
    try {
        const data = await postJson("api/auth/index.php", {
            action: "login",
            email: loginEmailValue,
            password: document.getElementById("loginPassword").value
        });
        setAuthUI(data.user);
        currentSessionKey = "";
        clearChat();
        await ensureSession();
        await loadHistory();
        showAuthSuccessMessage("login");
        window.setTimeout(() => {
            authModal.hide();
            hideAuthSuccessMessage();
        }, 2200);
    } catch (error) {
        if ((error.message || "").includes("Account not found")) {
            const registerTabTrigger = document.getElementById("register-tab");
            if (registerTabTrigger) {
                bootstrap.Tab.getOrCreateInstance(registerTabTrigger).show();
            }
            const registerEmailInput = document.getElementById("registerEmail");
            if (registerEmailInput) {
                registerEmailInput.value = loginEmailValue;
            }
            const registerNameInput = document.getElementById("registerName");
            if (registerNameInput) {
                registerNameInput.focus();
            }
        }
        alert(error.message);
    }
});

registerForm.addEventListener("submit", async (event) => {
    event.preventDefault();
    try {
        const data = await postJson("api/auth/index.php", {
            action: "register",
            name: document.getElementById("registerName").value.trim(),
            email: document.getElementById("registerEmail").value.trim(),
            password: document.getElementById("registerPassword").value
        });
        setAuthUI(data.user);
        currentSessionKey = "";
        clearChat();
        await ensureSession();
        await loadHistory();
        showAuthSuccessMessage("register");
        window.setTimeout(() => {
            authModal.hide();
            hideAuthSuccessMessage();
        }, 2200);
    } catch (error) {
        alert(error.message);
    }
});

profileForm.addEventListener("submit", async (event) => {
    event.preventDefault();
    try {
        const data = await postJson("api/auth/index.php", {
            action: "profile_update",
            name: document.getElementById("profileName").value.trim(),
            email: document.getElementById("profileEmail").value.trim(),
            password: document.getElementById("profilePassword").value
        });
        setAuthUI(data.user);
        profileModal.hide();
        alert("Profile updated successfully.");
    } catch (error) {
        alert(error.message);
    }
});

chatForm.addEventListener("submit", (event) => {
    event.preventDefault();
    const message = messageInput.value.trim();
    if (!message) {
        return;
    }

    appendMessage("user", message);
    messageInput.value = "";
    sendMessage(message);
});

messageInput.addEventListener("keydown", (event) => {
    if (event.key === "Enter" && !event.shiftKey) {
        event.preventDefault();
        chatForm.requestSubmit();
    }
});

welcomeTime.textContent = formatTime();
applyTheme(localStorage.getItem("travel_chat_theme") || "dark");

(async () => {
    try {
        const status = await postJson("api/auth/index.php", { action: "status" });
        setAuthUI(status.authenticated ? status.user : null);
        if (status.authenticated) {
            await ensureSession();
        }
        await loadHistory();
    } catch (error) {
        setAuthUI(null);
    }
    scrollToBottom();
})();
