/* ═══════════════════════════════════════════════════════════
   reviews.js — компонент отзывов, комментариев и голосования
   ═══════════════════════════════════════════════════════════ */

/* ─── Глобальные хелперы (нужны до инициализации Alpine) ─── */
window.formatDate = function(dateStr) {
    if (!dateStr) return '';
    var d = new Date(dateStr);
    if (isNaN(d.getTime())) return dateStr;
    var now = new Date();
    var diff = (now - d) / 1000;
    if (diff < 60) return 'только что';
    if (diff < 3600) return Math.floor(diff / 60) + ' мин назад';
    if (diff < 86400) return Math.floor(diff / 3600) + ' ч назад';
    if (diff < 2592000) return Math.floor(diff / 86400) + ' дн назад';
    return d.toLocaleDateString('ru-RU', {
        day: 'numeric', month: 'long', year: 'numeric',
        hour: '2-digit', minute: '2-digit'
    });
};

window.stars = function(rating) {
    var r = Math.round(rating || 0);
    return '★'.repeat(r) + '☆'.repeat(5 - r);
};

window.renderEmojiText = function(text) {
    if (!text) return '';
    if (text.indexOf('<img') !== -1) return text;

    var tempDiv = document.createElement('div');
    tempDiv.innerHTML = text;

    var emojiMap = {};
    var packs = window.__EMOJIS_BY_PACK__ || {};
    Object.values(packs).forEach(function(emojis) {
        if (Array.isArray(emojis)) {
            emojis.forEach(function(emoji) {
                if (emoji && emoji.code && emoji.image_url) {
                    emojiMap[emoji.code.trim()] = emoji.image_url;
                }
            });
        }
    });

    if (Object.keys(emojiMap).length === 0) return text;

    var walker = document.createTreeWalker(tempDiv, NodeFilter.SHOW_TEXT, null, false);
    var textNodes = [];
    while (walker.nextNode()) textNodes.push(walker.currentNode);

    textNodes.forEach(function(node) {
        var content = node.textContent;
        var changed = false;
        for (var code in emojiMap) {
            var escaped = code.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            var regex = new RegExp(escaped, 'g');
            if (regex.test(content)) {
                content = content.replace(regex, '<img src="' + emojiMap[code] + '" class="inline-emoji" alt="' + code + '" />');
                changed = true;
            }
        }
        if (changed) {
            var span = document.createElement('span');
            span.innerHTML = content;
            node.parentNode.replaceChild(span, node);
        }
    });

    return tempDiv.innerHTML;
};

