document.addEventListener('DOMContentLoaded', function() {
    var modal = document.getElementById('modal-new-chat');
    var btnNewChat = document.getElementById('btn-new-chat');
    var btnClose = document.getElementById('modal-close');
    var btnCancelNew = document.getElementById('btn-cancel-new');
    var tabPersonal = document.getElementById('tab-personal');
    var tabGroup = document.getElementById('tab-group');
    var usersList = document.getElementById('chat-users-list');
    var groupPreview = document.getElementById('group-chat-preview');
    var participantsCount = document.getElementById('group-participants-count');
    var btnCreate = document.getElementById('btn-create-chat');

    var isGroupMode = false;

    function openModal() {
        modal.classList.remove('hidden');
        modal.setAttribute('aria-hidden', 'false');
    }

    function closeModal() {
        modal.classList.add('hidden');
        modal.setAttribute('aria-hidden', 'true');
    }

    btnNewChat.addEventListener('click', openModal);
    btnClose.addEventListener('click', closeModal);
    btnCancelNew.addEventListener('click', closeModal);

    modal.addEventListener('click', function(e) {
        if (e.target === modal) closeModal();
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && !modal.classList.contains('hidden')) closeModal();
    });

    function setMode(group) {
        isGroupMode = group;
        if (group) {
            tabGroup.classList.add('bg-amber-100', 'border-amber-400', 'text-amber-700');
            tabPersonal.classList.remove('bg-amber-100', 'border-amber-400', 'text-amber-700');
            groupPreview.classList.remove('hidden');
        } else {
            tabPersonal.classList.add('bg-amber-100', 'border-amber-400', 'text-amber-700');
            tabGroup.classList.remove('bg-amber-100', 'border-amber-400', 'text-amber-700');
            groupPreview.classList.add('hidden');
        }
        updateCreateButton();
        if (!group) {
            var checked = usersList.querySelectorAll('input[type="checkbox"]:checked');
            for (var i = 0; i < checked.length; i++) {
                checked[i].checked = false;
                checked[i].closest('label').classList.remove('ring-2', 'ring-amber-400', 'bg-amber-50');
            }
        }
    }

    tabPersonal.addEventListener('click', function() { setMode(false); });
    tabGroup.addEventListener('click', function() { setMode(true); });

    usersList.addEventListener('change', function(e) {
        if (!e.target.matches('input[type="checkbox"]')) return;

        var label = e.target.closest('label');

        if (e.target.checked) {
            label.classList.add('ring-2', 'ring-amber-400', 'bg-amber-50');
        } else {
            label.classList.remove('ring-2', 'ring-amber-400', 'bg-amber-50');
        }

        if (!isGroupMode && e.target.checked) {
            var others = usersList.querySelectorAll('input[type="checkbox"]:checked');
            for (var j = 0; j < others.length; j++) {
                if (others[j] !== e.target) {
                    others[j].checked = false;
                    others[j].closest('label').classList.remove('ring-2', 'ring-amber-400', 'bg-amber-50');
                }
            }
        }

        updateCreateButton();
    });

    function getSelectedEmails() {
        var checkboxes = usersList.querySelectorAll('input[type="checkbox"]:checked');
        var emails = [];
        for (var k = 0; k < checkboxes.length; k++) {
            emails.push(checkboxes[k].value);
        }
        return emails;
    }

    function updateCreateButton() {
        var selected = getSelectedEmails();
        if (isGroupMode) {
            btnCreate.disabled = selected.length < 2;
            participantsCount.textContent = String(selected.length);
        } else {
            btnCreate.disabled = selected.length !== 1;
        }
    }

    btnCreate.addEventListener('click', function() {
        var selected = getSelectedEmails();
        if (selected.length === 0) return;

        if (isGroupMode && selected.length >= 2) {
            // Заглушка: выводим сообщение
            alert('Групповой чат с ' + selected.length + ' участниками будет создан. Подключите бэкенд.');
            // Реальный код:
            // fetch('/api/chats/create-group', {
            //     method: 'POST',
            //     headers: { 'Content-Type': 'application/json' },
            //     body: JSON.stringify({ users: selected })
            // }).then(function(r) { return r.json(); })
            //   .then(function(data) { window.location.href = '/chat/group/' + data.group_id; });
        } else if (!isGroupMode && selected.length === 1) {
            window.location.href = '/chat/' + encodeURIComponent(selected[0]);
        }
    });

    // --- Удаление чата ---
    var deleteButtons = document.querySelectorAll('[data-chat-email]');
    for (var d = 0; d < deleteButtons.length; d++) {
        deleteButtons[d].addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();

            var email = this.getAttribute('data-chat-email');
            if (!confirm('Удалить чат с ' + email + '? Все сообщения будут потеряны.')) return;

            // Заглушка: плавно скрываем карточку
            var card = this.closest('a');
            card.style.transition = 'opacity 0.3s, transform 0.3s';
            card.style.opacity = '0';
            card.style.transform = 'translateX(-20px)';
            var self = this;
            setTimeout(function() { card.remove(); }, 300);

            // Реальный код:
            // fetch('/api/chats/delete', {
            //     method: 'POST',
            //     headers: { 'Content-Type': 'application/json' },
            //     body: JSON.stringify({ email: email })
            // }).then(function(r) { return r.json(); })
            //   .then(function(data) {
            //       if (data.success) {
            //           card.style.transition = 'opacity 0.3s, transform 0.3s';
            //           card.style.opacity = '0';
            //           card.style.transform = 'translateX(-20px)';
            //           setTimeout(function() { card.remove(); }, 300);
            //       }
            //   });
        });
    }

    // --- Закрепление чата ---
    var pinButtons = document.querySelectorAll('[data-pin-email]');
    for (var p = 0; p < pinButtons.length; p++) {
        pinButtons[p].addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();

            var email = this.getAttribute('data-pin-email');
            var card = this.closest('a');
            var star = this.querySelector('.pin-star');

            // Заглушка: переключаем визуально
            if (star.classList.contains('text-amber-500')) {
                star.classList.remove('text-amber-500');
                star.classList.add('text-gray-300');
            } else {
                star.classList.remove('text-gray-300');
                star.classList.add('text-amber-500');
            }

            // Реальный код:
            // fetch('/api/chats/pin', {
            //     method: 'POST',
            //     headers: { 'Content-Type': 'application/json' },
            //     body: JSON.stringify({ email: email })
            // }).then(function(r) { return r.json(); })
            //   .then(function(data) {
            //       if (data.pinned) {
            //           // Переместить карточку в начало списка
            //           var container = card.parentElement;
            //           container.insertBefore(card, container.firstChild);
            //       }
            //   });
        });
    }

    setMode(false);
});
