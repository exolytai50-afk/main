document.addEventListener('alpine:init', () => {
    Alpine.data('chatEngine', (options) => ({
        // ===== НАСТРОЙКИ =====
        isGroup: options.isGroup || false,
        groupId: options.groupId || 0,
        counterpartEmail: options.counterpartEmail || '',
        myEmail: options.myEmail || '',
        apiUrl: options.isGroup ? '/api/chat_group.php' : '/api/chat_messages.php',

        // ===== СОСТОЯНИЕ =====
        messages: [],
        hasNewMessages: false, 
        newMessagesCount: 0,
        isUserScrolledUp: false, 
        lastMessageId: 0,
        inputEmpty: true,
        imageFile: null,
        imagePreview: null,
        quotedMessage: null,
        editingMessageId: null,
        
        // Панели
        showPanel: false,
        showPanelMobile: false,
        panelMode: 'emojis',
        activeEmojiPack: null,
        activeStickerPack: null,
        activeStickerPacks: [],
        __lastRange: null,

        // Данные
        emojiPacks: window.__EMOJI_PACKS__ || [],
        emojisByPack: window.__EMOJIS_BY_PACK__ || {},
        stickerPacks: window.__STICKER_PACKS__ || [],
        stickersByPack: window.__STICKERS_BY_PACK__ || {},

        // Модалка
        showImageModal: false,
        modalImageUrl: '',
        pollTimer: null,
        __voteTimer: null,

        // ===== ИНИЦИАЛИЗАЦИЯ =====
        async init() {
            if (this.emojiPacks.length) this.activeEmojiPack = this.emojiPacks[0].id;
            if (this.stickerPacks.length) this.activeStickerPack = this.stickerPacks[0].id;

            await this.loadActiveStickerPacks();
            
            window.addEventListener('storage', (e) => {
                if (e.key === 'gc_sticker_packs_changed') {
                    this.loadActiveStickerPacks();
                }
            });
            
            this.fetchMessages(true);
            this.restoreDraft();
            
            this.pollTimer = setInterval(() => this.fetchMessages(false), 3000);
        },

        // ===== ВВОД И ЧЕРНОВИКИ =====
        getInput() {
            const desktop = this.$refs.chatInput;
            const mobile = this.$refs.chatInputMobile;
            if (desktop && desktop.offsetParent !== null) return desktop;
            if (mobile && mobile.offsetParent !== null) return mobile;
            return desktop || mobile;
        },

        onChatInput() {
            const input = this.getInput();
            if (!input) return;
            const text = input.innerText.trim();
            const hasImg = input.querySelector('img');
            this.inputEmpty = !text && !hasImg;
        },

        saveRange() {
            const input = this.getInput();
            if (!input) return;
            const sel = window.getSelection();
            if (sel.rangeCount > 0 && input.contains(sel.anchorNode)) {
                this.__lastRange = sel.getRangeAt(0).cloneRange();
            }
        },

        saveDraft() {
            const input = this.getInput();
            if (!input) return;
            const content = input.innerHTML.trim();
            const key = 'gc_chat_draft_' + (this.isGroup ? 'group_' + this.groupId : this.counterpartEmail);
            
            if (!content || content === '<br>') {
                localStorage.removeItem(key);
            } else {
                localStorage.setItem(key, content);
            }
        },

        restoreDraft() {
            const input = this.getInput();
            if (!input) return;
            const key = 'gc_chat_draft_' + (this.isGroup ? 'group_' + this.groupId : this.counterpartEmail);
            const draft = localStorage.getItem(key);
            if (draft) {
                input.innerHTML = draft;
                this.onChatInput();
            }
        },

        clearDraft() {
            const key = 'gc_chat_draft_' + (this.isGroup ? 'group_' + this.groupId : this.counterpartEmail);
            localStorage.removeItem(key);
        },
        
        // ===== СКРОЛЛ И БЕЙДЖ =====
        handleScroll(e) {
            const container = e.target;
            // Считаем, что мы "внизу", если до конца осталось меньше 100px
            const isAtBottom = container.scrollHeight - container.scrollTop - container.clientHeight < 100;
            
            if (isAtBottom) {
                this.isUserScrolledUp = false;
                this.hasNewMessages = false;
                this.newMessagesCount = 0;
            } else {
                this.isUserScrolledUp = true;
            }
        },

        scrollToBottom(force = false) {
            const container = this.$refs.messagesContainer;
            if (!container) return;
            
            // Если это принудительная прокрутка (клик по бейджу), скроллим плавно
            if (force) {
                container.scrollTo({ top: container.scrollHeight, behavior: 'smooth' });
                this.hasNewMessages = false;
                this.newMessagesCount = 0;
                this.isUserScrolledUp = false;
            } else {
                container.scrollTop = container.scrollHeight;
            }
        },

        // ===== ОТПРАВКА =====
        async sendMessage() {
            const input = this.getInput();
            const content = input.innerHTML.trim();
            const hasText = content && content !== '<br>';
            const quoteId = this.quotedMessage ? this.quotedMessage.id : null;

            if (!hasText && !this.imageFile) return;

            try {
                if (this.imageFile) {
                    const formData = new FormData();
                    formData.append('action', this.isGroup ? 'upload_image' : 'send');
                    if (this.isGroup) formData.append('group_id', this.groupId);
                    else formData.append('email', this.counterpartEmail);
                    formData.append('image', this.imageFile);
                    if (hasText) formData.append('message', content);
                    if (quoteId) formData.append('quoted_id', quoteId);

                    const res = await fetch(this.apiUrl, { method: 'POST', body: formData });
                    const data = await res.json();
                    if (data.success || data.ok) this._clearInput();
                } else {
                    const payload = {
                        action: this.isGroup ? 'send' : undefined,
                        message: content,
                        quoted_id: quoteId
                    };
                    if (this.isGroup) payload.group_id = this.groupId;
                    else payload.email = this.counterpartEmail;

                    const res = await fetch(this.apiUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload)
                    });
                    const data = await res.json();
                    if (data.success || data.ok) this._clearInput();
                }
            } catch (e) {
                console.error('Send error:', e);
            }
        },

        _clearInput() {
            const input = this.getInput();
            if (input) input.innerHTML = '';
            this.inputEmpty = true;
            this.cancelImageUpload();
            this.cancelQuote();
            this.clearDraft();
            this.fetchMessages(true);
            
            // Принудительный скролл после отправки своего сообщения
            this.$nextTick(() => {
                setTimeout(() => this.scrollToBottom(true), 100);
            });
        },
        
        leaveGroup() {
            if (!this.isGroup) return;
            if (!confirm('Покинуть группу?')) return;
            fetch(this.apiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'leave', group_id: this.groupId })
            }).then(r => r.json()).then(data => {
                if (data.success) window.location.href = '/chats';
            });
        },

        deleteGroup() {
            if (!this.isGroup) return;
            if (!confirm('Удалить группу? Все сообщения будут потеряны.')) return;
            fetch(this.apiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'delete_group', group_id: this.groupId })
            }).then(r => r.json()).then(data => {
                if (data.success) window.location.href = '/chats';
            });
        },

        // ===== ЭМОДЗИ И СТИКЕРЫ =====

        /**
         * Вставляет эмодзи в конец сообщения.
         * Фокус не возвращается в поле, поэтому мобильная клавиатура
         * не должна открываться при выборе эмодзи.
         */
        insertEmojiToChat(emoji) {
            const input = this.getInput();
            if (!input || !emoji || !emoji.image_url) return;

            // Скрываем клавиатуру.
            input.blur();

            // Создаём изображение эмодзи.
            const img = document.createElement('img');
            img.src = emoji.image_url;
            img.alt = emoji.code || '';
            img.className = 'inline-emoji';
            img.contentEditable = 'false';
            img.draggable = false;

            // Вставляем строго в конец сообщения.
            const range = document.createRange();
            range.selectNodeContents(input);
            range.collapse(false);
            range.insertNode(img);

            // Добавляем текстовый узел после изображения.
            // Он позволяет продолжить печать непосредственно после эмодзи.
            const spacer = document.createTextNode('');
            img.parentNode.insertBefore(spacer, img.nextSibling);

            // Сохраняем курсор после вставленного эмодзи.
            const cursor = document.createRange();
            cursor.setStartAfter(spacer);
            cursor.collapse(true);

            this.__lastRange = cursor.cloneRange();

            // Синхронизируем текст сообщения и черновик.
            this.onChatInput();
            this.saveDraft();
        },

        /**
         * Устанавливает курсор в конец содержимого редактора.
         */
        moveChatCursorToEnd(input) {
            if (!input) return;

            input.focus({ preventScroll: true });

            const range = document.createRange();
            range.selectNodeContents(input);
            range.collapse(false);

            const selection = window.getSelection();

            if (selection) {
                selection.removeAllRanges();
                selection.addRange(range);
            }

            this.__lastRange = range.cloneRange();
        },

        /**
         * Переключает панель эмодзи и клавиатуру.
         */
        toggleEmojiPanelMobile() {
            const input = this.$refs.chatInputMobile || this.getInput();

            if (this.showPanelMobile) {
                // Закрываем панель эмодзи.
                this.showPanelMobile = false;

                this.$nextTick(() => {
                    const editor = this.$refs.chatInputMobile || this.getInput();

                    if (!editor) return;

                    // Возвращаем фокус и курсор в конец сообщения.
                    this.moveChatCursorToEnd(editor);
                });
            } else {
                // Открываем панель эмодзи.
                // Перед этим запоминаем текущую позицию, если редактор активен.
                if (input) {
                    input.blur();
                }

                this.showPanelMobile = true;
            }
        },

        getActiveEmojis() {
            return this.emojisByPack[this.activeEmojiPack] || [];
        },

        getActivePackStickers() {
            return this.stickersByPack[this.activeStickerPack] || [];
        },

        async sendSticker(url) {
            try {
                const payload = {
                    action: this.isGroup ? 'send_sticker' : undefined,
                    sticker_url: url
                };
                if (this.isGroup) payload.group_id = this.groupId;
                else payload.email = this.counterpartEmail;

                const res = await fetch(this.apiUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();
                if (data.success || data.ok) {
                    this.showPanel = false;
                    this.showPanelMobile = false;
                    this.fetchMessages(true);
                    
                    // Принудительный скролл после отправки стикера
                    this.$nextTick(() => {
                        setTimeout(() => this.scrollToBottom(true), 100);
                    });
                }
            } catch (e) {
                console.error(e);
            }
        },

        // ===== СТИКЕР-ПАКИ =====
        get sortedStickerPacks() {
            const active = (this.activeStickerPacks || []).map(Number);
            const all = this.stickerPacks || [];
            const activePacks = active.map(id => all.find(p => Number(p.id) === id)).filter(Boolean);
            const restPacks = all.filter(p => !active.includes(Number(p.id)));
            return [...activePacks, ...restPacks];
        },

        get activeStickerPacksList() {
            return (this.activeStickerPacks || []).map(Number)
                .map(id => (this.stickerPacks || []).find(p => Number(p.id) === id))
                .filter(Boolean);
        },

        isStickerPackActive(packId) {
            return (this.activeStickerPacks || []).map(Number).includes(Number(packId));
        },

        async addStickerPack(packId) {
            packId = Number(packId);
            if (!this.activeStickerPacks.includes(packId)) {
                const newIds = [...this.activeStickerPacks, packId];
                this.activeStickerPacks = newIds;
                await this.saveActiveStickerPacks(newIds);
            }
        },

        async removeStickerPack(packId) {
            packId = Number(packId);
            const newIds = this.activeStickerPacks.filter(id => id !== packId);
            this.activeStickerPacks = newIds;
            await this.saveActiveStickerPacks(newIds);
        },

        async loadActiveStickerPacks() {
            try {
                const res = await fetch('/api/chat_sticker_packs.php');
                const data = await res.json();
                if (data.ok && Array.isArray(data.ids)) {
                    const receivedIds = data.ids.map(Number);
                    const allPacks = this.stickerPacks || [];
                    this.activeStickerPacks = receivedIds.filter(id =>
                        allPacks.some(p => Number(p.id) === id)
                    );
                }
            } catch (e) {
                console.error('loadActiveStickerPacks:', e);
            }
        },

        async saveActiveStickerPacks(ids) {
            try {
                await fetch('/api/chat_sticker_packs.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ ids: (ids || []).map(Number) })
                });
                localStorage.setItem('gc_sticker_packs_changed', Date.now());
            } catch (e) {
                console.error('saveActiveStickerPacks:', e);
            }
        },

        // ===== ЗАГРУЗКА СООБЩЕНИЙ =====
        fetchMessages(full) {
            const lastId = full ? 0 : this.lastMessageId;
            
            if (!this.isGroup) {
                fetch(this.apiUrl + '?email=' + encodeURIComponent(this.counterpartEmail))
                    .then(r => r.json())
                    .then(data => this._processMessages(data.messages || [], full))
                    .catch(err => console.error(err));
                return;
            }

            fetch(this.apiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'fetch', group_id: this.groupId, last_id: lastId })
            }).then(r => r.json()).then(data => {
                if (data.messages) {
                    this._processMessages(data.messages, full);
                } else if (data.success && data.messages) {
                    this._processMessages(data.messages, full);
                }
            }).catch(err => console.error(err));
        },

        _processMessages(newMsgs, full) {
            const container = this.$refs.messagesContainer;
            
            if (!container) {
                this.messages = newMsgs;
                return;
            }

            const isAtBottom = container.scrollHeight - container.scrollTop - container.clientHeight < 100;

            let addedCount = 0;

            if (full || this.messages.length === 0) {
                this.messages = newMsgs;
                if (this.messages.length > 0) {
                    this.lastMessageId = this.messages[this.messages.length - 1].id;
                }
            } else if (newMsgs.length > 0) {
                const existingIds = new Set(this.messages.map(m => m.id));
                const added = newMsgs.filter(m => !existingIds.has(m.id));
                addedCount = added.length;
                
                if (added.length > 0) {
                    this.messages.push(...added);
                    this.lastMessageId = added[added.length - 1].id;
                }
            }

            this.$nextTick(() => {
                // Если мы были внизу, прокручиваем вниз
                if (isAtBottom && !this.isUserScrolledUp) {
                    container.scrollTop = container.scrollHeight;
                } 
                // Если пользователь листает историю и пришли новые сообщения
                else if (this.isUserScrolledUp && addedCount > 0) {
                    this.hasNewMessages = true;
                    this.newMessagesCount += addedCount;
                }
            });

            clearTimeout(this.__voteTimer);
            this.__voteTimer = setTimeout(() => this.loadMessageVotes(), 300);
        },

        // ===== ЛАЙКИ =====
        async loadMessageVotes() {
            if (!this.messages.length) return;
            const ids = this.messages.map(m => m.id);
            const type = this.isGroup ? 'group_message' : 'chat_message';
            try {
                const resp = await fetch('/api/chat_votes.php?target_type=' + type + '&target_ids=' + ids.join(','));
                const data = await resp.json();
                if (data.ok && data.votes) {
                    this.messages.forEach(m => {
                        const v = data.votes[m.id];
                        if (v) {
                            m.like_count = v.like_count || 0;
                            m.dislike_count = v.dislike_count || 0;
                            m.my_vote = v.my_vote || 0;
                        }
                    });
                }
            } catch (e) { console.error(e); }
        },

        async vote(targetObj, targetType, targetId, voteValue, event) {
            if (event && event.target) {
                const btn = event.target.closest('.vote-btn');
                if (btn) {
                    btn.classList.add('anim-click');
                    setTimeout(() => btn.classList.remove('anim-click'), 200);
                }
            }

            const prevVote = targetObj.my_vote || 0;
            const prevLikes = targetObj.like_count || 0;
            const prevDislikes = targetObj.dislike_count || 0;

            if (prevVote === voteValue) {
                targetObj.my_vote = 0;
                if (voteValue === 1) targetObj.like_count = Math.max(0, prevLikes - 1);
                if (voteValue === -1) targetObj.dislike_count = Math.max(0, prevDislikes - 1);
            } else {
                targetObj.my_vote = voteValue;
                if (voteValue === 1) {
                    targetObj.like_count = prevLikes + 1;
                    if (prevVote === -1) targetObj.dislike_count = Math.max(0, prevDislikes - 1);
                } else {
                    targetObj.dislike_count = prevDislikes + 1;
                    if (prevVote === 1) targetObj.like_count = Math.max(0, prevLikes - 1);
                }
            }

            try {
                const formData = new FormData();
                formData.append('target_type', targetType);
                formData.append('target_id', targetId);
                formData.append('vote', voteValue);
                const resp = await fetch('/api/chat_votes.php', { method: 'POST', body: formData });
                const data = await resp.json();
                if (data.ok) {
                    targetObj.my_vote = data.my_vote;
                    targetObj.like_count = data.like_count;
                    targetObj.dislike_count = data.dislike_count;
                } else {
                    targetObj.my_vote = prevVote;
                    targetObj.like_count = prevLikes;
                    targetObj.dislike_count = prevDislikes;
                }
            } catch (e) { console.error(e); }
        },

        // ===== ОСТАЛЬНОЕ =====
        onChatEnter(e) {
            if (e.shiftKey) return;
            e.preventDefault();
            this.sendMessage();
        },

        onChatPaste(e) {
            e.preventDefault();
            const text = (e.clipboardData || window.clipboardData).getData('text/plain');
            document.execCommand('insertText', false, text);
            this.saveDraft();
        },

        triggerImageUpload() {
            const inp = this.$refs.imageInput || this.$refs.imageInputMobile;
            if (inp) inp.click();
        },

        handleImageUpload(e) {
            const file = e.target.files[0];
            if (!file) return;
            this.imageFile = file;
            const reader = new FileReader();
            reader.onload = (ev) => { this.imagePreview = ev.target.result; };
            reader.readAsDataURL(file);
        },

        cancelImageUpload() {
            this.imageFile = null;
            this.imagePreview = null;
            if (this.$refs.imageInput) this.$refs.imageInput.value = '';
            if (this.$refs.imageInputMobile) this.$refs.imageInputMobile.value = '';
        },

        startQuote(msg) {
            this.quotedMessage = msg;
            this.editingMessageId = null;
            this.getInput().focus();
        },

        cancelQuote() { this.quotedMessage = null; },

        getQuoteAuthorName(msg) {
            if (!msg) return '';
            return msg.sender_email === this.myEmail ? 'Вы' : (msg.display_name || 'Собеседник');
        },

        getQuotePreview(msg) {
            if (!msg) return '';
            if (msg.sticker_url) return 'Стикер';
            if (msg.image_url) return 'Фото';
            return msg.message || '';
        },

        startEdit(msg) {
            this.editingMessageId = msg.id;
            this.quotedMessage = null;
            const input = this.getInput();
            input.innerHTML = msg.message || '';
            input.focus();
            this.inputEmpty = false;
        },

        cancelEdit() {
            this.editingMessageId = null;
            this.getInput().innerHTML = '';
            this.inputEmpty = true;
        },

        saveEdit() {
            if (!this.editingMessageId) return;
            const content = this.getInput().innerHTML.trim();
            if (!content) return;
            const payload = {
                action: this.isGroup ? 'edit' : undefined,
                message_id: this.editingMessageId,
                message: content
            };
            if (this.isGroup) payload.group_id = this.groupId;
            
            fetch(this.apiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            }).then(r => r.json()).then(data => {
                if (data.success || data.ok) {
                    this.cancelEdit();
                    this.fetchMessages(true);
                    
                    // Скролл вниз после сохранения
                    this.$nextTick(() => {
                        setTimeout(() => this.scrollToBottom(true), 100);
                    });
                }
            });
        },

        deleteMessage(msgId) {
            if (!confirm('Удалить сообщение?')) return;
            const payload = { action: this.isGroup ? 'delete' : undefined, message_id: msgId };
            if (this.isGroup) payload.group_id = this.groupId;

            fetch(this.apiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            }).then(r => r.json()).then(data => {
                if (data.success || data.ok) {
                    this.messages = this.messages.filter(m => m.id !== msgId);
                }
            });
        },

        renderMessage(text) { return text || ''; },

        formatTime(dt) {
            if (!dt) return '';
            const d = new Date(dt.replace(' ', 'T'));
            return d.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' });
        },

        openImageModal(url) {
            this.modalImageUrl = url;
            this.showImageModal = true;
        },

        closeImageModal() {
            this.showImageModal = false;
            this.modalImageUrl = '';
        }
    }));
});