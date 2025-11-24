<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Unrestricted Roles
    |--------------------------------------------------------------------------
    | Role yang bisa melihat SEMUA data tanpa dibatasi employee_id atau department_id.
    | Misalnya: admin, superadmin, manager.
    */
    'unrestricted_roles' => [
        'admin',
        'super_admin',
        'manager',
        'head_marketing',
    ],

    /*
    |--------------------------------------------------------------------------
    | Department Lead Roles
    |--------------------------------------------------------------------------
    | Role yang hanya bisa melihat data milik SATU departemen.
    | Misalnya: kepala sales, kepala marketing, kepala digital.
    */
    'department_lead_roles' => [
        'head_sales',
        'head_digital',
    ],

];
