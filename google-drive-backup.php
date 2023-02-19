<?php
/*
Plugin Name: Google Drive Backup
Description: A custom backup script using Google Drive API
Version: 1.0
Author: Collen T Hove - Snr Web Developer
*/

require_once plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';

// Set your Google Drive API credentials
$client = new Google\Client();
$client->setAuthConfig( plugin_dir_path( __FILE__ ) . 'client_secret.json' );
$client->addScope( Google_Service_Drive::DRIVE );

// Set your backup source directory and destination folder ID on Google Drive
$sourceDir = '/path/to/wordpress/files';
$destFolderId = 'your_destination_folder_id';

// Create a new backup archive using the current timestamp
$backupFilename = 'wordpress-backup-' . date('YmdHis') . '.zip';
$backupPath = plugin_dir_path( __FILE__ ) . $backupFilename;
$zip = new ZipArchive();
$zip->open($backupPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($sourceDir),
    RecursiveIteratorIterator::LEAVES_ONLY
);
foreach ($files as $name => $file) {
    if (!$file->isDir()) {
        $filePath = $file->getRealPath();
        $relativePath = substr($filePath, strlen($sourceDir) + 1);
        $zip->addFile($filePath, $relativePath);
    }
}
$zip->close();

// Upload the backup archive to Google Drive
$service = new Google_Service_Drive($client);
$fileMetadata = new Google_Service_Drive_DriveFile(array(
    'name' => $backupFilename,
    'parents' => array($destFolderId),
));
$content = file_get_contents($backupPath);
$file = $service->files->create($fileMetadata, array(
    'data' => $content,
    'mimeType' => 'application/zip',
    'uploadType' => 'multipart',
    'fields' => 'id',
));

// Delete the local backup archive
unlink($backupPath);

// Log the backup ID and timestamp to the WordPress database
global $wpdb;
$table_name = $wpdb->prefix . 'google_drive_backup';
$wpdb->insert( $table_name, array(
    'backup_id' => $file->id,
    'backup_timestamp' => current_time( 'mysql' )
) );

// Display a success message
echo 'Backup created and uploaded to Google Drive with ID: ' . $file->id . PHP_EOL;

?>
