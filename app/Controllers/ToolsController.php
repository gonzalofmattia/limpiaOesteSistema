<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;

final class ToolsController extends Controller
{
    public function fixStock(): void
    {
        $mode = trim((string) $this->query('mode', 'report'));
        $allowed = ['report', 'fix_committed', 'fix_physical_preview', 'fix_physical_apply'];
        if (!in_array($mode, $allowed, true)) {
            $mode = 'report';
        }
        $qs = '?mode=' . urlencode($mode);
        $token = trim((string) $this->query('token', ''));
        $ts = trim((string) $this->query('ts', ''));
        if ($token !== '' && $ts !== '') {
            $qs .= '&token=' . urlencode($token) . '&ts=' . urlencode($ts);
        }
        redirect('/fix_stock.php' . $qs);
    }

    public function fixStockApply(): void
    {
        if (!verifyCsrf()) {
            flash('error', 'Token inválido.');
            redirect('/fix-stock?mode=fix_physical_preview');
            return;
        }
        $mode = trim((string) $this->input('mode', 'fix_physical_apply'));
        $token = trim((string) $this->input('token', ''));
        $ts = trim((string) $this->input('ts', ''));
        if ($mode !== 'fix_physical_apply' || $token === '' || $ts === '') {
            flash('error', 'Datos incompletos para aplicar corrección.');
            redirect('/fix-stock?mode=fix_physical_preview');
            return;
        }
        redirect('/fix_stock.php?mode=fix_physical_apply&token=' . urlencode($token) . '&ts=' . urlencode($ts));
    }
}
