document.addEventListener('alpine:init', () => {
    Alpine.data('challengeDetail', () => {
        var data = window.__CHALLENGE_DATA__ || {};
        return {
            challengeId: data.id || 0,
            myEmail: data.myEmail || '',
            joined: data.isJoined || false,
            joining: false,
            participantsCount: data.participantsCount || 0,
            submissions: data.submissions || [],

            photoFile: null,
            photoPreview: null,
            comment: '',
            submitting: false,
            submitError: '',

            emojiPacks: window.__EMOJI_PACKS__ || [],
            emojisByPack: window.__EMOJIS_BY_PACK__ || {},
            activeEmojiPack: null,
            showSubmitEmoji: false,

            showComments: {},
            commentText: {},
            showCommentEmoji: {},
            commentPosting: {},
            editingCommentId: null,
            editCommentContent: {},
            showEditCommentEmoji: {},
            savingCommentEdit: {},

            showImageModal: false,
            modalImageUrl: '',

            editingSubmission: null,
            editSubContent: '',
            editSubPhotoFile: null,
            editSubPhotoPreview: null,
            editSubPhotoRemoved: false,
            savingSubEdit: false,

            init() {
                if (this.emojiPacks.length > 0) {
                    this.activeEmojiPack = this.emojiPacks[0].id;
                }
            },

            getActiveEmojis() {
                return this.activeEmojiPack ? (this.emojisByPack[this.activeEmojiPack] || []) : [];
            },

            // ==========================================
            // ЭМОДЗИ
            // ==========================================
            insertEmojiToElement(elementId, emojiUrl, emojiCode = '') {
                const input = document.getElementById(elementId);
                if (!input || !emojiUrl) return;

                input.blur();

                const img = document.createElement('img');
                img.src = emojiUrl;
                img.alt = emojiCode;
                img.className = 'inline-emoji';
                img.contentEditable = 'false';
                img.draggable = false;

                const range = document.createRange();
                range.selectNodeContents(input);
                range.collapse(false);
                range.insertNode(img);

                const spacer = document.createTextNode('');
                img.parentNode.insertBefore(spacer, img.nextSibling);

                if (elementId === 'submit-comment-input') {
                    this.comment = input.innerHTML;
                } else if (elementId.startsWith('comment-input-')) {
                    const sId = elementId.replace('comment-input-', '');
                    this.commentText[sId] = input.innerHTML;
                } else if (elementId.startsWith('edit-comment-input-')) {
                    const cId = elementId.replace('edit-comment-input-', '');
                    this.editCommentContent[cId] = input.innerHTML;
                }
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

            toggleSubmitEmoji() {
                const input = document.getElementById('submit-comment-input');
                if (this.showSubmitEmoji) {
                    this.showSubmitEmoji = false;
                    this.$nextTick(() => { this.moveCursorToEnd(input); });
                } else {
                    if (input) input.blur();
                    this.showSubmitEmoji = true;
                }
            },

            toggleCommentEmoji(submissionId) {
                const input = document.getElementById('comment-input-' + submissionId);
                if (this.showCommentEmoji[submissionId]) {
                    this.showCommentEmoji[submissionId] = false;
                    this.$nextTick(() => { this.moveCursorToEnd(input); });
                } else {
                    if (input) input.blur();
                    this.showCommentEmoji[submissionId] = true;
                }
            },

            toggleEditCommentEmoji(commentId) {
                const input = document.getElementById('edit-comment-input-' + commentId);
                if (this.showEditCommentEmoji[commentId]) {
                    this.showEditCommentEmoji[commentId] = false;
                    this.$nextTick(() => { this.moveCursorToEnd(input); });
                } else {
                    if (input) input.blur();
                    this.showEditCommentEmoji[commentId] = true;
                }
            },

            addEmojiToSubmit(emoji) { this.insertEmojiToElement('submit-comment-input', emoji.image_url, emoji.code); },
            addEmojiToComment(submissionId, emoji) { this.insertEmojiToElement('comment-input-' + submissionId, emoji.image_url, emoji.code); },
            addEmojiToEditComment(commentId, emoji) { this.insertEmojiToElement('edit-comment-input-' + commentId, emoji.image_url, emoji.code); },

            formatDate(date) {
                if (!date) return '';
                return new Date(date).toLocaleDateString('ru-RU', { day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' });
            },

            openImageModal(url) { this.modalImageUrl = url; this.showImageModal = true; },
            closeImageModal() { this.showImageModal = false; this.modalImageUrl = ''; },

            // ==========================================
            // ОТПРАВКА РАБОТЫ
            // ==========================================
            handlePhoto(event) {
                var file = event.target.files[0];
                if (!file) return;
                if (file.size > 5 * 1024 * 1024) { this.submitError = 'Файл больше 5 МБ'; return; }
                this.photoFile = file;
                var self = this;
                var reader = new FileReader();
                reader.onload = function(e) { self.photoPreview = e.target.result; };
                reader.readAsDataURL(file);
                event.target.value = '';
            },

            removePhoto() { this.photoFile = null; this.photoPreview = null; },

            onDescriptionKeydown(event) {
                if (event.key === 'Enter' && !event.shiftKey && !event.ctrlKey && !event.metaKey) {
                    event.preventDefault();
                    document.execCommand('insertHTML', false, '<br>');
                    var el = document.getElementById('submit-comment-input');
                    if (el) this.comment = el.innerHTML;
                    return;
                }
                if (event.key === 'Enter' && (event.ctrlKey || event.metaKey)) {
                    event.preventDefault();
                    this.submitWork();
                }
            },

            async submitWork() {
                var el = document.getElementById('submit-comment-input');
                if (el) this.comment = el.innerHTML;

                if (!this.comment.trim() && !this.photoFile) {
                    this.submitError = 'Добавьте фото или описание';
                    return;
                }

                this.submitting = true;
                this.submitError = '';
                try {
                    var formData = new FormData();
                    formData.append('challenge_id', this.challengeId);
                    formData.append('comment', this.comment);
                    if (this.photoFile) formData.append('photo', this.photoFile);
                    var resp = await fetch('/api/challenge_submit.php', { method: 'POST', body: formData });
                    var data = await resp.json();
                    if (data.ok) {
                        if (typeof toast === 'function') toast('Работа отправлена! 🎉', 'success');
                        setTimeout(function() { location.reload(); }, 800);
                    } else {
                        this.submitError = data.error || 'Ошибка';
                    }
                } catch (e) { this.submitError = e.message; }
                finally { this.submitting = false; }
            },

            // ==========================================
            // РЕДАКТИРОВАНИЕ РАБОТЫ
            // ==========================================
            editSubmission(sub) {
                this.editingSubmission = sub;
                this.editSubContent = sub.comment || '';
                this.editSubPhotoFile = null;
                this.editSubPhotoPreview = null;
                this.editSubPhotoRemoved = false;
            },

            removeEditSubPhoto() { this.editSubPhotoRemoved = true; },
            removeNewEditSubPhoto() { this.editSubPhotoFile = null; this.editSubPhotoPreview = null; },

            handleEditSubPhoto(event) {
                var file = event.target.files[0];
                if (!file) return;
                if (file.size > 5 * 1024 * 1024) { alert('Файл больше 5 МБ'); return; }
                this.editSubPhotoFile = file;
                this.editSubPhotoRemoved = false;
                var self = this;
                var reader = new FileReader();
                reader.onload = function(e) { self.editSubPhotoPreview = e.target.result; };
                reader.readAsDataURL(file);
                event.target.value = '';
            },

            async saveEditSubmission() {
                if (!this.editingSubmission) return;
                this.savingSubEdit = true;
                try {
                    var formData = new FormData();
                    formData.append('submission_id', this.editingSubmission.id);
                    formData.append('comment', this.editSubContent);
                    formData.append('photo_removed', this.editSubPhotoRemoved ? '1' : '0');
                    if (this.editSubPhotoFile) formData.append('photo', this.editSubPhotoFile);
                    var resp = await fetch('/api/challenge_update.php', { method: 'POST', body: formData });
                    var data = await resp.json();
                    if (data.ok) {
                        this.editingSubmission = null;
                        if (typeof toast === 'function') toast('Работа обновлена! ✅', 'success');
                        setTimeout(function() { location.reload(); }, 500);
                    } else { alert(data.error || 'Ошибка'); }
                } catch (e) { alert('Ошибка соединения'); }
                finally { this.savingSubEdit = false; }
            },

            async deleteSubmission(subId) {
                if (!confirm('Удалить эту работу?')) return;
                try {
                    var resp = await fetch('/api/challenge_delete.php?id=' + subId, { method: 'DELETE' });
                    var data = await resp.json();
                    if (data.ok) {
                        if (typeof toast === 'function') toast('Работа удалена', 'info');
                        setTimeout(function() { location.reload(); }, 500);
                    } else { alert(data.error || 'Ошибка'); }
                } catch (e) { alert('Ошибка соединения'); }
            },

            // ==========================================
            // КОММЕНТАРИИ
            // ==========================================
            toggleComments(submissionId) {
                this.showComments[submissionId] = !this.showComments[submissionId];
            },

            onCommentKeydown(event, submissionId) {
                if (event.key === 'Enter' && !event.shiftKey) {
                    event.preventDefault();
                    this.submitComment(submissionId);
                }
            },

            async submitComment(submissionId) {
                var el = document.getElementById('comment-input-' + submissionId);
                if (el) this.commentText[submissionId] = el.innerHTML;
                var content = (this.commentText[submissionId] || '').trim();
                if (!content) return;

                this.commentPosting[submissionId] = true;
                try {
                    var formData = new FormData();
                    formData.append('submission_id', submissionId);
                    formData.append('comment', content);
                    var resp = await fetch('/api/challenge_comments.php', { method: 'POST', body: formData });
                    var data = await resp.json();
                    if (data.ok) {
                        this.commentText[submissionId] = '';
                        if (el) el.innerHTML = '';
                        this.showCommentEmoji[submissionId] = false;
                        await this.loadComments(submissionId);
                    } else {
                        alert(data.error || 'Ошибка');
                    }
                } catch (e) {
                    alert('Ошибка соединения');
                } finally {
                    this.commentPosting[submissionId] = false;
                }
            },

            async loadComments(submissionId) {
                try {
                    var resp = await fetch('/api/challenge_comments.php?submission_id=' + submissionId);
                    var data = await resp.json();
                    if (data.ok) {
                        var sub = this.submissions.find(function(s) { return s.id === submissionId; });
                        if (sub) {
                            var oldVotes = {};
                            (sub.comments || []).forEach(function(c) {
                                oldVotes[c.id] = { like_count: c.like_count, dislike_count: c.dislike_count, my_vote: c.my_vote };
                            });
                            (data.comments || []).forEach(function(c) {
                                var ov = oldVotes[c.id];
                                if (ov) {
                                    c.like_count = ov.like_count || 0;
                                    c.dislike_count = ov.dislike_count || 0;
                                    c.my_vote = ov.my_vote || 0;
                                } else {
                                    c.like_count = 0;
                                    c.dislike_count = 0;
                                    c.my_vote = 0;
                                }
                            });
                            sub.comments = data.comments || [];
                            sub.comment_count = sub.comments.length;
                        }
                    }
                } catch (e) { console.error(e); }
            },

            editComment(comment) {
                this.editingCommentId = comment.id;
                this.editCommentContent[comment.id] = comment.comment || '';
                this.showEditCommentEmoji[comment.id] = false;
                var self = this;
                this.$nextTick(function() {
                    var el = document.getElementById('edit-comment-input-' + comment.id);
                    if (el) { el.innerHTML = comment.comment || ''; el.focus(); }
                });
            },

            cancelEditComment() {
                this.editingCommentId = null;
                this.showEditCommentEmoji = {};
            },

            onEditCommentKeydown(event, commentId, submissionId) {
                if (event.key === 'Enter' && !event.shiftKey && !event.ctrlKey && !event.metaKey) {
                    event.preventDefault();
                    document.execCommand('insertHTML', false, '<br>');
                    var el = document.getElementById('edit-comment-input-' + commentId);
                    if (el) this.editCommentContent[commentId] = el.innerHTML;
                    return;
                }
                if (event.key === 'Enter' && (event.ctrlKey || event.metaKey)) {
                    event.preventDefault();
                    this.saveEditComment(commentId, submissionId);
                }
            },

            async saveEditComment(commentId, submissionId) {
                var el = document.getElementById('edit-comment-input-' + commentId);
                if (el) this.editCommentContent[commentId] = el.innerHTML;
                var content = (this.editCommentContent[commentId] || '').trim();
                if (!content) return;

                this.savingCommentEdit[commentId] = true;
                try {
                    var formData = new FormData();
                    formData.append('action', 'edit_comment');
                    formData.append('comment_id', commentId);
                    formData.append('content', content);
                    var resp = await fetch('/api/challenge_comments.php', { method: 'POST', body: formData });
                    var data = await resp.json();
                    if (data.ok) {
                        this.editingCommentId = null;
                        await this.loadComments(submissionId);
                    } else {
                        alert(data.error || 'Ошибка');
                    }
                } catch (e) { alert('Ошибка соединения'); }
                finally { this.savingCommentEdit[commentId] = false; }
            },

            async deleteComment(commentId, submissionId) {
                if (!confirm('Удалить комментарий?')) return;
                try {
                    var resp = await fetch('/api/challenge_comments.php?id=' + commentId, { method: 'DELETE' });
                    var data = await resp.json();
                    if (data.ok) { await this.loadComments(submissionId); }
                    else { alert(data.error || 'Ошибка'); }
                } catch (e) { alert('Ошибка соединения'); }
            },

            // ==========================================
            // ГОЛОСОВАНИЕ
            // ==========================================
            async vote(targetObj, targetType, targetId, voteValue) {
                var prevVote = targetObj.my_vote || 0;
                var prevLikes = targetObj.like_count || 0;
                var prevDislikes = targetObj.dislike_count || 0;

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
                    var formData = new FormData();
                    formData.append('target_type', targetType);
                    formData.append('target_id', targetId);
                    formData.append('vote', voteValue);
                    var resp = await fetch('/api/challenge_votes.php', { method: 'POST', body: formData });
                    var data = await resp.json();
                    if (data.ok) {
                        targetObj.my_vote = data.my_vote;
                        targetObj.like_count = data.like_count;
                        targetObj.dislike_count = data.dislike_count;
                    } else {
                        targetObj.my_vote = prevVote;
                        targetObj.like_count = prevLikes;
                        targetObj.dislike_count = prevDislikes;
                        alert(data.error || 'Ошибка голосования');
                    }
                } catch (e) {
                    targetObj.my_vote = prevVote;
                    targetObj.like_count = prevLikes;
                    targetObj.dislike_count = prevDislikes;
                    console.error('Vote error:', e);
                }
            },

            onPastePlain(event) {
                event.preventDefault();
                var text = (event.clipboardData || window.clipboardData).getData('text/plain');
                document.execCommand('insertText', false, text);
            }
        };
    });
});