<?php

return [
    'name'          =>  'Dokter Ralan',
    'description'   =>  'Modul dokter rawat jalan untuk mLITE',
    'author'        =>  'Basoro',
    'version'       =>  '1.0',
    'compatibility' =>  '2023',
    'icon'          =>  'user-md',
    'install'       =>  function () use ($core) {
      $core->db()->pdo()->exec("INSERT INTO `mlite_settings` (`module`, `field`, `value`) VALUES ('dokter_ralan', 'set_sudah', 'tidak')");
    },
    'uninstall'     =>  function() use($core)
    {
    }
];
