<?php
date_default_timezone_set('America/Mexico_City');
set_time_limit(0);

require dirname(__DIR__) . '/config/db.php';

$config = [
    'email_to' => ['jacardenas@secape.net', 'sedconanp@gmail.com'],
    'email_from' => 'contacto@sedconanp.online',
    'retention_days' => 30,
    'attach_max_mb' => 8,
    'backup_dir' => dirname(__DIR__) . '/../db_backups_conanp'
];

function send_mail_with_optional_attachment($to, $from, $subject, $message, $attachmentPath = null)
{
    if (is_array($to)) {
        $to = implode(',', array_filter(array_map('trim', $to)));
    }

    if (empty($attachmentPath) || !is_file($attachmentPath)) {
        $headers = "From: {$from}\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        return @mail($to, $subject, $message, $headers);
    }

    $fileContent = file_get_contents($attachmentPath);
    if ($fileContent === false) {
        return false;
    }

    $boundary = md5((string)microtime(true));
    $filename = basename($attachmentPath);

    $headers = "From: {$from}\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n";

    $body = "--{$boundary}\r\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
    $body .= $message . "\r\n\r\n";
    $body .= "--{$boundary}\r\n";
    $body .= "Content-Type: application/octet-stream; name=\"{$filename}\"\r\n";
    $body .= "Content-Transfer-Encoding: base64\r\n";
    $body .= "Content-Disposition: attachment; filename=\"{$filename}\"\r\n\r\n";
    $body .= chunk_split(base64_encode($fileContent)) . "\r\n";
    $body .= "--{$boundary}--";

    return @mail($to, $subject, $body, $headers);
}

function clean_old_backups($backupDir, $retentionDays)
{
    if (!is_dir($backupDir)) {
        return;
    }

    $limit = time() - ($retentionDays * 86400);
    $files = glob($backupDir . '/*.sql*');
    if ($files === false) {
        return;
    }

    foreach ($files as $file) {
        if (is_file($file) && filemtime($file) < $limit) {
            @unlink($file);
        }
    }
}

function quote_value(PDO $pdo, $value)
{
    if ($value === null) {
        return 'NULL';
    }
    return $pdo->quote((string)$value);
}

try {
    if (!is_dir($config['backup_dir'])) {
        if (!mkdir($config['backup_dir'], 0755, true) && !is_dir($config['backup_dir'])) {
            throw new RuntimeException('No se pudo crear directorio de respaldo: ' . $config['backup_dir']);
        }
    }

    $dbName = $pdo->query("SELECT DATABASE()")->fetchColumn();
    if (!$dbName) {
        throw new RuntimeException('No se pudo detectar el nombre de la base de datos.');
    }

    $timestamp = date('Ymd_His');
    $sqlFile = rtrim($config['backup_dir'], '/\\') . '/' . $dbName . '_' . $timestamp . '.sql';

    $handle = fopen($sqlFile, 'wb');
    if ($handle === false) {
        throw new RuntimeException('No se pudo abrir archivo para escritura: ' . $sqlFile);
    }

    fwrite($handle, "-- Backup generado: " . date('Y-m-d H:i:s') . "\n");
    fwrite($handle, "-- Base de datos: {$dbName}\n\n");
    fwrite($handle, "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n");
    fwrite($handle, "SET time_zone = \"+00:00\";\n\n");

    $tablesStmt = $pdo->query('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"');
    $tables = $tablesStmt->fetchAll(PDO::FETCH_NUM);

    foreach ($tables as $tableRow) {
        $table = $tableRow[0];
        fwrite($handle, "\n-- ----------------------------\n");
        fwrite($handle, "-- Tabla: `{$table}`\n");
        fwrite($handle, "-- ----------------------------\n");

        $createStmt = $pdo->query("SHOW CREATE TABLE `{$table}`");
        $createRow = $createStmt->fetch(PDO::FETCH_ASSOC);
        $createSql = $createRow['Create Table'] ?? null;

        if (!$createSql) {
            continue;
        }

        fwrite($handle, "DROP TABLE IF EXISTS `{$table}`;\n");
        fwrite($handle, $createSql . ";\n\n");

        $dataStmt = $pdo->query("SELECT * FROM `{$table}`", PDO::FETCH_ASSOC);
        while ($row = $dataStmt->fetch(PDO::FETCH_ASSOC)) {
            $columns = array_map(function ($column) {
                return "`{$column}`";
            }, array_keys($row));

            $values = array_map(function ($value) use ($pdo) {
                return quote_value($pdo, $value);
            }, array_values($row));

            $insertSql = "INSERT INTO `{$table}` (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $values) . ");\n";
            fwrite($handle, $insertSql);
        }

        fwrite($handle, "\n");
    }

    fclose($handle);

    $finalFile = $sqlFile;
    if (function_exists('gzopen')) {
        $gzFile = $sqlFile . '.gz';
        $in = fopen($sqlFile, 'rb');
        $out = gzopen($gzFile, 'wb9');

        if ($in !== false && $out !== false) {
            while (!feof($in)) {
                gzwrite($out, (string)fread($in, 1024 * 512));
            }
            fclose($in);
            gzclose($out);
            @unlink($sqlFile);
            $finalFile = $gzFile;
        } else {
            if ($in !== false) {
                fclose($in);
            }
            if ($out !== false) {
                gzclose($out);
            }
        }
    }

    clean_old_backups($config['backup_dir'], (int)$config['retention_days']);

    $sizeMb = round(filesize($finalFile) / 1024 / 1024, 2);
    $subject = '[CONANP] Backup diario OK - ' . $dbName;
    $message = "Backup generado correctamente.\n";
    $message .= "Fecha: " . date('Y-m-d H:i:s') . "\n";
    $message .= "Base: {$dbName}\n";
    $message .= "Archivo: {$finalFile}\n";
    $message .= "Tamaño: {$sizeMb} MB\n";

    $attachFile = null;
    $maxBytes = (int)$config['attach_max_mb'] * 1024 * 1024;
    if (is_file($finalFile) && filesize($finalFile) <= $maxBytes) {
        $attachFile = $finalFile;
    }

    send_mail_with_optional_attachment(
        $config['email_to'],
        $config['email_from'],
        $subject,
        $message,
        $attachFile
    );

    echo "OK\n";
    exit(0);
} catch (Throwable $e) {
    $subject = '[CONANP] Backup diario ERROR';
    $message = "Error al generar backup.\n";
    $message .= "Fecha: " . date('Y-m-d H:i:s') . "\n";
    $message .= "Detalle: " . $e->getMessage() . "\n";

    send_mail_with_optional_attachment(
        $config['email_to'],
        $config['email_from'],
        $subject,
        $message
    );

    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
