<?php

namespace Plugins\Master\Src;

use Systems\Lib\QueryWrapper;
use Systems\MySQL;

class Bahasa
{

    protected function db($table)
    {
        return new QueryWrapper($table);
    }

    public function getIndex()
    {

      $totalRecords = $this->mysql('bahasa_pasien')
        ->select('id')
        ->toArray();
      $offset         = 10;
      $return['halaman']    = 1;
      $return['jml_halaman']    = ceil(count($totalRecords) / $offset);
      $return['jumlah_data']    = count($totalRecords);

      $return['list'] = $this->mysql('bahasa_pasien')
        ->desc('id')
        ->limit(10)
        ->toArray();

      return $return;

    }

    public function anyForm()
    {
        if (isset($_POST['id'])){
          $return['form'] = $this->mysql('bahasa_pasien')->where('id', $_POST['id'])->oneArray();
        } else {
          $return['form'] = [
            'id' => '',
            'nama_bahasa' => ''
          ];
        }

        return $return;
    }

    public function anyDisplay()
    {

        $perpage = '10';
        $totalRecords = $this->mysql('bahasa_pasien')
          ->select('id')
          ->toArray();
        $offset         = 10;
        $return['halaman']    = 1;
        $return['jml_halaman']    = ceil(count($totalRecords) / $offset);
        $return['jumlah_data']    = count($totalRecords);

        $return['list'] = $this->mysql('bahasa_pasien')
          ->desc('id')
          ->offset(0)
          ->limit($perpage)
          ->toArray();

        if(isset($_POST['cari'])) {
          $return['list'] = $this->mysql('bahasa_pasien')
            ->like('id', '%'.$_POST['cari'].'%')
            ->orLike('nama_bahasa', '%'.$_POST['cari'].'%')
            ->desc('id')
            ->offset(0)
            ->limit($perpage)
            ->toArray();
          $jumlah_data = count($return['list']);
          $jml_halaman = ceil($jumlah_data / $offset);
        }
        if(isset($_POST['halaman'])){
          $offset     = (($_POST['halaman'] - 1) * $perpage);
          $return['list'] = $this->mysql('bahasa_pasien')
            ->desc('id')
            ->offset($offset)
            ->limit($perpage)
            ->toArray();
          $return['halaman'] = $_POST['halaman'];
        }

        return $return;
    }

    public function postSave()
    {
      if (!$this->mysql('bahasa_pasien')->where('id', $_POST['id'])->oneArray()) {
        $query = $this->mysql('bahasa_pasien')->save($_POST);
      } else {
        $query = $this->mysql('bahasa_pasien')->where('id', $_POST['id'])->save($_POST);
      }
      return $query;
    }

    public function postHapus()
    {
      return $this->mysql('bahasa_pasien')->where('id', $_POST['id'])->delete();
    }

    protected function mysql($table = NULL)
    {
        return new MySQL($table);
    }

}
