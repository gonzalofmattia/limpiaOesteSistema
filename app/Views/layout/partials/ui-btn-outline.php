<?php
declare(strict_types=1);
/** @var string $uiBtnHref */
/** @var string $uiBtnLabel */
$icon = isset($uiOutlineIcon) && is_string($uiOutlineIcon) && $uiOutlineIcon !== ''
    ? $uiOutlineIcon
    : 'upload';
?>
<a href="<?= e($uiBtnHref) ?>" class="inline-flex w-full sm:w-auto items-center justify-center gap-2 px-5 py-2.5 bg-white text-gray-700 text-sm font-medium rounded-lg border border-gray-300 hover:bg-gray-50 transition-colors">
    <i data-lucide="<?= e($icon) ?>" class="w-4 h-4 shrink-0 text-gray-500"></i><span><?= e($uiBtnLabel) ?></span>
</a>