/* ─── Регистрация компонента ─── */
document.addEventListener('alpine:init', () => {

Alpine.data('reviews', (recipeId) => ({
    recipeId: recipeId,
    comments: [],
    avg: 0,
    count: 0,
    form: { rating: 5, text: '' },
    sending: false,
    editingReviewId: null,
    editorEmpty: true,

    // Состояние для эмодзи
    showEmoji: false,
    activeEmojiPack: null,
    emojiPacks: window.__EMOJI_PACKS__ || [],
    emojisByPack: window.__EMOJIS_BY_PACK__ || {},

    reviewPhotoFile: null,
    reviewPhotoPreview: null,
    showImageModal: false,
    modalImageUrl: '',

    rvVotes: {},
    cvVotes: {},

    replyingTo: null,
    replyingReviewId: null,
    replyText: '',
    showReplyEmojiFor: null,

    editingCommentId: null,
    editedCommentText: '',
    showEditEmojiFor: null,

    showCommentEmojiFor: null,
    commentTexts: {},

    init() {
        this.load();
        if (this.emojiPacks.length > 0) this.activeEmojiPack = this.emojiPacks[0].id;
    },

    // ==========================================
    // ЛОГИКА ЭМОДЗИ (1-в-1 как в чатах)
    // ==========================================

    insertEmojiToElement(elementId, emojiUrl, emojiCode = '') {
        const input = document.getElementById(elementId);
        if (!input || !emojiUrl) return;

        // 1. Убираем фокус — мобильная клавиатура закрывается.
        input.blur();

        // 2. Создаём картинку-эмодзи.
        const img = document.createElement('img');
        img.src = emojiUrl;
        img.alt = emojiCode;
        img.className = 'inline-emoji';
        img.contentEditable = 'false';
        img.draggable = false;

        // 3. Вставляем строго в конец содержимого.
        const range = document.createRange();
        range.selectNodeContents(input);
        range.collapse(false);
        range.insertNode(img);

        // 4. Пустой текстовый узел после эмодзи — чтобы курсор мог встать после него.
        const spacer = document.createTextNode('');
        img.parentNode.insertBefore(spacer, img.nextSibling);

        // 5. НЕ вызываем input.focus() — клавиатура не должна открыться.

        // 6. Синхронизируем состояние (текст/черновик/кнопка отправки).
        if (elementId === 'reply-editor-' + this.replyingReviewId) {
            this.replyText = input.innerHTML;
        } else if (elementId.startsWith('comment-editor-')) {
            const reviewId = elementId.replace('comment-editor-', '');
            if (typeof this.onCommentInput === 'function') {
                this.onCommentInput({ target: input }, reviewId);
            }
        } else if (elementId === 'review-editor') {
            this.onEditorInput({ target: input });
        } else if (elementId.startsWith('edit-editor-')) {
            this.editedCommentText = input.innerHTML;
        }
    },

    insertEmoji(emoji) {
        this.insertEmojiToElement('review-editor', emoji.image_url, emoji.code);
    },
    insertEmojiToComment(emoji, reviewId) {
        this.insertEmojiToElement('comment-editor-' + reviewId, emoji.image_url, emoji.code);
    },
    insertEmojiToReply(emoji, reviewId) {
        this.insertEmojiToElement('reply-editor-' + reviewId, emoji.image_url, emoji.code);
    },
    insertEmojiToEdit(emoji, commentId) {
        this.insertEmojiToElement('edit-editor-' + commentId, emoji.image_url, emoji.code);
    },

    getActiveEmojis() {
        if (!this.activeEmojiPack) return [];
        return this.emojisByPack[this.activeEmojiPack] || [];
    },

    moveCursorToEnd(input) {
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
    },

toggleEmojiPanelMobile() {
    const input = document.getElementById('review-editor');
    if (this.showEmoji) {
        // Закрываем панель → возвращаем фокус, курсор в конец.
        this.showEmoji = false;
        this.$nextTick(() => { this.moveCursorToEnd(input); });
    } else {
        // Открываем панель → убираем фокус, клавиатура прячется.
        if (input) input.blur();
        this.showEmoji = true;
    }
},

toggleCommentEmoji(reviewId) {
    const input = document.getElementById('comment-editor-' + reviewId);
    if (this.showCommentEmojiFor === reviewId) {
        this.showCommentEmojiFor = null;
        this.$nextTick(() => { this.moveCursorToEnd(input); });
    } else {
        if (input) input.blur();
        this.showCommentEmojiFor = reviewId;
    }
},

toggleReplyEmoji(reviewId) {
    const input = document.getElementById('reply-editor-' + reviewId);
    if (this.showReplyEmojiFor === reviewId) {
        this.showReplyEmojiFor = null;
        this.$nextTick(() => { this.moveCursorToEnd(input); });
    } else {
        if (input) input.blur();
        this.showReplyEmojiFor = reviewId;
    }
},

toggleEditEmoji(commentId) {
    const input = document.getElementById('edit-editor-' + commentId);
    if (this.showEditEmojiFor === commentId) {
        this.showEditEmojiFor = null;
        this.$nextTick(() => { this.moveCursorToEnd(input); });
    } else {
        if (input) input.blur();
        this.showEditEmojiFor = commentId;
    }
},

    // ==========================================
    // ОСТАЛЬНАЯ ЛОГИКА
    // ==========================================

    onEditorInput(event) {
        var editor = (event && event.target) || this.$refs.editor;
        if (!editor) { this.editorEmpty = true; return; }
        var hasText = editor.innerText.trim().length > 0;
        var hasImg  = editor.querySelector('img') !== null;
        this.editorEmpty = !hasText && !hasImg;
        this.form.text = editor.innerHTML;
    },
    

    onCommentPaste(e) {
        e.preventDefault();
        var text = (e.clipboardData || window.clipboardData).getData('text/plain');
        document.execCommand('insertText', false, text);
    },

    onEditorPaste(event) {
        event.preventDefault();
        var text = (event.clipboardData || window.clipboardData).getData('text/plain');
        document.execCommand('insertText', false, text);
        this.onEditorInput();
    },

    isEditorEmpty() {
        return this.editorEmpty;
    },

    handleReviewPhoto(event) {
        var file = event.target.files[0];
        if (!file) return;
        if (file.size > 5 * 1024 * 1024) {
            if (typeof toast === 'function') toast('Файл больше 5 МБ', 'error');
            return;
        }
        this.reviewPhotoFile = file;
        var reader = new FileReader();
        reader.onload = (e) => { this.reviewPhotoPreview = e.target.result; };
        reader.readAsDataURL(file);
        event.target.value = '';
    },

    removeReviewPhoto() {
        this.reviewPhotoFile = null;
        this.reviewPhotoPreview = null;
    },

    stars(rating) {
        var r = Math.round(rating || 0);
        return '★'.repeat(r) + '☆'.repeat(5 - r);
    },

    formatDate(date) {
        return window.formatDate(date);
    },

    async load() {
        try {
            var res = await fetch('/api/recipe_reviews.php?recipe_id=' + this.recipeId);
            if (!res.ok) throw new Error('HTTP ' + res.status);
            var data = await res.json().catch(function() { return {}; });
            if (data.ok) {
                this.comments = data.comments || [];
                this.avg = data.avg || 0;
                this.count = data.count || 0;
                this.comments.forEach((rv) => {
                    this.rvVotes[rv.id] = {
                        likes: rv.likes_count || 0,
                        dislikes: rv.dislikes_count || 0,
                        my_vote: rv.my_vote || 0
                    };
                    if (rv.comments) {
                        rv.comments.forEach((c) => {
                            this.cvVotes[c.id] = {
                                likes: c.likes_count || 0,
                                dislikes: c.dislikes_count || 0,
                                my_vote: c.my_vote || 0
                            };
                        });
                    }
                });
            }
        } catch (e) {
            console.error('Load reviews error:', e);
        }
    },

    rvMyVote(id)    { return (this.rvVotes[id] || {}).my_vote || 0; },
    rvLikes(id)     { return (this.rvVotes[id] || {}).likes || 0; },
    rvDislikes(id)  { return (this.rvVotes[id] || {}).dislikes || 0; },

    cvMyVote(id)    { return (this.cvVotes[id] || {}).my_vote || 0; },
    cvLikes(id)     { return (this.cvVotes[id] || {}).likes || 0; },
    cvDislikes(id)  { return (this.cvVotes[id] || {}).dislikes || 0; },

    async voteReview(reviewId, voteValue, event) {
        if (!window.__IS_LOGGED_IN__) { window.location.href = '/login'; return; }
        if (event && event.currentTarget) {
            var btn = event.currentTarget;
            btn.classList.add('anim-click');
            setTimeout(function() { btn.classList.remove('anim-click'); }, 350);
        }

        var key = reviewId;
        var v = this.rvVotes[key] || { likes: 0, dislikes: 0, my_vote: 0 };
        var pv = v.my_vote, pl = v.likes, pd = v.dislikes;

        if (pv === voteValue) {
            v.my_vote = 0;
            if (voteValue === 1)  v.likes    = Math.max(0, pl - 1);
            if (voteValue === -1) v.dislikes = Math.max(0, pd - 1);
        } else {
            v.my_vote = voteValue;
            if (voteValue === 1)  { v.likes = pl + 1; if (pv === -1) v.dislikes = Math.max(0, pd - 1); }
            else                  { v.dislikes = pd + 1; if (pv === 1) v.likes = Math.max(0, pl - 1); }
        }
        this.rvVotes[key] = { likes: v.likes, dislikes: v.dislikes, my_vote: v.my_vote };

        try {
            var fd = new FormData();
            fd.append('type', 'review');
            fd.append('id', reviewId);
            fd.append('vote', voteValue);
            var res = await fetch('/api/review_votes.php', { method: 'POST', body: fd });
            var data = await res.json();
            if (data.ok) {
                this.rvVotes[key] = { likes: data.likes_count, dislikes: data.dislikes_count, my_vote: data.my_vote };
            } else {
                this.rvVotes[key] = { likes: pl, dislikes: pd, my_vote: pv };
                if (typeof toast === 'function') toast(data.error || 'Ошибка', 'error');
            }
        } catch (e) {
            this.rvVotes[key] = { likes: pl, dislikes: pd, my_vote: pv };
            if (typeof toast === 'function') toast('Ошибка соединения', 'error');
        }
    },

    async voteComment(commentId, voteValue, event) {
        if (!window.__IS_LOGGED_IN__) { window.location.href = '/login'; return; }
        if (event && event.currentTarget) {
            var btn = event.currentTarget;
            btn.classList.add('anim-click');
            setTimeout(function() { btn.classList.remove('anim-click'); }, 350);
        }

        var key = commentId;
        var v = this.cvVotes[key] || { likes: 0, dislikes: 0, my_vote: 0 };
        var pv = v.my_vote, pl = v.likes, pd = v.dislikes;

        if (pv === voteValue) {
            v.my_vote = 0;
            if (voteValue === 1)  v.likes    = Math.max(0, pl - 1);
            if (voteValue === -1) v.dislikes = Math.max(0, pd - 1);
        } else {
            v.my_vote = voteValue;
            if (voteValue === 1)  { v.likes = pl + 1; if (pv === -1) v.dislikes = Math.max(0, pd - 1); }
            else                  { v.dislikes = pd + 1; if (pv === 1) v.likes = Math.max(0, pl - 1); }
        }
        this.cvVotes[key] = { likes: v.likes, dislikes: v.dislikes, my_vote: v.my_vote };

        try {
            var fd = new FormData();
            fd.append('type', 'comment');
            fd.append('id', commentId);
            fd.append('vote', voteValue);
            var res = await fetch('/api/review_votes.php', { method: 'POST', body: fd });
            var data = await res.json();
            if (data.ok) {
                this.cvVotes[key] = { likes: data.likes_count, dislikes: data.dislikes_count, my_vote: data.my_vote };
            } else {
                this.cvVotes[key] = { likes: pl, dislikes: pd, my_vote: pv };
                if (typeof toast === 'function') toast(data.error || 'Ошибка', 'error');
            }
        } catch (e) {
            this.cvVotes[key] = { likes: pl, dislikes: pd, my_vote: pv };
            if (typeof toast === 'function') toast('Ошибка соединения', 'error');
        }
    },

    async submit() {
        var editor = this.$refs.editor;
        var htmlContent = editor ? editor.innerHTML : '';
        var hasText = htmlContent.trim() !== '' && htmlContent !== '<br>';

        if (!hasText && !this.reviewPhotoFile) {
            if (typeof toast === 'function') toast('Напишите отзыв или добавьте фото', 'error');
            return;
        }

        this.sending = true;
        try {
            var formData = new FormData();
            formData.append('recipe_id', this.recipeId);
            formData.append('rating', this.form.rating);
            formData.append('text', htmlContent);
            if (this.reviewPhotoFile) formData.append('photo', this.reviewPhotoFile);
            if (this.editingReviewId) formData.append('review_id', this.editingReviewId);

            var url = this.editingReviewId
                ? '/api/recipe_reviews.php?action=update'
                : '/api/recipe_reviews.php';

            var res = await fetch(url, {
                method: 'POST',
                headers: { 'X-CSRF-Token': window.__CSRF_TOKEN__ || '' },
                body: formData
            });

            var data = await res.json().catch(function() { return {}; });

            if (data.ok) {
                if (editor) editor.innerHTML = '';
                this.form.rating = 5;
                this.form.text = '';
                this.reviewPhotoFile = null;
                this.reviewPhotoPreview = null;
                this.showEmoji = false;
                this.editorEmpty = true;
                this.editingReviewId = null;
                await this.load();
                if (typeof toast === 'function') toast('Отзыв сохранён!', 'success');
            } else {
                if (typeof toast === 'function') toast(data.error || 'Ошибка', 'error');
            }
        } catch (e) {
            console.error('Submit review error:', e);
            if (typeof toast === 'function') toast('Ошибка соединения', 'error');
        } finally {
            this.sending = false;
        }
    },

    cancelEdit() {
        this.editingReviewId = null;
        if (this.$refs.editor) this.$refs.editor.innerHTML = '';
        this.editorEmpty = true;
        this.form.rating = 5;
        this.removeReviewPhoto();
    },

    onCommentInput(event, reviewId) {
        var el = event.target;
        var hasText = el.innerText.trim().length > 0;
        var hasImg = el.querySelector('img') !== null;
        this.commentTexts = { ...this.commentTexts, [reviewId]: (hasText || hasImg) ? '1' : '' };
    },

    hasCommentText(reviewId) {
        return (this.commentTexts[reviewId] || '').length > 0;
    },

    async addComment(review, editorElement) {
        if (!editorElement) {
            editorElement = document.getElementById('comment-editor-' + review.id);
        }
        if (!editorElement) {
            console.error('addComment: editor not found for review', review.id);
            return;
        }
        var hasText = editorElement.innerText.trim().length > 0;
        var hasImg = editorElement.querySelector('img') !== null;
        if (!hasText && !hasImg) {
            return;
        }

        try {
            var res = await fetch('/api/recipe_comments.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': window.__CSRF_TOKEN__ || ''
                },
                body: JSON.stringify({
                    recipe_id: this.recipeId,
                    review_id: review.id,
                    text: editorElement.innerHTML
                })
            });
            var data = await res.json().catch(function() { return {}; });
            if (data.ok) {
                editorElement.innerHTML = '';
                this.commentTexts = { ...this.commentTexts, [review.id]: '' };
                this.showCommentEmojiFor = null;
                await this.load();
            } else {
                if (typeof toast === 'function') toast(data.error || 'Ошибка', 'error');
            }
        } catch (e) {
            console.error('addComment error:', e);
            if (typeof toast === 'function') toast('Ошибка соединения', 'error');
        }
    },

    startReply(comment, reviewId) {
        this.replyingTo = comment.id;
        this.replyingReviewId = reviewId;
        this.replyText = '';
        this.$nextTick(() => {
            var ed = document.getElementById('reply-editor-' + reviewId);
            if (ed) ed.focus();
        });
    },

    cancelReply() {
        this.replyingTo = null;
        this.replyingReviewId = null;
        this.replyText = '';
        this.showReplyEmojiFor = null;
    },

    async addReply(reviewId, parentId) {
        if (!this.replyText.trim() || this.replyText.trim() === '<br>') return;
        try {
            var res = await fetch('/api/recipe_comments.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': window.__CSRF_TOKEN__ || ''
                },
                body: JSON.stringify({
                    recipe_id: this.recipeId,
                    review_id: reviewId,
                    parent_id: parentId,
                    text: this.replyText
                })
            });
            var data = await res.json().catch(function() { return {}; });
            if (data.ok) {
                this.cancelReply();
                await this.load();
            } else {
                if (typeof toast === 'function') toast(data.error || 'Ошибка', 'error');
            }
        } catch (e) {
            if (typeof toast === 'function') toast('Ошибка соединения', 'error');
        }
    },

    getQuotedComment(comments, commentId) {
        return comments.find(function(c) { return c.id === commentId; });
    },

    getParentComment(comments, parentId) {
        return comments.find(function(c) { return c.id === parentId; });
    },

    startEditComment(comment) {
        this.editingCommentId = comment.id;
        this.editedCommentText = comment.text || '';
        this.$nextTick(() => {
            var ed = document.getElementById('edit-editor-' + comment.id);
            if (ed) { ed.innerHTML = comment.text || ''; ed.focus(); }
        });
    },

    cancelEditComment() {
        this.editingCommentId = null;
        this.editedCommentText = '';
        this.showEditEmojiFor = null;
    },

    async updateComment(commentId) {
        if (!this.editedCommentText.trim() || this.editedCommentText.trim() === '<br>') return;
        try {
            var res = await fetch('/api/recipe_comments.php', {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': window.__CSRF_TOKEN__ || ''
                },
                body: JSON.stringify({
                    comment_id: commentId,
                    text: this.editedCommentText
                })
            });
            var data = await res.json().catch(function() { return {}; });
            if (data.ok) {
                this.cancelEditComment();
                await this.load();
            } else {
                if (typeof toast === 'function') toast(data.error || 'Ошибка', 'error');
            }
        } catch (e) {
            if (typeof toast === 'function') toast('Ошибка соединения', 'error');
        }
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

/* ─── Глобальные функции для onclick в шаблоне ─── */
window.editReview = function(review) {
    var el = document.querySelector('[x-data^="reviews("]');
    if (!el) return;
    var comp = el._x_dataStack ? el._x_dataStack[0] : null;
    if (!comp) return;
    comp.editingReviewId = review.id;
    comp.form.rating = review.rating || 5;
    if (comp.$refs.editor) {
        comp.$refs.editor.innerHTML = review.text || '';
        comp.editorEmpty = false;
        comp.form.text = review.text || '';
        comp.$refs.editor.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
};

window.deleteReview = function(reviewId) {
    if (!confirm('Удалить отзыв?')) return;
    fetch('/api/recipe_reviews.php?id=' + encodeURIComponent(reviewId), {
        method: 'DELETE',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': window.__CSRF_TOKEN__ || ''
        }
    }).then(function(r) { return r.json(); }).then(function(data) {
        if (data.ok) {
            location.reload();
        } else {
            if (typeof toast === 'function') toast(data.error || 'Ошибка', 'error');
        }
    }).catch(function() {
        if (typeof toast === 'function') toast('Ошибка соединения', 'error');
    });
};

window.deleteComment = function(commentId) {
    if (!confirm('Удалить комментарий?')) return;
    fetch('/api/recipe_comments.php?id=' + commentId, {
        method: 'DELETE',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': window.__CSRF_TOKEN__ || ''
        }
    }).then(function(r) { return r.json(); }).then(function(data) {
        if (data.ok) {
            location.reload();
        } else {
            if (typeof toast === 'function') toast(data.error || 'Ошибка', 'error');
        }
    }).catch(function() {
        if (typeof toast === 'function') toast('Ошибка соединения', 'error');
    });
};

}); /* end alpine:init */