<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Validador\GestorSesiones;
use Validador\Logger;

$cfg = require __DIR__ . '/src/config.php';

ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/logs/php_errors.log');

$gestor   = new GestorSesiones($cfg['storage_dir']);
$error    = null;
$sesiones = [];

try {
    $sesiones = $gestor->listar();
} catch (\Throwable $e) {
    $error = $e->getMessage();
    Logger::error('Error listando sesiones', $e);
}

?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auditorías — Validador CPMS</title>
    <link rel="stylesheet" href="assets/estilos.css">
    <style>
        .progress-bar-wrap {
            background: var(--gray-border, #e2e8f0);
            border-radius: 99px;
            height: 10px;
            width: 120px;
            overflow: hidden;
            display: inline-block;
            vertical-align: middle;
        }
        .progress-bar-fill {
            height: 100%;
            background: var(--green, #198754);
            border-radius: 99px;
            transition: width 0.3s;
        }
        .progress-text {
            display: inline-block;
            vertical-align: middle;
            margin-left: 8px;
            font-size: 0.85rem;
            color: #555;
            white-space: nowrap;
        }
        .td-id {
            font-family: monospace;
            font-size: 0.78rem;
            color: #888;
            max-width: 120px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #777;
        }
        .empty-state p { margin: 0.5rem 0; }
        .ipress-list { font-size: 0.82rem; color: #555; }
        .badge-pendiente {
            display: inline-block;
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffc107;
            border-radius: 4px;
            padding: 1px 7px;
            font-size: 0.78rem;
            font-weight: 600;
        }
        .badge-completa {
            display: inline-block;
            background: #d1e7dd;
            color: #0f5132;
            border: 1px solid #a3cfbb;
            border-radius: 4px;
            padding: 1px 7px;
            font-size: 0.78rem;
            font-weight: 600;
        }
    </style>
</head>
<body>
<header class="site-header">
    <h1>Validador CPMS — Auditorías</h1>
    <p>Sesiones de auditoría registradas. Cada subida genera una sesión independiente.</p>
</header>

<main class="container">

    <?php if ($error): ?>
    <div class="alert alert-error">
        <strong>Error al cargar las sesiones.</strong><br>
        <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <div style="margin-bottom:1.5rem; display:flex; gap:1rem; align-items:center;">
        <a href="index.php" class="btn btn-outline">← Volver al validador</a>
        <span style="color:#888; font-size:0.9rem;">
            <?= count($sesiones) ?> sesión<?= count($sesiones) !== 1 ? 'es' : '' ?> registrada<?= count($sesiones) !== 1 ? 's' : '' ?>
        </span>
    </div>

    <?php if (empty($sesiones)): ?>
    <section class="card">
        <div class="empty-state">
            <p style="font-size:2rem">📂</p>
            <p><strong>No hay sesiones de auditoría todavía.</strong></p>
            <p>Sube un archivo Excel desde el <a href="index.php">validador</a> para crear la primera sesión.</p>
        </div>
    </section>
    <?php else: ?>

    <section class="card card-table">
        <h2 style="margin-bottom:1rem">Sesiones de auditoría</h2>
        <div class="table-wrap">
            <table id="tablaSesiones">
                <thead>
                    <tr>
                        <th>Archivo</th>
                        <th>Creada</th>
                        <th>IPRESS</th>
                        <th>Progreso</th>
                        <th>Estado</th>
                        <th>ID</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sesiones as $s): ?>
                    <?php
                        $fechaFormato = 'N/D';
                        try {
                            $dt = new \DateTimeImmutable($s['creada']);
                            $fechaFormato = $dt->format('d/m/Y H:i');
                        } catch (\Throwable) {}
                        $completa = $s['validadas'] >= $s['total'] && $s['total'] > 0;
                    ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($s['archivo']) ?></strong>
                            <div style="font-size:0.8rem;color:#888"><?= number_format($s['total']) ?> prestaciones</div>
                        </td>
                        <td style="white-space:nowrap"><?= htmlspecialchars($fechaFormato) ?></td>
                        <td class="ipress-list">
                            <?php if (!empty($s['ipress'])): ?>
                                <?= htmlspecialchars(implode(', ', array_column($s['ipress'], 'nombre'))) ?>
                            <?php else: ?>
                                <span style="color:#bbb">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="progress-bar-wrap">
                                <span class="progress-bar-fill"
                                      style="width:<?= min(100, (float)$s['progreso']) ?>%"></span>
                            </span>
                            <span class="progress-text">
                                <?= number_format($s['validadas']) ?> / <?= number_format($s['total']) ?>
                                (<?= number_format($s['progreso'], 1) ?>%)
                            </span>
                        </td>
                        <td>
                            <?php if ($completa): ?>
                            <span class="badge-completa">Completa</span>
                            <?php else: ?>
                            <span class="badge-pendiente">En curso</span>
                            <?php endif; ?>
                        </td>
                        <td class="td-id" title="<?= htmlspecialchars($s['id']) ?>">
                            <?= htmlspecialchars(substr($s['id'], 0, 8)) ?>…
                        </td>
                        <td style="white-space:nowrap">
                            <a href="revisar.php?id=<?= htmlspecialchars($s['id']) ?>"
                               class="btn btn-outline"
                               style="padding:.3rem .8rem;font-size:.78rem">
                                Revisar →
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <?php endif; ?>

</main>
</body>
</html>
