document.addEventListener('alpine:init', () => {
    Alpine.data('communityDetail', (communityId, isMember, isOwner, myEmail) => ({
        communityId: communityId,
        isOwner: isOwner,
        myEmail: myEmail,
        joined: isMember,

        // Состояние постов
        posts: [],
        newPost: '',
        postPhotoFile: null,
        postPhotoPreview: null,
        posting: false,

        // Эмодзи
        showEmojiPanel: false,
        showCommentEmoji: {},
        showEditPostEmoji: false,
        activeEmojiPack: null,
        emojiPacks: window.__EMOJI_PACKS__ || [],
        emojisByPack: window.__EMOJIS_BY_PACK__ || {},

        // Модалки и обложка
        showInviteModal: false,
        inviteSearch: '',
        inviteUsers: [],
        invitedEmails: [],
        showCoverModal: false,
        coverFile: null,
        coverPreview: null,
        savingCover: false,
        coverError: '',
        currentCoverSrc: window.__COMMUNITY_COVER__ || window.__DEFAULT_COMMUNITY_COVER__,

        // Редактирование поста
        editingPost: null,
        editContent: '',
        editPhotoFile: null,
        editPhotoPreview: null,
        editPhotoRemoved: false,
        savingEdit: false,

        // Комментарии
        postComments: {},
        commentText: {},
        showComments: {},
        commentPosting: {},
        loadedComments: {},
        editingCommentId: null,
        editCommentContent: {},
        savingCommentEdit: {},

        // Просмотр изображений
        showImageModal: false,
        modalImageUrl: '',

        // Управление сообществом
        loading: false,
        leaving: false,

        init() {
            if (this.emojiPacks.length > 0) {
                this.activeEmojiPack = this.emojiPacks[0].id;
            }
            this.loadPosts();
        },

        getActiveEmojis() {
            return this.activeEmojiPack ? (this.emojisByPack[this.activeEmojiPack] || []) : [];
        },

        // ==========================================
        // ЛОГИКА ЭМОДЗИ
        // ==========================================

        insertEmojiToElement(elementId, emojiUrl, emojiCode = '') {
            const input = document.getElementById(elementId);
            if (!input || !emojiUrl) return;

            input.blur(); // Убираем фокус

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

            // Синхронизация
            if (elementId === 'post-input') this.newPost = input.innerHTML;
            else if (elementId === 'edit-post-input') this.editContent = input.innerHTML;
            else if (elementId.startsWith('comment-input-')) this.commentText[elementId.replace('comment-input-', '')] = input.innerHTML;
            else if (elementId.startsWith('edit-comment-input-')) this.editCommentContent[elementId.replace('edit-comment-input-', '')] = input.innerHTML;
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

        togglePostEmoji() {
            const input = document.getElementById('post-input');
            if (this.showEmojiPanel) {
                this.showEmojiPanel = false;
                this.$nextTick(() => { this.moveCursorToEnd(input); });
            } else {
                if (input) input.blur();
                this.showEmojiPanel = true;
            }
        },

        toggleCommentEmoji(postId) {
            const input = document.getElementById('comment-input-' + postId);
            if (this.showCommentEmoji[postId]) {
                this.showCommentEmoji[postId] = false;
                this.$nextTick(() => { this.moveCursorToEnd(input); });
            } else {
                if (input) input.blur();
                this.showCommentEmoji[postId] = true;
            }
        },

        toggleEditPostEmoji() {
            const input = document.getElementById('edit-post-input');
            if (this.showEditPostEmoji) {
                this.showEditPostEmoji = false;
                this.$nextTick(() => { this.moveCursorToEnd(input); });
            } else {
                if (input) input.blur();
                this.showEditPostEmoji = true;
            }
        },

        addEmojiToPost(emoji) { this.insertEmojiToElement('post-input', emoji.image_url, emoji.code); },
        addEmojiToComment(postId, emoji) { this.insertEmojiToElement('comment-input-' + postId, emoji.image_url, emoji.code); },
        addEmojiToEditPost(emoji) { this.insertEmojiToElement('edit-post-input', emoji.image_url, emoji.code); },

        // ==========================================
        // УТИЛИТЫ
        // ==========================================
        formatDate(date) {
            if (!date) return '';
            var d = new Date(date);
            return d.toLocaleDateString('ru-RU', { day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' });
        },

        openImageModal(url) { this.modalImageUrl = url; this.showImageModal = true; },
        closeImageModal() { this.showImageModal = false; this.modalImageUrl = ''; },
        getCsrfToken() { var m = document.querySelector('meta[name="csrf-token"]'); return m ? m.content : ''; },

        // ==========================================
        // ПОСТЫ
        // ==========================================
        async loadPosts() {
            try {
                var res = await fetch('/api/community_posts.php?community_id=' + this.communityId);
                var data = await res.json();
                if (data.ok) {
                    this.posts = data.posts || [];
                    this.loadPostVotes();
                }
            } catch (e) { console.error('Load posts error:', e); }
        },

        async loadPostVotes() {
            if (!this.posts.length) return;
            var ids = this.posts.map(function (p) { return p.id; });
            try {
                var resp = await fetch('/api/community_votes.php?target_type=post&target_ids=' + ids.join(','));
                var data = await resp.json();
                if (data.ok && data.votes) {
                    this.posts.forEach(function (p) {
                        var v = data.votes[p.id];
                        if (v) {
                            p.like_count = v.like_count || 0;
                            p.dislike_count = v.dislike_count || 0;
                            p.my_vote = v.my_vote || 0;
                        }
                    });
                }
            } catch (e) { console.error('loadPostVotes:', e); }
        },

        handlePostPhoto(event) {
            var file = event.target.files[0];
            if (!file) return;
            if (file.size > 5 * 1024 * 1024) { alert('Файл больше 5 МБ'); return; }
            this.postPhotoFile = file;
            var self = this;
            var reader = new FileReader();
            reader.onload = function (e) { self.postPhotoPreview = e.target.result; };
            reader.readAsDataURL(file);
            event.target.value = '';
        },

        removePostPhoto() {
            this.postPhotoFile = null;
            this.postPhotoPreview = null;
        },

        async post() {
            var el = document.getElementById('post-input');
            if (el) this.newPost = el.innerHTML;
            var content = this.newPost.trim();
            if (!content && !this.postPhotoFile) return;
            this.posting = true;
            try {
                var formData = new FormData();
                formData.append('community_id', this.communityId);
                formData.append('content', content);
                if (this.postPhotoFile) formData.append('photo', this.postPhotoFile);
                var csrf = this.getCsrfToken();
                if (csrf) formData.append('csrf_token', csrf);
                var response = await fetch('/api/community_posts.php', { method: 'POST', body: formData });
                var data = await response.json();
                if (data.ok) {
                    this.newPost = '';
                    if (el) el.innerHTML = '';
                    this.postPhotoFile = null;
                    this.postPhotoPreview = null;
                    this.showEmojiPanel = false;
                    await this.loadPosts();
                } else {
                    alert(data.error || 'Не удалось опубликовать пост');
                }
            } catch (e) {
                console.error('Post error:', e);
                alert('Ошибка соединения');
            } finally {
                this.posting = false;
            }
        },

        // ==========================================
        // ГОЛОСОВАНИЕ
        // ==========================================
        async vote(targetObj, targetType, targetId, voteValue, event) {
            if (event && event.target) {
                var btn = event.target.closest('.vote-btn');
                if (btn) { btn.classList.add('anim-click'); setTimeout(function () { btn.classList.remove('anim-click'); }, 200); }
            }
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
                var resp = await fetch('/api/community_votes.php', { method: 'POST', body: formData });
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

        // ==========================================
        // РЕДАКТИРОВАНИЕ ПОСТА
        // ==========================================
        editPost(post) {
            this.editingPost = post;
            this.editContent = post.content || '';
            this.editPhotoFile = null;
            this.editPhotoPreview = null;
            this.editPhotoRemoved = false;
            this.showEditPostEmoji = false;
            var self = this;
            this.$nextTick(function () {
                var el = document.getElementById('edit-post-input');
                if (el) el.innerHTML = self.editContent;
            });
        },

        removeEditPhoto() { this.editPhotoRemoved = true; this.editPhotoPreview = null; },
        removeNewEditPhoto() { this.editPhotoFile = null; this.editPhotoPreview = null; },

        handleEditPhoto(event) {
            var file = event.target.files[0];
            if (!file) return;
            if (file.size > 5 * 1024 * 1024) { alert('Файл больше 5 МБ'); return; }
            this.editPhotoFile = file;
            this.editPhotoRemoved = false;
            var self = this;
            var reader = new FileReader();
            reader.onload = function (e) { self.editPhotoPreview = e.target.result; };
            reader.readAsDataURL(file);
            event.target.value = '';
        },

        async saveEditPost() {
            if (!this.editingPost) return;
            var el = document.getElementById('edit-post-input');
            if (el) this.editContent = el.innerHTML;
            this.savingEdit = true;
            try {
                var formData = new FormData();
                formData.append('post_id', this.editingPost.id);
                formData.append('content', this.editContent);
                formData.append('photo_removed', this.editPhotoRemoved ? '1' : '0');
                if (this.editPhotoFile) formData.append('photo', this.editPhotoFile);
                var csrf = this.getCsrfToken();
                if (csrf) formData.append('csrf_token', csrf);
                var response = await fetch('/api/community_posts.php', { method: 'POST', headers: { 'X-HTTP-Method-Override': 'PUT' }, body: formData });
                var data = await response.json();
                if (data.ok) {
                    this.editingPost = null;
                    await this.loadPosts();
                } else { alert(data.error || 'Ошибка сохранения'); }
            } catch (e) {
                console.error('Save edit error:', e);
                alert('Ошибка соединения');
            } finally { this.savingEdit = false; }
        },

        async deletePost(postId) {
            if (!confirm('Удалить пост?')) return;
            try {
                var csrf = this.getCsrfToken();
                var response = await fetch('/api/community_posts.php?id=' + postId, { method: 'DELETE', headers: csrf ? { 'X-CSRF-Token': csrf } : {} });
                var data = await response.json();
                if (data.ok) { await this.loadPosts(); }
                else { alert(data.error || 'Ошибка удаления'); }
            } catch (e) {
                console.error('Delete error:', e);
                alert('Ошибка соединения');
            }
        },

        // ==========================================
        // КОММЕНТАРИИ
        // ==========================================
        async toggleComments(postId) {
            this.showComments[postId] = !this.showComments[postId];
            if (this.showComments[postId] && !this.loadedComments[postId]) {
                await this.loadComments(postId);
            }
        },

        async loadComments(postId) {
            try {
                var response = await fetch('/api/community_posts.php?action=comments&post_id=' + postId);
                var data = await response.json();
                if (data.ok) {
                    this.postComments[postId] = data.comments || [];
                    this.loadedComments[postId] = true;
                    this.loadCommentVotes(postId);
                }
            } catch (e) { console.error('Load comments error:', e); }
        },

        async loadCommentVotes(postId) {
            var comments = this.postComments[postId];
            if (!comments || !comments.length) return;
            var ids = comments.map(function (c) { return c.id; });
            try {
                var resp = await fetch('/api/community_votes.php?target_type=comment&target_ids=' + ids.join(','));
                var data = await resp.json();
                if (data.ok && data.votes) {
                    comments.forEach(function (c) {
                        var v = data.votes[c.id];
                        if (v) {
                            c.like_count = v.like_count || 0;
                            c.dislike_count = v.dislike_count || 0;
                            c.my_vote = v.my_vote || 0;
                        }
                    });
                }
            } catch (e) { console.error(e); }
        },

        async submitComment(postId) {
            var el = document.getElementById('comment-input-' + postId);
            if (el) this.commentText[postId] = el.innerHTML;
            var content = (this.commentText[postId] || '').trim();
            if (!content) return;
            this.commentPosting[postId] = true;
            try {
                var formData = new FormData();
                formData.append('action', 'comment');
                formData.append('post_id', postId);
                formData.append('content', content);
                var csrf = this.getCsrfToken();
                if (csrf) formData.append('csrf_token', csrf);
                var response = await fetch('/api/community_posts.php', { method: 'POST', body: formData });
                var data = await response.json();
                if (data.ok) {
                    this.commentText[postId] = '';
                    if (el) el.innerHTML = '';
                    this.showCommentEmoji[postId] = false;
                    await this.loadComments(postId);
                    await this.loadPosts();
                } else { alert(data.error || 'Ошибка отправки'); }
            } catch (e) {
                console.error('Comment error:', e);
                alert('Ошибка соединения');
            } finally { this.commentPosting[postId] = false; }
        },

        editComment(comment) {
            this.editingCommentId = comment.id;
            this.editCommentContent[comment.id] = comment.content || '';
            var self = this;
            this.$nextTick(function () {
                var el = document.getElementById('edit-comment-input-' + comment.id);
                if (el) { el.innerHTML = self.editCommentContent[comment.id]; el.focus(); }
            });
        },

        cancelEditComment() {
            this.editingCommentId = null;
        },

        async saveEditComment(commentId, postId) {
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
                var csrf = this.getCsrfToken();
                if (csrf) formData.append('csrf_token', csrf);
                var response = await fetch('/api/community_posts.php', { method: 'POST', body: formData });
                var data = await response.json();
                if (data.ok) {
                    this.editingCommentId = null;
                    await this.loadComments(postId);
                } else { alert(data.error || 'Ошибка сохранения'); }
            } catch (e) {
                console.error('Edit comment error:', e);
                alert('Ошибка соединения');
            } finally { this.savingCommentEdit[commentId] = false; }
        },

        async deleteComment(commentId, postId) {
            if (!confirm('Удалить комментарий?')) return;
            try {
                var response = await fetch('/api/community_posts.php?action=comment&id=' + commentId, { method: 'DELETE' });
                var data = await response.json();
                if (data.ok) {
                    await this.loadComments(postId);
                    await this.loadPosts();
                } else { alert(data.error || 'Ошибка удаления'); }
            } catch (e) {
                console.error('Delete comment error:', e);
                alert('Ошибка соединения');
            }
        },

        // ==========================================
        // КЛАВИАТУРА
        // ==========================================
        onPostKeydown(event) {
            if (event.key === 'Enter' && !event.shiftKey && !event.ctrlKey && !event.metaKey) {
                event.preventDefault();
                document.execCommand('insertHTML', false, '<br>');
                var el = document.getElementById('post-input');
                if (el) this.newPost = el.innerHTML;
                return;
            }
            if (event.key === 'Enter' && (event.ctrlKey || event.metaKey)) { event.preventDefault(); this.post(); }
        },

        onCommentKeydown(event, postId) {
            if (event.key === 'Enter' && !event.shiftKey) { event.preventDefault(); this.submitComment(postId); }
        },

        onEditCommentKeydown(event, commentId, postId) {
            if (event.key === 'Enter' && !event.shiftKey) { event.preventDefault(); document.execCommand('insertHTML', false, '<br>'); }
        },

        onEditPostKeydown(event) {
            if (event.key === 'Enter' && !event.shiftKey) { event.preventDefault(); document.execCommand('insertHTML', false, '<br>'); }
        },

        onPastePlain(event) {
            event.preventDefault();
            var text = (event.clipboardData || window.clipboardData).getData('text/plain');
            document.execCommand('insertText', false, text);
        },

        // ==========================================
        // УЧАСТНИКИ И ПРИГЛАШЕНИЯ
        // ==========================================
        async join() {
            if (this.loading || this.joined) return;
            this.loading = true;
            try {
                var body = new URLSearchParams();
                body.append('community_id', this.communityId);
                var res = await fetch('/api/community_join.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body.toString() });
                var data = await res.json();
                if (data.ok) this.joined = true;
                else alert(data.error || 'Не удалось вступить');
            } catch (e) { alert('Ошибка соединения'); } finally { this.loading = false; }
        },

        async leaveCommunity() {
            if (this.leaving) return;
            if (!confirm('Выйти из сообщества?')) return;
            this.leaving = true;
            try {
                var fd = new FormData();
                fd.append('community_id', this.communityId);
                var res = await fetch('/api/community_leave.php', { method: 'POST', body: fd });
                var data = await res.json();
                if (data.ok) this.joined = false;
                else alert(data.error || 'Не удалось выйти');
            } catch (e) { alert('Ошибка соединения'); } finally { this.leaving = false; }
        },

        async removeMember(email) {
            if (!email) return;
            if (!confirm('Удалить участника ' + email + ' из сообщества?')) return;
            try {
                var fd = new FormData();
                fd.append('community_id', this.communityId);
                fd.append('user_email', email);
                var res = await fetch('/api/community_remove_member.php', { method: 'POST', body: fd });
                var data = await res.json();
                if (data.ok) location.reload();
                else alert(data.error || 'Не удалось удалить участника');
            } catch (e) { alert('Ошибка соединения'); }
        },

        async searchUsers() {
            if (!this.inviteSearch.trim()) { this.inviteUsers = []; return; }
            try {
                var response = await fetch('/api/users_search.php?q=' + encodeURIComponent(this.inviteSearch));
                var data = await response.json();
                if (data.ok) this.inviteUsers = data.users || [];
            } catch (e) { console.error('Search error:', e); }
        },

        async inviteUser(email) {
            try {
                var response = await fetch('/api/community_invite.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ community_id: this.communityId, email: email }) });
                var data = await response.json();
                if (data.ok) this.invitedEmails.push(email);
            } catch (e) { console.error('Invite error:', e); }
        },

        // ==========================================
        // ОБЛОЖКА
        // ==========================================
        openCoverModal() {
            if (!this.isOwner) return;
            this.coverFile = null;
            this.coverPreview = null;
            this.coverError = '';
            this.showCoverModal = true;
        },

        closeCoverModal() {
            this.showCoverModal = false;
            this.coverFile = null;
            this.coverPreview = null;
            this.coverError = '';
        },

        handleCoverFile(event) {
            var file = event.target.files && event.target.files[0];
            if (!file) return;
            if (file.size > 5 * 1024 * 1024) { this.coverError = 'Файл больше 5 МБ'; event.target.value = ''; return; }
            this.coverError = '';
            this.coverFile = file;
            var self = this;
            var reader = new FileReader();
            reader.onload = function (e) { self.coverPreview = e.target.result; };
            reader.readAsDataURL(file);
            event.target.value = '';
        },

        removeCoverPreview() {
            this.coverFile = null;
            this.coverPreview = null;
        },

        async saveCover() {
            if (!this.coverFile) return;
            this.savingCover = true;
            this.coverError = '';
            try {
                var fd = new FormData();
                fd.append('action', 'update_cover');
                fd.append('community_id', this.communityId);
                fd.append('cover_image', this.coverFile);
                var csrf = this.getCsrfToken();
                if (csrf) fd.append('csrf_token', csrf);
                var res = await fetch('/api/communities.php', { method: 'POST', body: fd });
                var data = await res.json();
                if (data.ok) {
                    this.currentCoverSrc = data.cover_url || window.__DEFAULT_COMMUNITY_COVER__;
                    var coverImg = document.querySelector('section > div.relative > img');
                    if (coverImg) coverImg.src = this.currentCoverSrc;
                    this.closeCoverModal();
                    if (typeof toast === 'function') toast('Обложка обновлена', 'success');
                } else { this.coverError = data.error || 'Ошибка загрузки'; }
            } catch (err) { this.coverError = 'Ошибка соединения'; } finally { this.savingCover = false; }
        },

        async removeCoverImage() {
            if (!confirm('Удалить обложку? Будет использована стандартная.')) return;
            this.savingCover = true;
            this.coverError = '';
            try {
                var fd = new FormData();
                fd.append('action', 'update_cover');
                fd.append('community_id', this.communityId);
                fd.append('cover_removed', '1');
                var csrf = this.getCsrfToken();
                if (csrf) fd.append('csrf_token', csrf);
                var res = await fetch('/api/communities.php', { method: 'POST', body: fd });
                var data = await res.json();
                if (data.ok) {
                    this.currentCoverSrc = window.__DEFAULT_COMMUNITY_COVER__;
                    var coverImg = document.querySelector('section > div.relative > img');
                    if (coverImg) coverImg.src = this.currentCoverSrc;
                    this.closeCoverModal();
                    if (typeof toast === 'function') toast('Обложка удалена', 'success');
                } else { this.coverError = data.error || 'Ошибка'; }
            } catch (err) { this.coverError = 'Ошибка соединения'; } finally { this.savingCover = false; }
        }
    }));
});