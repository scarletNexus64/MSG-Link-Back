<?php
/**
 * Script de vérification des limites PHP
 * Accédez à http://10.144.156.28:8000/check-limits.php pour voir les limites actuelles
 */

header('Content-Type: application/json');

$limits = [
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'post_max_size' => ini_get('post_max_size'),
    'max_execution_time' => ini_get('max_execution_time'),
    'max_input_time' => ini_get('max_input_time'),
    'memory_limit' => ini_get('memory_limit'),
    'file_uploads' => ini_get('file_uploads') ? 'Enabled' : 'Disabled',
    'max_file_uploads' => ini_get('max_file_uploads'),
];

// Convertir en bytes pour comparaison
function convertToBytes($value) {
    $value = trim($value);
    $last = strtolower($value[strlen($value)-1]);
    $value = (int)$value;

    switch($last) {
        case 'g': $value *= 1024;
        case 'm': $value *= 1024;
        case 'k': $value *= 1024;
    }

    return $value;
}

$uploadMaxBytes = convertToBytes($limits['upload_max_filesize']);
$postMaxBytes = convertToBytes($limits['post_max_size']);

$response = [
    'status' => 'OK',
    'limits' => $limits,
    'analysis' => [
        'upload_max_filesize_bytes' => $uploadMaxBytes,
        'post_max_size_bytes' => $postMaxBytes,
        'upload_max_mb' => round($uploadMaxBytes / (1024 * 1024), 2),
        'post_max_mb' => round($postMaxBytes / (1024 * 1024), 2),
    ],
    'recommendations' => [],
];

// Vérifier si les limites sont suffisantes
if ($uploadMaxBytes < 50 * 1024 * 1024) {
    $response['recommendations'][] = "⚠️ upload_max_filesize est trop bas. Recommandé: 50M";
    $response['status'] = 'WARNING';
}

if ($postMaxBytes < 50 * 1024 * 1024) {
    $response['recommendations'][] = "⚠️ post_max_size est trop bas. Recommandé: 50M";
    $response['status'] = 'WARNING';
}

if (empty($response['recommendations'])) {
    $response['recommendations'][] = "✅ Toutes les limites sont correctement configurées";
}

echo json_encode($response, JSON_PRETTY_PRINT);
