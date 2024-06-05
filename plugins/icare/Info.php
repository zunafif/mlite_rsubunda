<?php

return [
    'name'          =>  'iCare',
    'description'   =>  'Modul iCare BPJS',
    'author'        =>  'Basoro',
    'version'       =>  '1.0',
    'compatibility' =>  '2023',
    'icon'          =>  'heart',
    'install'       =>  function () use ($core) {
      $core->db()->pdo()->exec("INSERT INTO `mlite_settings` (`module`, `field`, `value`) VALUES ('icare', 'url', 'https://apijkn.bpjs-kesehatan.go.id/wsihs/api/rs/validate')");
      $core->db()->pdo()->exec("INSERT INTO `mlite_settings` (`module`, `field`, `value`) VALUES ('icare', 'consid', '')");
      $core->db()->pdo()->exec("INSERT INTO `mlite_settings` (`module`, `field`, `value`) VALUES ('icare', 'secretkey', '')");
      $core->db()->pdo()->exec("INSERT INTO `mlite_settings` (`module`, `field`, `value`) VALUES ('icare', 'userkey', '')");      
    },
    'uninstall'     =>  function() use($core)
    {
    }
];
