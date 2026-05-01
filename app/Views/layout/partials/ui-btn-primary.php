<?php
declare(strict_types=1);
/** @var string $uiBtnHref */
/** @var string $uiBtnLabel */
$icon = isset($uiPrimaryIcon) && is_string($uiPrimaryIcon) && $uiPrimaryIcon !== ''
    ? $uiPrimaryIcon
    : 'plus';
?>
<a href="<?= e($uiBtnHref) ?>" class="inline-flex w-full sm:w-auto items-center justify-center gap-2 px-5 py-2.5 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors">
    <i data-lucide="<?= e($icon) ?>" class="w-4 h-4 shrink-0"></i><span><?= e($uiBtnLabel) ?></span>
</a>
