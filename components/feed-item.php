<?php
/**
 * Элемент ленты = карточка активности + блок комментариев.
 *
 * Подключается ВМЕСТО components/activity-card.php:
 *   home.php, feed.php и api/feed_page.php (подгрузка при скролле).
 *
 * Ожидает в области видимости строку активности $item (из activity_feed).
 * Сама карточка (activity-card.php) не меняется.
 */
require_once __DIR__ . '/../includes/gc_social.php';

$gcActivityId = (int)($item['id'] ?? 0);

if ($gcActivityId <= 0) {
    include __DIR__ . '/activity-card.php';
    return;
}

$gcViewer   = gc_viewer();
$gcOwner    = (string)($item['user_email'] ?? '');
$gcVis      = (string)($item['visibility'] ?? 'public');
$gcIsOwner  = $gcViewer['logged'] && $gcViewer['email'] === $gcOwner;
// видимость уже отфильтрована запросом ленты; приватные доступны только автору
$gcCanComment = $gcViewer['logged'] && ($gcVis !== 'private' || $gcIsOwner);
?>
<div class="gc-feed-item" id="gc-post-<?= $gcActivityId ?>" data-gc-feed-item="<?= $gcActivityId ?>">
  <?php include __DIR__ . '/activity-card.php'; ?>
  <?= gc_render_comments_shell('feed', $gcActivityId, gc_count('feed', $gcActivityId), [
      'can_comment' => $gcCanComment,
      'owner_email' => $gcOwner,
      'owner_badge' => 'Автор',
      'hint'        => 'Комментарии недоступны',
  ]) ?>
</div>
