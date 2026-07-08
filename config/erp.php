<?php

return [
    'async_export_threshold' => (int) env('ERP_ASYNC_EXPORT_THRESHOLD', 5000),
    'async_import_threshold' => (int) env('ERP_ASYNC_IMPORT_THRESHOLD', 500),
    'async_auth_audit'       => (bool) env('ERP_ASYNC_AUTH_AUDIT', true),
    'fest_registration_lazy_student_threshold' => (int) env('ERP_FEST_LAZY_STUDENT_THRESHOLD', 300),
    'bulk_portal_provision_threshold' => (int) env('ERP_BULK_PORTAL_THRESHOLD', 50),
    'fcm_in_app_only'        => true,
    'login_max_attempts'     => (int) env('ERP_LOGIN_MAX_ATTEMPTS', 5),
    'login_lockout_minutes'  => (int) env('ERP_LOGIN_LOCKOUT_MINUTES', 15),
    // All user uploads use config('filesystems.upload_disk') — set UPLOAD_DISK=s3 in production.
    'legacy_migration_local_disks' => ['shared', 'local', 'public'],
    'legacy_migration_batch_size'  => (int) env('ERP_MIGRATION_BATCH_SIZE', 200),
    'legacy_migration_delete_local' => (bool) env('ERP_MIGRATION_DELETE_LOCAL', false),
];
