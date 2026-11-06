/**
 * GigaChefAI — общая клиентская логика (Alpine-хелперы, тосты, избранное, лайки)
 */
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/sw.js')
        .then(reg => console.log('SW OK:', reg.scope))
        .catch(err => console.error('SW error:', err));
}
// Глобальный тост
window.toast = function (message, type = 'info') {
    const colors = {
        info: 'bg-amber-100 text-amber-800 border-amber-200',
        success: 'bg-emerald-100 text-emerald-800 border-emerald-200',
        error: 'bg-rose-100 text-rose-800 border-rose-200',
    };
    const el = document.createElement('div');
    el.className = `fixed bottom-5 right-5 z-[100] px-4 py-3 rounded-xl border shadow-lg text-sm font-semibold ${colors[type] || colors.info} transition-all`;
    el.style.opacity = '0';
    el.style.transform = 'translateY(10px)';
    el.textContent = message;
    document.body.appendChild(el);
    requestAnimationFrame(() => {
        el.style.opacity = '1';
        el.style.transform = 'translateY(0)';
    });
    setTimeout(() => {
        el.style.opacity = '0';
        el.style.transform = 'translateY(10px)';
        setTimeout(() => el.remove(), 300);
    }, 3000);
};

// Получение CSRF токена
function getCsrfToken() {
    return window.__CSRF_TOKEN__ || 
           document.querySelector('meta[name="csrf-token"]')?.content || 
           '';
}

// ===== ИЗБРАННОЕ (Закладка) =====
window.saveRecipe = async function (recipeId, btn) {
    try {
        const response = await fetch('/api/saved_recipes.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': getCsrfToken(),
            },
            body: JSON.stringify({ recipe_id: recipeId }),
        });

        const text = await response.text();
        let result;
        try {
            result = JSON.parse(text);
        } catch (e) {
            console.error('Invalid JSON:', text);
            return;
        }

        if (result.ok) {
            // Обновляем SVG внутри кнопки
            if (btn) {
                const svg = btn.querySelector('svg');
                if (svg) {
                    if (result.saved) {
                        svg.setAttribute('fill', 'currentColor');
                        btn.classList.add('text-amber-500');
                        btn.classList.remove('text-gray-400');
                        btn.title = 'Убрать из избранного';
                        btn.setAttribute('aria-label', 'Убрать из избранного');
                    } else {
                        svg.setAttribute('fill', 'none');
                        btn.classList.remove('text-amber-500');
                        btn.classList.add('text-gray-400');
                        btn.title = 'Сохранить в избранное';
                        btn.setAttribute('aria-label', 'Сохранить в избранное');
                    }
                }
                
                toast(result.saved ? '📌 Сохранено в избранном!' : 'Убрано из избранного', 
                      result.saved ? 'success' : 'info');
            }
            
            // Обновляем все кнопки сохранения для этого рецепта
            updateSaveButtons(recipeId, result.saved);
        } else {
            toast(result.error || 'Ошибка', 'error');
        }
    } catch (e) {
        console.error('Save recipe error:', e);
        if (e.message && e.message.includes('401')) {
            if (confirm('Чтобы сохранять рецепты, нужно войти. Перейти на страницу входа?')) {
                window.location.href = '/login';
            }
        } else {
            toast('Не удалось сохранить', 'error');
        }
    }
};

// Обновляет все кнопки сохранения для рецепта
function updateSaveButtons(recipeId, isSaved) {
    document.querySelectorAll(`[onclick*="saveRecipe(${recipeId}"]`).forEach((btn) => {
        const svg = btn.querySelector('svg');
        if (svg) {
            if (isSaved) {
                svg.setAttribute('fill', 'currentColor');
                btn.classList.add('text-amber-500');
                btn.classList.remove('text-gray-400');
                btn.title = 'Убрать из избранного';
            } else {
                svg.setAttribute('fill', 'none');
                btn.classList.remove('text-amber-500');
                btn.classList.add('text-gray-400');
                btn.title = 'Сохранить в избранное';
            }
        }
    });
}

// ===== ЛАЙКИ (Сердце) =====
window.toggleLike = async function (recipeId, btn) {
    try {
        const response = await fetch('/api/recipe_likes.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': getCsrfToken(),
            },
            body: JSON.stringify({ recipe_id: recipeId }),
        });

        const text = await response.text();
        let result;
        try {
            result = JSON.parse(text);
        } catch (e) {
            console.error('Invalid JSON:', text);
            return;
        }

        if (result.ok) {
            // Обновляем текст счетчика
            if (btn) {
                const countSpan = btn.querySelector('.likes-count') || btn.querySelector('span');
                if (countSpan) {
                    countSpan.textContent = result.likes_count;
                }
                
                // Обновляем цвет
                if (result.liked) {
                    btn.classList.add('text-rose-400');
                } else {
                    btn.classList.remove('text-rose-400');
                }
            }
            
            // Обновляем все элементы с data-likes-count
            document.querySelectorAll(`[data-likes-count="${recipeId}"]`).forEach(el => {
                el.textContent = result.likes_count;
            });
            
            toast(result.liked ? '❤️ Лайк поставлен!' : 'Лайк убран', 
                  result.liked ? 'success' : 'info');
        }
    } catch (e) {
        console.error('Like error:', e);
        if (e.message && e.message.includes('401')) {
            if (confirm('Чтобы ставить лайки, нужно войти. Перейти на страницу входа?')) {
                window.location.href = '/login';
            }
        } else {
            toast('Не удалось обработать лайк', 'error');
        }
    }
};

// Проверка статуса избранного при загрузке
document.addEventListener('DOMContentLoaded', async () => {
    const buttons = document.querySelectorAll('[data-check-saved]');
    for (const btn of buttons) {
        const recipeId = btn.dataset.checkSaved;
        if (!recipeId) continue;

        try {
            const res = await fetch(`/api/saved_recipes.php?recipe_id=${recipeId}`);
            const text = await res.text();
            let data;
            try {
                data = JSON.parse(text);
            } catch (e) {
                continue;
            }

            if (data.ok && data.saved) {
                const svg = btn.querySelector('svg');
                if (svg) {
                    svg.setAttribute('fill', 'currentColor');
                }
                btn.classList.add('text-amber-500');
                btn.classList.remove('text-gray-400');
                btn.title = 'Убрать из избранного';
            }
        } catch (e) {
            console.error('Check saved error:', e);
        }
    }
});